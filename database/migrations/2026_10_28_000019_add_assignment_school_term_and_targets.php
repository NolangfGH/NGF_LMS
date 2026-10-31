<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments', fn (Blueprint $table) => $table->string('school_term',10)->nullable());
        Schema::create('classroom_teacher_assignment', function (Blueprint $table) {
            $table->foreignId('teacher_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->primary(['teacher_assignment_id','classroom_id']);
        });
        DB::table('teacher_assignments')->orderBy('id')->chunkById(100,function ($assignments) {
            foreach ($assignments as $assignment) DB::table('classroom_teacher_assignment')->insert(['teacher_assignment_id'=>$assignment->id,'classroom_id'=>$assignment->classroom_id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_teacher_assignment');
        Schema::table('teacher_assignments',fn (Blueprint $table) => $table->dropColumn('school_term'));
    }
};
