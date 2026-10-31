<?php

namespace App\Services;

use App\Models\{Student,CodeWorkspace,TeacherAssignment};

class ChallengeService
{
    public function describe(?CodeWorkspace $workspace): array
    {
        if (!$workspace) return ['status'=>'not_started','label'=>'Ready to start','score'=>null,'feedback'=>null];
        $latest=$workspace->submissions->sortByDesc('id')->first();
        if (!$latest) return ['status'=>'draft','label'=>'Draft saved','score'=>null,'feedback'=>null];
        foreach ($workspace->submissions->sortByDesc('id') as $review) {
            if (!$review->reviewed_at) continue;
            $correct=$review->rubric['correctness'] ?? 0;$logic=$review->rubric['logic'] ?? 0;
            if ($correct>=3 && $correct<=4 && $logic>=3 && $logic<=4) {
                return ['status'=>'completed','label'=>'Teacher-confirmed solution','score'=>$review->score,'feedback'=>$review->feedback];
            }
        }
        if (!$latest->reviewed_at) return ['status'=>'awaiting_review','label'=>'Awaiting teacher review','score'=>null,'feedback'=>null];
        return ['status'=>'improve','label'=>'Improve with feedback','score'=>$latest->score,'feedback'=>$latest->feedback];
    }
    public function cards(Student $student, $activities): array
    {
        $workspaces=CodeWorkspace::with(['submissions'=>fn ($query)=>$query->select('id','code_workspace_id','revision','rubric','score','feedback','reviewed_at','created_at')])
            ->where('student_id',$student->id)->get()->keyBy('activity_key');
        $cards=[];
        foreach ($activities as $activity) {
            $workspace=$workspaces->get('assignment:'.$activity->id);
            $grant=app(SecondChanceService::class)->requestFor($student->id,'assignment',$activity->id);
            $startedFinal=$workspace && $workspace->second_chance_request_id === $grant?->id;
            $deadline=$workspace && (!$grant?->isActive() || $startedFinal) ? app(CodeLabTimer::class)->deadline($workspace) : null;
            $deadline ??= $grant?->status === 'approved' ? $grant->expires_at : $activity->due_at;
            $closed=app(SecondChanceService::class)->assignmentScheduleClosed($activity,$student->id) || ($deadline && now()->gte($deadline));
            $cards[]=['key'=>'assignment:'.$activity->id,'source'=>'teacher','title'=>$activity->title,'objective'=>$activity->objective,'difficulty'=>$activity->difficulty,'sample_output'=>$activity->sample_output,'hint'=>'Use the objective and sample output to plan your algorithm. Test normal and boundary values.','assignment'=>$activity,'workspace'=>$workspace,'deadline'=>$deadline,'closed'=>$closed,'progress'=>$this->describe($workspace)];
        }
        foreach (config('challenges') as $key=>$definition) {
            $workspace=$workspaces->get('practice:'.$key);
            $practice=config('code_lab.activities.'.$key);
            $cards[]=['key'=>'practice:'.$key,'source'=>'practice','difficulty'=>$definition['difficulty'],'topic'=>$definition['topic'],'assignment'=>null,'workspace'=>$workspace,'deadline'=>null,'closed'=>false,'title'=>$practice['title'],'objective'=>$practice['objective'],'sample_output'=>$practice['sample_output'],'hint'=>$practice['hint'],'progress'=>$this->describe($workspace),'activity'=>$key];
        }
        return $cards;
    }
}
