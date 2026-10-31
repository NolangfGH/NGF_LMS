<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecondChanceRequest extends Model
{
    protected $guarded=['id'];
    protected function casts(): array
    {
        return ['expires_at'=>'datetime','reviewed_at'=>'datetime','used_at'=>'datetime','prior_timer_started_at'=>'datetime'];
    }
    public function student() { return $this->belongsTo(Student::class)->withTrashed(); }
    public function activity()
    {
        return $this->belongsTo($this->activity_type === 'assignment' ? TeacherAssignment::class : TeacherQuiz::class,'activity_id');
    }
    public function isActive(): bool
    {
        return $this->status === 'approved' && $this->expires_at && now()->lt($this->expires_at);
    }
}
