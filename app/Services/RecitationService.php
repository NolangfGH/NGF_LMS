<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\RecitationAttempt;
use App\Models\RecitationAward;
use App\Models\RecitationDraft;
use App\Models\RecitationRound;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecitationService
{
    public function writable(RecitationRound $round, Student $student): void
    {
        abort_unless($round->classroom_id === $student->classroom_id && Classroom::whereKey($round->classroom_id)->where('teacher_account_id', $round->teacher_account_id)->whereNull('archived_at')->exists(), 403);
        abort_unless($round->status === 'open', 409, 'This recitation is closed.');
    }

    public function save(RecitationRound $round, Student $student, string $answer, int $revision): RecitationDraft
    {
        return DB::transaction(function () use ($round, $student, $answer, $revision): RecitationDraft {
            $current = RecitationRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            $this->writable($current, $student);
            abort_if(RecitationAward::where('recitation_round_id', $current->id)->where('student_id', $student->id)->exists(), 409, 'Your recitation score is already recorded.');
            $draft = RecitationDraft::firstOrNew(['recitation_round_id' => $current->id, 'student_id' => $student->id]);
            abort_unless((int) ($draft->revision ?? 0) === $revision, 409, 'Another tab saved this draft. Copy your answer and reload.');
            $draft->fill(['answer' => $answer, 'revision' => $revision + 1])->save();
            $current->increment('revision');

            return $draft;
        }, 3);
    }

    public function submit(RecitationRound $round, Student $student, string $answer, string $requestKey): array
    {
        return DB::transaction(function () use ($round, $student, $answer, $requestKey): array {
            $current = RecitationRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->classroom_id === $student->classroom_id, 403);
            $existing = RecitationAttempt::where('recitation_round_id', $round->id)->where('student_id', $student->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->answer, $answer), 409, 'Use a new submission key after changing your answer.');

                return $this->result($current, $student, $existing->correct);
            }
            $this->writable($current, $student);
            abort_if(RecitationAward::where('recitation_round_id', $round->id)->where('student_id', $student->id)->exists(), 409, 'Your recitation score is already recorded.');
            $normalize = fn (string $text): string => $current->case_sensitive ? trim(str_replace("\r\n", "\n", $text)) : Str::lower(trim(str_replace("\r\n", "\n", $text)));
            $correct = collect($current->accepted_answers)->contains(fn (string $key): bool => hash_equals($normalize($key), $normalize($answer)));
            $attempt = RecitationAttempt::create(['recitation_round_id' => $current->id, 'student_id' => $student->id, 'request_key' => $requestKey, 'answer' => $answer, 'correct' => $correct]);
            if ($correct) {
                $position = RecitationAward::where('recitation_round_id', $current->id)->count() + 1;
                abort_if($position > 5, 409, 'The five recitation places have been awarded.');
                $xp = [1 => 10, 2 => 5, 3 => 3, 4 => 0, 5 => 0][$position];
                RecitationAward::create(['recitation_round_id' => $current->id, 'recitation_attempt_id' => $attempt->id, 'student_id' => $student->id, 'position' => $position, 'score' => 10, 'xp' => $xp]);
                if ($xp > 0) {
                    app(LearningRewards::class)->award($student->id, 'recitation:'.$current->id.':'.$student->id, $xp);
                }
                if ($position === 5) {
                    $current->status = 'completed';
                }
            }
            $current->revision++;
            $current->save();

            return $this->result($current, $student, $correct);
        }, 3);
    }

    private function result(RecitationRound $round, Student $student, bool $correct): array
    {
        $award = RecitationAward::where('recitation_round_id', $round->id)->where('student_id', $student->id)->first();
        $fresh = $student->fresh();

        return ['correct' => $correct, 'award' => $award ? ['position' => $award->position, 'score' => $award->score, 'xp' => $award->xp] : null, 'xp' => $fresh->xp, 'level' => $fresh->level, 'message' => $award ? 'Correct! Place '.$award->position.' · 10 recitation points · '.$award->xp.' XP.' : 'That answer does not match an accepted answer. Review the question and try again while the round is open.'];
    }
}
