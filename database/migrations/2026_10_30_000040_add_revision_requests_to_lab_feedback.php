<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_submissions', fn (Blueprint $table) => $table->boolean('resubmission_requested')->default(false));
    }

    public function down(): void
    {
        Schema::table('lab_submissions', fn (Blueprint $table) => $table->dropColumn('resubmission_requested'));
    }
};
