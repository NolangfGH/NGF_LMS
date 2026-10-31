<?php

namespace App\Http\Controllers;

use App\Models\{Student,TeacherAccount,TeacherAssignment,CodeWorkspace,LabSubmission};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\CodeLabTimer;

class CodeLabController extends Controller
{
    private function student(): Student
    {
        $student=Student::with('classroom')->find(session('student_id'));
        abort_unless($student,401);
        return $student;
    }

    public function activity(Request $request, Student $student): array
    {
        $data=$request->validate(['assignment'=>['nullable','integer'],'activity'=>['nullable','string','max:40']]);
        if (!empty($data['assignment'])) {
            $assignment=TeacherAssignment::forClassroom($student->classroom_id)->where('status','published')->findOrFail($data['assignment']);
            abort_if(app(\App\Services\SecondChanceService::class)->assignmentScheduleClosed($assignment,$student->id),403,'This assignment is closed. Your saved work has been kept.');
            return ['key'=>'assignment:'.$assignment->id,'assignment'=>$assignment,'title'=>$assignment->title,'objective'=>$assignment->objective,'hint'=>'Read the assignment objective and sample output. Plan the steps, predict the output, and explain how your code works.','code'=>$assignment->starter_code ?: config('code_lab.assignment_starter')];
        }
        $key=$data['activity'] ?? 'hello';
        $activity=config('code_lab.activities.'.$key);
        abort_unless(is_array($activity),404);
        return ['key'=>'practice:'.$key,'assignment'=>null,...$activity];
    }

    private function authorizeWorkspace(CodeWorkspace $workspace, Student $student): void
    {
        abort_unless($workspace->student_id === $student->id,403);
        if ($workspace->teacher_assignment_id) abort_unless($workspace->assignment && $workspace->assignment->isAssignedTo((int)$student->classroom_id) && $workspace->assignment->status === 'published',403);
    }

    public function openAssignment(TeacherAssignment $assignment)
    {
        $student=$this->student();
        $credited=DB::transaction(function () use ($student,$assignment) {
            $currentStudent=Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $assignment=TeacherAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($assignment->status === 'published' && $assignment->isAssignedTo((int)$currentStudent->classroom_id),404);
            if (app(\App\Services\SecondChanceService::class)->assignmentScheduleClosed($assignment,$student->id)) {
                throw ValidationException::withMessages(['assignment'=>'This assignment is closed because its due date has passed. No XP was added.']);
            }
            // The student row lock serializes repeated clicks; activity_key is also unique per student.
            $workspace=CodeWorkspace::firstOrCreate(
                ['student_id'=>$currentStudent->id,'activity_key'=>'assignment:'.$assignment->id],
                ['teacher_assignment_id'=>$assignment->id,'title'=>$assignment->title,'code'=>$assignment->starter_code ?: config('code_lab.assignment_starter')]
            );
            app(CodeLabTimer::class)->startIfNeeded($workspace);
            app(CodeLabTimer::class)->enforce($workspace);
            if ($workspace->assignment_opened_at) return 0;
            // Older rubric rewards count toward this assignment, so only credit any remaining XP.
            $additional=max(0,$assignment->xp - $workspace->xp_earned);
            $workspace->update(['assignment_opened_at'=>now(),'opening_xp_awarded'=>$additional,'xp_earned'=>max($workspace->xp_earned,$assignment->xp)]);
            if ($additional) {
                $currentStudent->xp += $additional;
                $currentStudent->level=intdiv($currentStudent->xp,600)+1;
                $currentStudent->save();
            }
            return $additional;
        });
        return redirect()->route('student.code-lab',['assignment'=>$assignment->id])
            ->with('assignment_reward',$credited ? '+'.number_format($credited).' XP added to your account and leaderboard.' : 'Code Lab opened. Your assignment XP has already been credited.');
    }

    public function save(Request $request, CodeWorkspace $workspace)
    {
        $student=$this->student();
        $this->authorizeWorkspace($workspace,$student);
        $data=$request->validate(['revision'=>['required','integer','min:1'],'code'=>['required','string','max:20000'],'input'=>['nullable','string','max:5000'],'prediction'=>['nullable','string','max:5000'],'reflection'=>['nullable','string','max:5000']]);
        return DB::transaction(function () use ($workspace,$student,$data) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $current=CodeWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $this->authorizeWorkspace($current,$student);
            app(CodeLabTimer::class)->startIfNeeded($current);
            app(CodeLabTimer::class)->enforce($current);
            if ($current->revision !== (int) $data['revision']) return response()->json(['message'=>'This workspace was changed in another tab. Download your code, then reload before saving.'],409);
            $current->fill(collect($data)->except('revision')->all());
            if ($current->isDirty()) { $current->revision++; $current->save(); }
            return response()->json(['message'=>'Draft saved.','revision'=>$current->revision,'saved_at'=>$current->updated_at->toIso8601String(),'timer'=>app(CodeLabTimer::class)->describe($current)]);
        });
    }

    public function submit(Request $request, CodeWorkspace $workspace)
    {
        $student=$this->student(); $this->authorizeWorkspace($workspace,$student);
        $data=$request->validate(['revision'=>['required','integer','min:1']]);
        return DB::transaction(function () use ($student,$workspace,$data) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $current=CodeWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $this->authorizeWorkspace($current,$student);
            $timer=app(CodeLabTimer::class);
            $timer->startIfNeeded($current);
            $timer->enforce($current);
            if ($current->revision !== (int) $data['revision']) throw ValidationException::withMessages(['code'=>'Save your latest draft before submitting.']);
            if (mb_strlen(trim($current->reflection ?? '')) < 10) throw ValidationException::withMessages(['reflection'=>'Explain your solution in at least 10 characters before submitting.']);
            if ($current->submissions()->where('second_chance_request_id',$current->second_chance_request_id)->whereNull('reviewed_at')->exists()) throw ValidationException::withMessages(['submission'=>'Your teacher has a submission awaiting review. You can keep editing your draft.']);
            if ($current->submissions()->where('revision',$current->revision)->exists()) throw ValidationException::withMessages(['submission'=>'This revision has already been submitted. Improve your work and save a new revision first.']);
            if ($current->assignment && app(\App\Services\SecondChanceService::class)->assignmentScheduleClosed($current->assignment,$student->id)) throw ValidationException::withMessages(['assignment'=>'The assignment due date has passed. Your draft remains saved.']);
            $submission=$current->submissions()->create(['code_run_id'=>\App\Models\CodeRun::where('code_workspace_id',$current->id)->where('revision',$current->revision)->where('status','completed')->where('mode','tests')->latest()->value('id'),'teacher_account_id'=>$current->assignment?->teacher_account_id ?? $student->classroom->teacher_account_id,'revision'=>$current->revision,'title'=>$current->title,'code'=>$current->code,'input'=>$current->input,'prediction'=>$current->prediction,'reflection'=>$current->reflection,'reward_budget'=>$current->assignment?->xp ?? 0,'sample_output'=>$current->assignment?->sample_output ?? config('code_lab.activities.'.str_replace('practice:','',$current->activity_key).'.sample_output'),'objective'=>$current->assignment?->objective ?? config('code_lab.activities.'.str_replace('practice:','',$current->activity_key).'.objective'),'time_spent_seconds'=>$timer->elapsed($current),'time_limit_seconds'=>$current->time_limit_seconds,'second_chance_request_id'=>$current->second_chance_request_id]);
            session()->flash('lab_submitted','Revision '.$current->revision.' submitted. Your teacher can now review your saved C++ code and explanation.');
            app(\App\Services\AchievementService::class)->sync($student);
            return response()->json(['message'=>'Your code and reflection were sent to your teacher.','submission_id'=>$submission->id]);
        });
    }

    public function status(CodeWorkspace $workspace)
    {
        $student=$this->student();
        $this->authorizeWorkspace($workspace,$student);
        return response()->json(['timer'=>app(CodeLabTimer::class)->describe($workspace),'closed'=>$workspace->assignment ? app(\App\Services\SecondChanceService::class)->assignmentScheduleClosed($workspace->assignment,$student->id) : false])->header('Cache-Control','no-store');
    }

    public function reviews(Request $request)
    {
        $teacher=TeacherAccount::find(session('teacher_id'));
        if (!$teacher) return redirect()->route('teacher.login');
        $filter=$request->validate(['activity'=>['nullable','in:assignment,challenge,practice'],'submission'=>['nullable','integer','min:1']]);
        $reviewActivity=$filter['activity'] ?? '';
        $query=LabSubmission::with('workspace.student.classroom')->where('teacher_account_id',$teacher->id);
        if (!empty($filter['submission'])) $query->whereKey($filter['submission']);
        if ($reviewActivity === 'practice') $query->whereHas('workspace',fn ($workspace)=>$workspace->whereNull('teacher_assignment_id'));
        elseif ($reviewActivity !== '') $query->whereHas('workspace.assignment',fn ($activity)=>$activity->where('kind',$reviewActivity));
        $submissions=$query->latest()->paginate(15)->withQueryString();
        $aiConfigured=app(\App\Services\AiLabReviewer::class)->configured();
        $aiDrafts=$submissions->getCollection()->filter(fn ($item)=>!$item->reviewed_at && $item->ai_suggestion)
            ->mapWithKeys(fn ($item)=>[$item->id=>$item->ai_suggestion])->all();
        return view('teacher-code-lab',compact('teacher','submissions','aiConfigured','aiDrafts','reviewActivity'));
    }

    public function review(Request $request, LabSubmission $submission)
    {
        $teacher=TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && $submission->teacher_account_id === $teacher->id,403);
        $data=$request->validate(['rubric'=>['required','array:correctness,logic,readability,explanation'],'rubric.correctness'=>['required','integer','between:0,4'],'rubric.logic'=>['required','integer','between:0,4'],'rubric.readability'=>['required','integer','between:0,4'],'rubric.explanation'=>['required','integer','between:0,4'],'feedback'=>['required','string','min:10','max:5000'],'resubmission_requested'=>['sometimes','boolean']]);
        DB::transaction(function () use ($submission,$data,$teacher) {
            $workspace=$submission->workspace;
            $student=Student::whereKey($workspace->student_id)->lockForUpdate()->firstOrFail();
            $workspace=CodeWorkspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $current=LabSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($current->reviewed_at) throw ValidationException::withMessages(['review'=>'This submission has already been reviewed.']);
            $score=array_sum($data['rubric']);
            $earned=(int) floor($current->reward_budget * $score / 16);
            $additional=$workspace->assignment_opened_at ? 0 : max(0,$earned - $workspace->xp_earned);
            $current->update(['resubmission_requested'=>(bool)($data['resubmission_requested'] ?? false),'rubric'=>$data['rubric'],'score'=>$score,'feedback'=>$data['feedback'],'reviewed_at'=>now(),'reviewed_by_teacher_id'=>$teacher->id,'xp_awarded'=>$additional]);
            if ($additional) {
                $workspace->xp_earned += $additional; $workspace->save();
                $student->xp += $additional; $student->level=intdiv($student->xp,600)+1; $student->save();
            }
            app(\App\Services\AchievementService::class)->sync($student);
        });
        return back()->with('lab_review_saved','Review saved and earned XP credited.');
    }
}
