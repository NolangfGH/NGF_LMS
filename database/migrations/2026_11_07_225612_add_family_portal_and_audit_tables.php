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
        Schema::table('students', function (Blueprint $table): void {
            $table->string('school_name', 180)->nullable();
            $table->string('ict_teacher_name', 180)->nullable();
        });
        Schema::create('parent_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamps();
        });
        Schema::create('parent_student_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_account_id')->constrained()->cascadeOnDelete();
            $table->string('student_reference', 40);
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('relationship', 20);
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['parent_account_id', 'student_reference']);
        });
        Schema::create('portal_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 180)->index();
            $table->string('method', 8);
            $table->unsignedSmallInteger('status');
            $table->string('outcome', 20);
            $table->json('targets')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('portal_settings');
        Schema::dropIfExists('parent_student_links');
        Schema::dropIfExists('parent_accounts');
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['school_name', 'ict_teacher_name']));
    }
};
