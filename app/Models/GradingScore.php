<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingScore extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }
}
