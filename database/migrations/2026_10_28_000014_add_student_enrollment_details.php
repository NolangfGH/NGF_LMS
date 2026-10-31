<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('middle_name', 80)->nullable();
            $table->string('surname', 80)->nullable();
            $table->string('grade_level', 50)->nullable();
            $table->string('section', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['middle_name', 'surname', 'grade_level', 'section']));
    }
};
