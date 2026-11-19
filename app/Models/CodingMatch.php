<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CodingMatch extends Model
{
    protected $attributes = ['participant_count' => 2, 'course' => 'cpp', 'origin' => 'teacher'];

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'deadline_at' => 'datetime', 'awarded_at' => 'datetime', 'cancelled_at' => 'datetime', 'winner_xp' => 'integer', 'xp_awarded' => 'integer', 'time_limit_minutes' => 'integer', 'participant_count' => 'integer', 'requested_by_student_id' => 'integer'];
    }

    public function participants()
    {
        return $this->hasMany(CodingMatchParticipant::class)->orderBy('slot');
    }

    public function winningSubmission()
    {
        return $this->belongsTo(CodingMatchSubmission::class, 'winning_submission_id');
    }

    public function scopeSupervisedBy(Builder $query, int $teacherId): Builder
    {
        return $query->where(fn (Builder $scope): Builder => $scope->where('teacher_account_id', $teacherId)
            ->orWhere(fn (Builder $peer): Builder => $peer->where('origin', 'student')->whereIn('id', DB::table('coding_match_approvals')
                ->where('teacher_account_id', $teacherId)->where(fn (\Illuminate\Database\Query\Builder $approval): \Illuminate\Database\Query\Builder => $approval->where('decision', 'approved')
                ->orWhereIn('coding_matches.status', ['awaiting_approval', 'cancelled']))->select('coding_match_id'))));
    }

    public function canBeViewedByTeacher(int $teacherId): bool
    {
        return (int) $this->teacher_account_id === $teacherId || static::supervisedBy($teacherId)->whereKey($this->id)->exists();
    }
}
