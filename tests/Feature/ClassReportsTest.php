<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassReportsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-10-29 04:00:00', 'UTC'));
        $teacher = TeacherAccount::create(['first_name' => 'Report', 'last_name' => 'Teacher', 'email' => 'reports@example.test', 'school' => 'School', 'password' => 'password123']);
        $outsider = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-reports@example.test', 'school' => 'Other School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'A', 'join_code' => 'REPORTA']);
        $other = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'B', 'join_code' => 'REPORTB']);
        $foreign = Classroom::create(['teacher_account_id' => $outsider->id, 'name' => 'Private classroom', 'grade' => 'Grade 8', 'section' => 'Private', 'join_code' => 'REPORTC']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Alice', 'nickname' => 'AliceCoder', 'student_id' => 'REPORT1', 'password' => 'password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $other->id, 'first_name' => 'Bob', 'nickname' => 'BobCoder', 'student_id' => 'REPORT2', 'password' => 'password123', 'xp' => 200]);
        $stranger = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'nickname' => 'PrivateCoder', 'student_id' => 'PRIVATE1', 'password' => 'password123']);
        $quiz = TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'C++ Quiz', 'status' => 'published', 'questions' => []]);
        $quiz->classrooms()->sync([$room->id, $other->id]);
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'title' => 'Console assignment', 'status' => 'published', 'school_term' => 'Term1']);
        $assignment->classrooms()->sync([$room->id, $other->id]);
        $this->withSession(['teacher_id' => $teacher->id]);

        return compact('teacher', 'outsider', 'room', 'other', 'foreign', 'student', 'peer', 'stranger', 'quiz', 'assignment');
    }

    private function saveQuizResult(Student $student, TeacherQuiz $quiz, int $score, int $total = 2, int $attempt = 1, $at = null, ?int $graded = null): void
    {
        DB::table('quiz_submissions')->insert(['student_id' => $student->id, 'teacher_quiz_id' => $quiz->id, 'answers' => '[]',
            'score' => $score, 'total_questions' => $total, 'graded_questions' => $graded ?? $total, 'attempt_number' => $attempt,
            'created_at' => $at ?? now(), 'updated_at' => $at ?? now()]);
    }

    private function lesson(Student $student, string $key, $at): LessonProgress
    {
        return LessonProgress::create(['student_id' => $student->id, 'lesson_key' => $key, 'started_at' => $at, 'last_opened_at' => $at,
            'completed_at' => $at, 'best_score' => 3, 'latest_score' => 3, 'attempts' => 1, 'reflection' => 'I traced each statement before testing my result.']);
    }

    private function workspace(Student $student, string $key, ?TeacherAssignment $assignment = null): CodeWorkspace
    {
        return CodeWorkspace::create(['student_id' => $student->id, 'teacher_assignment_id' => $assignment?->id, 'activity_key' => $key, 'title' => 'Student solution', 'code' => 'int main() {}']);
    }

    private function submission(CodeWorkspace $workspace, TeacherAccount $teacher, int $revision, ?array $rubric = null): LabSubmission
    {
        return LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $teacher->id, 'revision' => $revision,
            'title' => 'Student solution', 'code' => 'int main() {}', 'reflection' => 'I tested my output and reviewed the boundary case.',
            'rubric' => $rubric, 'score' => $rubric ? array_sum($rubric) : null, 'reviewed_at' => $rubric ? now() : null]);
    }

    public function test_report_is_teacher_only_and_never_exposes_another_teachers_class(): void
    {
        $this->get(route('teacher.reports'))->assertRedirect(route('teacher.login'));
        $this->get(route('teacher.reports.export'))->assertForbidden();
        extract($this->fixture());
        $this->get(route('teacher.reports'))->assertOk()->assertSee('Class Reports')->assertSee('AliceCoder')->assertSee('BobCoder')
            ->assertDontSee('PrivateCoder')->assertDontSee('Private classroom')->assertDontSee('LLVM')->assertDontSee('Simulate Error')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('teacher.reports', ['classroom' => $foreign->id]))->assertForbidden();
        $this->get(route('teacher.reports.export', ['classroom' => $foreign->id]))->assertForbidden();
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('teacher.reports'))->assertRedirect(route('teacher.login'));
    }

    public function test_empty_reports_use_zero_counts_and_missing_score_indicators(): void
    {
        extract($this->fixture());
        $response = $this->get(route('teacher.reports'))->assertOk()->assertSee('No final scores yet')->assertSee('No saved activity');
        $metrics = $response->viewData('report')['metrics'];
        $this->assertSame(0, $metrics['completed']);
        $this->assertNull($metrics['quiz_average']);
        $this->assertSame(0, $metrics['confirmed']);
        $this->withSession(['teacher_id' => $outsider->id])->get(route('teacher.reports'))->assertOk()->assertDontSee('AliceCoder');
        $empty = TeacherAccount::create(['first_name' => 'New', 'last_name' => 'Teacher', 'email' => 'empty-report@example.test', 'school' => 'School', 'password' => 'password123']);
        $this->withSession(['teacher_id' => $empty->id])->get(route('teacher.reports'))->assertOk()
            ->assertViewHas('report', fn ($report) => $report['metrics']['students'] === 0 && $report['metrics']['completion'] === null);
    }

    public function test_metrics_deduplicate_quizzes_and_revisions_and_require_real_final_reviews(): void
    {
        extract($this->fixture());
        $this->lesson($student, 'output', now());
        $this->saveQuizResult($student, $quiz, 1);
        $this->saveQuizResult($student, $quiz, 2, 2, 2);
        $this->saveQuizResult($peer, $quiz, 0);
        $partial = $quiz->replicate();
        $partial->title = 'Partially graded';
        $partial->save();
        $this->saveQuizResult($student, $partial, 1, 3, 1, null, 1);
        $work = $this->workspace($student, 'assignment:'.$assignment->id, $assignment);
        $this->submission($work, $teacher, 1, ['correctness' => 4, 'logic' => 4, 'readability' => 4, 'explanation' => 4]);
        $this->submission($work, $teacher, 2, ['correctness' => 1, 'logic' => 1, 'readability' => 2, 'explanation' => 2]);
        $strong = $this->workspace($student, 'practice:variables');
        $this->submission($strong, $teacher, 1, ['correctness' => 3, 'logic' => 3, 'readability' => 4, 'explanation' => 4]);
        $draft = $this->workspace($student, 'practice:greeting');
        $ai = $this->submission($draft, $teacher, 1);
        $ai->update(['score' => 16, 'rubric' => ['correctness' => 4, 'logic' => 4, 'readability' => 4, 'explanation' => 4], 'ai_suggestion' => ['feedback' => 'AI draft only']]);
        $response = $this->get(route('teacher.reports'))->assertOk()->assertDontSee('AI draft only');
        $report = $response->viewData('report');
        $this->assertSame(round(100 / (2 * count(config('lessons'))), 1), $report['metrics']['completion']);
        $this->assertSame(50.0, $report['metrics']['quiz_average']);
        $this->assertSame(2, $report['metrics']['graded_quizzes']);
        $this->assertSame(1, $report['metrics']['confirmed']);
        $this->assertSame(1, $report['metrics']['pending']);
        $this->assertSame(2, $report['metrics']['support']);
        $row = $report['rows']->firstWhere('id', $student->id);
        $this->assertSame(100.0, $row['quiz_average']);
        $this->assertSame(3, $row['coding_count']);
        $this->assertSame(2, $row['quiz_count']);
        $this->assertSame(1, $report['performance']->firstWhere('title', 'Console assignment')['submitted']);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_custom_dates_use_inclusive_local_calendar_dates_with_exclusive_utc_end(): void
    {
        extract($this->fixture());
        $this->lesson($student, 'output', '2026-10-28 15:59:59');
        $this->lesson($student, 'variables', '2026-10-28 16:00:00');
        $this->lesson($student, 'input', '2026-10-29 15:59:59');
        $this->lesson($student, 'conditions', '2026-10-29 16:00:00');
        $this->saveQuizResult($student, $quiz, 2, 2, 1, '2026-10-28 16:00:00');
        $response = $this->get(route('teacher.reports', ['range' => 'custom', 'start' => '2026-10-29', 'end' => '2026-10-29']))->assertOk();
        $report = $response->viewData('report');
        $this->assertSame(2, $report['metrics']['completed']);
        $this->assertSame(2, collect($report['chart'])->sum('lessons'));
        $this->assertSame(1, collect($report['chart'])->sum('quizzes'));
        $this->assertSame('2026-10-29 15:59:59', $report['rows']->firstWhere('id', $student->id)['last_active']->format('Y-m-d H:i:s'));
        $this->getJson(route('teacher.reports', ['range' => 'custom']))->assertUnprocessable();
        $this->getJson(route('teacher.reports', ['range' => 'custom', 'start' => '2026-10-29', 'end' => '2026-10-28']))->assertUnprocessable();
        $this->getJson(route('teacher.reports', ['range' => 'custom', 'start' => '2020-01-01', 'end' => '2026-01-01']))->assertUnprocessable();
        $this->getJson(route('teacher.reports', ['range' => 'invalid']))->assertUnprocessable();
    }

    public function test_multi_section_targets_are_counted_against_only_the_selected_enrollment(): void
    {
        extract($this->fixture());
        $work = $this->workspace($student, 'assignment:'.$assignment->id, $assignment);
        $this->submission($work, $teacher, 1);
        $this->saveQuizResult($student, $quiz, 2);
        $response = $this->get(route('teacher.reports', ['classroom' => $other->id]))->assertOk()->assertDontSee('AliceCoder')->assertSee('BobCoder');
        $report = $response->viewData('report');
        $activity = $report['performance']->firstWhere('title', 'Console assignment');
        $this->assertSame(1, $activity['eligible']);
        $this->assertSame(0, $activity['submitted']);
        $this->assertNull($report['metrics']['quiz_average']);
        $this->assertSame(1, $report['metrics']['students']);
    }

    public function test_roster_filters_sorting_inspection_and_csv_use_the_same_owned_records(): void
    {
        extract($this->fixture());
        $student->update(['nickname' => '=SUM(1,2)', 'student_id' => '+123']);
        $record = $this->lesson($student, 'output', now());
        $record->update(['confidence' => 'need_help', 'help_request' => 'Explain <script>alert(1)</script> safely.']);
        $response = $this->get(route('teacher.reports', ['status' => 'support']))->assertOk()->assertSee('Explain &lt;script&gt;alert(1)&lt;/script&gt; safely.', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('data-report-inspect', false);
        $this->assertSame(1, $response->viewData('students')->total());
        $this->assertSame(2, $response->viewData('report')['metrics']['students']);
        $sorted = $this->get(route('teacher.reports', ['sort' => 'xp']))->assertOk();
        $this->assertSame($peer->id, $sorted->viewData('students')->first()['id']);
        $export = $this->get(route('teacher.reports.export', ['status' => 'support']))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString("'+123", $csv);
        $this->assertStringNotContainsString('BobCoder', $csv);
        $this->assertStringNotContainsString('PrivateCoder', $csv);
        $this->get(route('teacher.reports', ['search' => 'does-not-exist']))->assertOk()->assertSee('No students match these roster filters');
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_print_and_export_include_all_filtered_students_beyond_the_paginated_roster(): void
    {
        extract($this->fixture());
        for ($i = 0; $i < 26; $i++) {
            Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => sprintf('Learner%02d', $i), 'student_id' => 'EXPORT'.$i, 'password' => 'password123']);
        }
        $page = $this->get(route('teacher.reports'))->assertOk();
        $this->assertCount(25, $page->viewData('students'));
        $this->assertSame(28, $page->viewData('students')->total());
        $print = $this->get(route('teacher.reports',['print' => 1]))->assertOk()->assertSee('data-print-report="true"',false);
        $this->assertCount(28,$print->viewData('students'));
        $csv = $this->get(route('teacher.reports.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('EXPORT25',$csv);
        $this->assertStringContainsString('BobCoder',$csv);
        $this->assertStringNotContainsString('PrivateCoder',$csv);
    }
}
