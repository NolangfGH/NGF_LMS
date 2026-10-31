<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingMatchParticipant extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['draft_code'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'draft_saved_at' => 'datetime', 'draft_revision' => 'integer'];
    }

    public function match()
    {
        return $this->belongsTo(CodingMatch::class, 'coding_match_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function submissions()
    {
        return $this->hasMany(CodingMatchSubmission::class, 'participant_id');
    }
}
