<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('second_chance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type',20);
            $table->unsignedBigInteger('activity_id');
            $table->string('activity_title');
            $table->text('reason');
            $table->string('proof_path');
            $table->string('proof_mime',50);
            $table->string('status',20)->default('pending');
            $table->text('teacher_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('prior_timer_started_at')->nullable();
            $table->unsignedInteger('prior_time_limit_seconds')->nullable();
            $table->timestamps();
            $table->unique(['student_id','activity_type','activity_id'],'second_chance_student_activity_unique');
        });
        foreach (['code_workspaces','lab_submissions','quiz_attempts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('second_chance_request_id')->nullable()->constrained()->nullOnDelete();
            });
        }
        foreach (['quiz_attempts','quiz_submissions'] as $name) {
            Schema::table($name, fn (Blueprint $table)=>$table->index('student_id',$name.'_student_lookup'));
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedTinyInteger('attempt_number')->default(1);
                $table->dropUnique(['student_id','teacher_quiz_id']);
                $table->unique(['student_id','teacher_quiz_id','attempt_number']);
            });
        }
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->unsignedInteger('xp_credited')->nullable();
        });
        DB::table('quiz_submissions')->whereNotNull('xp_awarded_at')
            ->update(['xp_credited'=>DB::raw('answer_xp + perfect_bonus_xp + speed_bonus_xp')]);
    }

    public function down(): void
    {
        if (DB::table('second_chance_requests')->exists() ||
            DB::table('quiz_attempts')->where('attempt_number',2)->exists() ||
            DB::table('quiz_submissions')->where('attempt_number',2)->exists()) {
            throw new \RuntimeException('Archive the final-opportunity history before rolling back this migration.');
        }
        foreach (['quiz_attempts','quiz_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['student_id','teacher_quiz_id','attempt_number']);
                $table->dropColumn('attempt_number');
                $table->unique(['student_id','teacher_quiz_id']);
            });
            if (Schema::hasIndex($name,$name.'_student_lookup')) {
                Schema::table($name,fn (Blueprint $table)=>$table->dropIndex($name.'_student_lookup'));
            }
        }
        Schema::table('quiz_submissions',fn (Blueprint $table)=>$table->dropColumn('xp_credited'));
        foreach (['code_workspaces','lab_submissions','quiz_attempts'] as $name) {
            Schema::table($name, fn (Blueprint $table)=>$table->dropConstrainedForeignId('second_chance_request_id'));
        }
        Schema::dropIfExists('second_chance_requests');
    }
};
