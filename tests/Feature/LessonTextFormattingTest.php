<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LessonContent;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\LearningCourses;
use App\Services\LessonTextFormatting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LessonTextFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_rich_explanations_across_courses_are_saved_and_displayed_with_safe_semantic_formatting(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Sample', 'last_name' => 'Teacher', 'email' => 'formatting@example.test', 'school' => 'Sample RSHS', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Sample', 'grade' => 'Grade 7', 'section' => 'Sample', 'join_code' => 'FORMAT7']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Sample', 'nickname' => 'Reader', 'student_id' => 'FORMAT001', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => $teacher->id]);
        $html = '<h3>Key concept</h3><p><b>Remember</b> the <i>definition</i>.</p><ul><li>Observe</li><li><span style="color: #14532d; background-color: #fef08a">Explain</span></li></ul><p align="center">H<sub>2</sub>O</p><div class="unapproved">Filipino: mga salitang may diin — café</div>';
        foreach (['math7' => 'math7-polygons', 'cpp' => 'introduction', 'arduino-uno' => 'arduino-robotics'] as $course => $key) {
            $definition = LearningCourses::lessons($course)[$key];
            $definition['sections'][0]['text'] = 'Plain fallback';
            $definition['sections'][0]['formatted_text'] = $html;
            $this->post(route('teacher.content.save', $key), [...$definition, 'revision' => 0, 'status' => 'published'])->assertRedirect()->assertSessionHasNoErrors();
            $section = LessonContent::where('lesson_key', $key)->sole()->definition['sections'][0];
            $this->assertStringContainsString('<h3>Key concept</h3>', $section['formatted_text']);
            $this->assertStringContainsString('<strong>Remember</strong>', $section['formatted_text']);
            $this->assertStringContainsString('class="lesson-color-green lesson-highlight"', $section['formatted_text']);
            $this->assertStringContainsString('class="lesson-align-center"', $section['formatted_text']);
            $this->assertStringNotContainsString('unapproved', $section['formatted_text']);
            $this->assertStringContainsString('Filipino: mga salitang may diin — café', $section['text']);
            $this->assertStringContainsString("Observe\nExplain", $section['text']);
            $page = $this->get(route('teacher.content.edit', $key))->assertOk()->assertSee('Explanation text formatting')->assertSee('data-format-command="bold"', false)->assertSee('<strong>Remember</strong>', false);
            if ($course === 'math7' && ($preview = getenv('ACADEMIC_SUBJECT_PREVIEW'))) {
                file_put_contents($preview.'/editor.html', $page->getContent());
            }
        }

        foreach ([CourseAssessments::REASONING, 'math7-pre-test'] as $assessment) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $lesson = $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true])->get(route('student.lesson.show', 'math7-polygons'))->assertOk()->assertSee('<h3>Key concept</h3>', false)->assertSee('<strong>Remember</strong>', false)->assertSee('Listen to this lesson');
        if ($preview = getenv('ACADEMIC_SUBJECT_PREVIEW')) {
            file_put_contents($preview.'/lesson.html', $lesson->getContent());
        }
        $principal = new TeacherAccount(['first_name' => 'Sample', 'last_name' => 'Principal', 'school' => 'Sample RSHS', 'email' => 'principal-formatting@example.test', 'password' => 'Password123']);
        $principal->forceFill(['role' => 'principal'])->save();
        $this->withSession(['student_id' => null, 'teacher_id' => $principal->id, 'teacher_session_version' => 1])->get(route('school.overview', ['tab' => 'lessons', 'course' => 'math7']))->assertOk()->assertSee('<strong>Remember</strong>', false);
    }

    public function test_formatting_uses_only_allowed_markup_and_legacy_text_remains_literal(): void
    {
        $safe = LessonTextFormatting::sanitize('<p id="demo" style="position: absolute; text-align: right">Example <b title="label">word</b></p><iframe>Not lesson text</iframe><a href="https://example.com">Link text</a><img src="example.png">');
        $this->assertSame('<p class="lesson-align-right">Example <strong>word</strong></p>Link text', $safe);
        $this->assertSame($safe, LessonTextFormatting::sanitize($safe));
        $this->assertSame('Example word'."\n".'Link text', LessonTextFormatting::plainText($safe));
        $this->assertSame('Use &lt;iostream&gt;<br>Practice &amp; review.', LessonTextFormatting::render(['text' => "Use <iostream>\nPractice & review."]));
        $this->assertSame(['text' => 'Legacy'], LessonTextFormatting::normalize(['text' => 'Legacy'], 0));
    }

    public function test_empty_formatted_content_cannot_replace_a_readable_explanation(): void
    {
        $this->expectException(ValidationException::class);
        LessonTextFormatting::normalize(['text' => 'Unrelated fallback', 'formatted_text' => '<p><br></p>'], 0);
    }
}
