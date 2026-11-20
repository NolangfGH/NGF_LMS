<?php

namespace App\Models;

use App\Services\AcademicCurriculum;
use App\Services\CreativeTechnologyCurriculum;
use Illuminate\Database\Eloquent\Model;

class CodeWorkspace extends Model
{
    protected $guarded = ['id'];

    public function isArduino(): bool
    {
        return $this->environment() === 'arduino-uno';
    }

    public function environment(): string
    {
        if ($this->teacher_assignment_id) {
            return match ($this->assignment?->unit) {
                'Arduino UNO R3', CreativeTechnologyCurriculum::ROBOTICS_UNIT => 'arduino-uno',
                CreativeTechnologyCurriculum::DESIGN_UNIT => 'design',
                AcademicCurriculum::UNIT => 'academic',
                default => 'cpp',
            };
        }

        return config('code_lab.activities.'.str_replace('practice:', '', $this->activity_key).'.environment', 'cpp');
    }

    public function filename(): string
    {
        return match ($this->environment()) {
            'arduino-uno' => 'sketch.ino',
            'design' => 'design-notebook.txt',
            'academic' => 'study-notebook.txt',
            default => 'main.cpp',
        };
    }

    protected function casts(): array
    {
        return ['assignment_opened_at' => 'datetime', 'opening_xp_awarded' => 'integer', 'timer_started_at' => 'datetime', 'time_limit_seconds' => 'integer'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function secondChanceRequest()
    {
        return $this->belongsTo(SecondChanceRequest::class);
    }

    public function assignment()
    {
        return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id');
    }

    public function submissions()
    {
        return $this->hasMany(LabSubmission::class);
    }
}
