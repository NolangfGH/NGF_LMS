<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizBeeMatch extends Model
{
    public const LEVELS = ['easy', 'average', 'difficult'];

    protected $guarded = ['id'];

    protected $hidden = ['questions'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['questions' => 'array', 'current_run_id' => 'integer', 'teacher_account_id' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
