<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $attributes = ['enrollment_open' => true];

    protected $fillable = ['teacher_account_id', 'name', 'grade', 'period', 'join_code', 'section', 'enrollment_open', 'archived_at', 'sequential_learning', 'weekly_xp_goal'];

    protected function casts(): array
    {
        return ['enrollment_open' => 'boolean', 'archived_at' => 'datetime', 'sequential_learning' => 'boolean', 'weekly_xp_goal' => 'integer'];
    }

    public function scopeOpenForStudentRegistration(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->where('enrollment_open', true)
            ->whereIn('grade', config('teacher_settings.grades'))
            ->whereNotNull('section')->whereRaw("TRIM(section) <> ''")
            ->where(fn (Builder $grades) => $grades->where('grade', '<>', config('student_registration.grade'))
                ->orWhereIn('section', config('student_registration.sections')));
    }

    public function acceptsStudentRegistration(): bool
    {
        return ! $this->archived_at && $this->enrollment_open && filled($this->section)
            && in_array($this->grade, config('teacher_settings.grades'), true)
            && ($this->grade !== config('student_registration.grade') || in_array($this->section, config('student_registration.sections'), true));
    }

    public function registrationFingerprint(): string
    {
        return hash('sha256', json_encode([$this->join_code, $this->grade, $this->section], JSON_THROW_ON_ERROR));
    }

    public function teacher()
    {
        return $this->belongsTo(TeacherAccount::class, 'teacher_account_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
