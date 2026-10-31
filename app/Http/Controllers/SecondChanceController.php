<?php

namespace App\Http\Controllers;

use App\Models\{SecondChanceRequest,Student,TeacherAccount,TeacherAssignment,TeacherQuiz};
use App\Services\SecondChanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class SecondChanceController extends Controller
{
    public function store(Request $request, string $type, int $activity, SecondChanceService $service)
    {
        abort_unless(in_array($type,['assignment','quiz'],true),404);
        $student=Student::find(session('student_id'));abort_unless($student,401);
        $target=($type === 'assignment' ? TeacherAssignment::class : TeacherQuiz::class)::findOrFail($activity);
        abort_unless($target->status === 'published' && $target->isAssignedTo((int)$student->classroom_id),403);
        $data=$request->validate([
            'reason'=>['required','string','min:15','max:3000'],
            'proof'=>['required','image','mimes:jpg,jpeg,png,webp','max:2048','dimensions:max_width=6000,max_height=6000'],
        ]);
        $path=null;
        try {
            DB::transaction(function () use ($student,$target,$type,$data,$request,$service,&$path) {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                if ($service->requestFor($student->id,$type,$target->id)) throw ValidationException::withMessages(['request'=>'You have already requested your final opportunity for this activity.']);
                if (!$service->eligible($student,$type,$target)) throw ValidationException::withMessages(['request'=>'An excuse request is available only after the activity or its timer closes.']);
                $path=$request->file('proof')->store('excuse-proofs','local');
                abort_unless($path,500,'The image could not be saved. Please try again.');
                SecondChanceRequest::create(['student_id'=>$student->id,'teacher_account_id'=>$target->teacher_account_id,'activity_type'=>$type,'activity_id'=>$target->id,'activity_title'=>$target->title,'reason'=>$data['reason'],'proof_path'=>$path,'proof_mime'=>$request->file('proof')->getMimeType()]);
            });
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
        return back()->with('excuse_notice','Your excuse request was sent to your teacher. Wait for approval before starting your final opportunity.');
    }
    public function index()
    {
        $teacher=TeacherAccount::find(session('teacher_id'));
        if (!$teacher) return redirect()->route('teacher.login');
        $requests=SecondChanceRequest::with('student.classroom')->where('teacher_account_id',$teacher->id)->latest()->paginate(15);
        return view('second-chance-reviews',compact('requests'));
    }
    public function review(Request $request, SecondChanceRequest $excuse)
    {
        $teacher=TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && $excuse->teacher_account_id === $teacher->id,403);
        $data=$request->validate(['decision'=>['required','in:approved,declined'],'teacher_note'=>['required','string','min:5','max:2000'],'expires_at'=>['nullable','required_if:decision,approved','date_format:Y-m-d\\TH:i'],'duration_minutes'=>['nullable','integer','min:1','max:240']]);
        DB::transaction(function () use ($excuse,$data) {
            Student::whereKey($excuse->student_id)->lockForUpdate()->firstOrFail();
            $current=SecondChanceRequest::whereKey($excuse->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'pending') throw ValidationException::withMessages(['request'=>'This request has already been reviewed. Only one final opportunity is allowed.']);
            $end=null;$duration=null;
            if ($data['decision'] === 'approved') {
                $target=$current->activity;$student=$current->student;
                abort_unless($target && $target->status === 'published' && $target->teacher_account_id === $current->teacher_account_id && $target->isAssignedTo((int)$student->classroom_id),422,'The activity is no longer published for this student.');
                $end=\Carbon\Carbon::createFromFormat('!Y-m-d\\TH:i',$data['expires_at'],config('learning.timezone'))->utc();
                if ($end->lte(now())) throw ValidationException::withMessages(['expires_at'=>'Choose a new deadline in the future (UTC+08:00).']);
                $duration=$current->activity_type === 'assignment' ? ($data['duration_minutes'] ?? $target->time_limit_minutes ?? 30) : null;
            }
            $current->update(['status'=>$data['decision'],'teacher_note'=>$data['teacher_note'],'expires_at'=>$end,'duration_minutes'=>$duration,'reviewed_at'=>now()]);
        });
        return back()->with('excuse_notice','Excuse request reviewed. The student can see your decision.');
    }
    public function proof(SecondChanceRequest $excuse)
    {
        $teacher=TeacherAccount::find(session('teacher_id'));
        $student=Student::find(session('student_id'));
        abort_unless(($teacher && $teacher->id === $excuse->teacher_account_id) || ($student && $student->id === $excuse->student_id),403);
        abort_unless(Storage::disk('local')->exists($excuse->proof_path),404);
        return Storage::disk('local')->response($excuse->proof_path,null,['Content-Type'=>$excuse->proof_mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }
}
