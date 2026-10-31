<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('badge_code',60);
            $table->timestamp('earned_at');
            $table->json('evidence');
            $table->timestamp('seen_at')->nullable();
            $table->unique(['student_id','badge_code']);
        });
        Schema::table('students',fn (Blueprint $table)=>$table->string('achievement_goal',60)->nullable());
    }
    public function down(): void
    {
        Schema::table('students',fn (Blueprint $table)=>$table->dropColumn('achievement_goal'));
        Schema::dropIfExists('student_achievements');
    }
};
