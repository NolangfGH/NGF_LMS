<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_submissions', function (Blueprint $table) {
            $table->text('objective')->nullable();
            $table->json('ai_suggestion')->nullable();
            $table->string('ai_model',100)->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->foreignId('reviewed_by_teacher_id')->nullable()->constrained('teacher_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lab_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_teacher_id');
            $table->dropColumn(['objective','ai_suggestion','ai_model','ai_generated_at']);
        });
    }
};
