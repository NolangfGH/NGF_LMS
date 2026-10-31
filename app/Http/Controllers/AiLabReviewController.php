<?php

namespace App\Http\Controllers;

use App\Exceptions\AiReviewUnavailable;
use App\Models\{LabSubmission,TeacherAccount};
use App\Services\AiLabReviewer;
use Illuminate\Support\Facades\{Cache,DB};

class AiLabReviewController extends Controller
{
    public function suggest(LabSubmission $submission, AiLabReviewer $reviewer)
    {
        $teacher=TeacherAccount::find(session('teacher_id'));
        abort_unless($teacher && $submission->teacher_account_id === $teacher->id,403);
        abort_if($submission->reviewed_at,409,'This submission already has a final teacher review.');
        $lock=Cache::lock('lab-ai-draft:'.$submission->id,45);
        if (!$lock->get()) return response()->json(['message'=>'An AI draft is already being prepared. Try again shortly.'],409);
        try {
            $current=$submission->fresh();
            abort_if($current->reviewed_at,409,'This submission already has a final teacher review.');
            if (!$current->ai_suggestion) {
                $draft=$reviewer->suggest($current);
                DB::transaction(function () use ($current,$draft) {
                    $locked=LabSubmission::whereKey($current->id)->lockForUpdate()->firstOrFail();
                    abort_if($locked->reviewed_at,409,'The teacher already finalized this review. The AI draft was not applied.');
                    $locked->update(['ai_suggestion'=>$draft,'ai_generated_at'=>now(),'ai_model'=>config('services.openai.model')]);
                });
                $current->refresh();
            }
            return response()->json(['suggestion'=>$current->ai_suggestion,'generated_at'=>$current->ai_generated_at?->toIso8601String(),'model'=>$current->ai_model,'draft_only'=>true])->header('Cache-Control','private, no-store');
        } catch (AiReviewUnavailable $error) {
            return response()->json(['message'=>$error->getMessage()],$error->httpStatus);
        } finally {
            $lock->release();
        }
    }
}
