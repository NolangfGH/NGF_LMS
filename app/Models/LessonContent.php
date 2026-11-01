<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonContent extends Model
{
    public const MIN_CHECKPOINT_QUESTIONS = 5;

    public const MAX_CHECKPOINT_QUESTIONS = 20;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'revision' => 'integer'];
    }
}
