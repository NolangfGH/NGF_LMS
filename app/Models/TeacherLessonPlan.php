<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherLessonPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
