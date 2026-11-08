<?php

namespace App\Services;

use App\Models\QuizBeeMatch;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuizBeeService
{
    private function timestamp(): string
    {
        return now()->format('Y-m-d H:i:s.u');
    }

    public function teams(QuizBeeMatch $match)
    {
        return DB::table('quiz_bee_teams')->where('quiz_bee_match_id', $match->id)->orderBy('id')->get();
    }

    public function teamFor(QuizBeeMatch $match, int $studentId): ?object
    {
        return DB::table('quiz_bee_teams')->join('quiz_bee_members', 'quiz_bee_members.quiz_bee_team_id', '=', 'quiz_bee_teams.id')
            ->where('quiz_bee_members.quiz_bee_match_id', $match->id)->where('student_id', $studentId)->select('quiz_bee_teams.*')->first();
    }

    public function standings(QuizBeeMatch $match)
    {
        $scores = DB::table('quiz_bee_answers')->join('quiz_bee_runs', 'quiz_bee_runs.id', '=', 'quiz_bee_answers.quiz_bee_run_id')
            ->where('quiz_bee_runs.quiz_bee_match_id', $match->id)->whereNotNull('closed_at')
            ->selectRaw('quiz_bee_team_id, SUM(correct) as correct_count, SUM(xp) as answer_xp')->groupBy('quiz_bee_team_id')->get()->keyBy('quiz_bee_team_id');

        return $this->teams($match)->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'captain_id' => $team->captain_id,
            'ready' => $team->ready_at !== null, 'correct' => (int) ($scores[$team->id]->correct_count ?? 0), 'answer_xp' => (int) ($scores[$team->id]->answer_xp ?? 0)])
            ->sortBy([['correct', 'desc'], ['id', 'asc']])->values();
    }

    private function open(QuizBeeMatch $match, string $level, string $kind, int $index, array $eligible, int $sequence): void
    {
        $id = DB::table('quiz_bee_runs')->insertGetId(['quiz_bee_match_id' => $match->id, 'sequence' => $sequence, 'level' => $level, 'kind' => $kind,
            'question_index' => $index, 'question' => json_encode($match->questions[$level][$kind][$index]), 'eligible_team_ids' => json_encode(array_values($eligible)),
            'opened_at' => $this->timestamp(), 'closes_at' => now()->addSeconds(60)->format('Y-m-d H:i:s.u')]);
        $match->update(['current_run_id' => $id]);
    }

    public function ready(QuizBeeMatch $match, Student $student): void
    {
        DB::transaction(function () use ($match, $student) {
            $current = QuizBeeMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'lobby', 409, 'The invitation lobby is closed.');
            $team = $this->teamFor($current, $student->id);
            abort_unless($team && (int) $team->captain_id === $student->id, 403, 'Only the team captain can mark the team ready.');
            if (! $team->ready_at) {
                DB::table('quiz_bee_teams')->where('id', $team->id)->update(['ready_at' => $this->timestamp()]);
                app(LearningNotifications::class)->quizBee($current, 'teacher', $current->teacher_account_id, 'ready:'.$team->id, 'Quiz Bee team ready', $team->name.' is ready to compete.');
            }
        }, 5);
    }

    public function start(QuizBeeMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $current = QuizBeeMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'lobby', 409, 'This match has already started or is not published.');
            $teams = $this->teams($current);
            abort_unless($teams->count() >= 2 && $teams->every(fn ($team) => $team->ready_at !== null), 422, 'At least two teams are needed and every captain must mark ready.');
            $current->update(['status' => 'running', 'started_at' => now()]);
            $this->open($current, 'easy', 'regular', 0, $teams->pluck('id')->all(), 1);
        }, 5);
    }

    public function settle(QuizBeeMatch $match): void
    {
        $preview = QuizBeeMatch::findOrFail($match->id);
        if ($preview->status !== 'running' || ! $preview->current_run_id) {
            return;
        }
        $run = DB::table('quiz_bee_runs')->find($preview->current_run_id);
        if ($run->closed_at || (now()->lt(Carbon::parse($run->closes_at)) && DB::table('quiz_bee_answers')->where('quiz_bee_run_id', $run->id)->count() < count(json_decode($run->eligible_team_ids, true)))) {
            return;
        }
        DB::transaction(function () use ($match) {
            $current = QuizBeeMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'running' || ! $current->current_run_id) {
                return;
            }
            $run = DB::table('quiz_bee_runs')->find($current->current_run_id);
            $eligible = json_decode($run->eligible_team_ids, true);
            if (! $run->closed_at && (now()->gte(Carbon::parse($run->closes_at)) || DB::table('quiz_bee_answers')->where('quiz_bee_run_id', $run->id)->count() === count($eligible))) {
                $this->close($current, $run, $eligible);
            }
        }, 5);
    }

    private function close(QuizBeeMatch $match, object $run, array $eligible): void
    {
        foreach ($eligible as $teamId) {
            DB::table('quiz_bee_answers')->insertOrIgnore(['quiz_bee_run_id' => $run->id, 'quiz_bee_team_id' => $teamId, 'student_id' => null,
                'answer' => null, 'correct' => false, 'elapsed_ms' => 60000, 'xp' => 0]);
        }
        $members = DB::table('quiz_bee_members')->where('quiz_bee_match_id', $match->id)->whereIn('quiz_bee_team_id', $eligible)->whereNotNull('student_id')->orderBy('student_id')->get();
        $answers = DB::table('quiz_bee_answers')->where('quiz_bee_run_id', $run->id)->get()->keyBy('quiz_bee_team_id');
        foreach ($members as $member) {
            $xp = (int) $answers[$member->quiz_bee_team_id]->xp;
            if ($xp && Student::whereKey($member->student_id)->exists()) {
                $awarded = app(LearningRewards::class)->award($member->student_id, 'quiz-bee:'.$match->id.':question:'.$run->id, $xp, ['type' => 'quiz-bee', 'id' => $match->id, 'title' => $match->title, 'reason' => 'Quiz Bee correct team answer', 'details' => ['Your team answered correctly. '.$xp.' XP credited to this member; includes a 5 XP speed bonus when eligible.']]);
                DB::table('quiz_bee_members')->where('id', $member->id)->increment('xp_awarded', $awarded);
            }
        }
        DB::table('quiz_bee_runs')->where('id', $run->id)->update(['closed_at' => $this->timestamp()]);
    }

    public function answer(QuizBeeMatch $match, Student $student, int $runId, int|string $answer): void
    {
        $this->settle($match);
        DB::transaction(function () use ($match, $student, $runId, $answer) {
            $current = QuizBeeMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'running' && $current->current_run_id === $runId, 409, 'This question is no longer current.');
            $team = $this->teamFor($current, $student->id);
            abort_unless($team && (int) $team->captain_id === $student->id, 403, 'Only the team captain can submit an answer.');
            $run = DB::table('quiz_bee_runs')->find($runId);
            abort_unless(in_array($team->id, json_decode($run->eligible_team_ids, true), true), 403, 'Your team is not in this tie-breaker.');
            $question = json_decode($run->question, true);
            $fillBlank = ($question['type'] ?? 'multiple_choice') === 'fill_blank';
            if ($fillBlank) {
                $answer = trim((string) $answer);
                abort_unless($answer !== '' && mb_strlen($answer) <= 500, 422, 'Enter your answer, up to 500 characters.');
            } else {
                abort_unless(preg_match('/^[0-3]$/D', (string) $answer) && array_key_exists((int) $answer, $question['options']), 422, 'Choose one of the available answers.');
                $answer = (int) $answer;
            }
            $existing = DB::table('quiz_bee_answers')->where('quiz_bee_run_id', $runId)->where('quiz_bee_team_id', $team->id)->first();
            if ($existing && $existing->submitted_at) {
                abort_unless(($fillBlank ? $existing->answer_text : (int) $existing->answer) === $answer, 409, 'The first submitted answer is final.');

                return;
            }
            abort_if($run->closed_at || now()->gte(Carbon::parse($run->closes_at)), 409, 'Time is up. Answers must reach the server within one minute.');
            $elapsed = max(0, (int) ceil(Carbon::parse($run->opened_at)->diffInMilliseconds(now(), false)));
            $normalize = fn ($value) => ($question['case_sensitive'] ?? true) ? trim((string) $value) : mb_strtolower(trim((string) $value));
            $correct = $fillBlank ? in_array($normalize($answer), array_map($normalize, $question['accepted_answers']), true) : $answer === $question['correct'];
            DB::table('quiz_bee_answers')->insert(['quiz_bee_run_id' => $runId, 'quiz_bee_team_id' => $team->id, 'student_id' => $student->id,
                'answer' => $fillBlank ? null : $answer, 'answer_text' => $fillBlank ? $answer : null, 'correct' => $correct, 'elapsed_ms' => $elapsed, 'xp' => $correct ? 10 + ($elapsed <= 30000 ? 5 : 0) : 0, 'submitted_at' => $this->timestamp()]);
        }, 5);
        $this->settle($match);
    }

    public function next(QuizBeeMatch $match, int $runId): void
    {
        $this->settle($match);
        DB::transaction(function () use ($match, $runId) {
            $current = QuizBeeMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'running' && $current->current_run_id === $runId, 409, 'Refresh: this question has already advanced.');
            $run = DB::table('quiz_bee_runs')->find($runId);
            abort_unless($run->closed_at, 409, 'Wait for every eligible team to answer or for the minute to end.');
            $allTeams = $this->teams($current)->pluck('id')->all();
            if ($run->kind === 'regular' && $run->question_index + 1 < count($current->questions[$run->level]['regular'])) {
                $this->open($current, $run->level, 'regular', $run->question_index + 1, $allTeams, $run->sequence + 1);

                return;
            }
            $eligible = $run->kind === 'regular' ? $allTeams : json_decode($run->eligible_team_ids, true);
            $leaders = $this->standings($current)->whereIn('id', $eligible);
            $leaders = $leaders->where('correct', $leaders->max('correct'))->pluck('id')->values()->all();
            $tieIndex = $run->kind === 'regular' ? 0 : $run->question_index + 1;
            if (count($leaders) > 1 && $tieIndex < 5) {
                $this->open($current, $run->level, 'tiebreak', $tieIndex, $leaders, $run->sequence + 1);

                return;
            }
            $position = array_search($run->level, QuizBeeMatch::LEVELS, true);
            if ($position < 2) {
                $this->open($current, QuizBeeMatch::LEVELS[$position + 1], 'regular', 0, $allTeams, $run->sequence + 1);

                return;
            }
            $this->complete($current);
        }, 5);
    }

    private function complete(QuizBeeMatch $match): void
    {
        $regularTotal = collect($match->questions)->sum(fn ($level) => count($level['regular']));
        $correct = DB::table('quiz_bee_answers')->join('quiz_bee_runs', 'quiz_bee_runs.id', '=', 'quiz_bee_answers.quiz_bee_run_id')
            ->where('quiz_bee_runs.quiz_bee_match_id', $match->id)->where('kind', 'regular')->where('correct', true)
            ->selectRaw('quiz_bee_team_id, COUNT(*) as total')->groupBy('quiz_bee_team_id')->pluck('total', 'quiz_bee_team_id');
        foreach (DB::table('quiz_bee_members')->where('quiz_bee_match_id', $match->id)->whereNotNull('student_id')->orderBy('student_id')->get() as $member) {
            if ((int) ($correct[$member->quiz_bee_team_id] ?? 0) === $regularTotal && Student::whereKey($member->student_id)->exists()) {
                $xp = app(LearningRewards::class)->award($member->student_id, 'quiz-bee:'.$match->id.':perfect', 100, ['type' => 'quiz-bee', 'id' => $match->id, 'title' => $match->title, 'reason' => 'Quiz Bee perfect-score bonus', 'details' => ['All regular questions were correct: 100 XP bonus.']]);
                DB::table('quiz_bee_members')->where('id', $member->id)->increment('xp_awarded', $xp);
            }
            app(LearningNotifications::class)->quizBee($match, 'student', $member->student_id, 'completed', 'Quiz Bee results ready', 'Your match is complete. View your final standing and saved XP.');
        }
        $match->update(['status' => 'completed', 'completed_at' => now()]);
        app(LearningNotifications::class)->quizBee($match, 'teacher', $match->teacher_account_id, 'completed', 'Quiz Bee completed', 'Final standings and XP are saved for '.$match->title.'.');
    }
}
