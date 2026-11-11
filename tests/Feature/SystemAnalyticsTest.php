<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\QuizGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function classroom(): Classroom
    {
        $teacher = TeacherAccount::create(['first_name' => 'Sample', 'last_name' => 'Teacher', 'email' => fake()->unique()->safeEmail(), 'school' => 'Test School', 'password' => 'ExamplePassword123']);

        return Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Programming', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => fake()->unique()->bothify('ANA######')]);
    }

    private function student(Classroom $classroom): Student
    {
        return Student::create(['classroom_id' => $classroom->id, 'first_name' => 'Private learner name', 'nickname' => 'Private learner nickname',
            'student_id' => fake()->unique()->bothify('ANA########'), 'password' => 'ExamplePassword123', 'xp' => 100]);
    }

    private function question(string $prompt): array
    {
        return ['type' => 'multiple_choice', 'prompt' => $prompt, 'options' => ['Right', 'Distractor', 'Other'], 'correct' => ['Right']];
    }

    private function exam(Classroom $classroom, string $title, array $questions): TeacherQuiz
    {
        return TeacherQuiz::create(['teacher_account_id' => $classroom->teacher_account_id, 'classroom_id' => $classroom->id, 'title' => $title, 'status' => 'published', 'questions' => $questions]);
    }

    private function submit(TeacherQuiz $exam, Student $student, array $questions, array $answers, int $number = 1, bool $snapshot = true): void
    {
        if ($snapshot) {
            QuizAttempt::create(['teacher_quiz_id' => $exam->id, 'student_id' => $student->id, 'attempt_number' => $number, 'questions' => $questions,
                'answers' => $answers, 'started_at' => now()->subMinutes(2), 'expires_at' => now()->addMinute(), 'submitted_at' => now()]);
        }
        DB::table('quiz_submissions')->insert(['teacher_quiz_id' => $exam->id, 'student_id' => $student->id, 'attempt_number' => $number,
            ...app(QuizGrader::class)->grade($questions, $answers), 'answers' => json_encode($answers, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_analysis_matches_shuffled_questions_and_separates_earlier_exam_versions(): void
    {
        $room = $this->classroom();
        $questions = [$this->question('Trace a loop'), $this->question('Predict the output'), $this->question('Choose a data type')];
        $exam = $this->exam($room, 'Term 2 · C++ Term Exam', $questions);
        for ($index = 0; $index < 5; $index++) {
            $student = $this->student($room);
            $answer = $index < 3 ? 'Right' : ($index === 3 ? 'Distractor' : null);
            $shuffled = [$questions[2], $questions[0], $questions[1]];
            $this->submit($exam, $student, $shuffled, ['Right', $answer, $answer]);
            if ($index === 4) {
                $this->submit($exam, $student, $questions, ['Right', 'Right', 'Right'], 2);
            }
        }
        $earlier = [$this->question('Trace an earlier loop'), $questions[1], $questions[2]];
        $this->submit($exam, $this->student($room), $earlier, ['Distractor', 'Right', 'Right']);
        $this->submit($exam, $this->student($room), $questions, ['Right', 'Right', 'Right'], snapshot: false);
        $this->submit($exam, $this->student($this->classroom()), $questions, ['Right', 'Right', 'Right']);

        $filters = ['exam' => $exam->id, 'term' => 'Term2', 'classroom' => $room->id];
        $response = $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.analytics', $filters))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('60% correct')->assertSee('3 correct · 1 incorrect · 1 unanswered')
            ->assertSee('1 submission could not be included')->assertDontSee('Private learner nickname')->assertDontSee('Trace an earlier loop');
        $report = $response->viewData('report');
        $this->assertSame(5, $report['learners']);
        $this->assertSame(73.3, $report['average']);
        $this->assertSame([3, 1, 0], $report['items'][0]['selections']);
        $this->assertSame(1.0, $report['items'][0]['discrimination']);
        $this->assertNull($report['items'][2]['discrimination']);
        $earlierVersion = $report['versions']->keys()->first(fn ($version) => $version !== $report['current_version']);
        $earlierReport = $this->get(route('teacher.analytics', $filters + ['version' => $earlierVersion]))
            ->assertOk()->assertSee('Earlier exam version')->assertSee('Trace an earlier loop')->viewData('report');
        $this->assertSame(1, $earlierReport['learners']);
        $this->assertNull($earlierReport['items'][0]['discrimination']);
        $this->assertSame(9, DB::table('quiz_submissions')->count());
    }

    public function test_only_assigned_active_sections_and_owned_published_exams_are_visible(): void
    {
        $room = $this->classroom();
        $foreign = $this->classroom();
        $second = Classroom::create(['teacher_account_id' => $room->teacher_account_id, 'name' => 'Robotics', 'grade' => 'Grade 9', 'section' => 'BP', 'join_code' => 'ANASECOND']);
        $exam = $this->exam($room, 'Robotics skills exam', [$this->question('Which choice is correct?')]);
        $exam->classrooms()->attach($second);
        $foreignExam = $this->exam($foreign, 'Term 3 · Private exam', [$this->question('Private foreign prompt')]);
        $draft = $this->exam($room, 'Term 3 · Draft exam', [$this->question('Private draft prompt')]);
        $draft->update(['status' => 'draft']);
        $sheet = DB::table('grading_sheets')->insertGetId(['teacher_account_id' => $room->teacher_account_id, 'classroom_id' => $second->id,
            'school_year' => '2026-2027', 'term' => 'Term3', 'weights' => '{}']);
        DB::table('grading_assessments')->insert(['grading_sheet_id' => $sheet, 'title' => 'Robotics skills exam', 'category' => 'TA',
            'max_score' => 1, 'source_type' => 'quiz', 'source_id' => $exam->id]);
        $this->submit($exam, $this->student($room), $exam->questions, ['Right']);
        $this->submit($exam, $this->student($second), $exam->questions, ['Distractor']);
        $this->get(route('teacher.analytics'))->assertRedirect(route('teacher.login'));
        $this->withSession(['teacher_id' => $room->teacher_account_id])->get(route('teacher.analytics', ['term' => 'Term3']))
            ->assertOk()->assertSee('Robotics skills exam')->assertDontSee('Private exam')->assertDontSee('Draft exam');
        $this->get(route('teacher.analytics', ['exam' => $foreignExam->id]))->assertNotFound();
        $this->get(route('teacher.analytics', ['exam' => $exam->id, 'classroom' => $foreign->id]))->assertNotFound();
        $this->get(route('teacher.analytics', ['exam' => $exam->id, 'classroom' => $second->id, 'term' => 'Term3']))->assertOk()->assertSee('0% correct');
        $this->get(route('teacher.analytics', ['exam' => $exam->id]))->assertOk()->assertSee('50% correct');
        $second->update(['archived_at' => now()]);
        $this->get(route('teacher.analytics', ['exam' => $exam->id, 'classroom' => $second->id]))->assertNotFound();
        $foreign->teacher->forceFill(['role' => 'principal'])->save();
        $this->withSession(['teacher_id' => $foreign->teacher_account_id])->get(route('teacher.analytics'))->assertForbidden();
    }
}
