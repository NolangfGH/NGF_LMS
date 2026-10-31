<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_accounts', function (Blueprint $table) {
            $table->string('display_name', 80)->nullable();
            $table->text('bio')->nullable();
            $table->boolean('profile_visible')->default(true);
            $table->json('preferences')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('avatar_mime', 40)->nullable();
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamp('password_changed_at')->nullable();
        });
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('enrollment_open')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('enrollment_open'));
        Schema::table('teacher_accounts', fn (Blueprint $table) => $table->dropColumn(['display_name', 'bio', 'profile_visible', 'preferences', 'avatar_path', 'avatar_mime', 'session_version', 'password_changed_at']));
    }
};
