<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
        });
        Schema::table('code_workspaces', function (Blueprint $table) {
            $table->timestamp('timer_started_at')->nullable();
            $table->unsignedInteger('time_limit_seconds')->nullable();
        });
        Schema::table('lab_submissions', function (Blueprint $table) {
            $table->text('sample_output')->nullable();
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->unsignedInteger('time_limit_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lab_submissions', function (Blueprint $table) {
            $table->dropColumn(['sample_output','time_spent_seconds','time_limit_seconds']);
        });
        Schema::table('code_workspaces', function (Blueprint $table) {
            $table->dropColumn(['timer_started_at','time_limit_seconds']);
        });
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropColumn('time_limit_minutes');
        });
    }
};
