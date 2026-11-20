<?php

namespace App\Models;

use App\Services\AcademicCurriculum;
use App\Services\CodingMatchService;
use App\Services\CreativeTechnologyCurriculum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherAssignment extends Model
{
    use SoftDeletes;

    protected $attributes = ['kind' => 'assignment'];

    protected $hidden = ['test_cases'];

    protected $fillable = ['kind', 'teacher_account_id', 'classroom_id', 'title', 'unit', 'course', 'school_term', 'objective', 'due_at', 'difficulty', 'xp', 'status', 'starter_code', 'sample_output', 'csta_standard', 'time_limit_minutes', 'test_cases', 'hints'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'xp' => 'integer', 'time_limit_minutes' => 'integer', 'test_cases' => 'array', 'hints' => 'array'];
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function isClosed(): bool
    {
        return $this->due_at !== null && now()->greaterThanOrEqualTo($this->due_at);
    }

    public function starterCode(): string
    {
        return $this->starter_code ?: match ($this->unit) {
            CreativeTechnologyCurriculum::DESIGN_UNIT => CodingMatchService::starterCode('ct7'),
            AcademicCurriculum::UNIT => AcademicCurriculum::STARTER,
            CreativeTechnologyCurriculum::ROBOTICS_UNIT, 'Arduino UNO R3' => CodingMatchService::starterCode('arduino-uno'),
            default => config('code_lab.assignment_starter'),
        };
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_teacher_assignment');
    }

    public function scopeForClassroom($query, int $classroomId)
    {
        return $query->where(fn ($targets) => $targets->where('classroom_id', $classroomId)
            ->orWhereHas('classrooms', fn ($rooms) => $rooms->where('classrooms.id', $classroomId)));
    }

    public function isAssignedTo(int $classroomId): bool
    {
        return (int) $this->classroom_id === $classroomId || $this->classrooms()->where('classrooms.id', $classroomId)->exists();
    }
}
