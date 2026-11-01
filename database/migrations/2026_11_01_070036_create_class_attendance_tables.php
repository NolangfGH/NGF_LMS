<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->string('title', 160);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('checkin_opens_at');
            $table->timestamp('checkin_closes_at');
            $table->string('checkin_code', 12);
            $table->boolean('checkin_enabled')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['classroom_id', 'session_date']);
        });
        Schema::create('attendance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_name', 250);
            $table->string('school_id', 40);
            $table->string('status', 12)->default('unmarked');
            $table->string('method', 20)->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['attendance_session_id', 'student_id']);
        });
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_entry_id')->constrained()->cascadeOnDelete();
            $table->string('requested_status', 12);
            $table->string('reason', 1000);
            $table->string('decision', 12)->default('pending');
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('attendance_entry_id')->nullable();
            $table->string('actor_role', 10);
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 40);
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['attendance_events', 'attendance_requests', 'attendance_entries', 'attendance_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
