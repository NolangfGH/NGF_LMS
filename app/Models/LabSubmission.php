<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabSubmission extends Model
{
    protected $guarded = ['id'];
    protected $hidden=['ai_suggestion'];
    protected function casts(): array { return ['rubric'=>'array','reviewed_at'=>'datetime','time_spent_seconds'=>'integer','time_limit_seconds'=>'integer','ai_suggestion'=>'array','ai_generated_at'=>'datetime','resubmission_requested'=>'boolean']; }
    public function workspace() { return $this->belongsTo(CodeWorkspace::class,'code_workspace_id'); }
    public function codeRun() { return $this->belongsTo(CodeRun::class); }
}
