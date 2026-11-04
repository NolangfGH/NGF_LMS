<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\QuizGrader;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TeachingLibraryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{teacher: TeacherAccount, other: TeacherAccount, rooms: Collection, foreign: Classroom, student: Student} */
    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Library', 'last_name' => 'Teacher', 'email' => 'library@example.test', 'school' => 'School', 'password' => 'Password123']);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-library@example.test', 'school' => 'School', 'password' => 'Password123']);
        $rooms = collect(['AM', 'BP', 'DB'])->map(fn (string $section): Classroom => Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Grade 8 C++', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'LIB'.$section]));
        $foreign = Classroom::create(['teacher_account_id' => $other->id, 'name' => 'PRIVATE FOREIGN CLASS', 'grade' => 'Grade 8', 'section' => 'DB', 'join_code' => 'FOREIGNLIB']);
        $student = Student::create(['classroom_id' => $rooms[0]->id, 'first_name' => 'Learner', 'nickname' => 'LibraryCoder', 'student_id' => 'LIBSTUDENT', 'password' => 'Password123', 'xp' => 100]);
        $this->withSession(['teacher_id' => $teacher->id]);

        return compact('teacher', 'other', 'rooms', 'foreign', 'student');
    }

    public function test_every_lesson_has_ten_valid_auto_graded_questions_and_two_coding_tasks(): void
    {
        $catalog = app(ReadyMadeActivities::class)->catalog();
        $this->assertSame(array_keys(config('lessons')), array_keys($catalog));
        $this->assertCount(25, $catalog);
        foreach ($catalog as $lesson) {
            $questions = $lesson['quiz']['questions'];
            $this->assertCount(10, $questions);
            $this->assertCount(10, array_unique(array_column($questions, 'prompt')));
            foreach ($questions as $question) {
                $this->assertSame('multiple_choice', $question['type']);
                $this->assertGreaterThanOrEqual(3, count($question['options']));
                $this->assertSame($question['options'], array_values(array_unique($question['options'])));
                $this->assertCount(1, $question['correct']);
                $this->assertContains($question['correct'][0], $question['options']);
                $this->assertNotEmpty($question['explanation']);
            }
            $grade = app(QuizGrader::class)->grade($questions, array_map(fn (array $question): string => $question['correct'][0], $questions));
            $this->assertSame(10, $grade['score']);
            foreach (['assignment', 'challenge'] as $kind) {
                $task = $lesson[$kind];
                $this->assertSame($kind, $task['kind']);
                $this->assertNotEmpty($task['objective']);
                $this->assertSame($task['sample_input'], $task['test_cases'][0]['input']);
                $this->assertSame($task['sample_output'], $task['test_cases'][0]['output']);
                $this->assertFalse($task['test_cases'][0]['hidden']);
                $this->assertNotEmpty($task['hints']);
                $this->assertGreaterThan(0, $task['time_limit_minutes']);
            }
        }
    }

    public function test_library_is_staff_only_and_principals_cannot_copy_or_read_answer_keys(): void
    {
        $this->get(route('teacher.library'))->assertRedirect(route('teacher.login'));
        $this->get(route('teacher.library.show', ['input', 'quiz']))->assertRedirect(route('teacher.login'));
        $this->postJson(route('teacher.library.store', ['input', 'quiz']), [])->assertForbidden();
        extract($this->fixture());
        $teacher->forceFill(['role' => 'principal'])->save();
        $this->get(route('teacher.library'))->assertForbidden();
        $this->get(route('teacher.library.show', ['input', 'quiz']))->assertForbidden();
        $this->postJson(route('teacher.library.store', ['input', 'quiz']), ['classroom_ids' => [$rooms[0]->id]])->assertForbidden();
        $this->assertDatabaseCount('teacher_quizzes', 0);
    }

    public function test_all_previews_render_and_search_and_empty_class_guidance_work(): void
    {
        extract($this->fixture());
        $page = $this->get(route('teacher.library'))->assertOk()->assertSee('250')->assertSee('Ready-made teaching library');
        $this->get(route('teacher.library', ['q' => 'multidimensional']))->assertOk()->assertSee('C++ Arrays')->assertDontSee('C++ Comments');
        $this->get(route('teacher.library', ['term' => 'Term3', 'q' => 'pointer']))->assertOk()->assertSee('C++ Pointers')->assertDontSee('C++ Output');
        $this->getJson(route('teacher.library', ['term' => 'Term9']))->assertUnprocessable();
        $this->get(route('teacher.library', ['q' => 'no-such-topic']))->assertOk()->assertSee('No topics match');
        $this->getJson(route('teacher.library', ['q' => ['invalid']]))->assertUnprocessable();
        foreach (array_keys(config('lessons')) as $key) {
            foreach (['quiz', 'assignment', 'challenge', 'article'] as $kind) {
                $this->get(route('teacher.library.show', [$key, $kind]))->assertOk()->assertSee('Create editable draft')->assertDontSee('PRIVATE FOREIGN CLASS')->assertDontSee('FOREIGNLIB');
            }
        }
        if (getenv('LIBRARY_PREVIEW')) {
            file_put_contents(base_path('.codex/library-preview.html'), $page->getContent());
            file_put_contents(base_path('.codex/library-task-preview.html'), $this->get(route('teacher.library.show', ['arrays', 'challenge']))->getContent());
            file_put_contents(base_path('.codex/library-quiz-preview.html'), $this->get(route('teacher.library.show', ['input', 'quiz']))->getContent());
            file_put_contents(base_path('.codex/library-article-preview.html'), $this->get(route('teacher.library.show', ['for-loops', 'article']))->getContent());
            file_put_contents(base_path('.codex/library-term-three-preview.html'), $this->get(route('teacher.library', ['term' => 'Term3']))->getContent());
            file_put_contents(base_path('.codex/library-parameters-preview.html'), $this->get(route('teacher.library.show', ['function-parameters', 'quiz']))->getContent());
        }
        $rooms->each->update(['archived_at' => now()]);
        $this->get(route('teacher.library.show', ['arrays', 'assignment']))->assertOk()->assertSee('Add an active class first')->assertSee('disabled', false);
    }

    public function test_quiz_copies_are_private_editable_drafts_and_ignore_published_payloads(): void
    {
        extract($this->fixture());
        $this->post(route('teacher.library.store', ['input', 'quiz']), ['classroom_ids' => $rooms->pluck('id')->all(), 'teacher_account_id' => $other->id, 'status' => 'published', 'questions' => [], 'xp' => 9999])->assertRedirect();
        $quiz = TeacherQuiz::firstOrFail();
        $this->assertSame('draft', $quiz->status);
        $this->assertSame($teacher->id, $quiz->teacher_account_id);
        $this->assertSame(0, $quiz->xp);
        $this->assertSame(60, $quiz->question_seconds);
        $this->assertNull($quiz->opens_at);
        $this->assertCount(10, $quiz->questions);
        $this->assertSame(3, $quiz->classrooms()->count());
        $this->assertDatabaseCount('challenge_notices', 0);
        $this->assertSame(100, $student->fresh()->xp);
        $this->get(route('teacher.quizzes', ['edit' => $quiz->id]))->assertOk()->assertSee('requestedQuizId')->assertSee('Your private draft is ready');
        $this->withSession(['student_id' => $student->id])->get(route('student.quiz'))->assertDontSee($quiz->title);
        $this->get(route('teacher.library.show', ['does-not-exist', 'quiz']))->assertNotFound();
    }

    public function test_coding_copies_retain_sample_output_tests_hints_and_independent_school_term(): void
    {
        extract($this->fixture());
        foreach (['assignment', 'challenge'] as $kind) {
            $this->post(route('teacher.library.store', ['arrays', $kind]), ['classroom_ids' => $rooms->take(2)->pluck('id')->all(), 'school_term' => 'Term2'])->assertRedirect();
            $task = TeacherAssignment::where('kind', $kind)->firstOrFail();
            $expected = app(ReadyMadeActivities::class)->lesson('arrays')[$kind];
            $this->assertSame('draft', $task->status);
            $this->assertSame('Term2', $task->school_term);
            $this->assertSame($expected['sample_output'], $task->sample_output);
            $this->assertSame($expected['test_cases'], $task->test_cases);
            $this->assertSame($expected['hints'], $task->hints);
            $this->assertSame(2, $task->classrooms()->count());
            $route = $kind === 'assignment' ? 'teacher.assignments' : 'teacher.section-challenges';
            $this->get(route($route, ['edit' => $task->id]))->assertOk()->assertViewHas('assignmentEditor', fn (array $editor): bool => $editor['id'] === $task->id && $editor['sample_output'] === $task->sample_output);
            $this->withSession(['student_id' => $student->id])->get(route('student.code-lab', ['assignment' => $task->id]))->assertNotFound();
        }
        $this->assertDatabaseCount('challenge_notices', 0);
    }

    public function test_invalid_foreign_archived_and_duplicate_sections_never_create_drafts(): void
    {
        extract($this->fixture());
        $rooms[2]->update(['archived_at' => now()]);
        foreach ([[], [$foreign->id], [$rooms[2]->id], [$rooms[0]->id, $rooms[0]->id], [$rooms[0]->id, $foreign->id]] as $ids) {
            $this->postJson(route('teacher.library.store', ['input', 'quiz']), ['classroom_ids' => $ids])->assertUnprocessable();
        }
        $this->postJson(route('teacher.library.store', ['input', 'assignment']), ['classroom_ids' => [$rooms[0]->id], 'school_term' => 'Term9'])->assertUnprocessable();
        $this->postJson(route('teacher.library.store', ['missing', 'quiz']), ['classroom_ids' => [$rooms[0]->id]])->assertNotFound();
        $this->get('/teaching-library/input/unknown-kind')->assertNotFound();
        $this->assertDatabaseCount('teacher_quizzes', 0);
        $this->assertDatabaseCount('teacher_assignments', 0);
    }

    public function test_draft_quiz_can_be_edited_and_published_through_existing_builder(): void
    {
        extract($this->fixture());
        $this->post(route('teacher.library.store', ['functions', 'quiz']), ['classroom_ids' => [$rooms[0]->id]])->assertRedirect();
        $quiz = TeacherQuiz::firstOrFail();
        $questions = $quiz->questions;
        $questions[0]['prompt'] = 'Teacher revised question';
        $payload = ['title' => 'Revised functions quiz', 'instructions' => 'Teacher reviewed', 'classroom_ids' => [$rooms[0]->id, $rooms[1]->id], 'questions' => $questions, 'status' => 'published', 'xp' => 0, 'time_limit' => 10, 'question_seconds' => 60];
        $this->putJson(route('teacher.quizzes.update', $quiz), $payload)->assertOk();
        $this->assertSame('Teacher revised question', $quiz->fresh()->questions[0]['prompt']);
        $this->assertSame('published', $quiz->fresh()->status);
        $this->withSession(['student_id' => $student->id])->get(route('student.quiz'))->assertOk()->assertSee('Revised functions quiz');
        $this->assertNotSame('Teacher revised question', app(ReadyMadeActivities::class)->lesson('functions')['quiz']['questions'][0]['prompt']);
    }

    public function test_coding_draft_can_be_edited_and_published_without_changing_the_original_template(): void
    {
        extract($this->fixture());
        $this->post(route('teacher.library.store', ['operators', 'challenge']), ['classroom_ids' => [$rooms[0]->id], 'school_term' => 'Term1'])->assertRedirect();
        $task = TeacherAssignment::firstOrFail();
        $data = $task->only(['kind', 'school_term', 'objective', 'difficulty', 'xp', 'starter_code', 'sample_output', 'time_limit_minutes', 'test_cases', 'hints']);
        $this->putJson(route('teacher.assignments.update', $task), [...$data, 'title' => 'Teacher revised challenge', 'classroom_ids' => [$rooms[0]->id], 'status' => 'published'])->assertOk();
        $this->assertSame('Teacher revised challenge', $task->fresh()->title);
        $this->assertSame('published', $task->fresh()->status);
        $this->assertNotSame($task->fresh()->title, app(ReadyMadeActivities::class)->lesson('operators')['challenge']['title']);
        $this->withSession(['student_id' => $student->id])->get(route('student.challenges'))->assertOk()->assertSee('Teacher revised challenge');
    }

    public function test_every_lesson_has_a_ready_made_article_with_a_private_teacher_guide(): void
    {
        foreach (app(ReadyMadeActivities::class)->catalog() as $key => $lesson) {
            $article = $lesson['article'];
            $this->assertNotEmpty($article['title']);
            $this->assertLessThanOrEqual(180, mb_strlen($article['title']));
            $this->assertContains($article['category'], config('blog.categories'));
            $this->assertStringContainsString(config('lessons.'.$key.'.code'), $article['body']);
            $this->assertStringContainsString('Your private response', $article['body']);
            $this->assertStringNotContainsString($article['teacher_notes'], $article['body']);
            $this->assertLessThanOrEqual(30000, mb_strlen($article['body']));
            $this->assertFalse($article['is_graded']);
            $this->assertSame(5, $article['response_time_limit_minutes']);
        }
    }

    public function test_article_templates_copy_as_private_owned_drafts_and_open_in_the_blog_editor(): void
    {
        extract($this->fixture());
        foreach (app(ReadyMadeActivities::class)->catalog() as $key => $lesson) {
            $this->travel(4)->seconds();
            $this->post(route('teacher.library.store', [$key, 'article']), [
                'teacher_account_id' => $other->id, 'status' => 'published', 'body' => 'Forged content', 'is_graded' => true,
            ])->assertRedirect();
            $post = BlogPost::latest('id')->firstOrFail();
            $this->assertSame($teacher->id, $post->teacher_account_id);
            $this->assertSame($lesson['article']['body'], $post->body);
            $this->assertSame('draft', $post->status);
            $this->assertNull($post->published_at);
            $this->assertFalse($post->is_graded);
        }
        $this->assertDatabaseCount('blog_posts', 25);
        $this->assertDatabaseCount('challenge_notices', 0);
        $this->assertSame(100, $student->fresh()->xp);
        $editor = $this->get(route('teacher.blog.edit', $post))->assertOk()->assertSee('Your private article draft is ready')->assertSee('Save article changes')->assertSee($post->title);
        if (getenv('LIBRARY_PREVIEW')) {
            file_put_contents(base_path('.codex/library-blog-editor-preview.html'), $editor->getContent());
        }
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('student.blog'))->assertOk()->assertDontSee($post->title);
        $this->get(route('student.blog.show', $post))->assertForbidden();
        $this->get(route('teacher.library.show', ['for-loops', 'article']))->assertRedirect(route('teacher.login'));
        $this->postJson(route('teacher.library.store', ['for-loops', 'article']), [])->assertForbidden();
        $this->withSession(['teacher_id' => $other->id, 'student_id' => null])->get(route('teacher.blog.edit', $post))->assertForbidden();
        $teacher->forceFill(['role' => 'principal'])->save();
        $this->withSession(['teacher_id' => $teacher->id])->get(route('teacher.library.show', ['for-loops', 'article']))->assertForbidden();
        $this->postJson(route('teacher.library.store', ['for-loops', 'article']), [])->assertForbidden();
    }

    public function test_article_drafts_do_not_require_a_class_but_can_be_reviewed_before_enrollment(): void
    {
        extract($this->fixture());
        $this->withSession(['teacher_id' => $other->id]);
        $foreign->update(['archived_at' => now()]);
        $this->get(route('teacher.library.show', ['strings', 'article']))->assertOk()->assertSee('Create editable draft')->assertDontSee('name="classroom_ids[]"', false);
        $this->post(route('teacher.library.store', ['strings', 'article']), [])->assertRedirect();
        $this->assertDatabaseHas('blog_posts', ['teacher_account_id' => $other->id, 'status' => 'draft']);
        $this->assertDatabaseCount('challenge_notices', 0);
    }

    public function test_article_copy_can_be_edited_and_published_with_private_conversations_for_each_student(): void
    {
        extract($this->fixture());
        $peer = Student::create(['classroom_id' => $rooms[1]->id, 'first_name' => 'Peer', 'nickname' => 'PeerCoder', 'student_id' => 'LIBPEER', 'password' => 'Password123']);
        $outsider = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Outsider', 'nickname' => 'OutsideCoder', 'student_id' => 'LIBOUTSIDE', 'password' => 'Password123']);
        $this->post(route('teacher.library.store', ['arrays', 'article']), [])->assertRedirect();
        $post = BlogPost::firstOrFail();
        $this->put(route('teacher.blog.update', $post), [
            'title' => 'Our class array discussion', 'category' => 'C++ Basics', 'body' => $post->body."\n\nTeacher-added reflection prompt.",
            'status' => 'published', 'is_graded' => 1, 'response_time_limit_minutes' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($post->fresh()->is_graded);
        $this->assertNull($post->fresh()->response_time_limit_minutes);
        $this->assertNotNull($post->fresh()->published_at);
        $this->assertNotSame('Our class array discussion', app(ReadyMadeActivities::class)->lesson('arrays')['article']['title']);
        $this->assertDatabaseHas('challenge_notices', ['student_id' => $student->id, 'event_key' => 'article:published:'.$post->id]);
        $this->assertDatabaseHas('challenge_notices', ['student_id' => $peer->id, 'event_key' => 'article:published:'.$post->id]);
        $this->assertDatabaseMissing('challenge_notices', ['student_id' => $outsider->id, 'event_key' => 'article:published:'.$post->id]);
        foreach ([$student, $peer] as $learner) {
            $this->withSession(['teacher_id' => null, 'student_id' => $learner->id])->get(route('student.blog.show', $post))->assertOk()->assertSee('Teacher-added reflection prompt.')->assertDontSee(app(ReadyMadeActivities::class)->lesson('arrays')['article']['teacher_notes']);
        }
        $this->withSession(['student_id' => $student->id])->post(route('student.blog.comment', $post), ['body' => 'My private array explanation'])->assertRedirect();
        $comment = BlogComment::firstOrFail();
        $this->withSession(['student_id' => $peer->id])->get(route('student.blog.show', $post))->assertDontSee('My private array explanation');
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->post(route('teacher.blog.reply', [$post, $comment]), ['body' => 'Private teacher feedback'])->assertRedirect();
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('student.blog.show', $post))->assertSee('My private array explanation')->assertSee('Private teacher feedback');
        $this->withSession(['student_id' => $peer->id])->get(route('student.blog.show', $post))->assertDontSee('Private teacher feedback');
        $this->withSession(['student_id' => $outsider->id])->get(route('student.blog.show', $post))->assertForbidden();
    }
}
