<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogResponseAttempt extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['image_path', 'image_mime', 'audio_path', 'audio_mime'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function participation()
    {
        return $this->belongsTo(BlogParticipation::class, 'blog_participation_id');
    }

    public function comment()
    {
        return $this->belongsTo(BlogComment::class, 'blog_comment_id');
    }
}
