<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementXpHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $teacher = TeacherAccount::create(['first_name' => 'History', 'last_name' => 'Teacher', 'email' => 'history@example.test', 'school' => 'School', 'password' => 'Password123']);
        $other = TeacherAccount::create(['first_name' => 'Other', 'last_name' => 'Teacher', 'email' => 'other-history@example.test', 'school' => 'School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'XPHISTORY']);
        $student = Student::create(['classroom_id' => $room->id, 'first_name' => 'Test', 'nickname' => 'HistoryCoder', 'student_id' => 'HISTORY01', 'password' => 'Password123', 'xp' => 100]);
        $ledger = app(XpLedger::class);
        $ledger->change($student->id, 'history:lesson', 25, ['type' => 'lesson', 'title' => 'Variables checkpoint', 'reason' => 'First completion', 'details' => ['All checkpoint answers correct: 25 XP earned.']]);
        $ledger->change($student->id, 'history:recitation', -5, ['type' => 'recitation', 'title' => 'Output recitation', 'reason' => 'Incorrect recitation answer', 'details' => ['One wrong submitted answer × 5 XP.']]);

        return [$teacher, $other, $student];
    }

    public function test_student_achievements_display_real_earnings_deductions_and_balances_with_working_filters(): void
    {
        [, , $student] = $this->fixture();
        $this->withSession(['student_id' => $student->id, 'student_session_version' => 1]);
        $page = $this->get(route('student.achievements'))->assertOk()->assertSee('XP history')->assertSee('120 XP')->assertSee('Variables checkpoint')->assertSee('Output recitation')->assertSee('−5 XP')->assertSee('+25 XP')->assertSee('125 XP');
        $this->assertSame(120, $page->viewData('xpHistory')['balance']);
        $this->assertSame(125, $page->viewData('xpHistory')['earned']);
        $this->assertSame(5, $page->viewData('xpHistory')['deducted']);
        $this->assertSame(3, $page->viewData('xpHistory')['events']->total());
        $this->get(route('student.achievements', ['xp_filter' => 'earned']))->assertOk()->assertSee('Variables checkpoint')->assertDontSee('Output recitation');
        $this->get(route('student.achievements', ['xp_filter' => 'deducted']))->assertOk()->assertSee('Output recitation')->assertDontSee('Variables checkpoint');
        $this->getJson(route('student.achievements', ['xp_page' => -1]))->assertUnprocessable();
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        if ($preview = getenv('XP_PREVIEW')) {
            file_put_contents($preview.'/student-xp-history.html', $page->getContent());
        }
    }

    public function test_teachers_can_open_only_their_own_active_students_xp_and_principals_and_other_students_cannot(): void
    {
        [$teacher, $other, $student] = $this->fixture();
        $url = route('teacher.achievements.xp', $student->id);
        $this->get($url)->assertForbidden();
        $this->withSession(['teacher_id' => $teacher->id])->get(route('teacher.achievements'))->assertOk()->assertSee($url, false)->assertSee('120 XP');
        $page = $this->get($url)->assertOk()->assertSee('HistoryCoder')->assertSee('Variables checkpoint')->assertSee('Output recitation')->assertSee('125 XP');
        $this->withSession(['teacher_id' => $other->id])->get($url)->assertNotFound();
        $other->forceFill(['role' => 'principal'])->save();
        $this->get($url)->assertForbidden();
        $peer = Student::create(['classroom_id' => $student->classroom_id, 'first_name' => 'Peer', 'nickname' => 'PrivatePeer', 'student_id' => 'PEERXP', 'password' => 'Password123', 'xp' => 100]);
        $this->withSession(['teacher_id' => null, 'student_id' => $peer->id])->get($url)->assertForbidden();
        $this->get(route('student.achievements', ['student_id' => $student->id]))->assertOk()->assertDontSee('Variables checkpoint')->assertDontSee('Output recitation');
        $student->classroom->update(['archived_at' => now()]);
        $this->withSession(['student_id' => null, 'teacher_id' => $teacher->id])->get($url)->assertNotFound();
        if ($preview = getenv('XP_PREVIEW')) {
            file_put_contents($preview.'/teacher-xp-history.html', $page->getContent());
        }
    }

    public function test_zero_balance_receipts_legacy_details_pagination_and_untrusted_titles_are_displayed_honestly(): void
    {
        [, , $student] = $this->fixture();
        $ledger = app(XpLedger::class);
        $ledger->change($student->id, 'floor', -150, ['type' => 'quiz', 'title' => '<script>unsafe</script>', 'reason' => 'Incorrect answers', 'details' => ['30 wrong × 5 XP.']]);
        DB::table('xp_events')->insert(['student_id' => $student->id, 'amount' => 3, 'reason' => 'Old learning activity', 'counts_for_period' => false, 'created_at' => now()->subDay()]);
        $this->withSession(['student_id' => $student->id]);
        $this->get(route('student.achievements'))->assertOk()->assertSee('<script>unsafe</script>')->assertDontSee('<script>unsafe</script>', false)->assertSee('150 XP requested; 120 XP applied')->assertSee('Historical entry')->assertSee('Not recorded');
        $this->assertSame(0, $student->fresh()->xp);
        for ($index = 0; $index < 16; $index++) {
            $ledger->change($student->id, 'page:'.$index, 1, ['type' => 'practice', 'title' => 'New practice '.$index, 'reason' => 'Tests passed']);
        }
        $this->get(route('student.achievements', ['xp_page' => 2]))->assertOk()->assertViewHas('xpHistory', fn ($history) => $history['events']->currentPage() === 2 && $history['events']->total() === 21)->assertSee('Old learning activity');
    }
}
