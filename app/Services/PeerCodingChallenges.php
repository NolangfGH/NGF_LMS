<?php

namespace App\Services;

use App\Models\CodingMatch;
use App\Models\Student;
use App\Models\TeacherAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PeerCodingChallenges
{
    public function request(Student $actor, int $opponentId, string $course, string $lessonKey): CodingMatch
    {
        return DB::transaction(function () use ($actor, $opponentId, $course, $lessonKey): CodingMatch {
            $students = Student::with('classroom')->whereIn('id', [$actor->id, $opponentId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $challenger = $students->get($actor->id);
            $opponent = $students->get($opponentId);
            if ($students->count() !== 2 || ! $challenger?->classroom || ! $opponent?->classroom
                || $challenger->classroom->grade !== $opponent->classroom->grade) {
                throw ValidationException::withMessages(['opponent' => 'Choose another approved learner in your grade level.']);
            }
            $teacherIds = $students->map(fn (Student $student): int => $student->classroom->teacher_account_id)->unique()->sort()->values();
            if (! $this->activeTeachers($teacherIds->all())) {
                throw ValidationException::withMessages(['opponent' => 'Both learners need an active supervising teacher.']);
            }
            $open = ['awaiting_approval', 'inviting', 'active'];
            if (CodingMatch::where('origin', 'student')->where('requested_by_student_id', $challenger->id)->whereIn('status', $open)->count() >= 3) {
                throw ValidationException::withMessages(['opponent' => 'You already have three open requests or matches. Finish or withdraw one before requesting another.']);
            }
            if (CodingMatch::whereIn('status', $open)->whereHas('participants', fn ($query) => $query->where('student_id', $challenger->id))
                ->whereHas('participants', fn ($query) => $query->where('student_id', $opponent->id))->exists()) {
                throw ValidationException::withMessages(['opponent' => 'A match between you and this learner is already open.']);
            }
            $lesson = $this->unlockedLesson($challenger, $course, $lessonKey);
            if ($teacherIds->count() > 1 && ! isset(config('lessons')[$lessonKey]) && ! isset(config('arduino_curriculum.lessons')[$lessonKey])) {
                throw ValidationException::withMessages(['lesson' => 'Choose a shared course topic when the learners have different teachers.']);
            }
            $activity = app(ReadyMadeActivities::class)->catalog($course)[$lessonKey]['challenge'] ?? [];
            $match = CodingMatch::create(['teacher_account_id' => $challenger->classroom->teacher_account_id, 'origin' => 'student', 'requested_by_student_id' => $challenger->id,
                'participant_count' => 2, 'course' => $course, 'lesson_key' => $lessonKey, 'requested_grade' => $challenger->classroom->grade, 'status' => 'awaiting_approval',
                'title' => Str::limit('Peer match · '.$lesson['title'], 160, ''), 'instructions' => $activity['objective'] ?? $lesson['objective'],
                'sample_output' => $activity['sample_output'] ?? $lesson['output'] ?? '', 'time_limit_minutes' => 15, 'winner_xp' => 100, 'award_title' => 'Peer Coding Champion']);
            foreach ([$challenger, $opponent] as $slot => $member) {
                $match->participants()->create(['student_id' => $member->id, 'slot' => $slot + 1,
                    'student_name' => $member->leaderboard_visible ? $member->nickname : $member->rankingAlias(), 'section_label' => $member->classroom->grade,
                    'draft_code' => CodingMatchService::starterCode($course), 'response' => $slot === 0 ? 'accepted' : 'invited', 'accepted_at' => $slot === 0 ? now() : null]);
            }
            foreach ($teacherIds as $id) {
                DB::table('coding_match_approvals')->insert(['coding_match_id' => $match->id, 'teacher_account_id' => $id, 'decision' => 'pending']);
            }
            app(CodingMatchService::class)->notice($match, 'teacher', $match->teacher_account_id, 'peer-request', 'A learner requested “'.$match->title.'”. Review the topic, task and roster before approving.');

            return $match;
        }, 5);
    }

    public function rosterValid(CodingMatch $match, bool $requireApprovals = true): bool
    {
        $match->loadMissing('participants.student.classroom');
        if ($match->participant_count !== 2 || $match->participants->count() !== 2 || ! $match->requested_grade
            || ! $match->participants->every(fn ($participant): bool => $participant->student && $participant->student->classroom?->grade === $match->requested_grade)) {
            return false;
        }
        $actual = $match->participants->map(fn ($participant): int => $participant->student->classroom->teacher_account_id)->unique()->sort()->values()->all();
        $approvals = DB::table('coding_match_approvals')->where('coding_match_id', $match->id)->get();
        $recorded = $approvals->pluck('teacher_account_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();

        return in_array($match->teacher_account_id, $actual, true) && $actual === $recorded && $this->activeTeachers($actual)
            && (! $requireApprovals || $approvals->every(fn (object $approval): bool => $approval->decision === 'approved'));
    }

    /** @param array<string, mixed> $data */
    public function review(CodingMatch $match, TeacherAccount $teacher, array $data): void
    {
        DB::transaction(function () use ($match, $teacher, $data): void {
            $current = CodingMatch::whereKey($match->id)->where('origin', 'student')->lockForUpdate()->firstOrFail();
            $approval = DB::table('coding_match_approvals')->where('coding_match_id', $current->id)->where('teacher_account_id', $teacher->id)->first();
            abort_unless($approval, 404);
            abort_unless($current->status === 'awaiting_approval', 409, 'This request has already been approved or closed.');
            abort_unless($approval->decision === 'pending', 409, 'Your approval has already been recorded.');
            if ($data['decision'] === 'rejected') {
                DB::table('coding_match_approvals')->where('id', $approval->id)->update(['decision' => 'rejected', 'reason' => $data['reason'], 'reviewed_at' => now()]);
                app(CodingMatchService::class)->cancel($current, TeacherAccount::findOrFail($current->teacher_account_id), 'Teacher declined the request: '.$data['reason']);

                return;
            }
            if ($teacher->id !== $current->teacher_account_id && ! DB::table('coding_match_approvals')->where('coding_match_id', $current->id)->where('teacher_account_id', $current->teacher_account_id)->where('decision', 'approved')->exists()) {
                throw ValidationException::withMessages(['decision' => 'Wait for the host teacher to approve the task first.']);
            }
            if (! $this->rosterValid($current, false)) {
                throw ValidationException::withMessages(['decision' => 'Enrollment or supervising teachers changed. Close this request and ask the learners to send a new one.']);
            }
            foreach ($current->participants as $participant) {
                $this->unlockedLesson($participant->student, $current->course, $current->lesson_key);
            }
            if ($teacher->id === $current->teacher_account_id) {
                $current->update(collect($data)->only(['title', 'instructions', 'sample_output', 'time_limit_minutes', 'winner_xp', 'award_title'])->all());
            }
            DB::table('coding_match_approvals')->where('id', $approval->id)->update(['decision' => 'approved', 'reason' => $data['reason'] ?? null, 'reviewed_at' => now()]);
            $remaining = DB::table('coding_match_approvals')->where('coding_match_id', $current->id)->where('decision', '!=', 'approved')->get();
            $notices = app(CodingMatchService::class);
            if ($remaining->isNotEmpty()) {
                foreach ($remaining as $pending) {
                    $notices->notice($current, 'teacher', $pending->teacher_account_id, 'peer-review', 'The host teacher approved “'.$current->title.'”. Review and approve participation for your learner.');
                }

                return;
            }
            $current->update(['status' => 'inviting']);
            foreach ($current->participants as $participant) {
                $notices->notice($current, 'student', $participant->student_id, 'invitation', 'Your teacher approved “'.$current->title.'”. '.$participant->student_name.($participant->response === 'accepted' ? ', your request counts as your acceptance. Wait for the opponent to accept.' : ', read the task and accept or decline.'));
            }
            $notices->noticeTeachers($current, 'peer-approved', 'Every supervising teacher approved this peer match. The opponent can now accept and start the shared countdown.');
        }, 5);
    }

    public function withdraw(CodingMatch $match, Student $student): void
    {
        DB::transaction(function () use ($match, $student): void {
            $current = CodingMatch::whereKey($match->id)->where('origin', 'student')->lockForUpdate()->firstOrFail();
            abort_unless($current->participants()->where('student_id', $student->id)->exists(), 404);
            abort_unless(in_array($current->status, ['awaiting_approval', 'inviting'], true), 409, 'Only a request that has not started can be withdrawn.');
            app(CodingMatchService::class)->cancel($current, TeacherAccount::findOrFail($current->teacher_account_id), 'A participating learner withdrew or declined this request.');
        }, 5);
    }

    private function activeTeachers(array $ids): bool
    {
        return TeacherAccount::whereIn('id', $ids)->whereIn('role', array_diff(array_keys(TeacherAccount::ROLES), ['principal']))->where('staff_access_status', 'active')->count() === count($ids);
    }

    private function unlockedLesson(Student $student, string $course, string $key): array
    {
        $service = app(LessonService::class);
        $lesson = $service->catalogForTeacher($student->classroom?->teacher_account_id, $course)[$key] ?? null;
        $card = $service->path($student, $course)['cards']->firstWhere('key', $key);
        if (! $lesson || ! $card || $card['locked']) {
            throw ValidationException::withMessages(['lesson' => 'Both learners must unlock the selected lesson, including the reasoning challenge, course pre-test and preceding checkpoints. Choose an available topic or complete its prerequisites first.']);
        }

        return $lesson;
    }
}
