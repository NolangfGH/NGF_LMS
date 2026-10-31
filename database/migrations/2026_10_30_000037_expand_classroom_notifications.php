<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_notices', function (Blueprint $table) {
            $table->foreignId('coding_match_id')->nullable()->change();
            $table->string('category', 30)->default('challenges');
            $table->string('title', 180)->default('Challenge update');
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('delivery_key', 64)->nullable()->unique();
            $table->index(['student_id', 'read_at', 'id'], 'learning_notice_student_read');
            $table->index(['teacher_account_id', 'read_at', 'id'], 'learning_notice_teacher_read');
        });
        foreach (['students', 'teacher_accounts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->timestamp('notifications_initialized_at')->nullable());
        }
    }

    public function down(): void
    {
        DB::table('challenge_notices')->whereNull('coding_match_id')->delete();
        Schema::table('challenge_notices', function (Blueprint $table) {
            $table->dropIndex('learning_notice_student_read');
            $table->dropIndex('learning_notice_teacher_read');
            $table->dropUnique(['delivery_key']);
            $table->dropColumn(['category', 'title', 'target_type', 'target_id', 'delivery_key']);
            $table->foreignId('coding_match_id')->nullable(false)->change();
        });
        foreach (['students', 'teacher_accounts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('notifications_initialized_at'));
        }
    }
};
