<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained('teacher_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('grade')->default('Grade 8');
            $table->string('period')->default('Period 3');
            $table->string('join_code', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
