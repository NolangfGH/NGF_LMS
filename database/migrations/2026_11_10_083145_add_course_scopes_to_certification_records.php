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
        Schema::table('course_post_test_attempts', function (Blueprint $table): void {
            $table->string('course', 30)->default('cpp');
            $table->unique(['student_id', 'course', 'attempt_number'], 'course_post_student_course_attempt_unique');
            $table->dropUnique('course_post_student_attempt_unique');
        });
        Schema::table('course_certificates', function (Blueprint $table): void {
            $table->string('course', 30)->default('cpp');
            $table->unique(['student_id', 'course', 'award_type'], 'course_certificate_student_course_award_unique');
            $table->dropUnique('course_certificate_student_award_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('course_post_test_attempts')->where('course', '!=', 'cpp')->exists()
            || DB::table('course_certificates')->where('course', '!=', 'cpp')->exists()) {
            throw new RuntimeException('Retain Arduino exam attempts and certificates before rolling back this migration.');
        }
        Schema::table('course_certificates', function (Blueprint $table): void {
            $table->unique(['student_id', 'award_type'], 'course_certificate_student_award_unique');
            $table->dropUnique('course_certificate_student_course_award_unique');
            $table->dropColumn('course');
        });
        Schema::table('course_post_test_attempts', function (Blueprint $table): void {
            $table->unique(['student_id', 'attempt_number'], 'course_post_student_attempt_unique');
            $table->dropUnique('course_post_student_course_attempt_unique');
            $table->dropColumn('course');
        });
    }
};
