<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingSheet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weights' => 'array', 'passing_score' => 'float', 'revision' => 'integer'];
    }

    public function assessments()
    {
        return $this->hasMany(GradingAssessment::class)->orderBy('category')->orderBy('id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
