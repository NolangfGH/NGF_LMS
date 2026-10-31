<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_comments', function (Blueprint $table) {
            $table->string('last_response_role', 20)->default('student');
        });
        Schema::create('blog_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_comment_id')->constrained()->cascadeOnDelete();
            $table->string('author_role', 20);
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime', 40)->nullable();
            $table->timestamps();
            $table->index(['blog_comment_id', 'author_role']);
        });

        // Keep existing replies and their private image URLs when upgrading.
        DB::table('blog_comments')->where(function ($query) {
            $query->whereNotNull('replied_at')->orWhereNotNull('teacher_reply')->orWhereNotNull('reply_image_path');
        })->orderBy('id')->chunkById(100, function ($comments) {
            foreach ($comments as $comment) {
                DB::table('blog_responses')->insert([
                    'blog_comment_id' => $comment->id, 'author_role' => 'teacher',
                    'body' => $comment->teacher_reply, 'image_path' => $comment->reply_image_path,
                    'image_mime' => $comment->reply_image_mime,
                    'created_at' => $comment->replied_at ?? $comment->updated_at,
                    'updated_at' => $comment->replied_at ?? $comment->updated_at,
                ]);
                DB::table('blog_comments')->where('id', $comment->id)->update(['last_response_role' => 'teacher']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_responses');
        Schema::table('blog_comments', fn (Blueprint $table) => $table->dropColumn('last_response_role'));
    }
};
