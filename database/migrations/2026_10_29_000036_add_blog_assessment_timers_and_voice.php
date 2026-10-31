<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->boolean('is_graded')->default(false);
            $table->unsignedSmallInteger('response_time_limit_minutes')->nullable();
        });
        foreach (['blog_comments', 'blog_responses'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('audio_path')->nullable();
                $table->string('audio_mime', 60)->nullable();
                $table->unsignedInteger('submission_number')->nullable();
                $table->string('assessment', 30)->nullable();
                $table->text('grade_note')->nullable();
                $table->unsignedTinyInteger('xp_credited')->default(0);
                $table->timestamp('graded_at')->nullable();
                $table->boolean('auto_submitted')->default(false);
            });
        }
        Schema::create('blog_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('submission_count')->default(0);
            $table->unsignedTinyInteger('xp_awarded')->default(0);
            $table->string('first_assessment', 30)->nullable();
            $table->timestamps();
            $table->unique(['blog_post_id', 'student_id']);
        });
        Schema::create('blog_response_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_participation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_comment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime', 40)->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_mime', 60)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_comment_id')->nullable();
            $table->unsignedBigInteger('submitted_response_id')->nullable();
            $table->timestamps();
            $table->index(['submitted_at', 'expires_at']);
        });

        // Preserve the order of older comments and student follow-ups for grading.
        $pairs = DB::table('blog_comments')->select('blog_post_id', 'student_id')->distinct()->get();
        foreach ($pairs as $pair) {
            $comments = DB::table('blog_comments')->where('blog_post_id', $pair->blog_post_id)->where('student_id', $pair->student_id)->get();
            $events = $comments->map(fn ($row) => ['table' => 'blog_comments', 'id' => $row->id, 'at' => $row->created_at]);
            $responses = DB::table('blog_responses')->whereIn('blog_comment_id', $comments->pluck('id'))->where('author_role', 'student')->get();
            $events = $events->concat($responses->map(fn ($row) => ['table' => 'blog_responses', 'id' => $row->id, 'at' => $row->created_at]))
                ->sortBy(fn ($row) => [$row['at'], $row['table'] === 'blog_comments' ? 0 : 1, $row['id']])->values();
            foreach ($events as $index => $event) {
                DB::table($event['table'])->where('id', $event['id'])->update(['submission_number' => $index + 1]);
            }
            DB::table('blog_participations')->insert(['blog_post_id' => $pair->blog_post_id, 'student_id' => $pair->student_id, 'submission_count' => $events->count(), 'xp_awarded' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_response_attempts');
        Schema::dropIfExists('blog_participations');
        foreach (['blog_comments', 'blog_responses'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['audio_path', 'audio_mime', 'submission_number', 'assessment', 'grade_note', 'xp_credited', 'graded_at', 'auto_submitted']));
        }
        Schema::table('blog_posts', fn (Blueprint $table) => $table->dropColumn(['is_graded', 'response_time_limit_minutes']));
    }
};
