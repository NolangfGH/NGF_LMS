<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;

class TeacherAccount extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['teacher' => 'Teacher', 'principal' => 'Principal', 'school_admin' => 'System Admin'];

    protected $attributes = ['session_version' => 1, 'profile_visible' => true, 'role' => 'teacher', 'staff_access_status' => 'active', 'staff_revision' => 1];

    protected $fillable = ['first_name', 'middle_initial', 'last_name', 'email', 'school', 'password', 'display_name', 'bio', 'profile_visible', 'preferences'];

    protected $hidden = ['password', 'remember_token', 'avatar_path', 'avatar_mime', 'session_version', 'staff_review_note', 'staff_revision'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'preferences' => 'array', 'profile_visible' => 'boolean', 'session_version' => 'integer', 'password_changed_at' => 'datetime', 'staff_reviewed_at' => 'datetime', 'staff_revision' => 'integer'];
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class)->whereNull('archived_at');
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === 'school_admin';
    }

    public function isPrincipal(): bool
    {
        return $this->role === 'principal';
    }

    public function hasStaffAccess(): bool
    {
        return $this->staff_access_status === 'active' && isset(self::ROLES[$this->role]);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Staff';
    }

    public function dashboardRoute(): string
    {
        return $this->isPrincipal() ? 'school.overview' : 'teacher.dashboard';
    }

    public function portalLabel(): string
    {
        return $this->isPrincipal() ? 'Principal Portal' : ($this->isSchoolAdmin() ? 'Admin Portal' : 'Teacher Portal');
    }

    public static function roleRules(): array
    {
        return [
            'teacher' => ['Manage your own classes and join codes', 'Approve students enrolled in your own sections', 'Create lessons, assignments, quizzes, challenges and articles', 'Review student work, give private feedback and maintain your grading sheets'],
            'principal' => ['View your school’s student progress, achievements and XP rankings', 'Browse teacher-prepared lessons, quizzes, assignments and challenges', 'Manage your own profile and password; school affiliation requires System Admin confirmation', 'Read-only learning access: no grading, editing, answer keys or private student conversations'],
            'school_admin' => ['Approve or decline Principal and System Admin requests', 'Create staff accounts; confirm your password for access approvals and role changes', 'Check the compiler connection and archive or restore classes', 'Manage teaching and private student work only within your own sections'],
        ];
    }

    protected function middleInitial(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => filled($value) ? mb_substr(mb_strtoupper(trim($value)), 0, 1) : null);
    }

    public function fullName(): string
    {
        return implode(' ', array_filter([$this->first_name, filled($this->middle_initial) ? $this->middle_initial.'.' : null, $this->last_name], fn ($part) => filled($part)));
    }

    public function effectivePreferences(): array
    {
        return array_replace(config('teacher_settings.defaults'), Arr::only($this->preferences ?? [], array_keys(config('teacher_settings.defaults'))));
    }

    public function classroomProfile(): array
    {
        return ['name' => $this->profile_visible && filled($this->display_name) ? $this->display_name : $this->fullName(),
            'bio' => $this->profile_visible ? $this->bio : null,
            'avatar' => $this->profile_visible && $this->avatar_path ? route('teacher.settings.avatar', $this->id) : null];
    }
}
