<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\XpLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class XpLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        $teacher = TeacherAccount::create(['first_name' => 'XP', 'last_name' => 'Teacher', 'email' => 'ledger@example.test', 'school' => 'School', 'password' => 'Password123']);
        $room = Classroom::create(['teacher_account_id' => $teacher->id, 'name' => 'C++', 'grade' => 'Grade 8', 'section' => 'AM', 'join_code' => 'LEDGER']);

        return Student::create(['classroom_id' => $room->id, 'first_name' => 'Test', 'nickname' => 'LedgerCoder', 'student_id' => 'LEDGER01', 'password' => 'Password123', 'xp' => 599]);
    }

    public function test_signed_changes_are_recorded_once_with_real_balances_and_levels_never_go_negative(): void
    {
        $student = $this->student();
        $ledger = app(XpLedger::class);
        $context = ['type' => 'practice', 'id' => 12, 'title' => 'Loop practice', 'reason' => 'Tests passed', 'details' => ['First completion: 10 XP.']];
        $this->assertSame(10, $ledger->change($student->id, 'practice:12', 10, $context));
        $this->assertSame(609, $student->fresh()->xp);
        $this->assertSame(2, $student->fresh()->level);
        $this->assertSame(0, $ledger->change($student->id, 'practice:12', 10, $context));
        $this->assertDatabaseCount('xp_events', 2);
        $this->assertSame(-609, $ledger->change($student->id, 'penalty:1', -700, [...$context, 'reason' => 'Wrong answers']));
        $this->assertSame(0, $student->fresh()->xp);
        $this->assertSame(1, $student->fresh()->level);
        $this->assertSame(0, $ledger->change($student->id, 'penalty:2', -5, $context));
        $this->assertDatabaseHas('xp_events', ['event_key' => 'penalty:1', 'requested_amount' => -700, 'amount' => -609, 'balance_after' => 0]);
        $this->assertDatabaseHas('xp_events', ['event_key' => 'penalty:2', 'requested_amount' => -5, 'amount' => 0, 'balance_after' => 0]);
        $this->assertSame(0, (int) DB::table('xp_events')->sum('amount'));
    }

    public function test_an_invalid_receipt_rolls_back_the_balance_and_does_not_leave_an_unexplained_adjustment(): void
    {
        $student = $this->student();
        try {
            app(XpLedger::class)->change($student->id, 'bad-receipt', -5, ['type' => 'quiz', 'title' => 'Quiz', 'reason' => 'Wrong answer', 'details' => ["\xB1"]]);
            $this->fail('Invalid receipt should not commit.');
        } catch (\JsonException $error) {
            $this->assertSame(599, $student->fresh()->xp);
            $this->assertDatabaseCount('xp_events', 1);
        }
    }
}
