<?php

namespace App\Models;

use Database\Factories\RecitationRoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecitationRound extends Model
{
    /** @use HasFactory<RecitationRoundFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['accepted_answers'];

    protected $attributes = ['status' => 'draft', 'revision' => 1, 'case_sensitive' => true];

    protected static function booted(): void
    {
        static::creating(function (self $round): void {
            $year = now(config('learning.timezone'))->year;
            $round->school_year ??= $year.'-'.($year + 1);
        });
    }

    protected function casts(): array
    {
        return ['accepted_answers' => 'array', 'case_sensitive' => 'boolean', 'revision' => 'integer'];
    }
}
