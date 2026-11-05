<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_submit_text_or_an_image_and_only_the_author_or_admin_can_open_it(): void
    {
        Storage::fake('local');
        $teacher = TeacherAccount::create(['first_name' => 'Class', 'last_name' => 'Teacher', 'school' => 'School', 'email' => 'feedback@example.test', 'password' => 'Password123']);
        $admin = $teacher->replicate()->forceFill(['email' => 'admin@example.test', 'role' => 'school_admin']);
        $admin->save();
        $principal = $teacher->replicate()->forceFill(['email' => 'principal@example.test', 'role' => 'principal']);
        $principal->save();
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'join_code' => 'FEEDBACK']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Coder', 'nickname' => 'Cadet', 'student_id' => 'FEEDBACK001', 'password' => 'Password123']);
        $peer = Student::create(['classroom_id' => $room->id, 'first_name' => 'Other', 'nickname' => 'Peer', 'student_id' => 'FEEDBACK002', 'password' => 'Password123']);
        $about = route('system.about');
        $store = route('system.feedback.store');
        $inbox = route('school.feedback');
        $this->get($about)->assertOk()->assertSee('Sign in to your student or staff account');
        $this->postJson($store, ['category' => 'suggestion', 'message' => 'Guest'])->assertForbidden();
        $this->withSession(['student_id' => $student->id]);
        $this->postJson($store, ['category' => 'issue'])->assertUnprocessable()->assertJsonValidationErrors(['message', 'image']);
        $this->postJson($store, ['category' => 'issue', 'image' => UploadedFile::fake()->createWithContent('fake.png', '<html>invalid</html>')])->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $screenshot = UploadedFile::fake()->createWithContent('example.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->post($store, ['category' => 'issue', 'image' => $screenshot, 'student_id' => $peer->id])->assertRedirect($about.'#feedback');
        $entry = DB::table('system_feedback')->first();
        $this->assertSame($student->id, $entry->student_id);
        $this->assertNull($entry->message);
        Storage::disk('local')->assertExists($entry->image_path);
        $image = route('system.feedback.image', $entry->id);
        $this->get($about)->assertOk()->assertSee($image);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->withSession(['student_id' => $peer->id])->get($image)->assertNotFound();
        $this->get($about)->assertOk()->assertDontSee($image);
        $this->get($inbox)->assertForbidden();
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get($inbox)->assertForbidden();
        $this->get($image)->assertNotFound();
        $this->withSession(['teacher_id' => $principal->id])->post($store, ['category' => 'suggestion', 'message' => 'Add a print option for school summaries.'])
            ->assertRedirect($about.'#feedback');
        $this->get($inbox)->assertForbidden();

        $this->withSession(['teacher_id' => $admin->id])->get($inbox)->assertOk()->assertSee($image)->assertSee('Add a print option for school summaries.');
        $this->get(route('school.feedback', ['category' => 'suggestion']))->assertOk()->assertDontSee($image);
        $this->get($image)->assertOk();
        $this->withSession(['teacher_id' => null])->get($image)->assertForbidden();
        $this->get($about)->assertOk()->assertDontSee('Add a print option for school summaries.');
    }
}
