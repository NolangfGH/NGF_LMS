<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class Student extends Model
{
    use SoftDeletes;

    public const REGISTRATION_XP = 100;

    protected $attributes = ['session_version' => 1, 'roster_revision' => 1, 'leaderboard_visible' => true, 'registration_status' => 'approved', 'registration_revision' => 1];

    protected $fillable = ['school_name', 'ict_teacher_name', 'classroom_id', 'first_name', 'middle_name', 'surname', 'grade_level', 'section', 'nickname', 'student_id', 'password', 'avatar', 'xp', 'streak_days', 'best_streak', 'level', 'badges_count', 'achievement_goal', 'preferences', 'leaderboard_visible'];

    protected $hidden = ['password', 'session_version', 'roster_revision', 'registration_revision', 'registration_reviewed_by', 'registration_review_note'];

    protected static function booted(): void
    {
        static::addGlobalScope('registration_approved', fn (Builder $query) => $query->where('students.registration_status', 'approved'));
    }

    public function scopeWithUnapproved(Builder $query): Builder
    {
        return $query->withoutGlobalScope('registration_approved');
    }

    public function isRegistrationApproved(): bool
    {
        return $this->registration_status === 'approved';
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'xp' => 'integer', 'streak_days' => 'integer', 'best_streak' => 'integer', 'level' => 'integer', 'badges_count' => 'integer', 'preferences' => 'array', 'leaderboard_visible' => 'boolean', 'session_version' => 'integer', 'roster_revision' => 'integer', 'password_changed_at' => 'datetime', 'registration_revision' => 'integer', 'registration_reviewed_by' => 'integer', 'registration_reviewed_at' => 'datetime'];
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function achievements()
    {
        return $this->hasMany(StudentAchievement::class);
    }

    public function effectivePreferences(): array
    {
        return array_replace(config('student_settings.defaults'), Arr::only($this->preferences ?? [], array_keys(config('student_settings.defaults'))));
    }

    public function avatarAppearance(): array
    {
        return config('student_settings.avatars.'.$this->avatar) ?? config('student_settings.avatars.Astro Bot');
    }

    public function rankingName(int $viewerId): string
    {
        return $this->leaderboard_visible || $viewerId === $this->id ? $this->nickname : $this->rankingAlias();
    }

    public function rankingAlias(): string
    {
        return 'Coder-'.strtoupper(substr(hash_hmac('sha256', (string) $this->id, (string) config('app.key')), 0, 8));
    }
}
