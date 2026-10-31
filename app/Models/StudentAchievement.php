<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAchievement extends Model
{
    public $timestamps=false;
    protected $guarded=['id'];
    protected function casts(): array { return ['earned_at'=>'datetime','seen_at'=>'datetime','evidence'=>'array']; }
    public function student() { return $this->belongsTo(Student::class); }
}
