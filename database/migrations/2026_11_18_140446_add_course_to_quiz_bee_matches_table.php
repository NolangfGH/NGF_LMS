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
        Schema::table('quiz_bee_matches', function (Blueprint $table): void {
            $table->string('course', 20)->default('cpp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_bee_matches', function (Blueprint $table): void {
            $table->dropColumn('course');
        });
    }
};
