<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments',fn (Blueprint $table)=>$table->string('kind',20)->default('assignment')->index());
    }
    public function down(): void
    {
        Schema::table('teacher_assignments',function (Blueprint $table) {$table->dropIndex(['kind']);$table->dropColumn('kind');});
    }
};
