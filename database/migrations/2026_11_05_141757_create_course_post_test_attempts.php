<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_post_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('code_issued_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('access_code_hash')->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->dateTime('started_at', 3)->nullable();
            $table->dateTime('question_started_at', 3)->nullable();
            $table->unsignedTinyInteger('question_seconds')->nullable();
            $table->unsignedTinyInteger('question_index')->default(0);
            $table->json('questions')->nullable();
            $table->json('answers')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'attempt_number'], 'course_post_student_attempt_unique');
        });
        Schema::table('course_certificates', function (Blueprint $table) {
            $table->string('award_type', 20)->default('completion');
            $table->unique(['student_id', 'award_type'], 'course_certificate_student_award_unique');
        });
        Schema::table('course_certificates', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('course_certificates')->where('award_type', 'excellence')->exists()) {
            throw new RuntimeException('Back up and retain issued excellence certificates before rolling back this migration.');
        }
        Schema::table('course_certificates', function (Blueprint $table) {
            $table->unique('student_id');
        });
        Schema::table('course_certificates', function (Blueprint $table) {
            $table->dropUnique('course_certificate_student_award_unique');
            $table->dropColumn('award_type');
        });
        Schema::dropIfExists('course_post_test_attempts');
    }
};
