<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_opened_at' => 'datetime', 'completed_at' => 'datetime', 'feedback_at' => 'datetime', 'answers' => 'array', 'completion_evidence' => 'array', 'last_section' => 'integer', 'attempts' => 'integer', 'latest_score' => 'integer', 'best_score' => 'integer'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
