<?php

namespace App\Services;

use App\Models\QuizBeeMatch;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuizBeePresenter
{
    public function snapshot(QuizBeeMatch $match, ?Student $student = null): array
    {
        $service = app(QuizBeeService::class);
        $service->settle($match);
        $match->refresh();
        $ownTeam = $student ? $service->teamFor($match, $student->id) : null;
        $run = $match->current_run_id ? DB::table('quiz_bee_runs')->find($match->current_run_id) : null;
        $members = DB::table('quiz_bee_members')->where('quiz_bee_match_id', $match->id)->get();
        $students = Student::whereIn('id', $members->pluck('student_id'))->get()->keyBy('id');
        $question = null;
        $answers = $run ? DB::table('quiz_bee_answers')->where('quiz_bee_run_id', $run->id)->get()->keyBy('quiz_bee_team_id') : collect();
        if ($run && $match->status !== 'cancelled') {
            $source = json_decode($run->question, true);
            $fillBlank = ($source['type'] ?? 'multiple_choice') === 'fill_blank';
            $eligible = json_decode($run->eligible_team_ids, true);
            $receipt = $ownTeam ? $answers->get($ownTeam->id) : null;
            $question = ['id' => $run->id, 'sequence' => $run->sequence, 'level' => $run->level, 'kind' => $run->kind,
                'number' => $run->question_index + 1, 'total' => count($match->questions[$run->level][$run->kind]), 'prompt' => $source['prompt'],
                'type' => $fillBlank ? 'fill_blank' : 'multiple_choice', 'options' => $fillBlank ? [] : $source['options'],
                'case_sensitive' => $fillBlank ? ($source['case_sensitive'] ?? true) : null,
                'opened_at' => Carbon::parse($run->opened_at)->toISOString(), 'closes_at' => Carbon::parse($run->closes_at)->toISOString(), 'closed' => $run->closed_at !== null,
                'answered' => $answers->whereNotNull('submitted_at')->count(), 'eligible' => count($eligible),
                'can_answer' => $match->status === 'running' && ! $run->closed_at && $ownTeam && (int) $ownTeam->captain_id === $student?->id && in_array($ownTeam->id, $eligible, true) && ! $receipt,
                'own_answer' => $fillBlank ? $receipt?->answer_text : $receipt?->answer, 'own_seconds' => $receipt ? (int) ceil($receipt->elapsed_ms / 1000) : null,
                'own_correct' => $run->closed_at ? ($receipt ? (bool) $receipt->correct : null) : null,
                'own_xp' => $run->closed_at ? ($receipt?->xp ?? 0) : null,
                'correct' => $run->closed_at && ! $fillBlank ? $source['correct'] : null,
                'accepted_answers' => $run->closed_at && $fillBlank ? $source['accepted_answers'] : null, 'why' => $run->closed_at ? ($source['why'] ?? '') : null];
        }
        $rank = 0;
        $previous = null;
        $standings = $service->standings($match)->map(function ($team, $index) use (&$rank, &$previous, $student, $students, $members, $answers, $run, $match) {
            if ($previous !== $team['correct']) {
                $rank = $index + 1;
            }
            $previous = $team['correct'];
            $memberRows = $members->where('quiz_bee_team_id', $team['id']);
            $individual = $students->get($team['captain_id']);
            $name = $match->category === 'individual' && $individual && $student ? $individual->rankingName($student->id) : $team['name'];
            $answer = $answers->get($team['id']);

            return ['id' => $team['id'], 'rank' => $rank, 'name' => $name, 'correct' => $team['correct'], 'ready' => $team['ready'],
                'xp_per_member' => (int) $memberRows->max('xp_awarded'), 'members' => $memberRows->count(),
                'answered' => $answer?->submitted_at !== null,
                'in_round' => ! $run || in_array($team['id'], json_decode($run->eligible_team_ids, true), true),
                'seconds' => ! $student && $run?->closed_at && $answer ? (int) ceil($answer->elapsed_ms / 1000) : null,
                'last_correct' => ! $student && $run?->closed_at && $answer ? (bool) $answer->correct : null,
                'own' => $student && $memberRows->contains('student_id', $student->id)];
        })->all();
        $teamMembers = $ownTeam ? $members->where('quiz_bee_team_id', $ownTeam->id)->map(fn ($member) => ['nickname' => $students->get($member->student_id)?->nickname ?? 'Former learner',
            'captain' => (int) $member->student_id === (int) $ownTeam->captain_id, 'xp' => $member->xp_awarded])->values()->all() : [];

        return ['id' => $match->id, 'title' => $match->title, 'category' => $match->category, 'status' => $match->status,
            'server_now' => now()->toISOString(), 'question' => $question, 'standings' => $standings, 'members' => $teamMembers,
            'regular_total' => collect($match->questions)->sum(fn ($level) => count($level['regular'])),
            'can_ready' => $match->status === 'lobby' && $ownTeam && (int) $ownTeam->captain_id === $student?->id && ! $ownTeam->ready_at,
            'can_start' => ! $student && $match->status === 'lobby' && count($standings) >= 2 && collect($standings)->every('ready'),
            'can_next' => ! $student && $match->status === 'running' && $run?->closed_at !== null,
            'own_team' => $ownTeam?->id];
    }
}
