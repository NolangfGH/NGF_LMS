<?php

namespace App\Models;

use Database\Factories\ParentAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ParentAccount extends Authenticatable
{
    /** @use HasFactory<ParentAccountFactory> */
    use HasFactory;

    protected $attributes = ['session_version' => 1];

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'session_version'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'session_version' => 'integer'];
    }
}
