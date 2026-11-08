<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecitationAttempt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['correct' => 'boolean'];
    }
}
