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
        foreach (['teacher_assignments', 'recitation_rounds'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('course', 20)->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['teacher_assignments', 'recitation_rounds'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['course']);
                $table->dropColumn('course');
            });
        }
    }
};
