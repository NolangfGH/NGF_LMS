<?php

namespace App\Services;

use App\Models\CodingMatch;

class CodingMatchPresenter
{
    public function snapshot(CodingMatch $match, ?int $studentId = null): array
    {
        $service = app(CodingMatchService::class);
        $match->load('participants.student.classroom', 'participants.submissions');
        $teacher = $studentId === null;
        $winner = $match->winning_submission_id ? $match->participants->first(fn ($p) => $p->submissions->contains('id', $match->winning_submission_id)) : null;
        $candidate = $teacher ? $service->candidate($match) : null;
        $name = fn ($p) => $teacher ? $p->student_name : ($p->student?->rankingName($studentId) ?? 'Former coder');

        return [
            'server_now' => now()->getTimestampMs(), 'phase' => $service->phase($match),
            'roster_valid' => $service->rosterValid($match),
            'started_at' => $match->started_at?->getTimestampMs(), 'deadline_at' => $match->deadline_at?->getTimestampMs(),
            'time_limit_minutes' => $match->time_limit_minutes,
            ...(! $teacher ? ['my_xp' => $match->participants->firstWhere('student_id', $studentId)?->student?->xp,
                'my_level' => $match->participants->firstWhere('student_id', $studentId)?->student?->level] : []),
            'winner' => $winner ? $name($winner) : null, 'xp_awarded' => $match->xp_awarded,
            'award_title' => $match->award_title, 'award_note' => $match->award_note, 'cancel_reason' => $match->cancel_reason,
            'candidate_id' => $candidate['submission']?->id ?? null, 'pending_earlier' => $candidate['pending_earlier'] ?? 0,
            'participants' => $match->participants->map(fn ($p) => [
                'id' => $p->id, 'name' => $name($p), 'section' => $p->section_label, 'response' => $p->response,
                'mine' => $p->student_id === $studentId,
                ...($teacher || $p->student_id === $studentId ? [
                    'code' => $p->draft_code, 'revision' => $p->draft_revision,
                    'saved_at' => $p->draft_saved_at?->toIso8601String(),
                    'submissions' => $p->submissions->sortByDesc('id')->values()->map(fn ($s) => [
                        'id' => $s->id, 'revision' => $s->revision, 'submitted_at' => $s->submitted_at->toIso8601String(),
                        'on_time' => $s->on_time, 'verdict' => $s->verdict, 'feedback' => $s->feedback,
                        ...($teacher ? ['code' => $s->code, 'observed_output' => $s->observed_output,
                            'download_url' => route('teacher.matches.download', [$match, $s])] : []),
                    ])->all(),
                ] : []),
            ])->all(),
        ];
    }
}
