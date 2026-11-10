<?php

namespace App\Http\Controllers;

use App\Models\LessonContent;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Services\AchievementService;
use App\Services\CourseAssessments;
use App\Services\LearningRewards;
use App\Services\LessonService;
use App\Services\XpLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    private function unlocked(Student $student, string $key, LessonService $service): void
    {
        $card = $service->path($student, $service->lesson($key)['course'] ?? 'cpp')['cards']->firstWhere('key', $key);
        abort_unless($card, 404);
        abort_if($card['locked'], 403, 'Complete the preceding lesson checkpoints to unlock this mission.');
    }

    public function resume(LessonService $service)
    {
        $student = Student::find(session('student_id'));
        if (! $student) {
            return redirect()->route('student.login');
        }
        $next = $service->path($student, request('course') === 'arduino-uno' ? 'arduino-uno' : 'cpp')['next'];

        return redirect()->route('student.lesson.show', $next['key'] ?? array_key_first($service->catalog(CourseAssessments::selectedCourse())));
    }

    public function show(string $lesson, LessonService $service)
    {
        $student = Student::with('classroom')->find(session('student_id'));
        if (! $student) {
            return redirect()->route('student.login');
        }
        $definition = $service->lesson($lesson);
        $this->unlocked($student, $lesson, $service);
        $record = DB::transaction(function () use ($student, $lesson) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $record = LessonProgress::firstOrCreate(['student_id' => $student->id, 'lesson_key' => $lesson], ['started_at' => now(), 'last_opened_at' => now()]);
            $record->update(['last_opened_at' => now()]);

            return $record;
        });
        $path = $service->path($student, $definition['course'] ?? 'cpp');
        $card = $path['cards']->firstWhere('key', $lesson);
        $position = $path['cards']->search(fn ($item) => $item['key'] === $lesson);

        return response()->view('lesson', [
            'student' => $student, 'classroom' => $student->classroom,
            'lastSyncedAt' => now(config('learning.timezone'))->format('M j, Y · g:i A'),
            'lesson' => $definition, 'record' => $record, 'card' => $card,
            'nextLesson' => $path['cards']->get($position + 1), 'lessonNumber' => $position + 1,
            'previousLesson' => $position > 0 ? $path['cards']->get($position - 1) : null,
            'contentResource' => ! empty($definition['content_id']) ? LessonContent::find($definition['content_id']) : null,
            'lessonTotal' => $path['total'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function bookmark(Request $request, string $lesson, LessonService $service)
    {
        $student = Student::find(session('student_id'));
        abort_unless($student, 401);
        $definition = $service->lesson($lesson);
        $data = $request->validate(['section' => ['required', 'integer', 'min:0', 'max:'.(count($definition['sections']) - 1)]]);
        $record = LessonProgress::where('student_id', $student->id)->where('lesson_key', $lesson)->firstOrFail();
        $record->update(['last_section' => (int) $data['section']]);

        return redirect()->to(route('student.lesson.show', $lesson).'#section-'.$data['section'])->with('lesson_notice', 'Your reading bookmark is saved.');
    }

    public function checkpoint(Request $request, string $lesson, LessonService $service, AchievementService $achievements, XpLedger $ledger): RedirectResponse
    {
        $student = Student::find(session('student_id'));
        abort_unless($student, 401);
        $definition = $service->lesson($lesson);
        $this->unlocked($student, $lesson, $service);
        $rules = [
            'request_key' => ['required', 'uuid'],
            'answers' => ['required', 'array:'.implode(',', array_keys($definition['checks'])), 'size:'.count($definition['checks'])],
            'reflection' => ['required', 'string', 'min:15', 'max:2000'],
            'confidence' => ['nullable', Rule::in(['need_help', 'practising', 'ready'])],
            'help_request' => ['nullable', 'string', 'max:1000'],
        ];
        foreach ($definition['checks'] as $index => $check) {
            $rules['answers.'.$index] = ['required', 'integer', 'min:0', 'max:'.(count($check['options']) - 1)];
        }
        $data = $request->validate($rules, ['request_key.required' => 'Refresh this lesson before checking your answers.']);
        $score = 0;
        foreach ($definition['checks'] as $index => $check) {
            if ((int) $data['answers'][$index] === $check['correct']) {
                $score++;
            }
        }
        $duplicate = DB::transaction(function () use ($student, $lesson, $definition, $data, $score, $ledger): bool {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $record = LessonProgress::where('student_id', $student->id)->where('lesson_key', $lesson)->lockForUpdate()->firstOrFail();
            $answers = array_map('intval', $data['answers']);
            ksort($answers);
            $fingerprint = hash('sha256', json_encode([$answers, $data['reflection'], $data['confidence'] ?? null, $data['help_request'] ?? null], JSON_THROW_ON_ERROR));
            $key = 'checkpoint:'.$record->id.':'.$data['request_key'];
            if ($ledger->existing($student->id, $key, $fingerprint)) {
                return true;
            }
            $wrong = [];
            foreach ($definition['checks'] as $index => $check) {
                if ($answers[$index] !== $check['correct']) {
                    $wrong[] = $index + 1;
                }
            }
            $ledger->change($student->id, $key, -count($wrong) * XpLedger::CHECKPOINT_PENALTY, ['type' => 'checkpoint', 'id' => $record->id, 'title' => $definition['title'],
                'reason' => 'Checkpoint answer check', 'details' => [count($wrong).' incorrect × 2 XP = '.(count($wrong) * 2).' XP deduction requested.', $wrong ? 'Incorrect checkpoint questions: '.implode(', ', $wrong).'.' : 'Every checkpoint answer was correct.']], $fingerprint);
            $record->fill([
                'answers' => array_map('intval', $data['answers']), 'reflection' => $data['reflection'],
                'confidence' => $data['confidence'] ?? null, 'help_request' => $data['help_request'] ?? null,
                'attempts' => $record->attempts + 1, 'latest_score' => $score,
                'best_score' => max($record->best_score, $score),
            ]);
            if ($score === count($definition['checks']) && ! $record->completed_at) {
                app(LearningRewards::class)->award($student->id, 'lesson:'.$lesson, config('compiler.lesson_xp'), ['type' => 'lesson', 'id' => $lesson, 'title' => $definition['title'], 'reason' => 'First checkpoint completion', 'details' => ['All checkpoint answers correct. First completion reward; repeated completions do not earn more XP.']]);
                $record->completed_at = now();
                $record->completion_evidence = ['version' => $definition['version'], 'score' => $score,
                    'total' => count($definition['checks']), 'reflection' => $data['reflection'], 'answers' => $record->answers];
            }
            $record->save();

            return false;
        });
        $achievements->sync($student);
        $message = $score === count($definition['checks'])
            ? 'Checkpoint complete! Apply what you learned in Code Lab.'
            : 'You got '.$score.' of '.count($definition['checks']).' correct. Read the explanations, then try again.';
        $message .= $duplicate ? ' This submission was already checked; no additional deduction.' : ' Incorrect answers cost 2 XP each, with a minimum balance of zero. See Achievements for your XP history.';

        return redirect()->to(route('student.lesson.show', $lesson).'#checkpoint')->with('lesson_notice', $message);
    }

    public function support(Request $request, string $lesson, LessonService $service)
    {
        $student = Student::find(session('student_id'));
        abort_unless($student, 401);
        $service->lesson($lesson);
        $data = $request->validate([
            'help_request' => ['required', 'string', 'min:5', 'max:1000'],
            'reflection' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($student, $lesson, $data) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $record = LessonProgress::where('student_id', $student->id)->where('lesson_key', $lesson)->lockForUpdate()->firstOrFail();
            $record->help_request = $data['help_request'];
            $record->confidence = 'need_help';
            if (filled($data['reflection'] ?? null)) {
                $record->reflection = $data['reflection'];
            }
            $record->save();
        });

        return redirect()->to(route('student.lesson.show', $lesson).'#checkpoint')->with('lesson_notice', 'Your question is saved for your teacher. You can keep learning while you wait for feedback.');
    }
}
