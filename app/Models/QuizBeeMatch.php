<?php

namespace App\Models;

use App\Services\LearningCourses;
use Illuminate\Database\Eloquent\Model;

class QuizBeeMatch extends Model
{
    public const LEVELS = ['easy', 'average', 'difficult'];

    public const COURSES = LearningCourses::TITLES;

    protected $attributes = ['course' => 'cpp'];

    public function courseTitle(): string
    {
        return self::COURSES[$this->course] ?? self::COURSES['cpp'];
    }

    public static function questionBank(string $course): array
    {
        if (LearningCourses::isCreative($course)) {
            return config('creative_technology.'.$course.'.bee');
        }

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
