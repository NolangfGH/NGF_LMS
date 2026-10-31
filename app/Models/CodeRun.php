<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodeRun extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code', 'cases', 'tokens', 'results', 'request_key'];

    protected function casts(): array
    {
        return ['cases' => 'array', 'tokens' => 'array', 'results' => 'array', 'last_polled_at' => 'datetime', 'earned' => 'integer', 'possible' => 'integer', 'revision' => 'integer'];
    }

    public function workspace()
    {
        return $this->belongsTo(CodeWorkspace::class, 'code_workspace_id');
    }
}
