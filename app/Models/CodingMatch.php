<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingMatch extends Model
{
    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'deadline_at' => 'datetime', 'awarded_at' => 'datetime', 'cancelled_at' => 'datetime', 'winner_xp' => 'integer', 'xp_awarded' => 'integer', 'time_limit_minutes' => 'integer'];
    }

    public function participants()
    {
        return $this->hasMany(CodingMatchParticipant::class)->orderBy('slot');
    }

    public function winningSubmission()
    {
        return $this->belongsTo(CodingMatchSubmission::class, 'winning_submission_id');
    }
}
