<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogResponse extends Model
{
    protected $fillable = ['body'];

    protected $hidden = ['image_path', 'image_mime', 'audio_path', 'audio_mime'];

    protected function casts(): array
    {
        return ['graded_at'=>'datetime','submission_number'=>'integer','xp_credited'=>'integer','auto_submitted'=>'boolean'];
    }

    public function comment()
    {
        return $this->belongsTo(BlogComment::class, 'blog_comment_id');
    }
}
