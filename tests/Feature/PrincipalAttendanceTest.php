<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrincipalAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-08 04:00:00', 'UTC'));
        $teacher = TeacherAccount::create(['first_name' => 'Our', 'last_name' => 'Teacher', 'email' => 'our-attendance@example.test', 'school' => 'Our School', 'password' => 'Password123']);
        $principal = TeacherAccount::create(['first_name' => 'Principal', 'last_name' => 'Reader', 'email' => 'principal-attendance@example.test', 'school' => 'Our School', 'password' => 'Password123']);
        $principal->forceFill(['role' => 'principal'])->save();
        $foreign = TeacherAccount::create(['first_name' => 'Foreign', 'last_name' => 'Teacher', 'email' => 'foreign-attendance@example.test', 'school' => 'Other School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++ AM', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'OURCODE']);
        $foreignRoom = Classroom::create(['teacher_account_id' => $foreign->id, 'name' => 'Foreign attendance class', 'grade' => 'Grade 9', 'section' => 'PRIVATE', 'join_code' => 'FOREIGNCODE']);
        $session = $this->createAttendanceSession($room, '2026-11-08', 'C++ attendance register');
        $foreignSession = $this->createAttendanceSession($foreignRoom, '2026-11-08', 'Foreign private register');
        $students = collect(['present', 'late', 'absent', 'excused', 'unmarked'])->map(function ($status, $index) use ($room, $session): Student {
            $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'PRIVATE-FULL-NAME', 'nickname' => 'AttendanceCadet'.$index, 'student_id' => 'PRIVATE-SCHOOL-ID-'.$index, 'password' => 'Password123', 'leaderboard_visible' => $index !== 1]);
            DB::table('attendance_entries')->insert(['attendance_session_id' => $session, 'student_id' => $student->id, 'student_name' => 'PRIVATE-SNAPSHOT-NAME', 'school_id' => $student->student_id, 'status' => $status, 'note' => 'PRIVATE-MEDICAL-NOTE', 'method' => 'Selfie reviewed', 'checked_in_at' => $status === 'present' || $status === 'late' ? now() : null, 'late_minutes' => $status === 'late' ? 5 : 0, 'created_at' => now(), 'updated_at' => now()]);

            return $student;
        });
        DB::table('attendance_entries')->insert(['attendance_session_id' => $foreignSession, 'student_name' => 'PRIVATE-FOREIGN-STUDENT', 'school_id' => 'FOREIGN-ID', 'status' => 'present', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession(['teacher_id' => $principal->id, 'teacher_session_version' => 1]);

        return compact('teacher', 'principal', 'foreign', 'room', 'foreignRoom', 'session', 'foreignSession', 'students');
    }

    private function createAttendanceSession(Classroom $room, string $date, string $title): int
    {
        return DB::table('attendance_sessions')->insertGetId(['teacher_account_id' => $room->teacher_account_id, 'classroom_id' => $room->id, 'title' => $title, 'session_date' => $date,
            'starts_at' => $date.' 00:00:00', 'ends_at' => $date.' 01:00:00', 'checkin_opens_at' => $date.' 00:00:00', 'checkin_closes_at' => $date.' 00:30:00', 'checkin_code' => 'PRIVATE-CODE', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_principal_sees_school_attendance_totals_and_private_safe_register_without_writes(): void
    {
        extract($this->fixture());
        DB::enableQueryLog();
        $page = $this->get(route('school.overview', ['tab' => 'attendance', 'session' => $session]))->assertOk()->assertSee('School attendance overview')->assertSee('Read-only register')->assertSee('Draft · provisional')
            ->assertSee('AttendanceCadet0')->assertSee($students[1]->rankingAlias())->assertDontSee('AttendanceCadet1')->assertViewHas('attendanceRate', 66.7);
        $this->assertSame(['unmarked' => 1, 'present' => 1, 'late' => 1, 'absent' => 1, 'excused' => 1], $page->viewData('attendanceCounts')->all());
        $this->assertSame(1, $page->viewData('attendanceSessions')->total());
        $this->assertSame(5, $page->viewData('attendanceRegister')->total());
        foreach (['PRIVATE-FULL-NAME', 'PRIVATE-SCHOOL-ID', 'PRIVATE-SNAPSHOT-NAME', 'PRIVATE-MEDICAL-NOTE', 'PRIVATE-CODE', 'Foreign private register', '/attendance-photo/'] as $private) {
            $page->assertDontSee($private);
        }
        $this->assertDoesNotMatchRegularExpression('/<form\b[^>]*method="POST"/i', $page->getContent());
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $query['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        DB::table('attendance_sessions')->where('id', $session)->update(['finalized_at' => now()]);
        $this->get(route('school.overview', ['tab' => 'attendance']))->assertOk()->assertSee('Finalized');
        if ($preview = getenv('CLASSROOM_PREVIEW')) {
            file_put_contents($preview.'/principal-attendance.html', $page->getContent());
        }
    }

    public function test_attendance_filters_block_foreign_archived_and_invalid_requests_and_remain_read_only(): void
    {
        extract($this->fixture());
        $this->get(route('school.overview', ['tab' => 'attendance', 'session' => $foreignSession]))->assertNotFound();
        $this->get(route('school.overview', ['tab' => 'attendance', 'classroom' => $foreignRoom->id]))->assertNotFound();
        $this->get(route('school.overview', ['tab' => 'attendance', 'from' => '2026-11-01', 'to' => '2026-11-07']))->assertOk()->assertViewHas('attendanceRate', null)->assertViewHas('attendanceSessions', fn ($rows) => $rows->total() === 0);
        $this->getJson(route('school.overview', ['tab' => 'attendance', 'from' => '2026-11-08', 'to' => '2026-11-01']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('school.overview', ['tab' => 'attendance', 'session' => '1 OR 1=1']))->assertUnprocessable();
        $this->postJson(route('teacher.attendance.action', $session), ['action' => 'mark', 'revision' => 1, 'status' => 'present', 'entries' => [1]])->assertForbidden();
        $room->update(['archived_at' => now()]);
        $this->get(route('school.overview', ['tab' => 'attendance', 'session' => $session]))->assertNotFound();
        $this->get(route('school.overview', ['tab' => 'attendance']))->assertOk()->assertViewHas('attendanceSessions', fn ($rows) => $rows->total() === 0);
        $this->withSession(['teacher_id' => $teacher->id])->get(route('school.overview', ['tab' => 'attendance']))->assertForbidden();
    }

    public function test_historical_statuses_remain_correct_when_a_student_leaves_and_unmarked_is_not_absent(): void
    {
        extract($this->fixture());
        $students[0]->delete();
        $page = $this->get(route('school.overview', ['tab' => 'attendance', 'session' => $session]))->assertOk()->assertSee('Former learner')->assertDontSee('AttendanceCadet0')->assertViewHas('attendanceRate', 66.7);
        $this->assertSame(1, $page->viewData('attendanceCounts')['present']);
        DB::table('attendance_entries')->where('attendance_session_id', $session)->update(['status' => 'unmarked']);
        $this->get(route('school.overview', ['tab' => 'attendance']))->assertOk()->assertViewHas('attendanceRate', null)->assertViewHas('attendanceCounts', fn ($counts) => $counts['absent'] === 0 && $counts['unmarked'] === 5);
    }
}
