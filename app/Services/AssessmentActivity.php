<?php

namespace App\Services;

use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\CodingMatchParticipant;
use App\Models\QuizAttempt;
use App\Models\QuizBeeMatch;
use App\Models\RecitationAward;
use App\Models\RecitationRound;
use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Support\Facades\DB;

class AssessmentActivity
{
    public const KINDS = ['quiz', 'post_test', 'recitation', 'quiz_bee', 'coding_match', 'challenge_lab'];

    public const SIGNALS = ['monitor_started', 'page_hidden', 'page_visible', 'window_blur', 'window_focus', 'fullscreen_enter', 'fullscreen_exit'];

    /** @return array{active: bool, title: string} */
    public function state(Student $student, string $kind, int $id): array
    {
        abort_unless($student->classroom && ! $student->classroom->archived_at, 403);
        if ($kind === 'quiz') {
            $attempt = QuizAttempt::where('student_id', $student->id)->findOrFail($id);
            $quiz = TeacherQuiz::forClassroom($student->classroom_id)->where('teacher_account_id', $student->classroom->teacher_account_id)->where('status', 'published')->findOrFail($attempt->teacher_quiz_id);

            return ['active' => ! $attempt->submitted_at && now()->lt($attempt->expires_at) && $attempt->scheduleOpen($quiz), 'title' => $quiz->title];
        }
        if ($kind === 'post_test') {
            $attempt = DB::table('course_post_test_attempts')->where('student_id', $student->id)->find($id);
            abort_unless($attempt, 404);

            return ['active' => $attempt->started_at !== null && $attempt->finished_at === null, 'title' => ($attempt->course === 'arduino-uno' ? 'Arduino Uno Robotics' : 'C++').' certification post-test'];
        }
        if ($kind === 'recitation') {
            $round = RecitationRound::where('classroom_id', $student->classroom_id)->where('teacher_account_id', $student->classroom->teacher_account_id)->where('status', '!=', 'draft')->findOrFail($id);

            return ['active' => $round->status === 'open' && ! RecitationAward::where('recitation_round_id', $id)->where('student_id', $student->id)->exists()
                && ! app(RecitationService::class)->termProgress($round, $student)['limit_reached'], 'title' => $round->title];
        }
        if ($kind === 'quiz_bee') {
            $bee = QuizBeeMatch::where('teacher_account_id', $student->classroom->teacher_account_id)->where('status', '!=', 'draft')->findOrFail($id);
            abort_unless(DB::table('quiz_bee_members')->where('quiz_bee_match_id', $id)->where('student_id', $student->id)->exists(), 404);

            return ['active' => $bee->status === 'running', 'title' => $bee->title];
        }
        if ($kind === 'coding_match') {
            $match = CodingMatch::where('teacher_account_id', $student->classroom->teacher_account_id)->findOrFail($id);
            $participant = CodingMatchParticipant::where('coding_match_id', $id)->where('student_id', $student->id)->firstOrFail();

            return ['active' => $participant->response === 'accepted' && in_array(app(CodingMatchService::class)->phase($match), ['active', 'overtime'], true)
                && ! $participant->submissions()->exists()
                && app(CodingMatchService::class)->rosterValid($match), 'title' => $match->title];
        }
        abort_unless($kind === 'challenge_lab', 404);
        $workspace = CodeWorkspace::with('assignment')->where('student_id', $student->id)->findOrFail($id);
        $assignment = $workspace->assignment;
        abort_unless($assignment && $assignment->kind === 'challenge' && $assignment->status === 'published'
            && $assignment->teacher_account_id === $student->classroom->teacher_account_id && $assignment->isAssignedTo($student->classroom_id), 404);

        return ['active' => ! app(SecondChanceService::class)->assignmentScheduleClosed($assignment, $student->id) && ! app(CodeLabTimer::class)->describe($workspace)['expired'], 'title' => $assignment->title];
    }
}
