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
        Schema::create('assessment_item_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 24);
            $table->unsignedBigInteger('source_id');
            $table->char('version', 64);
            $table->unsignedSmallInteger('question_count');
            $table->unsignedSmallInteger('score');
            $table->json('items');
            $table->timestamp('recorded_at');
            $table->unique(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_item_snapshots');
    }
};
