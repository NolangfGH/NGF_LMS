<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\CodeWorkspace;
use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\LearningCourses;
use App\Services\LessonMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonMediaAndEquationsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $teacher = TeacherAccount::create(['first_name' => 'Media', 'last_name' => 'Teacher', 'school' => 'Sample RSHS', 'email' => 'media-math@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Grade 7', 'grade' => 'Grade 7', 'section' => 'Sample', 'join_code' => 'MEDIAEQ7']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => 'EquationReader', 'student_id' => 'EQ001', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => $teacher->id]);

        return [$teacher, $student, LearningCourses::lessons('math7')['math7-polygons'] + ['revision' => 0, 'status' => 'published']];
    }

    private function principal(string $school, string $email): TeacherAccount
    {
        $principal = new TeacherAccount(['first_name' => 'Sample', 'last_name' => 'Principal', 'school' => $school, 'email' => $email, 'password' => 'Password123']);
        $principal->forceFill(['role' => 'principal'])->save();

        return $principal;
    }

    public function test_independent_media_settings_persist_and_principal_images_remain_school_scoped(): void
    {
        [$teacher, $student, $payload] = $this->fixture();
        $lesson = 'math7-polygons';
        $formula = 'The area is \(\frac{bh}{2}\).';
        $payload['sections'][0] += [
            'image' => UploadedFile::fake()->createWithContent('diagram.png', file_get_contents(public_path('images/branding/app-icon-192.png'))),
            'image_alt' => 'Illustration for a geometry activity',
            'image_width' => 'medium', 'image_alignment' => 'right', 'image_aspect' => 'square', 'image_fit' => 'cover', 'image_position' => 'top', 'image_frame' => 'border', 'image_caption' => 'Figure 1. Identify the important parts.',
            'video_url' => 'https://youtu.be/aqz-KE-bpKQ', 'video_title' => 'Video demonstration', 'video_caption' => 'Observe the example from the selected timestamp.', 'video_width' => 'large', 'video_alignment' => 'left', 'video_aspect' => 'portrait', 'video_frame' => 'plain', 'video_start' => 31,
            'formatted_text' => '<p>'.$formula.'</p>',
        ];
        $payload['sections'][1]['image_alignment'] = 'left';
        $this->post(route('teacher.content.save', $lesson), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $content = LessonContent::sole();
        $section = $content->definition['sections'][0];
        $this->assertSame($formula, $section['text']);
        $this->assertSame('medium', $section['image_width']);
        $this->assertSame('right', $section['image_alignment']);
        $this->assertSame('left', $content->definition['sections'][1]['image_alignment']);
        $this->assertSame(31, $section['video_start']);
        Storage::disk('local')->assertExists($section['image_path']);
        $editor = $this->get(route('teacher.content.edit', $lesson))->assertOk()->assertSee('Image studio')->assertSee('Video studio')->assertSee('Equation & scientific notation')->assertSee('Image preview')->assertSee('Video layout preview')->assertSee($formula);
        $this->get(route('teacher.content.edit', 'science7-models'))->assertOk()->assertSee('Equation & scientific notation')->assertSee('Molecular formula');
        $this->get(route('teacher.content.edit', 'introduction'))->assertOk()->assertSee('Image studio')->assertDontSee('Equation & scientific notation');
        if ($preview = getenv('MEDIA_EQUATIONS_PREVIEW')) {
            file_put_contents($preview.'/media-editor.html', $editor->getContent());
            copy(public_path('images/branding/app-icon-192.png'), $preview.'/media-preview.png');
        }
        $schoolImage = route('school.lesson.section-image', [$content, $section['media_key']]);
        $this->get($schoolImage)->assertNotFound();
        $principal = $this->principal('Sample RSHS', 'principal-media@example.test');
        $this->withSession(['teacher_id' => $principal->id, 'teacher_session_version' => 1])->get($schoolImage)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('school.overview', ['tab' => 'lessons', 'course' => 'math7']))->assertOk()->assertSee($schoolImage)->assertSee('lesson-media-width-medium', false)->assertSee('Video demonstration');
        $outsider = $this->principal('Other School', 'outsider-media@example.test');
        $this->withSession(['teacher_id' => $outsider->id, 'teacher_session_version' => 1])->get($schoolImage)->assertNotFound();
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true]);
        foreach ([CourseAssessments::REASONING, 'math7-pre-test'] as $assessment) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $page = $this->get(route('student.lesson.show', $lesson))->assertOk()->assertSee($formula)->assertSee('lesson-media-width-medium', false)->assertSee('lesson-media-align-right', false)->assertSee('lesson-media-aspect-square', false)->assertSee('lesson-media-aspect-portrait', false)->assertSee('Figure 1. Identify the important parts.')->assertSee('rel=0&amp;start=31', false)->assertSee('data-equation-target="lesson-reflection"', false)->assertSee('data-equation-target="lesson-help"', false)->assertDontSee('<iframe', false);
        if ($preview = getenv('MEDIA_EQUATIONS_PREVIEW')) {
            file_put_contents($preview.'/media-lesson.html', $page->getContent());
        }
        $this->get(route('student.lesson.section-image', [$content, $section['media_key']]))->assertOk();
        $this->assertSame('https://player.vimeo.com/video/123456789?dnt=1#t=45s', LessonMedia::embedUrl('https://vimeo.com/123456789', 45));
    }

    public function test_saved_media_validates_format_controls_and_student_formula_text_survives_review(): void
    {
        [$teacher, $student, $payload] = $this->fixture();
        $key = 'math7-polygons';
        $payload['sections'][0]['image_width'] = 'unknown';
        $payload['sections'][0]['video_start'] = -1;
        $this->postJson(route('teacher.content.save', $key), $payload)->assertUnprocessable()->assertJsonValidationErrors(['sections.0.image_width', 'sections.0.video_start']);
        $this->assertDatabaseCount('lesson_contents', 0);
        $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true]);
        foreach ([CourseAssessments::REASONING, 'math7-pre-test'] as $assessment) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $this->get(route('student.lesson.show', $key))->assertOk();
        $reflection = 'I calculated \(\frac{12}{3}=4\).';
        $help = 'Why is \(\sqrt{16}=4\)?';
        $this->post(route('student.lesson.support', $key), ['reflection' => $reflection, 'help_request' => $help])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($reflection, LessonProgress::sole()->reflection);
        $this->assertSame($help, LessonProgress::sole()->help_request);
        $notebook = $this->get(route('student.code-lab', ['activity' => $key]))->assertOk()->assertSee('data-equation-target="codeEditor"', false)->assertSee('data-equation-target="reflection"', false);
        if ($preview = getenv('MEDIA_EQUATIONS_PREVIEW')) {
            file_put_contents($preview.'/media-notebook.html', $notebook->getContent());
        }
        $workspace = CodeWorkspace::sole();
        $this->putJson(route('student.code-lab.save', $workspace), ['revision' => $workspace->revision, 'code' => 'My calculation: \[\frac{12}{3}=4\]', 'reflection' => $reflection])->assertOk();
        $this->postJson(route('student.code-lab.submit', $workspace), ['revision' => $workspace->fresh()->revision])->assertOk();
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->get(route('teacher.lessons.progress', ['course' => 'math7']))->assertOk()->assertSee($reflection)->assertSee($help)->assertSee('data-equation-display', false);
        $this->get(route('teacher.code-lab', ['activity' => 'practice']))->assertOk()->assertSee($reflection)->assertSee('My calculation: \[\frac{12}{3}=4\]')->assertSee('data-equation-display', false);
    }
}
