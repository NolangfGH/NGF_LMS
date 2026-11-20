<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Models\TeacherQuiz;
use App\Services\DepEdSubjectGrading;
use App\Services\ExamSpecification;
use App\Services\LearningCourses;
use App\Services\ReadyMadeActivities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamSpecificationAndSchoolReportsTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(string $name, string $school, string $role = 'teacher'): TeacherAccount
    {
        $teacher = new TeacherAccount(['first_name' => $name, 'last_name' => 'Sample', 'school' => $school, 'email' => fake()->unique()->safeEmail(), 'password' => 'Password123']);
        $teacher->forceFill(['role' => $role])->save();

        return $teacher;
    }

    public function test_all_132_term_exam_blueprints_assign_every_original_question_once(): void
    {
        $library = app(ReadyMadeActivities::class);
        $spec = app(ExamSpecification::class);
        $count = 0;
        foreach (LearningCourses::keys() as $course) {
            foreach (['Term1', 'Term2', 'Term3'] as $term) {
                $exam = $spec->exam($course, $term, $library);
                $this->assertCount(25, $exam['item_specs']);
                $rows = $spec->defaults($exam);
                $numbers = [];
                foreach ($rows as $row) {
                    foreach ($row['items'] as $level => $items) {
                        foreach ($items as $number) {
                            $numbers[] = $number;
                            $this->assertSame($row['topic'], $exam['item_specs'][$number - 1]['topic']);
                            $this->assertSame($level, $exam['item_specs'][$number - 1]['level']);
                        }
                    }
                }
                sort($numbers);
                $this->assertSame(range(1, 25), $numbers);
                $this->assertSame(25, $spec->totals($rows)['items']);
                $count++;
            }
        }
        $this->assertSame(132, $count);
    }

    public function test_teacher_can_edit_download_and_validate_a_private_tos(): void
    {
        $teacher = $this->teacher('Teacher', 'RSHS');
        $this->withSession(['teacher_id' => $teacher->id]);
        $spec = app(ExamSpecification::class);
        $exam = $spec->exam('math7', 'Term1', app(ReadyMadeActivities::class));
        $rows = $spec->defaults($exam);
        $url = route('teacher.tos.edit', ['math7', 'Term1']);
        $page = $this->get($url)->assertOk()->assertSee('Table of Specifications (TOS)')->assertSee('Save editable TOS');
        if ($preview = getenv('MEDIA_EQUATIONS_PREVIEW')) {
            file_put_contents($preview.'/tos.html', $page->getContent());
        }
        foreach ($rows as &$row) {
            $row['items'] = array_map(fn ($items) => implode(', ', $items), $row['items']);
        }
        unset($row);
        $rows[0]['topic'] = 'Edited geometry competency';
        $rows[0]['hours'] = 3.5;
        $this->put(route('teacher.tos.save', ['math7', 'Term1']), ['revision' => 0, 'rows' => $rows])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('Edited geometry competency');
        $csv = $this->get(route('teacher.tos.download', ['math7', 'Term1']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Edited geometry competency', $csv);
        $this->putJson(route('teacher.tos.save', ['math7', 'Term1']), ['revision' => 0, 'rows' => $rows])->assertConflict();
        $rows[0]['items']['create'] = '1, 1';
        $this->putJson(route('teacher.tos.save', ['math7', 'Term1']), ['revision' => 1, 'rows' => $rows])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $other = $this->teacher('Other', 'RSHS');
        $this->withSession(['teacher_id' => $other->id])->get($url)->assertOk()->assertDontSee('Edited geometry competency');
        $principal = $this->teacher('Principal', 'RSHS', 'principal');
        $this->withSession(['teacher_id' => $principal->id, 'teacher_session_version' => 1])->get($url)->assertForbidden();
    }

    public function test_principal_analytics_and_reports_only_cover_active_staff_and_school_students(): void
    {
        $teacher = $this->teacher('Teacher', 'RSHS');
        $other = $this->teacher('Second', 'RSHS');
        $foreign = $this->teacher('Outsider', 'Other School');
        $principal = $this->teacher('Principal', 'RSHS', 'principal');
        $students = [];
        $quizzes = [];
        foreach ([$teacher, $other, $foreign] as $i => $owner) {
            $room = Classroom::create(['teacher_account_id' => $owner->id, 'name' => 'Mathematics', 'grade' => 'Grade 7', 'section' => 'Section '.$i, 'join_code' => 'SRPT'.$i]);
            $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => 'SchoolLearner'.$i, 'student_id' => 'SRPT00'.$i, 'password' => 'Password123']);
            $students[] = $student;
            $quizzes[] = TeacherQuiz::create(['teacher_account_id' => $owner->id, 'classroom_id' => $room->id, 'course' => 'math7', 'title' => 'Math Term 1 · Term Exam '.$i, 'status' => 'published', 'questions' => []]);
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => 'math7-pre-test', 'score' => 20 + 10 * $i, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $grading = app(DepEdSubjectGrading::class);
        $definition = $grading->template('math7');
        foreach ([$teacher, $foreign] as $offset => $owner) {
            $student = $students[$offset === 0 ? 0 : 2];
            $scores = [];
            foreach ($definition['slots'] as $key => $slot) {
                if ($slot['max'] > 0) {
                    $scores[$key] = $slot['max'];
                }
            }
            DB::table('subject_gradebooks')->insert(['teacher_account_id' => $owner->id, 'classroom_id' => $student->classroom_id, 'course' => 'math7', 'school_year' => '2026-2027', 'term' => 'Term1', 'definition' => json_encode($definition), 'entries' => json_encode([$student->id => ['scores' => $scores, 'reviewed' => true]]), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->withSession(['teacher_id' => $teacher->id])->get(route('school.reports'))->assertForbidden();
        $this->get(route('school.analytics'))->assertForbidden();
        $this->withSession(['teacher_id' => $principal->id, 'teacher_session_version' => 1]);
        $reports = $this->get(route('school.reports', ['course' => 'math7']))->assertOk()->assertSee('School Reports')->assertSee('SchoolLearner0')->assertSee('SchoolLearner1')->assertDontSee('SchoolLearner2')->assertViewHas('metrics', fn ($m) => $m['students'] === 2)->assertViewHas('gradebooks', fn ($books) => $books->count() === 1 && $books->first()['reviewed'] === 1);
        $analytics = $this->get(route('school.analytics', ['kind' => 'pre', 'course' => 'math7']))->assertOk()->assertViewHas('insight', fn ($x) => $x['metrics']['count'] === 2 && $x['metrics']['mps'] === 50.0);
        if ($preview = getenv('MEDIA_EQUATIONS_PREVIEW')) {
            file_put_contents($preview.'/school-reports.html', $reports->getContent());
            file_put_contents($preview.'/school-analytics.html', $analytics->getContent());
        }
        $this->get(route('school.analytics', ['kind' => 'term-exam', 'course' => 'math7', 'term' => 'Term1']))->assertOk()->assertSee('Math Term 1 · Term Exam 0')->assertSee('Math Term 1 · Term Exam 1')->assertDontSee('Math Term 1 · Term Exam 2');
        $this->get(route('school.analytics', ['exam' => $quizzes[2]->id]))->assertNotFound();
        $this->get(route('school.analytics', ['classroom' => $students[2]->classroom_id]))->assertNotFound();
        $this->get(route('school.reports', ['classroom' => $students[2]->classroom_id]))->assertNotFound();
        $csv = $this->get(route('school.reports.export', ['course' => 'math7']))->assertOk()->streamedContent();
        $this->assertStringContainsString('SchoolLearner0',$csv);
        $this->assertStringNotContainsString('SchoolLearner2',$csv);
        $this->get(route('teacher.analytics'))->assertForbidden();
    }
}
