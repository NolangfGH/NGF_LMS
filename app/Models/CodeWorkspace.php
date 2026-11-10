<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeWorkspace extends Model
{
    protected $guarded = ['id'];

    public function isArduino(): bool
    {
        return $this->teacher_assignment_id
            ? $this->assignment?->unit === 'Arduino UNO R3'
            : config('code_lab.activities.'.str_replace('practice:', '', $this->activity_key).'.environment') === 'arduino-uno';
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
