<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('code_workspaces', function (Blueprint $table) {
            $table->timestamp('assignment_opened_at')->nullable();
            $table->unsignedInteger('opening_xp_awarded')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('code_workspaces', function (Blueprint $table) {
            $table->dropColumn(['assignment_opened_at', 'opening_xp_awarded']);
        });
    }
};
