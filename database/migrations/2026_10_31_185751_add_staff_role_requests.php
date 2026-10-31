<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teacher_accounts', function (Blueprint $table) {
            $table->string('staff_access_status', 12)->default('active');
            $table->string('requested_role', 20)->nullable();
            $table->foreignId('staff_reviewed_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->timestamp('staff_reviewed_at')->nullable();
            $table->text('staff_review_note')->nullable();
            $table->unsignedInteger('staff_revision')->default(1);
            $table->index(['staff_access_status', 'role'], 'staff_access_roles');
        });
        Schema::create('staff_role_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('teacher_accounts')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->string('previous_role', 20);
            $table->string('assigned_role', 20);
            $table->string('decision', 12);
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['account_id', 'created_at'], 'staff_review_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('teacher_accounts')->where('staff_access_status', '!=', 'active')->exists() || DB::table('staff_role_reviews')->exists()) {
            throw new RuntimeException('Preserve staff access requests and audit history before removing approval protection.');
        }
        Schema::dropIfExists('staff_role_reviews');
        Schema::table('teacher_accounts', function (Blueprint $table) {
            $table->dropIndex('staff_access_roles');
            $table->dropConstrainedForeignId('staff_reviewed_by');
            $table->dropColumn(['staff_access_status', 'requested_role', 'staff_reviewed_at', 'staff_review_note', 'staff_revision']);
        });
    }
};
