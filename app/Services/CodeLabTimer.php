<?php

namespace App\Services;

use App\Models\CodeWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CodeLabTimer
{
    // Call only after locking the student's workspace inside a transaction.
    public function startIfNeeded(CodeWorkspace $workspace): void
    {
        app(SecondChanceService::class)->activateAssignment($workspace);
        if (!$workspace->timer_started_at && $workspace->assignment?->time_limit_minutes) {
            $workspace->update([
                'timer_started_at'=>now()->startOfSecond(),
                'time_limit_seconds'=>$workspace->assignment->time_limit_minutes * 60,
            ]);
        }
    }

    public function deadline(CodeWorkspace $workspace): ?CarbonInterface
    {
        if (!$workspace->timer_started_at || !$workspace->time_limit_seconds) return null;
        $deadline=$workspace->timer_started_at->copy()->addSeconds($workspace->time_limit_seconds);
        $due=$workspace->secondChanceRequest?->expires_at ?? $workspace->assignment?->due_at;
        return $due && $due->lt($deadline) ? $due : $deadline;
    }

    public function describe(CodeWorkspace $workspace): array
    {
        $deadline=$this->deadline($workspace);
        return [
            'serverNow'=>now()->getTimestampMs(),
            'startedAt'=>$workspace->timer_started_at?->getTimestampMs(),
            'expiresAt'=>$deadline?->getTimestampMs(),
            'durationSeconds'=>$workspace->time_limit_seconds,
            'expired'=>$deadline && now()->greaterThanOrEqualTo($deadline),
        ];
    }

    public function enforce(CodeWorkspace $workspace): void
    {
        if ($this->describe($workspace)['expired']) {
            throw ValidationException::withMessages(['time'=>'Your allotted time or assignment deadline has ended. Your last saved draft is kept. Download any unsaved code for your records.']);
        }
    }

    public function elapsed(CodeWorkspace $workspace): ?int
    {
        if (!$workspace->timer_started_at) return null;
        return max(0,(int)$workspace->timer_started_at->diffInSeconds(now()));
    }
}
