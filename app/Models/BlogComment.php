<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogComment extends Model
{
    protected $fillable = ['body'];

    protected $hidden = ['image_path', 'image_mime', 'reply_image_path', 'reply_image_mime', 'audio_path', 'audio_mime'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime','graded_at'=>'datetime','submission_number'=>'integer','xp_credited'=>'integer','auto_submitted'=>'boolean'];
    }

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function responses()
    {
        return $this->hasMany(BlogResponse::class)->orderBy('created_at')->orderBy('id');
    }
}
