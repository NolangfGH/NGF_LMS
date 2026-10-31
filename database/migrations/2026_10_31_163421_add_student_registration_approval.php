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
        Schema::table('students', function (Blueprint $table) {
            $table->string('registration_status', 12)->default('approved');
            $table->foreignId('registration_reviewed_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->timestamp('registration_reviewed_at')->nullable();
            $table->text('registration_review_note')->nullable();
            $table->unsignedInteger('registration_revision')->default(1);
            $table->index(['classroom_id', 'registration_status', 'created_at'], 'students_registration_queue');
        });
        Schema::create('student_registration_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 12)->nullable();
            $table->string('decision', 12);
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['student_id', 'created_at'], 'registration_review_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('students')->where('registration_status', '!=', 'approved')->exists() || DB::table('student_registration_reviews')->exists()) {
            throw new RuntimeException('Preserve registration requests and review history before removing approval protection.');
        }
        Schema::dropIfExists('student_registration_reviews');
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_registration_queue');
            $table->dropConstrainedForeignId('registration_reviewed_by');
            $table->dropColumn(['registration_status', 'registration_reviewed_at', 'registration_review_note', 'registration_revision']);
        });
    }
};
