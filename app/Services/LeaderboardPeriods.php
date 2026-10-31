<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardPeriods
{
    public function points(Collection $students, string $period): Collection
    {
        if ($period === 'all') {
            return $students->mapWithKeys(fn ($student) => [$student->id => (int) $student->xp]);
        }
        $now = now(config('learning.timezone'));
        $start = $period === 'week' ? $now->copy()->startOfWeek() : $now->copy()->startOfMonth();

        return DB::table('xp_events')->whereIn('student_id', $students->pluck('id'))->where('counts_for_period', true)
            ->where('created_at', '>=', $start->utc())->where('created_at', '<=', $now->copy()->utc())
            ->groupBy('student_id')->selectRaw('student_id, SUM(amount) as points')->pluck('points', 'student_id');
    }
}
