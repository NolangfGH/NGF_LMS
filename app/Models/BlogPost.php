<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'category', 'body', 'status', 'is_graded', 'response_time_limit_minutes'];

    protected $hidden = ['image_path', 'image_mime'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_graded'=>'boolean', 'response_time_limit_minutes'=>'integer'];
    }

    public function teacher()
    {
        return $this->belongsTo(TeacherAccount::class, 'teacher_account_id');
    }

    public function comments()
    {
        return $this->hasMany(BlogComment::class);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(count(preg_split('/\s+/u', trim($this->body ?? ''), -1, PREG_SPLIT_NO_EMPTY)) / 180));
    }

    public function excerpt(): string
    {
        return filled($this->body) ? Str::limit($this->body, 180) : 'Open this illustrated topic and share what you learned with your teacher.';
    }
}
