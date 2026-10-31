<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\GradingAssessment;
use App\Models\GradingScore;
use App\Models\GradingSheet;
use App\Models\LabSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherAssignment;
use App\Models\TeacherQuiz;
use App\Services\GradingSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradingSheetTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'Grading', 'last_name' => 'Teacher', 'email' => 'grading@example.test', 'school' => 'School', 'password' => 'password123']);
        $other = TeacherAccount::create(['first_name' => 'Private', 'last_name' => 'Teacher', 'email' => 'private-grading@example.test', 'school' => 'School', 'password' => 'password123']);
        $rooms = collect(['AM', 'BP', 'DB'])->map(fn ($section) => Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => $section, 'join_code' => 'GRA'.$section]));
        $foreignRoom = Classroom::create(['teacher_account_id' => $other->id, 'name' => 'Secret room', 'grade' => 'Grade 8', 'section' => 'Secret', 'join_code' => 'GRASECRET']);
        $student = Student::create(['classroom_id' => $rooms[0]->id, 'first_name' => 'Alice', 'surname' => 'Coder', 'nickname' => 'AliceCode', 'student_id' => '0001', 'password' => 'password123', 'xp' => 100]);
        $peer = Student::create(['classroom_id' => $rooms[1]->id, 'first_name' => 'Bob', 'nickname' => 'OtherSection', 'student_id' => '0002', 'password' => 'password123']);
        $foreignStudent = Student::create(['classroom_id' => $foreignRoom->id, 'first_name' => 'Secret', 'nickname' => 'SecretCoder', 'student_id' => 'SECRET', 'password' => 'password123']);
        $sheet = GradingSheet::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'school_year' => '2026-2027', 'term' => 'Term1', 'weights' => config('grading.weights')]);
        $this->withSession(['teacher_id' => $teacher->id]);

        return compact('teacher', 'other', 'rooms', 'foreignRoom', 'student', 'peer', 'foreignStudent', 'sheet');
    }

    private function item(GradingSheet $sheet, string $category = 'WW', array $extra = []): GradingAssessment
    {
        return $sheet->assessments()->create($extra + ['title' => 'Assessment '.$category, 'category' => $category, 'max_score' => 100]);
    }

    private function score(GradingAssessment $assessment, Student $student, ?float $score, string $status = 'scored'): void
    {
        GradingScore::create(['grading_assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => $score, 'status' => $status, 'scored_by' => $assessment->sheet->teacher_account_id]);
    }

    private function sheetQuery(GradingSheet $sheet, array $extra = []): array
    {
        return $extra + ['classroom' => $sheet->classroom_id, 'term' => $sheet->term, 'school_year' => $sheet->school_year];
    }

    public function test_teacher_only_page_filters_sections_and_renders_real_records_and_navigation(): void
    {
        $this->get(route('teacher.grading'))->assertRedirect(route('teacher.login'));
        $this->getJson(route('teacher.grading.status'))->assertForbidden();
        extract($this->fixture());
        $this->get(route('teacher.grading', $this->sheetQuery($sheet)))->assertOk()->assertSee('Grading Sheet')
            ->assertSee('Alice Coder')->assertDontSee('SecretCoder')->assertDontSee('OtherSection')->assertSee('No scores')
            ->assertDontSee('DEPED VERIFIED')->assertDontSee('84.6%')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('teacher.grading', $this->sheetQuery($sheet, ['classroom' => $foreignRoom->id])))->assertForbidden();
        $this->getJson(route('teacher.grading.status', $this->sheetQuery($sheet, ['student' => $peer->id])))->assertNotFound();
        $this->get(route('teacher.grading', $this->sheetQuery($sheet, ['classroom' => $rooms[1]->id])))->assertSee('OtherSection')->assertDontSee('Alice Coder');
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->get(route('teacher.grading'))->assertRedirect(route('teacher.login'));
        $this->putJson(route('teacher.grading.weights', $sheet), ['revision' => 1])->assertForbidden();
    }

    public function test_create_sheet_is_idempotent_and_assessments_support_edit_delete_without_changing_sources(): void
    {
        extract($this->fixture());
        $this->post(route('teacher.grading.store'), $this->sheetQuery($sheet))->assertRedirect();
        $this->assertDatabaseCount('grading_sheets', 1);
        $this->postJson(route('teacher.grading.store'), $this->sheetQuery($sheet, ['school_year' => '2026-2029']))->assertUnprocessable();
        $payload = ['revision' => 1, 'title' => 'Console output', 'category' => 'PT', 'max_score' => 20, 'source' => ''];
        $this->post(route('teacher.grading.assessments.store', $sheet), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $assessment = GradingAssessment::firstOrFail();
        $this->assertSame('manual', $assessment->source_type);
        $this->score($assessment, $student, 18);
        $this->putJson(route('teacher.grading.assessments.update', [$sheet, $assessment]), [...$payload, 'revision' => 2, 'max_score' => 10])->assertUnprocessable();
        $this->put(route('teacher.grading.assessments.update', [$sheet, $assessment]), [...$payload, 'revision' => 2, 'title' => 'Updated output'])->assertRedirect();
        $this->assertDatabaseHas('grading_assessments', ['id' => $assessment->id, 'title' => 'Updated output']);
        $this->deleteJson(route('teacher.grading.assessments.destroy', [$sheet, $assessment]), ['revision' => 3])->assertUnprocessable();
        $this->delete(route('teacher.grading.assessments.destroy', [$sheet, $assessment]), ['revision' => 3, 'confirm_delete' => 1])->assertRedirect();
        $this->assertDatabaseCount('grading_scores', 0);
        $this->assertDatabaseCount('grading_assessments', 0);
        $this->assertSame(100, $student->fresh()->xp);
    }

    public function test_calculation_uses_points_weighted_categories_and_distinguishes_zero_pending_and_excused(): void
    {
        extract($this->fixture());
        $ww = $this->item($sheet, 'WW');
        $ww2 = $this->item($sheet, 'WW', ['max_score' => 20]);
        $pt = $this->item($sheet, 'PT');
        $cp = $this->item($sheet, 'CP');
        $qa = $this->item($sheet, 'QA');
        $this->score($ww, $student, 80);
        $this->score($ww2, $student, 10);
        $this->score($pt, $student, 100);
        $this->score($cp, $student, 0);
        $service = app(GradingSheetService::class);
        $row = $service->build($sheet, $teacher->id, $rooms[0]->id)['rows']->first();
        $this->assertSame(75.0, $row['categories']['WW']['percentage']);
        $this->assertSame(67.19, $row['average']); // (75*25 + 100*35 + 0*20) / 80.
        $this->assertFalse($row['complete']);
        $this->assertSame(1, $row['pending']);
        $this->score($qa, $student, 50);
        $row = $service->build($sheet, $teacher->id, $rooms[0]->id)['rows']->first();
        $this->assertSame(63.75, $row['average']);
        $this->assertTrue($row['complete']);
        $excused = $this->item($sheet, 'WW');
        $this->score($excused, $student, null, 'excused');
        $row = $service->build($sheet, $teacher->id, $rooms[0]->id)['rows']->first();
        $this->assertSame(63.75, $row['average']);
        $this->assertTrue($row['complete']);
        $this->assertSame(6, $row['resolved']);
    }

    public function test_linked_quiz_and_final_code_review_results_refresh_live_and_allow_explicit_overrides(): void
    {
        extract($this->fixture());
        $quiz = TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Variables quiz', 'questions' => [['prompt' => 'Q1'], ['prompt' => 'Q2']], 'status' => 'published']);
        $assignment = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Console lab', 'school_term' => 'Term1', 'status' => 'published']);
        $q = $this->item($sheet, 'WW', ['source_type' => 'quiz', 'source_id' => $quiz->id]);
        $lab = $this->item($sheet, 'PT', ['source_type' => 'assignment', 'source_id' => $assignment->id]);
        DB::table('quiz_submissions')->insert(['teacher_quiz_id' => $quiz->id, 'student_id' => $student->id, 'answers' => '[]', 'score' => 1, 'total_questions' => 2, 'graded_questions' => 2, 'attempt_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $workspace = CodeWorkspace::create(['student_id' => $student->id, 'teacher_assignment_id' => $assignment->id, 'activity_key' => 'assignment:'.$assignment->id, 'title' => 'Console', 'code' => 'int main(){}']);
        $review = LabSubmission::create(['code_workspace_id' => $workspace->id, 'teacher_account_id' => $teacher->id, 'revision' => 1, 'title' => 'Console', 'code' => 'int main(){}', 'reflection' => 'I traced the output before testing.', 'score' => 12, 'rubric' => ['correctness' => 3, 'logic' => 3, 'readability' => 3, 'explanation' => 3], 'reviewed_at' => now()]);
        $service = app(GradingSheetService::class);
        $book = $service->build($sheet, $teacher->id, $rooms[0]->id);
        $this->assertSame(50.0, $book['rows'][0]['cells'][$q->id]['score']);
        $this->assertSame(75.0, $book['rows'][0]['cells'][$lab->id]['score']);
        $this->get(route('teacher.grading', $this->sheetQuery($sheet, ['student' => $student->id])))->assertOk()->assertSee('Latest final teacher rubric')->assertSee('Best fully graded quiz attempt');
        DB::table('quiz_submissions')->insert(['teacher_quiz_id' => $quiz->id, 'student_id' => $student->id, 'answers' => '[]', 'score' => 2, 'total_questions' => 2, 'graded_questions' => 2, 'attempt_number' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson(route('teacher.grading.status', $this->sheetQuery($sheet)))->assertOk()->assertJsonPath('revision', 1);
        $this->assertSame(100.0, $service->build($sheet, $teacher->id, $rooms[0]->id)['rows'][0]['cells'][$q->id]['score']);
        $this->put(route('teacher.grading.scores', [$sheet, $student]), ['revision' => 1, 'scores' => [$q->id => ['status' => 'scored', 'score' => 80]], 'note' => 'Revisit variable names.'])->assertRedirect();
        $this->assertSame(80.0, $service->build($sheet->fresh(), $teacher->id, $rooms[0]->id)['rows'][0]['cells'][$q->id]['score']);
        $this->put(route('teacher.grading.scores', [$sheet, $student]), ['revision' => 2, 'scores' => [$q->id => ['status' => 'source']]])->assertRedirect();
        $this->assertSame(100.0, $service->build($sheet->fresh(), $teacher->id, $rooms[0]->id)['rows'][0]['cells'][$q->id]['score']);
        $this->assertSame(100, $student->fresh()->xp);
        $assignment->delete();
        $quiz->delete();
        $this->assertSame(75.0, $service->build($sheet->fresh(), $teacher->id, $rooms[0]->id)['rows'][0]['cells'][$lab->id]['score']);
        $this->assertDatabaseHas('lab_submissions', ['id' => $review->id]);
    }

    public function test_source_linking_rejects_foreign_unassigned_draft_wrong_term_and_duplicate_activities(): void
    {
        extract($this->fixture());
        $base = ['revision' => 1, 'title' => 'Linked result', 'category' => 'WW', 'max_score' => 10];
        $foreignQuiz = TeacherQuiz::create(['teacher_account_id' => $other->id, 'classroom_id' => $foreignRoom->id, 'title' => 'Private', 'questions' => [['prompt' => 'Secret']], 'status' => 'published']);
        $draft = TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Draft', 'questions' => [['prompt' => 'Draft']], 'status' => 'draft']);
        $unassigned = TeacherQuiz::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[1]->id, 'title' => 'Other section', 'questions' => [['prompt' => 'Q']], 'status' => 'published']);
        foreach ([$foreignQuiz, $draft, $unassigned] as $bad) {
            $this->postJson(route('teacher.grading.assessments.store', $sheet), $base + ['source' => 'quiz:'.$bad->id])->assertUnprocessable();
        }
        $unassigned->classrooms()->sync([$rooms[0]->id]);
        $this->post(route('teacher.grading.assessments.store', $sheet), $base + ['source' => 'quiz:'.$unassigned->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson(route('teacher.grading.assessments.store', $sheet), [...$base, 'revision' => 2, 'source' => 'quiz:'.$unassigned->id])->assertUnprocessable();
        $wrongTerm = TeacherAssignment::create(['teacher_account_id' => $teacher->id, 'classroom_id' => $rooms[0]->id, 'title' => 'Term 2 work', 'school_term' => 'Term2', 'status' => 'published']);
        $this->postJson(route('teacher.grading.assessments.store', $sheet), [...$base, 'revision' => 2, 'source' => 'assignment:'.$wrongTerm->id])->assertUnprocessable();
    }

    public function test_score_changes_validate_ranges_ownership_and_stale_revisions_atomically(): void
    {
        extract($this->fixture());
        $item = $this->item($sheet);
        $target = route('teacher.grading.scores', [$sheet, $student]);
        $this->putJson($target, ['revision' => 1, 'scores' => [$item->id => ['status' => 'scored', 'score' => 101]]])->assertUnprocessable();
        $this->putJson($target, ['revision' => 1, 'scores' => [999 => ['status' => 'scored', 'score' => 50]]])->assertUnprocessable();
        $this->putJson(route('teacher.grading.scores', [$sheet, $peer]), ['revision' => 1])->assertNotFound();
        $this->put($target, ['revision' => 1, 'scores' => [$item->id => ['status' => 'scored', 'score' => 0]], 'note' => '<script>alert(1)</script>'])->assertRedirect();
        $this->assertDatabaseHas('grading_scores', ['student_id' => $student->id, 'score' => 0]);
        $this->putJson($target, ['revision' => 1, 'scores' => [$item->id => ['status' => 'scored', 'score' => 90]]])->assertConflict();
        $this->get(route('teacher.grading', $this->sheetQuery($sheet, ['student' => $student->id])))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->withSession(['teacher_id' => $other->id])->putJson($target, ['revision' => 2])->assertNotFound();
        $this->assertDatabaseHas('grading_scores', ['score' => 0]);
    }

    public function test_weights_require_exactly_one_hundred_and_term_records_are_independent(): void
    {
        extract($this->fixture());
        $this->putJson(route('teacher.grading.weights', $sheet), ['revision' => 1, 'weights' => ['WW' => 10, 'PT' => 10, 'CP' => 10, 'QA' => 10], 'passing_score' => 75])->assertUnprocessable();
        $this->put(route('teacher.grading.weights', $sheet), ['revision' => 1, 'weights' => ['WW' => 40, 'PT' => 60, 'CP' => 0, 'QA' => 0], 'passing_score' => 70])->assertRedirect();
        $this->assertSame(40, $sheet->fresh()->weights['WW']);
        $this->post(route('teacher.grading.store'), $this->sheetQuery($sheet, ['term' => 'Term2']))->assertRedirect();
        $this->assertDatabaseCount('grading_sheets', 2);
        $this->get(route('teacher.grading', $this->sheetQuery($sheet, ['term' => 'Term2'])))->assertOk()->assertViewHas('sheet', fn ($sheet) => $sheet->weights['WW'] === 25);
    }

    public function test_csv_import_is_atomic_and_exports_are_scoped_and_formula_safe(): void
    {
        extract($this->fixture());
        $item = $this->item($sheet);
        $header = "student_id,assessment_id,score,status\n";
        $bad = $header."0001,{$item->id},80,scored\nSECRET,{$item->id},90,scored\n";
        $this->postJson(route('teacher.grading.import', $sheet), ['revision' => 1, 'file' => UploadedFile::fake()->createWithContent('scores.csv', $bad)])->assertUnprocessable();
        $this->assertDatabaseCount('grading_scores', 0);
        $duplicate = $header."0001,{$item->id},80,scored\n0001,{$item->id},90,scored\n";
        $this->postJson(route('teacher.grading.import', $sheet), ['revision' => 1, 'file' => UploadedFile::fake()->createWithContent('scores.csv', $duplicate)])->assertUnprocessable();
        $good = $header."'0001,{$item->id},80,scored\n";
        $this->post(route('teacher.grading.import', $sheet), ['revision' => 1, 'file' => UploadedFile::fake()->createWithContent('scores.csv', $good)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grading_scores', ['student_id' => $student->id, 'score' => 80]);
        $student->update(['first_name' => '=HYPERLINK("bad")']);
        $csv = $this->get(route('teacher.grading.export', $this->sheetQuery($sheet)))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('SECRET', $csv);
        $this->assertStringNotContainsString('OtherSection', $csv);
        $masked = $this->get(route('teacher.grading.export', $this->sheetQuery($sheet, ['mask' => 1])))->streamedContent();
        $this->assertStringNotContainsString('HYPERLINK', $masked);
        $this->assertStringContainsString($student->rankingAlias(), $masked);
        $template = $this->get(route('teacher.grading.export', $this->sheetQuery($sheet, ['format' => 'scores'])))->streamedContent();
        $this->assertStringContainsString('student_id,assessment_id,score,status', $template);
        $this->assertStringContainsString("'0001", $template);
    }
}
