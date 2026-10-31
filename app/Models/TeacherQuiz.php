<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherQuiz extends Model
{
    use SoftDeletes;

    protected $fillable = ['teacher_account_id', 'classroom_id', 'title', 'instructions', 'questions', 'status', 'xp', 'time_limit', 'opens_at', 'closes_at', 'untimed', 'question_seconds'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'xp' => 'integer', 'time_limit' => 'integer', 'opens_at' => 'datetime', 'closes_at' => 'datetime', 'untimed'=>'boolean', 'question_seconds'=>'integer'];
    }

    public function isOpen(): bool
    {
        return (!$this->opens_at || now()->gte($this->opens_at))
            && (!$this->closes_at || now()->lt($this->closes_at));
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_teacher_quiz');
    }

    public function scopeForClassroom($query, int $classroomId)
    {
        return $query->where(fn ($targets) => $targets->where('classroom_id', $classroomId)
            ->orWhereHas('classrooms', fn ($rooms) => $rooms->where('classrooms.id', $classroomId)));
    }

    public function isAssignedTo(int $classroomId): bool
    {
        return (int) $this->classroom_id === $classroomId
            || $this->classrooms()->where('classrooms.id', $classroomId)->exists();
    }
}
