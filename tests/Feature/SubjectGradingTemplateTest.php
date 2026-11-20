<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\DepEdSubjectGrading;
use App\Services\LearningCourses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectGradingTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_profiles_and_transition_grades_are_computed_without_xp(): void
    {
        $service = app(DepEdSubjectGrading::class);
        foreach (LearningCourses::keys() as $course) {
            $definition = $service->template($course);
            $scores = [];
            foreach ($definition['slots'] as $key => $slot) {
                if ($slot['max'] > 0) {
                    $scores[$key] = $slot['max'] * .7;
                }
            }
            $result = $service->calculate($definition, $scores, '2026-2027');
            $this->assertTrue($result['complete'], $course);
            $this->assertSame(70.0, $result['initial'], $course);
            $this->assertSame(75, $result['grade'], $course);
            $this->assertSame(70, $service->calculate($definition, $scores, '2027-2028')['grade'], $course);
            $this->assertNull($service->calculate($definition, [], '2026-2027')['grade'], $course);
        }
        $this->assertSame(74, DepEdSubjectGrading::termGrade(69.99, '2026-2027'));
        $this->assertSame(75, DepEdSubjectGrading::termGrade(70, '2026-2027'));
        $this->assertSame(88, DepEdSubjectGrading::termGrade(85.8, '2026-2027'));
        $this->assertSame(60, DepEdSubjectGrading::termGrade(0, '2026-2027'));
        $this->assertSame(100, DepEdSubjectGrading::termGrade(99.5, '2026-2027'));
        $this->assertSame(['WW' => 20, 'PT' => 50, 'EX' => 30], DepEdSubjectGrading::weights('science7'));
        $this->assertSame(['WW' => 20, 'PT' => 60, 'EX' => 20], DepEdSubjectGrading::weights('mapeh7'));
        $this->assertSame(30, $service->template('ve7')['groups']['ptb']['weight']);
        $this->assertCount(2, $service->template('mapeh7')['parts']);
    }

    public function test_teacher_can_save_separate_subjects_and_terms_and_print_reviewed_learner_card(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Grade', 'last_name' => 'Teacher', 'school' => 'RSHS', 'email' => 'grades-subject@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Sample', 'grade' => 'Grade 7', 'section' => 'Sample', 'join_code' => 'GRADE7']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Sample', 'nickname' => 'GradeLearner', 'student_id' => 'GRADE001', 'password' => 'Password123', 'xp' => 9999]);
        $this->withSession(['teacher_id' => $teacher->id]);
        $params = ['classroom' => $room->id, 'course' => 'math7', 'school_year' => '2026-2027', 'term' => 'Term1'];
        $this->get(route('teacher.subject-grading', $params))->assertOk()->assertSee('Subject Grading Sheet')->assertSee('Create this subject gradebook');
        $this->post(route('teacher.subject-grading.create'), $params)->assertRedirect();
        $sheet = DB::table('subject_gradebooks')->first();
        $def = json_decode($sheet->definition, true);
        $scores = [];
        foreach ($def['slots'] as $key => $slot) {
            if ($slot['max'] > 0) {
                $scores[$key] = $slot['max'] * .8;
            }
        }
        $this->put(route('teacher.subject-grading.scores', [$sheet->id, $student]), ['revision' => 1, 'scores' => $scores, 'reviewed' => 1, 'remark' => 'Good reasoning; continue practice.'])->assertRedirect()->assertSessionHasNoErrors();
        $page = $this->get(route('teacher.subject-grading', $params))->assertOk()->assertSee('Teacher reviewed')->assertSee('Good reasoning; continue practice.');
        $card = $this->get(route('teacher.subject-grading.card', [...$params, 'student' => $student->id]))->assertOk()->assertSee('Subject Learner Progress Card')->assertSee('Teacher reviewed')->assertSee('Not finalized')->assertSee('Good reasoning; continue practice.');
        if ($preview = getenv('MEDIA_EQUATIONS_PREVIEW')) {
            file_put_contents($preview.'/subject-grades.html', $page->getContent());
            file_put_contents($preview.'/learner-card.html', $card->getContent());
        }
        foreach (['Term2', 'Term3'] as $term) {
            $this->post(route('teacher.subject-grading.create'), [...$params, 'term' => $term])->assertRedirect();
            $other = DB::table('subject_gradebooks')->where('term', $term)->first();
            $this->put(route('teacher.subject-grading.scores', [$other->id, $student]), ['revision' => 1, 'scores' => $scores, 'reviewed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->get(route('teacher.subject-grading.card', [...$params, 'student' => $student->id]))->assertOk()->assertViewHas('final', 83);
        $csv = $this->get(route('teacher.subject-grading.export', $params))->assertOk()->streamedContent();
        $this->assertStringContainsString('GRADE001', $csv);
        $this->assertStringContainsString('Teacher reviewed', $csv);
        $this->get(route('teacher.subject-grading.card', $params))->assertOk()->assertSee('Not finalized')->assertDontSee('GRADE001');
        $slots = array_map(fn ($slot) => ['label' => $slot['label'], 'max' => $slot['max']], $def['slots']);
        $slots['ww1']['max'] = 1;
        $this->putJson(route('teacher.subject-grading.settings', $sheet->id), ['revision' => 2, 'slots' => $slots])->assertUnprocessable()->assertJsonValidationErrors('slots.ww1.max');
        $slots['ww1']['max'] = 20;
        $slots['ww1']['label'] = 'Geometry written work';
        $this->put(route('teacher.subject-grading.settings', $sheet->id), ['revision' => 2, 'slots' => $slots])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('teacher.subject-grading.card', [...$params, 'student' => $student->id]))->assertOk()->assertViewHas('final', null);
        $this->post(route('teacher.subject-grading.create'), [...$params, 'course' => 'science7'])->assertRedirect();
        $this->assertDatabaseCount('subject_gradebooks', 4);
        $this->assertDatabaseCount('grading_sheets', 0);
        $this->assertSame(9999, $student->fresh()->xp);
        $scores[array_key_first($scores)] = null;
        $this->putJson(route('teacher.subject-grading.scores', [$sheet->id, $student]), ['revision' => 3, 'scores' => $scores, 'reviewed' => 1])->assertUnprocessable()->assertJsonValidationErrors('scores');
        $outsider = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'school' => 'Other', 'email' => 'other-grades@example.test', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => $outsider->id])->get(route('teacher.subject-grading.card',[...$params, 'student' => $student->id]))->assertNotFound();
        $this->putJson(route('teacher.subject-grading.scores',[$sheet->id, $student]),['revision' => 2, 'scores' => $scores])->assertNotFound();
    }
}
