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
        Schema::table('coding_matches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('participant_count')->default(2);
            $table->string('course', 20)->default('cpp');
            $table->string('origin', 20)->default('teacher');
            $table->foreignId('requested_by_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('lesson_key', 60)->nullable();
            $table->string('requested_grade', 30)->nullable();
        });
        Schema::create('coding_match_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coding_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unique(['coding_match_id', 'teacher_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_match_approvals');
        Schema::table('coding_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requested_by_student_id');
            $table->dropColumn(['participant_count', 'course', 'origin', 'lesson_key', 'requested_grade']);
        });
    }
};
