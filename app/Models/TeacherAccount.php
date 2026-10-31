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

    protected $attributes = ['session_version' => 1, 'profile_visible' => true];

    protected $fillable = ['first_name', 'middle_initial', 'last_name', 'email', 'school', 'password', 'display_name', 'bio', 'profile_visible', 'preferences'];

    protected $hidden = ['password', 'remember_token', 'avatar_path', 'avatar_mime', 'session_version'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'preferences' => 'array', 'profile_visible' => 'boolean', 'session_version' => 'integer', 'password_changed_at' => 'datetime'];
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class)->whereNull('archived_at');
    }

    public function isSchoolAdmin(): bool { return $this->role === 'school_admin'; }

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
