<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LearningNotification;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\QuizGrader;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TermExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_nine_exam_templates_have_distinct_questions_and_valid_keys(): void
    {
        $library = app(ReadyMadeActivities::class);
        $exams = $library->termExams();
        $this->assertCount(9, $exams);
        $this->assertSame(array_keys(config('lessons')), array_keys(config('term_exams')));
        $bank = [];
        foreach ($exams as $exam) {
            $questions = $exam['quiz']['questions'];
            $this->assertCount(25, $questions);
            $this->assertSame(25, $exam['quiz']['time_limit']);
            $this->assertSame(60, $exam['quiz']['question_seconds']);
            foreach ($questions as $question) {
                $this->assertSame('multiple_choice', $question['type']);
                $this->assertCount(4, array_unique($question['options']));
                $this->assertCount(1, $question['correct']);
                $this->assertContains($question['correct'][0], $question['options']);
                $this->assertNotEmpty($question['prompt']);
                $bank[] = $question['prompt'];
            }
            $grade = app(QuizGrader::class)->grade($questions, array_map(fn (array $question): string => $question['correct'][0], $questions));
            $this->assertSame(25, $grade['score']);
        }
        $this->assertCount(225, array_unique($bank));
    }

    public function test_a_teacher_can_edit_and_publish_an_exam_and_a_student_receives_normal_quiz_xp_once(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Exam', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'exam@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'EXAMAM']);
        $other = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'BP', 'join_code' => 'EXAMBP']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder', 'nickname' => 'Cadet', 'student_id' => 'EXAMSTUDENT', 'password' => 'Password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $other->id, 'first_name' => 'Other', 'nickname' => 'Peer', 'student_id' => 'EXAMPEER', 'password' => 'Password123', 'xp' => 100]);
        $template = ['term3-term-exam', 'quiz'];
        $this->get(route('teacher.library.show', $template))->assertRedirect(route('teacher.login'));
        $this->withSession(['teacher_id' => $teacher->id]);
        $this->get(route('teacher.library', ['term' => 'Term3']))->assertOk()->assertSee('Term 3 · Term Exam')->assertDontSee('Term 1 · Term Exam');
        foreach (app(ReadyMadeActivities::class)->termExams() as $key => $exam) {
            $this->get(route('teacher.library.show', [$key, 'quiz']))->assertOk()->assertSee($exam['title'])
                ->assertSee('25 multiple-choice questions')->assertSee('Create editable draft');
        }
        $this->post(route('teacher.library.store', $template), ['classroom_ids' => [$room->id], 'status' => 'published'])->assertRedirect();
        $quiz = TeacherQuiz::sole();
        $this->assertSame('draft', $quiz->status);
        $this->assertDatabaseEmpty(LearningNotification::class);
        $this->get(route('teacher.quizzes', ['edit' => $quiz->id]))->assertOk()->assertSee($quiz->title);
        $this->withSession(['student_id' => $student->id, 'teacher_id' => null])->get(route('student.quiz'))->assertOk()->assertDontSee($quiz->title);

        $questions = $quiz->questions;
        $questions[0]['prompt'] .= ' Choose one answer.';
        $title = 'Edited Term 3 exam';
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->putJson(route('teacher.quizzes.update', $quiz), [
            'title' => $title, 'questions' => $questions, 'classroom_ids' => [$room->id], 'status' => 'published',
            'xp' => 0, 'time_limit' => 25, 'untimed' => false, 'question_seconds' => 60,
        ])->assertOk();
        $this->assertSame($questions[0]['prompt'], $quiz->fresh()->questions[0]['prompt']);
        $this->withSession(['student_id' => $peer->id, 'teacher_id' => null])->get(route('student.quiz'))->assertOk()->assertDontSee($title);
        $this->withSession(['student_id' => $student->id])->get(route('student.quiz'))->assertOk()->assertSee($title);

        $this->freezeSecond();
        $this->post(route('student.quiz.start', $quiz))->assertRedirect();
        $attempt = QuizAttempt::sole();
        $this->assertSame(25 * 60, $attempt->expires_at->timestamp - $attempt->started_at->timestamp);
        $this->get(route('student.quiz'))->assertOk()->assertHeader('Permissions-Policy', 'display-capture=()')
            ->assertSee('Question 1 of 25')->assertSee($attempt->questions[0]['prompt'])->assertDontSee($attempt->questions[1]['prompt']);
        foreach ($attempt->questions as $index => $question) {
            $this->travel(10)->seconds();
            $this->postJson(route('student.quiz.question', $quiz), ['question_index' => $index, 'answer' => $question['correct'][0]])
                ->assertOk()->assertJsonPath('finished', $index === 24);
        }
        $this->assertDatabaseHas('quiz_submissions', ['teacher_quiz_id' => $quiz->id, 'score' => 25, 'answer_xp' => 250, 'speed_bonus_xp' => 125, 'perfect_bonus_xp' => 100, 'xp_credited' => 475]);
        $this->postJson(route('student.quiz.question', $quiz), ['question_index' => 24, 'answer' => 'retry'])->assertUnprocessable();
        $this->assertSame(575, $student->fresh()->xp);
        $this->assertSame(1, DB::table('quiz_submissions')->count());
    }
}
