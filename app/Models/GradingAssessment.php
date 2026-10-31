<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingAssessment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['max_score' => 'float', 'due_date' => 'date'];
    }

    public function sheet()
    {
        return $this->belongsTo(GradingSheet::class, 'grading_sheet_id');
    }

    public function scores()
    {
        return $this->hasMany(GradingScore::class);
    }
}
