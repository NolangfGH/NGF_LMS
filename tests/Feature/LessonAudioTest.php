<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\CourseAssessments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LessonAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_courses_offer_audio_for_explanations_without_reading_forms_code_or_checkpoint_answers(): void
    {
        $teacher = TeacherAccount::create(['first_name' => 'Audio', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'audio@example.test', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'Programming', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'AUDIO001']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Reader', 'nickname' => 'AudioCadet', 'student_id' => 'AUDIO001', 'password' => 'Password123', 'xp' => 100]);
        $this->withSession(['student_id' => $student->id]);
        $this->get(route('student.lesson.show', 'introduction'))->assertRedirect(route('student.assessments.show', 'reasoning'));
        $this->recordCompletedLessonPrerequisites($student);
        DB::table('course_assessment_results')->insert(['student_id' => $student->id, 'assessment' => CourseAssessments::ARDUINO_PRE, 'score' => 0, 'total_questions' => 50, 'skill_scores' => '{}', 'completed_at' => now()]);
        foreach (['introduction', 'arduino-robotics'] as $key) {
            $page = $this->get(route('student.lesson.show', $key))->assertOk()->assertSee('Listen to this lesson')->assertSee('Word by word')->assertSee('lesson-audio.js');
            $document = new \DOMDocument;
            @$document->loadHTML($page->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertGreaterThanOrEqual(7, $xpath->query('//*[@data-lesson-speech]')->length);
            $this->assertSame(0, $xpath->query('//form//*[@data-lesson-speech]')->length);
            $this->assertSame(0, $xpath->query('//pre//*[@data-lesson-speech]')->length);
            if ($preview = getenv('LESSON_AUDIO_PREVIEW')) {
                file_put_contents($preview.'/'.$key.'-audio.html', $page->getContent());
            }
        }
        $this->assertSame(100, $student->fresh()->xp);
    }
}
