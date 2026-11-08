<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendancePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function classroom(): array
    {
        $this->travelTo(Carbon::parse('2026-11-08 08:31:00', 'Asia/Singapore'));
        $teacher = TeacherAccount::create(['first_name' => 'Photo', 'last_name' => 'Teacher', 'email' => fake()->unique()->safeEmail(), 'school' => 'School', 'password' => 'password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => Str::upper(Str::random(8))]);
        $students = collect(range(1, 2))->map(fn ($i) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Photo '.$i, 'nickname' => 'CameraCoder'.$i, 'student_id' => Str::uuid()->toString(), 'password' => 'password123', 'xp' => 100]));
        $id = DB::table('attendance_sessions')->insertGetId(['teacher_account_id' => $teacher->id, 'classroom_id' => $room->id, 'session_date' => '2026-11-08', 'title' => 'C++ camera check-in', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'checkin_opens_at' => now()->subMinutes(5), 'checkin_closes_at' => now()->addMinutes(10), 'checkin_code' => 'SELF1234', 'checkin_enabled' => true, 'photo_required' => true, 'created_at' => now(), 'updated_at' => now()]);
        $session = DB::table('attendance_sessions')->find($id);
        app(AttendanceService::class)->sync($session);

        return [$teacher, $room, $students, $session];
    }

    private function asStudent(Student $student): self
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id, 'student_session_version' => 1]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('selfie.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1cAAAAASUVORK5CYII='));
    }

    public function test_student_photo_stays_pending_until_teacher_reviews_and_preserves_checkin_time(): void
    {
        [$teacher, , $students, $session] = $this->classroom();
        $studentPage = $this->asStudent($students[0])->get(route('student.attendance'))->assertOk()->assertSee('Take or choose a selfie')->assertSee('capture="user"', false);
        $this->post(route('student.attendance.photo', $session->id), ['code' => 'SELF1234', 'consent' => 1, 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertOk()->assertDontSee('photo_path');
        $photo = DB::table('attendance_photo_requests')->first();
        $entry = DB::table('attendance_entries')->where('student_id', $students[0]->id)->first();
        $this->assertSame('pending', $photo->status);
        $this->assertSame('unmarked', $entry->status);
        $this->assertNull($entry->checked_in_at);
        Storage::disk('local')->assertExists($photo->photo_path);
        $this->get(route('attendance.photos.image', $photo->id))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->asStudent($students[1])->get(route('attendance.photos.image', $photo->id))->assertForbidden();
        $this->travel(5)->minutes();
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null]);
        $reviewPage = $this->get(route('teacher.attendance.photos', $session->id))->assertOk()->assertSee('Approve Present');
        $this->post(route('teacher.attendance.photos.review', $photo->id), ['decision' => 'late', 'note' => 'Verified during class.'])->assertRedirect();
        $updated = DB::table('attendance_entries')->find($entry->id);
        $this->assertSame('late', $updated->status);
        $this->assertSame('Selfie reviewed', $updated->method);
        $this->assertSame($photo->created_at, $updated->checked_in_at);
        $this->assertSame(1, $updated->late_minutes);
        $this->postJson(route('teacher.attendance.photos.review', $photo->id), ['decision' => 'present'])->assertConflict();
        if ($preview = getenv('CLASSROOM_PREVIEW')) {
            file_put_contents($preview.'/selfie-student.html', $studentPage->getContent());
            file_put_contents($preview.'/selfie-teacher.html', $reviewPage->getContent());
        }
    }

    public function test_passcode_consent_window_duplicates_and_other_teachers_are_enforced(): void
    {
        [$teacher, , $students, $session] = $this->classroom();
        $this->asStudent($students[0])->postJson(route('student.attendance.action', $session->id), ['action' => 'checkin', 'code' => 'SELF1234'])->assertUnprocessable();
        $this->post(route('student.attendance.photo', $session->id), ['code' => 'WRONG123', 'consent' => 1, 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post(route('student.attendance.photo', $session->id), ['code' => 'SELF1234', 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_photo_requests', 0);
        $this->post(route('student.attendance.photo', $session->id), ['code' => 'SELF1234', 'consent' => 1, 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $this->post(route('student.attendance.photo', $session->id), ['code' => 'SELF1234', 'consent' => 1, 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertConflict();
        $photo = DB::table('attendance_photo_requests')->first();
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->postJson(route('teacher.attendance.photo-mode', $session->id), ['photo_required' => false])->assertConflict();
        [$other] = $this->classroom();
        $this->withSession(['teacher_id' => $other->id])->postJson(route('teacher.attendance.photos.review', $photo->id), ['decision' => 'present'])->assertForbidden();
        $this->get(route('attendance.photos.image', $photo->id))->assertForbidden();
        $this->get(route('teacher.attendance.photos', $session->id))->assertNotFound();
        $this->withSession(['teacher_id' => $teacher->id])->post(route('teacher.attendance.photos.review', $photo->id), ['decision' => 'rejected'])->assertRedirect();
        $this->assertDatabaseHas('attendance_photo_requests', ['id' => $photo->id, 'status' => 'rejected']);
        DB::table('attendance_sessions')->where('id', $session->id)->update(['checkin_closes_at' => now()->subSecond()]);
        $this->asStudent($students[0])->post(route('student.attendance.photo', $session->id), ['code' => 'SELF1234', 'consent' => 1, 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_teacher_mode_can_be_changed_but_finalized_or_manually_marked_records_cannot_be_overwritten(): void
    {
        [$teacher, , $students, $session] = $this->classroom();
        $this->asStudent($students[0])->post(route('student.attendance.photo', $session->id), ['code' => 'SELF1234', 'consent' => 1, 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $photo = DB::table('attendance_photo_requests')->first();
        DB::table('attendance_entries')->where('id', $photo->attendance_entry_id)->update(['status' => 'absent']);
        $this->withSession(['teacher_id' => $teacher->id, 'student_id' => null])->postJson(route('teacher.attendance.photos.review', $photo->id), ['decision' => 'present'])->assertConflict();
        $this->post(route('teacher.attendance.photos.review', $photo->id), ['decision' => 'rejected'])->assertRedirect();
        $this->post(route('teacher.attendance.photo-mode', $session->id), ['photo_required' => false])->assertRedirect();
        $this->assertDatabaseHas('attendance_sessions', ['id' => $session->id, 'photo_required' => false]);
        DB::table('attendance_sessions')->where('id', $session->id)->update(['finalized_at' => now()]);
        $this->postJson(route('teacher.attendance.photo-mode', $session->id), ['photo_required' => true])->assertConflict();
        $this->assertDatabaseHas('attendance_entries', ['id' => $photo->attendance_entry_id, 'status' => 'absent']);
    }
}
