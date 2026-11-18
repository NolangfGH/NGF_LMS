<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizBeeMatch extends Model
{
    public const LEVELS = ['easy', 'average', 'difficult'];

    public const COURSES = ['cpp' => 'C++ Programming', 'arduino-uno' => 'Arduino Uno Robotics'];

    protected $attributes = ['course' => 'cpp'];

    public function courseTitle(): string
    {
        return self::COURSES[$this->course] ?? self::COURSES['cpp'];
    }

    public static function questionBank(string $course): array
    {
        return config($course === 'arduino-uno' ? 'arduino_quiz_bee' : 'quiz_bee.questions');
    }

    protected $guarded = ['id'];

    protected $hidden = ['questions'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['questions' => 'array', 'current_run_id' => 'integer', 'teacher_account_id' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
