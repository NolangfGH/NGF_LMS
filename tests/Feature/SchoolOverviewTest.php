<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\CodingMatch;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherLessonPlan;
use App\Models\TeacherQuiz;
use App\Services\SchoolOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SchoolOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->freezeTime();
        $staff = collect(['principal', 'teacher', 'foreign'])->mapWithKeys(function ($key) {
            $account = new TeacherAccount(['first_name' => ucfirst($key), 'last_name' => 'Staff', 'email' => $key.'-overview@example.test', 'school' => $key === 'foreign' ? 'Other School' : 'Our School', 'password' => 'Password123']);
            $account->forceFill(['role' => $key === 'principal' ? 'principal' : 'teacher'])->save();

            return [$key => $account];
        });
        $rooms = collect(['AM', 'BP', 'DB', 'Foreign'])->mapWithKeys(fn ($section) => [$section => Classroom::create(['teacher_account_id' => $staff[$section === 'Foreign' ? 'foreign' : 'teacher']->id,
            'name' => $section.' C++ Classroom', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'SECRET-'.$section])]);
        $students = $rooms->mapWithKeys(fn ($room, $key) => [$key => Student::create(['classroom_id' => $room->id, 'first_name' => 'PrivateFirstName', 'surname' => 'PrivateSurname',
            'nickname' => $key.'Coder', 'student_id' => 'PRIVATE-STUDENT-'.$key, 'password' => 'Password123', 'xp' => $key === 'DB' ? 100 : 300, 'level' => 1])]);
        $quiz = TeacherQuiz::create(['teacher_account_id' => $staff['teacher']->id, 'classroom_id' => $rooms['AM']->id, 'title' => 'Output checkpoint', 'status' => 'published',
            'instructions' => 'Predict the output.', 'questions' => [['type' => 'identification', 'prompt' => 'Which stream prints text?', 'correct' => ['SECRET-ANSWER-KEY']]], 'opens_at' => now()->subHour(), 'closes_at' => now()->addHour()]);
        $quiz->classrooms()->sync([$rooms['AM']->id, $rooms['BP']->id]);
        foreach ([[1, 1], [2, 2]] as [$attempt, $score]) {
            DB::table('quiz_submissions')->insert(['student_id' => $students['AM']->id, 'teacher_quiz_id' => $quiz->id, 'attempt_number' => $attempt, 'answers' => '["PRIVATE-QUIZ-ANSWER"]',
                'score' => $score, 'graded_questions' => 2, 'total_questions' => 2, 'created_at' => now(), 'updated_at' => now()]);
        }
        LessonProgress::create(['student_id' => $students['AM']->id, 'lesson_key' => 'output', 'started_at' => now(), 'last_opened_at' => now(), 'completed_at' => now(), 'reflection' => 'PRIVATE-REFLECTION', 'help_request' => 'PRIVATE-HELP']);
        StudentAchievement::create(['student_id' => $students['AM']->id, 'badge_code' => 'first_quiz', 'earned_at' => now(), 'evidence' => [['detail' => 'PRIVATE-EVIDENCE']]]);
        $workspace = CodeWorkspace::create(['student_id' => $students['AM']->id, 'activity_key' => 'practice:greeting', 'title' => 'Practice', 'code' => 'PRIVATE-STUDENT-CODE']);
        foreach ([1, 2] as $revision) {
            $workspace->submissions()->create(['teacher_account_id' => $staff['teacher']->id, 'revision' => $revision, 'title' => 'Practice', 'code' => 'PRIVATE-STUDENT-CODE', 'reflection' => 'PRIVATE-REFLECTION',
                'score' => $revision === 1 ? 12 : null, 'reviewed_at' => $revision === 1 ? now() : null]);
        }
        $assignment = TeacherAssignment::create(['teacher_account_id' => $staff['teacher']->id, 'classroom_id' => $rooms['AM']->id, 'title' => 'Number printer', 'status' => 'draft',
            'objective' => 'Print a numbered sequence.', 'sample_output' => "1\n2\n3", 'school_term' => 'Term1', 'time_limit_minutes' => 20, 'xp' => 150, 'test_cases' => [['input' => 'PRIVATE-HIDDEN-TEST', 'output' => 'PRIVATE-HIDDEN-TEST']]]);
        $assignment->classrooms()->sync([$rooms['AM']->id, $rooms['BP']->id]);
        TeacherAssignment::create(['teacher_account_id' => $staff['teacher']->id, 'classroom_id' => $rooms['DB']->id, 'title' => 'Loop challenge', 'kind' => 'challenge', 'status' => 'published', 'sample_output' => 'Finished']);
        TeacherLessonPlan::create(['teacher_account_id' => $staff['teacher']->id, 'classroom_id' => $rooms['AM']->id, 'lesson_key' => 'output', 'status' => 'published', 'instructions' => 'Read and trace the C++ example.']);
        LessonContent::create(['teacher_account_id' => $staff['teacher']->id, 'lesson_key' => 'output', 'status' => 'published', 'definition' => ['title' => 'Teacher output lesson', 'objective' => 'Explain output.', 'sections' => [['title' => 'Console output', 'text' => 'Use std::cout.']], 'code' => 'std::cout << "Hello";', 'output' => 'Hello', 'checks' => [['correct' => 'PRIVATE-LESSON-KEY']]]]);
        LessonContent::create(['teacher_account_id' => $staff['teacher']->id, 'lesson_key' => 'custom-future', 'status' => 'draft', 'definition' => ['title' => 'Unassigned future lesson', 'objective' => 'Plan a function.']]);
        $match = CodingMatch::create(['teacher_account_id' => $staff['teacher']->id, 'title' => 'Friendly coding match', 'instructions' => 'Print the square.', 'sample_output' => '16', 'time_limit_minutes' => 5]);
        foreach (['AM', 'BP'] as $index => $key) {
            $match->participants()->create(['student_id' => $students[$key]->id, 'slot' => $index + 1, 'student_name' => 'PRIVATE-SNAPSHOT-NAME', 'section_label' => $key, 'draft_code' => 'PRIVATE-LIVE-DRAFT', 'response' => 'accepted']);
        }
        $this->withSession(['teacher_id' => $staff['principal']->id, 'teacher_session_version' => 1]);

        return compact('staff', 'rooms', 'students', 'quiz', 'assignment', 'match');
    }

    public function test_all_views_are_read_only_and_exclude_private_work_and_answer_keys(): void
    {
        extract($this->fixture());
        DB::enableQueryLog();

        foreach (array_keys(SchoolOverviewService::TABS) as $tab) {
            $page = $this->get(route('school.overview', ['tab' => $tab]))->assertOk()->assertViewHas('studentCount', 3)->assertSee('Read-only overview');
            foreach (['PrivateFirstName', 'PrivateSurname', 'PRIVATE-STUDENT-', 'PRIVATE-QUIZ-ANSWER', 'PRIVATE-REFLECTION', 'PRIVATE-EVIDENCE', 'PRIVATE-STUDENT-CODE', 'PRIVATE-HIDDEN-TEST', 'SECRET-ANSWER-KEY', 'PRIVATE-LESSON-KEY', 'PRIVATE-LIVE-DRAFT', 'PRIVATE-SNAPSHOT-NAME', 'ForeignCoder', 'SECRET-AM', 'Save feedback'] as $private) {
                $page->assertDontSee($private);
            }
            $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
            $this->assertDoesNotMatchRegularExpression('/<form\b[^>]*method="POST"/i', $page->getContent());
        }

        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $query['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        $this->assertNull(StudentAchievement::sole()->seen_at);
        $this->assertSame(300, $students['AM']->fresh()->xp);
    }

    public function test_progress_uses_best_fully_graded_quiz_and_distinct_coding_activities(): void
    {
        extract($this->fixture());
        $ungraded = TeacherQuiz::create(['teacher_account_id' => $staff['teacher']->id, 'classroom_id' => $rooms['AM']->id, 'title' => 'Not fully graded', 'questions' => [], 'status' => 'published']);
        DB::table('quiz_submissions')->insert(['student_id' => $students['AM']->id, 'teacher_quiz_id' => $ungraded->id, 'answers' => '[]', 'score' => 0, 'graded_questions' => 1, 'total_questions' => 2, 'created_at' => now(), 'updated_at' => now()]);

        $page = $this->get(route('school.overview', ['tab' => 'students', 'classroom' => $rooms['AM']->id]))->assertOk();

        $row = $page->viewData('rows')->items()[0];
        $this->assertSame('AMCoder', $row['name']);
        $this->assertSame(1, $row['completed']);
        $this->assertSame(100.0, $row['quiz_average']);
        $this->assertSame(1, $row['quiz_count']);
        $this->assertSame(1, $row['coding_submitted']);
        $this->assertSame(1, $row['coding_reviewed']);
        $this->assertCount(1, $row['badges']);
        $this->assertSame(100.0, round($page->viewData('quizAverage'), 1));
    }

    public function test_repeated_classrooms_have_one_section_choice_and_include_each_teachers_students(): void
    {
        extract($this->fixture());
        $rooms['Foreign']->update(['section' => 'AM']);
        $sameName = TeacherAccount::create(['first_name' => 'Teacher', 'last_name' => 'Staff', 'school' => 'Our School', 'email' => 'same-name@example.test', 'password' => 'Password123']);
        $another = TeacherAccount::create(['first_name' => 'Another', 'last_name' => 'Teacher', 'school' => 'Our School', 'email' => 'another@example.test', 'password' => 'Password123']);
        $duplicateRooms = collect([$staff['teacher'], $sameName, $another])->map(function ($account, $index): Classroom {
            $room = Classroom::create(['teacher_account_id' => $account->id, 'name' => 'AM C++ Classroom', 'grade' => $index === 0 ? ' Grade 8 ' : 'Grade 8',
                'section' => $index === 0 ? ' am ' : 'AM', 'join_code' => 'DUPLICATE-'.$index]);
            Student::create(['classroom_id' => $room->id, 'first_name' => 'Test', 'nickname' => 'ExtraCoder'.$index, 'student_id' => 'EXTRA-'.$index, 'password' => 'Password123', 'xp' => 100]);

            return $room;
        });
        Classroom::create(['teacher_account_id' => $staff['teacher']->id, 'name' => 'Grade 9 C++', 'grade' => 'Grade 9', 'section' => 'AM', 'join_code' => 'NINTH-AM']);

        $page = $this->get(route('school.overview'))->assertOk()->assertViewHas('studentCount', 6);
        $sections = $page->viewData('sections');
        $this->assertCount(4, $sections);
        $section = $sections->firstWhere('id', $rooms['AM']->id);
        $this->assertSame('Teacher Staff, Another Teacher', $section['teachers']);
        $this->assertSame(4, $section['students_count']);
        $this->assertCount(4, $section['classroom_ids']);
        $this->assertSame('AM C++ Classroom', $section['subjects']);
        $this->assertStringNotContainsString('Foreign', $section['teachers']);
        $this->assertSame(1, substr_count($page->getContent(), '<option value="'.$section['id'].'"'));

        foreach (['overview', 'students', 'leaderboard', 'lessons', 'assignments', 'quizzes', 'challenges'] as $tab) {
            $filtered = $this->get(route('school.overview', ['tab' => $tab, 'classroom' => $duplicateRooms->last()->id]))->assertOk()
                ->assertViewHas('studentCount', 4)->assertViewHas('selected', $rooms['AM']->id)->assertDontSee('ForeignCoder');
            $this->assertCount(4, $filtered->viewData('classrooms'));
        }
        $this->assertDatabaseCount('students', 7);
        if ($preview = getenv('PORTAL_UI_PREVIEW')) {
            file_put_contents($preview.'/principal-sections.html', $page->getContent());
        }
    }

    public function test_school_and_section_rankings_have_fair_ties_and_respect_private_nicknames(): void
    {
        extract($this->fixture());
        $students['BP']->update(['leaderboard_visible' => false]);
        $pending = Student::create(['classroom_id' => $rooms['AM']->id, 'first_name' => 'Pending', 'nickname' => 'UnapprovedCoder', 'student_id' => 'PENDING', 'password' => 'Password123', 'xp' => 9999]);
        $pending->forceFill(['registration_status' => 'pending'])->save();

        $page = $this->get(route('school.overview', ['tab' => 'leaderboard']))->assertOk()->assertDontSee('BPCoder')->assertDontSee('UnapprovedCoder');

        $this->assertSame([1, 1, 3], $page->viewData('rows')->getCollection()->pluck('rank')->map(fn ($rank) => (int) $rank)->all());
        $page->assertSee($students['BP']->rankingAlias());
        $this->get(route('school.overview', ['tab' => 'leaderboard', 'classroom' => $rooms['DB']->id]))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && (int) $rows->items()[0]['rank'] === 1 && $rows->items()[0]['name'] === 'DBCoder');
        $students['DB']->update(['xp' => 500]);
        $this->get(route('school.overview', ['tab' => 'leaderboard']))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->items()[0]['name'] === 'DBCoder');
    }

    public function test_activities_include_pivot_sections_drafts_and_readable_task_details(): void
    {
        extract($this->fixture());

        $this->get(route('school.overview', ['tab' => 'assignments', 'classroom' => $rooms['BP']->id]))->assertOk()->assertSee('Number printer')->assertSee('Print a numbered sequence.')->assertSee('20 minutes')->assertSee('Term1')->assertSee('150 XP')->assertSee('draft')->assertDontSee('Loop challenge');
        $this->get(route('school.overview', ['tab' => 'quizzes', 'classroom' => $rooms['BP']->id]))->assertOk()->assertSee('Output checkpoint')->assertSee('Which stream prints text?')->assertDontSee('SECRET-ANSWER-KEY');
        $this->get(route('school.overview', ['tab' => 'quizzes', 'classroom' => $rooms['DB']->id]))->assertOk()->assertDontSee('Output checkpoint');
        $this->get(route('school.overview', ['tab' => 'lessons']))->assertOk()->assertSee('Teacher output lesson')->assertSee('Use std::cout.')->assertSee('Unassigned future lesson')->assertDontSee('PRIVATE-LESSON-KEY');
        $this->get(route('school.overview', ['tab' => 'challenges']))->assertOk()->assertSee('Loop challenge')->assertSee('Friendly coding match')->assertSee('BPCoder')->assertSee('Accepted')->assertDontSee('PRIVATE-LIVE-DRAFT');
        $this->get(route('school.overview', ['tab' => 'challenges', 'classroom' => $rooms['AM']->id]))->assertOk()->assertSee('Grade 8 · BP')->assertDontSee('Section unavailable');
        $this->get(route('school.overview', ['tab' => 'achievements']))->assertOk()->assertSee('Knowledge Check')->assertSee('AMCoder');
    }

    public function test_scope_cannot_be_changed_to_another_school_and_archived_or_deleted_records_are_excluded(): void
    {
        extract($this->fixture());
        $rooms['DB']->update(['archived_at' => now()]);
        $students['BP']->delete();

        $this->get(route('school.overview', ['tab' => 'students']))->assertOk()->assertViewHas('studentCount', 1)->assertDontSee('DBCoder')->assertDontSee('BPCoder')->assertDontSee('Foreign C++ Classroom');
        $this->get(route('school.overview', ['classroom' => $rooms['Foreign']->id]))->assertNotFound();
        $this->get(route('school.overview', ['classroom' => $rooms['DB']->id]))->assertNotFound();
        $this->put(route('teacher.settings.account'), ['first_name' => 'Principal', 'last_name' => 'Staff', 'email' => $staff['principal']->email, 'school' => 'Other School'])->assertUnprocessable();
        $this->assertSame('Our School', $staff['principal']->fresh()->school);
    }

    public function test_page_numbers_are_validated_and_rankings_continue_across_pages(): void
    {
        extract($this->fixture());
        for ($index = 0; $index < 26; $index++) {
            Student::create(['classroom_id' => $rooms['AM']->id, 'first_name' => 'Test', 'nickname' => 'PagedCoder'.$index, 'student_id' => 'PAGE-'.$index, 'password' => 'Password123', 'xp' => 90 - $index]);
        }

        $this->get(route('school.overview', ['tab' => 'leaderboard', 'page' => 2]))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 29 && (int) $rows->items()[0]['rank'] === 26);
        $this->getJson(route('school.overview', ['tab' => 'leaderboard', 'page' => -1]))->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson(route('school.overview', ['tab' => 'delete']))->assertUnprocessable()->assertJsonValidationErrors('tab');
        $this->getJson(route('school.overview', ['classroom' => '1 OR 1=1']))->assertUnprocessable()->assertJsonValidationErrors('classroom');
    }

    public function test_teacher_prepared_text_is_escaped_and_principals_cannot_mutate_tasks(): void
    {
        extract($this->fixture());
        $payload = '<script>alert("unsafe")</script>';
        $assignment->update(['title' => $payload, 'objective' => $payload, 'sample_output' => $payload]);

        $this->get(route('school.overview', ['tab' => 'assignments']))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
        $this->putJson(route('teacher.assignments.update', $assignment), ['title' => 'Not allowed'])->assertForbidden();
        $this->deleteJson(route('teacher.assignments.destroy', $assignment))->assertForbidden();
        $this->assertSame($payload, $assignment->fresh()->title);
        $this->withSession(['teacher_id' => $staff['teacher']->id])->get(route('school.overview', ['tab' => 'students']))->assertForbidden();
        $this->withSession(['teacher_id' => null, 'student_id' => $students['AM']->id])->get(route('school.overview', ['tab' => 'students']))->assertForbidden();
    }

    #[TestWith(['overview'])]
    #[TestWith(['students'])]
    #[TestWith(['achievements'])]
    #[TestWith(['leaderboard'])]
    #[TestWith(['lessons'])]
    #[TestWith(['quizzes'])]
    #[TestWith(['assignments'])]
    #[TestWith(['challenges'])]
    public function test_empty_school_views_render_without_fabricated_results(string $tab): void
    {
        $principal = new TeacherAccount(['first_name' => 'Principal', 'last_name' => 'Staff', 'email' => 'empty@example.test', 'school' => 'Empty School', 'password' => 'Password123']);
        $principal->forceFill(['role' => 'principal'])->save();

        $this->withSession(['teacher_id' => $principal->id, 'teacher_session_version' => 1])->get(route('school.overview', ['tab' => $tab]))->assertOk()->assertViewHas('studentCount', 0)->assertViewHas('quizAverage', null);
    }
}
