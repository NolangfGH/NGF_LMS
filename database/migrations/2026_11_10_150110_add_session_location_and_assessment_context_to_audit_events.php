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
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('login_event_id')->nullable()->index();
            $table->timestamp('login_at')->nullable();
            $table->timestamp('logout_at')->nullable();
            $table->decimal('location_latitude', 4, 1)->nullable();
            $table->decimal('location_longitude', 4, 1)->nullable();
            $table->unsignedInteger('location_accuracy_km')->nullable();
            $table->json('details')->nullable();
            $table->char('event_key', 64)->nullable()->unique();
            $table->index(['actor_type', 'actor_id', 'created_at'], 'audit_actor_time_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropUnique(['event_key']);
            $table->dropIndex('audit_actor_time_index');
            $table->dropIndex(['login_event_id']);
            $table->dropColumn(['login_event_id', 'login_at', 'logout_at', 'location_latitude', 'location_longitude', 'location_accuracy_km', 'details', 'event_key']);
        });
    }
};
