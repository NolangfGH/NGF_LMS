<?php

namespace App\Services;

use App\Models\ChallengeNotice;
use App\Models\CodingMatch;
use App\Models\CodingMatchParticipant;
use App\Models\CodingMatchSubmission;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodingMatchService
{
    public const PREPARATION_SECONDS = 5;

    public function notice(CodingMatch $match, string $role, int $id, string $key, string $message): void
    {
        ChallengeNotice::firstOrCreate(['coding_match_id' => $match->id, $role === 'teacher' ? 'teacher_account_id' : 'student_id' => $id, 'event_key' => $key],
            ['message' => $message, 'created_at' => now()]);
    }

    public function rosterValid(CodingMatch $match): bool
    {
        $match->loadMissing('participants.student.classroom');

        return $match->participants->count() === 2 && $match->participants->every(fn ($participant) => $participant->student && $participant->student->classroom?->teacher_account_id === $match->teacher_account_id);
    }

    public function phase(CodingMatch $match): string
    {
        if ($match->status !== 'active') {
            return $match->status;
        }
        if (now()->lt($match->started_at)) {
            return 'countdown';
        }

        return now()->gte($match->deadline_at) ? 'overtime' : 'active';
    }

    private function assertWritable(CodingMatch $match): void
    {
        if (! $this->rosterValid($match)) {
            throw ValidationException::withMessages(['challenge' => 'Enrollment changed. Ask your teacher to cancel and reassign this challenge.']);
        }
        if (! in_array($this->phase($match), ['active', 'overtime'])) {
            throw ValidationException::withMessages(['challenge' => 'Coding is available after both students accept and the shared preparation countdown ends, until the teacher ends the match.']);
        }
    }

    public function respond(CodingMatch $match, Student $student, string $decision): void
    {
        DB::transaction(function () use ($match, $student, $decision) {
            $current = CodingMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $participant = $current->participants()->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            if ($participant->response === $decision) {
                return;
            }
            if ($current->status !== 'inviting' || $participant->response !== 'invited') {
                throw ValidationException::withMessages(['response' => 'This invitation has already been answered or closed.']);
            }
            if (! $this->rosterValid($current)) {
                throw ValidationException::withMessages(['response' => 'The roster changed. Your teacher needs to reassign the challenge.']);
            }
            $participant->update(['response' => $decision, 'accepted_at' => $decision === 'accepted' ? now() : null]);
            $this->notice($current, 'teacher', $current->teacher_account_id, 'response:'.$participant->id, $participant->student_name.' '.($decision === 'accepted' ? 'accepted' : 'declined').' your coding challenge.');
            if ($decision === 'declined') {
                $this->cancelLocked($current, 'An invited student declined the challenge.');

                return;
            }
            if ($current->participants()->where('response', 'accepted')->count() === 2) {
                $start = now()->addSeconds(self::PREPARATION_SECONDS);
                $current->update(['status' => 'active', 'started_at' => $start, 'deadline_at' => $start->copy()->addMinutes($current->time_limit_minutes)]);
                $this->notice($current, 'teacher', $current->teacher_account_id, 'ready', 'Both students accepted. The shared coding countdown is starting.');
                foreach ($current->participants as $member) {
                    $this->notice($current, 'student', $member->student_id, 'ready', 'Both students accepted. Open the arena for your shared countdown.');
                }
            }
        });
    }

    public function save(CodingMatch $match, Student $student, int $revision, string $code): CodingMatchParticipant
    {
        return DB::transaction(function () use ($match, $student, $revision, $code) {
            $current = CodingMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->assertWritable($current);
            $participant = $current->participants()->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            abort_unless($participant->response === 'accepted', 403);
            abort_if($participant->draft_revision !== $revision, 409, 'A newer draft exists. Download your current code before reloading to avoid overwriting another tab.');
            if ($participant->draft_code !== $code) {
                $participant->update(['draft_code' => $code, 'draft_revision' => $revision + 1, 'draft_saved_at' => now()]);
            }

            return $participant;
        });
    }

    public function submit(CodingMatch $match, Student $student, int $revision): CodingMatchSubmission
    {
        return DB::transaction(function () use ($match, $student, $revision) {
            $current = CodingMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->assertWritable($current);
            $participant = $current->participants()->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            abort_unless($participant->response === 'accepted', 403);
            abort_if($participant->draft_revision !== $revision, 409, 'Save your newest draft before submitting.');
            if (trim($participant->draft_code) === '') {
                throw ValidationException::withMessages(['code' => 'Write your C++ solution before submitting.']);
            }
            $submission = CodingMatchSubmission::firstOrCreate(['participant_id' => $participant->id, 'revision' => $revision], [
                'code' => $participant->draft_code, 'submitted_at' => now(), 'on_time' => now()->lt($current->deadline_at),
            ]);
            $this->notice($current, 'teacher', $current->teacher_account_id, 'submission:'.$submission->id, $participant->student_name.' submitted revision '.$revision.' for verification.');

            return $submission;
        });
    }

    public function normalizeOutput(string $output): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $output), "\n");
    }

    public function review(CodingMatch $match, CodingMatchSubmission $submission, TeacherAccount $teacher, array $data): void
    {
        DB::transaction(function () use ($match, $submission, $teacher, $data) {
            $current = CodingMatch::whereKey($match->id)->where('teacher_account_id', $teacher->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'active') {
                throw ValidationException::withMessages(['review' => 'This match is not open for review. Final awards cannot be changed.']);
            }
            $record = CodingMatchSubmission::with('participant')->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->participant->coding_match_id === $current->id, 404);
            if ($data['verdict'] === 'correct' && $this->normalizeOutput($data['observed_output'] ?? '') !== $this->normalizeOutput($current->sample_output)) {
                throw ValidationException::withMessages(['observed_output' => 'The observed output must match the sample output. Line endings and a final newline are ignored; other spacing is preserved.']);
            }
            $record->update(['verdict' => $data['verdict'], 'feedback' => $data['feedback'], 'observed_output' => $data['observed_output'] ?? null, 'reviewed_at' => now(), 'reviewed_by_teacher_id' => $teacher->id]);
            if ($record->participant->student_id) {
                $this->notice($current, 'student', $record->participant->student_id, 'review:'.$record->id.':'.hash('sha256', $data['verdict'].$data['feedback']), 'Your teacher reviewed submission #'.$record->id.'. Open the challenge to read the feedback.');
            }
        });
    }

    public function submissions(CodingMatch $match)
    {
        return CodingMatchSubmission::whereIn('participant_id', $match->participants()->pluck('id'))->orderBy('submitted_at')->orderBy('id');
    }

    public function candidate(CodingMatch $match): array
    {
        $records = $this->submissions($match)->get();
        $candidate = $records->firstWhere('verdict', 'correct');
        $pendingEarlier = $candidate ? $records->takeUntil(fn ($record) => $record->id === $candidate->id)->where('verdict', 'pending')->count() : 0;

        return ['submission' => $candidate, 'pending_earlier' => $pendingEarlier];
    }

    public function award(CodingMatch $match, TeacherAccount $teacher, int $submissionId, string $note): CodingMatch
    {
        return DB::transaction(function () use ($match, $teacher, $submissionId, $note) {
            $current = CodingMatch::whereKey($match->id)->where('teacher_account_id', $teacher->id)->lockForUpdate()->firstOrFail();
            if ($current->status === 'awarded') {
                abort_if($current->winning_submission_id !== $submissionId, 409, 'A final winner has already been awarded.');

                return $current;
            }
            if ($current->status !== 'active' || ! $this->rosterValid($current)) {
                throw ValidationException::withMessages(['award' => 'This match is closed or the roster changed.']);
            }
            $candidate = $this->candidate($current);
            if (! $candidate['submission'] || $candidate['submission']->id !== $submissionId) {
                throw ValidationException::withMessages(['submission_id' => 'Choose the earliest teacher-verified correct submission.']);
            }
            if ($candidate['pending_earlier']) {
                throw ValidationException::withMessages(['award' => 'Review earlier pending submissions before choosing the first correct solution.']);
            }
            $winner = $candidate['submission']->participant;
            $account = Student::whereKey($winner->student_id)->lockForUpdate()->firstOrFail();
            app(XpLedger::class)->change($account->id, 'coding-match:'.$current->id, $current->winner_xp,
                ['type' => 'coding-match', 'id' => $current->id, 'title' => $current->title, 'reason' => 'Teacher-confirmed coding match winner', 'details' => ['Winner reward confirmed by the class teacher: '.$current->winner_xp.' XP.']]);
            $current->update(['status' => 'awarded', 'winning_submission_id' => $submissionId, 'awarded_at' => now(), 'award_note' => $note, 'xp_awarded' => $current->winner_xp]);
            foreach ($current->participants as $participant) {
                $this->notice($current, 'student', $participant->student_id, 'awarded', $participant->id === $winner->id ? 'You won! Your teacher awarded '.$current->winner_xp.' XP.' : 'Your teacher has finalized this challenge. Open the arena for the result.');
            }
            $this->notice($current, 'teacher', $teacher->id, 'awarded', 'Winner award saved. '.$current->winner_xp.' XP was credited once.');

            return $current;
        });
    }

    private function cancelLocked(CodingMatch $match, string $reason): void
    {
        $match->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        foreach ($match->participants as $member) {
            if ($member->student_id) {
                $this->notice($match, 'student', $member->student_id, 'cancelled', 'Your teacher challenge was closed without a winner. Your saved code is kept.');
            }
        }
        $this->notice($match, 'teacher', $match->teacher_account_id, 'cancelled', 'Challenge closed without an XP award.');
    }

    public function cancel(CodingMatch $match, TeacherAccount $teacher, string $reason): void
    {
        DB::transaction(function () use ($match, $teacher, $reason) {
            $current = CodingMatch::whereKey($match->id)->where('teacher_account_id', $teacher->id)->lockForUpdate()->firstOrFail();
            if ($current->status === 'cancelled') {
                return;
            }
            if ($current->status === 'awarded') {
                throw ValidationException::withMessages(['challenge' => 'A final winner award cannot be cancelled.']);
            }
            $this->cancelLocked($current, $reason);
        });
    }
}
