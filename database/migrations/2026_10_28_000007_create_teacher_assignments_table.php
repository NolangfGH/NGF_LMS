<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained('teacher_accounts')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('title');
            $table->string('unit')->nullable();
            $table->text('objective')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('difficulty')->default('Mid');
            $table->unsignedInteger('xp')->default(0);
            $table->string('status')->default('draft');
            $table->text('starter_code')->nullable();
            $table->string('csta_standard')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
