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
        Schema::create('donation_receipts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('submission_hash', 64)->unique();
            $table->string('sender_name', 120)->nullable();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('PHP');
            $table->string('proof_path');
            $table->string('proof_mime', 50);
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_receipts');
    }
};
