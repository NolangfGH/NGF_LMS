<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeNotice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
