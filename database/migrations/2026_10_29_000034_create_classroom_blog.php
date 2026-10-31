<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('category', 60);
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime', 40)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['teacher_account_id', 'status', 'published_at']);
        });
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime', 40)->nullable();
            $table->text('teacher_reply')->nullable();
            $table->string('reply_image_path')->nullable();
            $table->string('reply_image_mime', 40)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['blog_post_id', 'student_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_posts');
    }
};
