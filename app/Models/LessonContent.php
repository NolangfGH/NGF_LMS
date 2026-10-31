<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonContent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'revision' => 'integer'];
    }
}
