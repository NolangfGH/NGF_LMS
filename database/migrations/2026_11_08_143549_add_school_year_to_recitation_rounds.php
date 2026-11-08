<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recitation_rounds', function (Blueprint $table): void {
            $table->string('school_year', 9)->nullable();
            $table->index(['school_year', 'term'], 'recitation_school_year_term');
        });
        DB::table('recitation_rounds')->orderBy('id')->chunkById(200, function ($rounds): void {
            foreach ($rounds as $round) {
                $year = Carbon::parse($round->created_at)->timezone(config('learning.timezone'))->year;
                DB::table('recitation_rounds')->where('id', $round->id)->update(['school_year' => $year.'-'.($year + 1)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recitation_rounds', function (Blueprint $table): void {
            $table->dropIndex('recitation_school_year_term');
            $table->dropColumn('school_year');
        });
    }
};
