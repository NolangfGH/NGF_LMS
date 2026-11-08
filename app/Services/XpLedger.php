<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class XpLedger
{
    public const CHECKPOINT_PENALTY = 2;

    public const ASSESSMENT_PENALTY = 5;

    public function existing(int $studentId, string $key, ?string $fingerprint = null): ?object
    {
        $event = DB::table('xp_events')->where('student_id', $studentId)->where('event_key', $key)->first();
        if ($event && $fingerprint !== null) {
            abort_unless($event->request_fingerprint && hash_equals($event->request_fingerprint, $fingerprint), 409, 'This submission key was already used for different answers. Refresh the activity before submitting again.');
        }

        return $event;
    }

    /** @param array{type: string, id?: int|string, title: string, reason: string, details?: list<string>} $context */
    public function change(int $studentId, string $key, int $requested, array $context, ?string $fingerprint = null): int
    {
        return DB::transaction(function () use ($studentId, $key, $requested, $context, $fingerprint): int {
            $student = Student::whereKey($studentId)->lockForUpdate()->firstOrFail();
            if ($this->existing($studentId, $key, $fingerprint)) {
                return 0;
            }
            $balance = max(0, $student->xp + $requested);
            $amount = $balance - $student->xp;
            $student->xpLedgerManaged = true;
            try {
                $student->xp = $balance;
                $student->level = intdiv($balance, 600) + 1;
                $student->save();
            } finally {
                $student->xpLedgerManaged = false;
            }
            DB::table('xp_events')->insert(['student_id' => $studentId, 'event_key' => $key, 'amount' => $amount, 'requested_amount' => $requested,
                'balance_after' => $balance, 'reason' => Str::limit($context['reason'], 120, ''), 'source_type' => $context['type'],
                'source_id' => isset($context['id']) ? (string) $context['id'] : null, 'activity_title' => Str::limit($context['title'], 180, ''),
                'details' => json_encode($context['details'] ?? [], JSON_THROW_ON_ERROR), 'request_fingerprint' => $fingerprint, 'counts_for_period' => true, 'created_at' => now()]);

            return $amount;
        }, 3);
    }

    public function history(Student $student, Request $request): array
    {
        $filters = $request->validate(['xp_filter' => ['nullable', Rule::in(['all', 'earned', 'deducted'])], 'xp_page' => ['nullable', 'integer', 'min:1']]);
        $filter = $filters['xp_filter'] ?? 'all';
        $query = DB::table('xp_events')->where('student_id', $student->id);
        $totals = (clone $query)->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS earned, COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS deducted')->first();
        if ($filter === 'earned') {
            $query->where('amount', '>', 0);
        } elseif ($filter === 'deducted') {
            $query->whereRaw('COALESCE(requested_amount, amount) < 0');
        }
        $events = $query->orderByDesc('id')->paginate(15, ['id', 'amount', 'requested_amount', 'balance_after', 'source_type', 'activity_title', 'reason', 'details', 'created_at'], 'xp_page')->withQueryString()
            ->through(fn ($event): array => ['amount' => (int) $event->amount, 'requested' => (int) ($event->requested_amount ?? $event->amount), 'balance' => $event->balance_after,
                'title' => $event->activity_title ?: $event->reason, 'reason' => $event->reason, 'legacy' => $event->source_type === null,
                'details' => $event->details ? json_decode($event->details, true, flags: JSON_THROW_ON_ERROR) : [], 'at' => $event->created_at]);

        return ['events' => $events, 'filter' => $filter, 'balance' => (int) $student->fresh()->xp, 'earned' => (int) $totals->earned, 'deducted' => (int) $totals->deducted];
    }
}
