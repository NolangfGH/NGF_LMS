<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id'];

    protected $attributes = ['attempt_number' => 1, 'untimed' => false, 'question_seconds' => 60, 'wrong_answer_penalty' => 0];

    protected $hidden = ['questions'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'answers' => 'array', 'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime', 'sequential' => 'boolean', 'current_question' => 'integer', 'question_started_at' => 'datetime', 'question_timings' => 'array', 'untimed' => 'boolean', 'question_seconds' => 'integer'];
    }

    public function questionDeadline()
    {
        return $this->untimed ? $this->expires_at : $this->question_started_at->copy()->addSeconds($this->question_seconds)->min($this->expires_at);
    }

    public function secondChanceRequest()
    {
        return $this->belongsTo(SecondChanceRequest::class);
    }

    public function scheduleDeadline(?TeacherQuiz $quiz)
    {
        return $this->attempt_number > 1 ? ($this->secondChanceRequest?->expires_at ?? $this->expires_at) : $quiz?->closes_at;
    }

    public function scheduleOpen(TeacherQuiz $quiz): bool
    {
        return $this->attempt_number > 1 ? (bool) $this->secondChanceRequest?->isActive() : $quiz->isOpen();
    }
}
