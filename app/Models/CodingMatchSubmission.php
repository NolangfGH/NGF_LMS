<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingMatchSubmission extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'on_time' => 'boolean', 'revision' => 'integer'];
    }

    public function participant()
    {
        return $this->belongsTo(CodingMatchParticipant::class, 'participant_id');
    }
}
