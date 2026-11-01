<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-01 00:20:00', 'UTC'));
        $teacher = TeacherAccount::create(['first_name' => 'Attendance', 'last_name' => 'Teacher', 'email' => 'attendance@example.test', 'school' => 'School', 'password' => 'Password123']);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-attendance@example.test', 'school' => 'Other', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'grade' => 'Grade 8', 'section' => 'AM', 'name' => 'C++ AM', 'join_code' => 'ATTAM']);
        $foreign = Classroom::create(['teacher_account_id' => $other->id, 'grade' => 'Grade 8', 'section' => 'DB', 'name' => 'Private DB', 'join_code' => 'ATTDB']);
        $students = collect(range(1, 2))->map(fn ($i) => Student::create(['classroom_id' => $room->id, 'first_name' => 'Student '.$i, 'surname' => 'Coder', 'nickname' => 'AttendanceCoder'.$i, 'student_id' => 'ATT'.$i, 'password' => 'Password123', 'xp' => 100]));
        $stranger = Student::create(['classroom_id' => $foreign->id, 'first_name' => 'Private', 'surname' => 'Learner', 'nickname' => 'PrivateLearner', 'student_id' => 'PRIVATEATT', 'password' => 'Password123']);
        $payload = ['classroom_id' => $room->id, 'session_date' => '2026-11-01', 'title' => 'C++ Variables and Data Types', 'start_time' => '08:30', 'end_time' => '09:45', 'open_time' => '08:25', 'close_time' => '08:45'];
        $this->withSession(['teacher_id' => $teacher->id]);

        return compact('teacher', 'other', 'room', 'foreign', 'students', 'stranger', 'payload');
    }

    private function createSession(array $payload): object
    {
        $this->postJson(route('teacher.attendance.store'), $payload)->assertOk();

        return DB::table('attendance_sessions')->latest('id')->first();
    }

    private function host(object $session, string $action, array $extra = [])
    {
        return $this->withSession(['teacher_id' => $session->teacher_account_id, 'student_id' => null])->postJson(route('teacher.attendance.action', $session->id),
            ['action' => $action, 'revision' => DB::table('attendance_sessions')->find($session->id)->revision, ...$extra]);
    }

    private function studentAction(object $session, Student $student, array $data)
    {
        return $this->withSession(['teacher_id' => null, 'student_id' => $student->id])->postJson(route('student.attendance.action', $session->id), $data);
    }

    public function test_access_is_limited_to_teachers_own_sections_and_students_own_records(): void
    {
        $this->get(route('teacher.attendance'))->assertRedirect(route('teacher.login'));
        $this->get(route('student.attendance'))->assertRedirect(route('student.login'));
        extract($this->fixture());
        $session = $this->createSession($payload);
        $page = $this->get(route('teacher.attendance'))->assertOk()->assertSee('Class Attendance')->assertSee($students[0]->first_name)->assertDontSee('PRIVATEATT');
        if (getenv('ATTENDANCE_PREVIEW')) {
            file_put_contents(base_path('.codex/attendance-preview.html'), $page->getContent());
        }
        $this->withSession(['teacher_id' => $other->id])->get(route('teacher.attendance', ['classroom' => $room->id]))->assertNotFound();
        $this->postJson(route('teacher.attendance.action', $session->id), ['action' => 'open', 'revision' => 1])->assertNotFound();
        $other->forceFill(['role' => 'principal'])->save();
        $this->get(route('teacher.attendance'))->assertForbidden();
        $this->studentAction($session, $stranger, ['action' => 'checkin', 'code' => $session->checkin_code])->assertNotFound();
        $this->withSession(['student_id' => $students[0]->id])->get(route('student.attendance'))->assertOk()->assertSee('My Attendance')->assertDontSee($students[1]->student_id)->assertDontSee($session->checkin_code);
    }

    public function test_session_creation_saves_approved_roster_and_timezone_and_rejects_duplicate_or_invalid_schedule(): void
    {
        extract($this->fixture());
        $students[1]->forceFill(['registration_status' => 'pending'])->save();
        foreach ([['end_time' => '08:00'], ['open_time' => '08:40'], ['close_time' => '10:00'], ['close_time' => '08:25']] as $change) {
            $this->postJson(route('teacher.attendance.store'), [...$payload, ...$change])->assertUnprocessable();
        }
        $this->postJson(route('teacher.attendance.store'), [...$payload, 'classroom_id' => $foreign->id])->assertNotFound();
        $session = $this->createSession($payload);
        $this->assertSame('2026-11-01 00:30:00', $session->starts_at);
        $this->assertSame(0, (int) $session->checkin_enabled);
        $this->assertDatabaseCount('attendance_entries', 1);
        $this->postJson(route('teacher.attendance.store'), $payload)->assertConflict();
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->getJson(route('teacher.attendance.status'))->assertOk()->assertJsonPath('revision', 1);
    }

    public function test_checkin_is_server_timed_present_or_late_and_cannot_overwrite_manual_status(): void
    {
        extract($this->fixture());
        $session = $this->createSession($payload);
        $this->studentAction($session, $students[0], ['action' => 'checkin', 'code' => $session->checkin_code])->assertUnprocessable();
        $this->host($session, 'open')->assertOk();
        $this->studentAction($session, $students[0], ['action' => 'checkin', 'code' => $session->checkin_code])->assertUnprocessable();
        $this->travelTo(Carbon::parse('2026-11-01 00:30:00', 'UTC'));
        $this->studentAction($session, $students[0], ['action' => 'checkin', 'code' => 'BADCODE1'])->assertUnprocessable();
        $this->studentAction($session, $students[0], ['action' => 'checkin', 'code' => strtolower($session->checkin_code), 'status' => 'excused', 'checked_in_at' => '2000-01-01'])->assertRedirect();
        $this->studentAction($session, $students[0], ['action' => 'checkin', 'code' => $session->checkin_code])->assertRedirect();
        $this->assertDatabaseHas('attendance_entries', ['student_id' => $students[0]->id, 'status' => 'present', 'checked_in_at' => '2026-11-01 00:30:00']);
        $this->travelTo(Carbon::parse('2026-11-01 00:31:01', 'UTC'));
        $this->studentAction($session, $students[1], ['action' => 'checkin', 'code' => $session->checkin_code])->assertRedirect();
        $this->assertDatabaseHas('attendance_entries', ['student_id' => $students[1]->id, 'status' => 'late', 'late_minutes' => 2]);
        $entry = DB::table('attendance_entries')->where('student_id', $students[1]->id)->first();
        $this->host($session, 'mark', ['entries' => [$entry->id], 'status' => 'excused'])->assertOk();
        $this->studentAction($session, $students[1], ['action' => 'checkin', 'code' => $session->checkin_code])->assertRedirect();
        $this->assertDatabaseHas('attendance_entries', ['id' => $entry->id, 'status' => 'excused']);
        $this->travelTo(Carbon::parse('2026-11-01 00:45:00', 'UTC'));
        $this->studentAction($session, $students[1], ['action' => 'checkin', 'code' => $session->checkin_code])->assertUnprocessable();
        $this->host($session, 'open')->assertUnprocessable();
        $this->assertSame(100, $students[0]->fresh()->xp);
    }

    public function test_manual_bulk_changes_reject_stale_revisions_and_foreign_entries_atomically(): void
    {
        extract($this->fixture());
        $session = $this->createSession($payload);
        $ids = DB::table('attendance_entries')->pluck('id')->all();
        $this->host($session, 'mark', ['entries' => $ids, 'status' => 'present', 'note' => 'Teacher confirmed in class'])->assertOk();
        $this->assertSame(2, DB::table('attendance_entries')->where('status', 'present')->count());
        $this->host($session, 'mark', ['revision' => 1, 'entries' => $ids, 'status' => 'absent'])->assertConflict();
        $this->host($session, 'mark', ['entries' => [$ids[0], 9999], 'status' => 'absent'])->assertNotFound();
        $this->host($session, 'mark', ['entries' => $ids, 'status' => 'invalid'])->assertUnprocessable();
        $this->assertSame(2, DB::table('attendance_entries')->where('status', 'present')->count());
        $this->assertSame(2, DB::table('attendance_events')->where('action', 'marked')->count());
    }

    public function test_private_corrections_require_teacher_review_and_block_finalization(): void
    {
        extract($this->fixture());
        $session = $this->createSession($payload);
        $reason = '<script>alert(1)</script> I was attending class before check-in closed.';
        $this->studentAction($session, $students[0], ['action' => 'correction', 'requested_status' => 'present', 'reason' => $reason])->assertRedirect();
        $this->studentAction($session, $students[0], ['action' => 'correction', 'requested_status' => 'present', 'reason' => $reason])->assertConflict();
        $this->withSession(['student_id' => $students[1]->id])->get(route('student.attendance'))->assertOk()->assertDontSee($reason);
        $this->host($session, 'finalize', ['auto_absent' => true])->assertUnprocessable();
        $this->get(route('teacher.attendance'))->assertOk()->assertSee($reason)->assertDontSee($reason, false);
        $correction = DB::table('attendance_requests')->first();
        $this->host($session, 'review', ['request_id' => $correction->id, 'decision' => 'approved', 'note' => 'Confirmed by teacher'])->assertOk();
        $this->assertDatabaseHas('attendance_entries', ['student_id' => $students[0]->id, 'status' => 'present']);
        $this->assertDatabaseHas('attendance_requests', ['id' => $correction->id, 'decision' => 'approved', 'reviewed_by' => $teacher->id]);
        $this->host($session, 'review', ['request_id' => $correction->id, 'decision' => 'declined'])->assertConflict();
        $this->withSession(['teacher_id' => null, 'student_id' => $students[0]->id])->get(route('student.attendance'))->assertOk()->assertSee('Confirmed by teacher');
    }

    public function test_finalization_needs_explicit_absent_choice_and_reopening_is_audited(): void
    {
        extract($this->fixture());
        $session = $this->createSession($payload);
        $this->host($session, 'finalize')->assertUnprocessable();
        $this->host($session, 'finalize', ['auto_absent' => true])->assertOk();
        $this->assertSame(2, DB::table('attendance_entries')->where('status', 'absent')->count());
        $this->assertNotNull(DB::table('attendance_sessions')->find($session->id)->finalized_at);
        $this->host($session, 'mark', ['entries' => DB::table('attendance_entries')->pluck('id')->all(), 'status' => 'present'])->assertConflict();
        $this->studentAction($session, $students[0], ['action' => 'correction', 'requested_status' => 'present', 'reason' => 'I attended this class.'])->assertConflict();
        $this->host($session, 'reopen')->assertUnprocessable();
        $this->host($session, 'reopen', ['note' => 'Rechecking the signed class roll'])->assertOk();
        $this->assertDatabaseHas('attendance_events', ['action' => 'reopened', 'note' => 'Rechecking the signed class roll']);
        $this->assertNull(DB::table('attendance_sessions')->find($session->id)->finalized_at);
        $this->assertSame(0, (int) DB::table('attendance_sessions')->find($session->id)->checkin_enabled);
    }

    public function test_roster_sync_retains_saved_records_and_archived_sessions_are_read_only(): void
    {
        extract($this->fixture());
        $session = $this->createSession($payload);
        $entry = DB::table('attendance_entries')->first();
        $this->host($session, 'mark', ['entries' => [$entry->id], 'status' => 'present'])->assertOk();
        $new = Student::create(['classroom_id' => $room->id, 'first_name' => 'New', 'nickname' => 'NewCoder', 'student_id' => 'NEWATT', 'password' => 'Password123']);
        $this->host($session, 'sync')->assertOk();
        $this->host($session, 'sync')->assertOk();
        $this->assertDatabaseCount('attendance_entries', 3);
        $this->assertDatabaseHas('attendance_entries', ['id' => $entry->id, 'status' => 'present']);
        $students[0]->delete();
        $this->assertDatabaseHas('attendance_entries', ['id' => $entry->id, 'student_name' => 'Student 1 Coder']);
        $room->update(['archived_at' => now()]);
        $this->host($session, 'sync')->assertConflict();
        $this->get(route('teacher.attendance', ['classroom' => $room->id]))->assertOk()->assertSee('Archived');
        $this->get(route('teacher.attendance.export', ['classroom' => $room->id]))->assertOk();
    }

    public function test_exports_use_real_records_and_protect_formula_cells_and_private_notes(): void
    {
        extract($this->fixture());
        $students[0]->update(['first_name' => '=2+2', 'student_id' => '+FORMULA']);
        $session = $this->createSession($payload);
        $this->host($session, 'mark', ['entries' => DB::table('attendance_entries')->pluck('id')->all(), 'status' => 'excused', 'note' => 'PRIVATE TEACHER NOTE'])->assertOk();
        $response = $this->get(route('teacher.attendance.export', ['classroom' => $room->id]));
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringContainsString("'+FORMULA", $csv);
        $this->assertStringContainsString('Excused', $csv);
        $this->assertStringNotContainsString('PRIVATE TEACHER NOTE', $csv);
        $this->assertStringNotContainsString($session->checkin_code, $csv);
        $this->assertStringNotContainsString('PRIVATEATT', $csv);
    }

    public function test_student_form_errors_return_to_the_attendance_page_with_a_clear_message(): void
    {
        extract($this->fixture());
        $session = $this->createSession($payload);
        $this->withSession(['teacher_id' => null, 'student_id' => $students[0]->id])->post(route('student.attendance.action', $session->id), ['action' => 'checkin', 'code' => 'BADCODE1'])
            ->assertRedirect(route('student.attendance'))->assertSessionHasErrors(['attendance' => 'Check-in is not open. Ask your teacher to record your attendance.']);
    }
}
