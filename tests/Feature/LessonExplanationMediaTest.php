<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\LessonContent;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use App\Services\LessonMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonExplanationMediaTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $teacher = TeacherAccount::create(['first_name' => 'Media', 'last_name' => 'Teacher', 'email' => 'media@example.test', 'school' => 'School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Media', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'MEDIA8']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Learner', 'nickname' => 'MediaLearner', 'student_id' => 'MEDIA001', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => $teacher->id]);
        $payload = config('lessons.introduction') + ['revision' => 0, 'status' => 'published'];

        return [$teacher, $student, $payload];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('diagram.png', file_get_contents(public_path('images/branding/app-icon-192.png')));
    }

    public function test_each_explanation_retains_its_private_image_when_reordered_and_can_remove_media(): void
    {
        [$teacher, $student, $payload] = $this->fixture();
        $payload['sections'][0] += ['image' => $this->image(), 'image_alt' => 'Flow from source code to output', 'video_url' => 'https://youtu.be/aqz-KE-bpKQ'];
        $this->post(route('teacher.content.save', 'introduction'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $content = LessonContent::sole();
        $section = $content->definition['sections'][0];
        $path = $section['image_path'];
        $this->assertTrue(Str::isUuid($section['media_key']));
        Storage::disk('local')->assertExists($path);
        $resource = route('teacher.content.section-image', [$content, $section['media_key']]);
        $this->get($resource)->assertOk()->assertHeader('Content-Type', 'image/png');
        $editor = $this->get(route('teacher.content.edit', 'introduction'))->assertOk()->assertSee($resource)->assertSee('Embed a lesson video');
        if ($preview = getenv('CLASSROOM_UPGRADES_PREVIEW')) {
            file_put_contents($preview.'/lesson-editor.html', $editor->getContent());
            copy(public_path('images/branding/app-icon-192.png'), $preview.'/lesson-image.png');
        }

        $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_class_verified' => true]);
        $studentResource = route('student.lesson.section-image', [$content, $section['media_key']]);
        $this->get($studentResource)->assertRedirect();
        foreach ([CourseAssessments::REASONING, CourseAssessments::PRE] as $assessment) {
            DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => $assessment, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        }
        $this->get($studentResource)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $lessonPage = $this->get(route('student.lesson.show', 'introduction'))->assertOk()->assertSee('Flow from source code to output')->assertSee('data-load-lesson-video', false)->assertSee('https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0', false)->assertDontSee('<iframe', false);
        if ($preview = getenv('CLASSROOM_UPGRADES_PREVIEW')) {
            file_put_contents($preview.'/lesson-media.html', $lessonPage->getContent());
        }
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
        unset($payload['sections'][0]['image']);
        $payload['sections'] = array_reverse($content->definition['sections']);
        foreach ($payload['sections'] as &$item) {
            unset($item['image_path'], $item['image_mime']);
        }
        unset($item);
        $payload['revision'] = 1;
        $this->post(route('teacher.content.save', 'introduction'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($section['media_key'], $content->fresh()->definition['sections'][array_key_last($payload['sections'])]['media_key']);
        $this->get($resource)->assertOk();
        $payload['revision'] = 2;
        $payload['sections'][array_key_last($payload['sections'])]['remove_image'] = 1;
        $payload['sections'][array_key_last($payload['sections'])]['video_url'] = '';
        $this->post(route('teacher.content.save', 'introduction'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
        $this->get($resource)->assertNotFound();
    }

    public function test_upload_validation_stale_edits_and_cross_teacher_media_keys_cannot_change_saved_resources(): void
    {
        [$teacher, $student, $payload] = $this->fixture();
        $payload['sections'][0]['image'] = $this->image();
        $this->post(route('teacher.content.save', 'introduction'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $content = LessonContent::sole();
        $before = Storage::disk('local')->allFiles();
        $this->postJson(route('teacher.content.save', 'introduction'), $payload)->assertConflict();
        $this->assertSame($before, Storage::disk('local')->allFiles());
        unset($payload['sections'][0]['image']);
        $payload['revision'] = 1;
        $payload['sections'][0]['media_key'] = (string) Str::uuid();
        $this->postJson(route('teacher.content.save', 'introduction'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sections.0.media_key');
        unset($payload['sections'][0]['media_key']);
        $payload['sections'][0]['image_path'] = 'private-student-file';
        $this->postJson(route('teacher.content.save', 'introduction'), $payload)->assertUnprocessable();
        unset($payload['sections'][0]['image_path']);
        $payload['sections'][0]['image'] = UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->postJson(route('teacher.content.save', 'introduction'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sections.0.image');
        $stranger = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'othermedia@example.test', 'school' => 'Other', 'password' => 'Password123']);
        $this->withSession(['teacher_id' => $stranger->id])->get(route('teacher.content.section-image', [$content, $content->definition['sections'][0]['media_key']]))->assertNotFound();
        $this->assertSame(1, $content->fresh()->revision);
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_only_supported_https_video_links_are_embedded_without_untrusted_parameters(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0', LessonMedia::embedUrl('https://www.youtube.com/watch?v=aqz-KE-bpKQ&autoplay=1'));
        $this->assertSame('https://player.vimeo.com/video/123456789?h=abc123def4&dnt=1', LessonMedia::embedUrl('https://vimeo.com/123456789/abc123def4'));
        foreach (['javascript:alert(1)', 'https://youtube.com.evil.test/watch?v=aqz-KE-bpKQ', 'https://youtube.com@evil.test/watch?v=aqz-KE-bpKQ', 'http://youtu.be/aqz-KE-bpKQ', '<iframe src="https://evil.test"></iframe>', 'https://www.youtube.com/watch?v[]=aqz-KE-bpKQ'] as $url) {
            $this->assertNull(LessonMedia::embedUrl($url));
        }
        [, , $payload] = $this->fixture();
        $payload['sections'][0]['video_url'] = 'https://evil.test/video';
        $this->postJson(route('teacher.content.save', 'introduction'), $payload)->assertUnprocessable()->assertJsonValidationErrors('sections.0.video_url');
    }
}
