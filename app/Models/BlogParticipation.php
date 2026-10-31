<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogParticipation extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['submission_count' => 0, 'xp_awarded' => 0];

    protected function casts(): array
    {
        return ['submission_count' => 'integer', 'xp_awarded' => 'integer'];
    }

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function attempts()
    {
        return $this->hasMany(BlogResponseAttempt::class);
    }
}
