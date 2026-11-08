<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\RecitationAttempt;
use App\Models\RecitationAward;
use App\Models\RecitationDraft;
use App\Models\RecitationRound;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecitationService
{
    public const TERM_LIMIT = 2;

    private function awardsForTerm(RecitationRound $round): Builder
    {
        return RecitationAward::join('recitation_rounds as term_rounds', 'term_rounds.id', '=', 'recitation_awards.recitation_round_id')
            ->where('term_rounds.school_year', $round->school_year)->where('term_rounds.term', $round->term);
    }

    private function progress(int $correct, int $score): array
    {
        return ['correct_answers' => $correct, 'score' => $score, 'remaining' => max(0, self::TERM_LIMIT - $correct), 'limit_reached' => $correct >= self::TERM_LIMIT || $score >= 20];
    }

    public function termProgress(RecitationRound $round, Student $student, bool $lock = false): array
    {
        $roundIds = RecitationRound::where('school_year', $round->school_year)->where('term', $round->term)->select('id');
        $awards = RecitationAward::where('student_id', $student->id)->whereIn('recitation_round_id', $roundIds)
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->get(['id', 'score']);

        return $this->progress($awards->count(), (int) $awards->sum('score'));
    }

    public function studentTermTotals(Student $student): Collection
    {
        return RecitationAward::join('recitation_rounds as term_rounds', 'term_rounds.id', '=', 'recitation_awards.recitation_round_id')
            ->where('recitation_awards.student_id', $student->id)->groupBy('term_rounds.school_year', 'term_rounds.term')
            ->selectRaw('term_rounds.school_year, term_rounds.term, COUNT(*) AS correct_answers, SUM(recitation_awards.score) AS points')->get()
            ->mapWithKeys(fn ($total): array => [$total->school_year.':'.$total->term => $this->progress((int) $total->correct_answers, (int) $total->points)]);
    }

    public function termStandings(RecitationRound $round, Collection $studentIds): Collection
    {
        return $this->awardsForTerm($round)->whereIn('recitation_awards.student_id', $studentIds)->groupBy('recitation_awards.student_id')
            ->selectRaw('recitation_awards.student_id, COUNT(*) AS correct_answers, SUM(recitation_awards.score) AS points, MAX(recitation_awards.id) AS last_award_id')->get()->keyBy('student_id');
    }

    public function writable(RecitationRound $round, Student $student): void
    {
        $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
        abort_unless($round->classroom_id === $student->classroom_id && Classroom::whereKey($round->classroom_id)->where('teacher_account_id', $round->teacher_account_id)->whereNull('archived_at')->exists(), 403);
        abort_unless($round->status === 'open', 409, 'This recitation is closed.');
        abort_if($this->termProgress($round, $student, true)['limit_reached'], 409, 'You have reached two correct answers (20 recitation points) for '.$round->term.' · '.$round->school_year.'. Please give your classmates a turn. You can participate again next term.');
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

        return ['correct' => $correct, 'award' => $award ? ['position' => $award->position, 'score' => $award->score, 'xp' => $award->xp] : null, 'xp' => $fresh->xp, 'level' => $fresh->level, 'term_progress' => $this->termProgress($round, $student, true), 'message' => $award ? 'Correct! Place '.$award->position.' · 10 recitation points · '.$award->xp.' XP.' : 'That answer does not match an accepted answer. Review the question and try again while the round is open.'];
    }
}
