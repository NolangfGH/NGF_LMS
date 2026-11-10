<?php

namespace App\Services;

use App\Models\LessonProgress;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use stdClass;

class CourseAssessments
{
    public const PRE = 'pre-test';

    public const POST = 'post-test';

    public const ARDUINO_PRE = 'arduino-pre-test';

    public const ARDUINO_POST = 'arduino-post-test';

    public const REASONING = 'reasoning';

    public const STARTING_CORRECT_XP = 5;

    public const STARTING_WRONG_XP = 3;

    public const TITLES = [
        self::PRE => 'C++ Pre-Test',
        self::POST => 'C++ Post-Test',
        self::ARDUINO_PRE => 'Arduino Uno Pre-Test',
        self::ARDUINO_POST => 'Arduino Uno Post-Test',
        self::REASONING => 'Student Reasoning Challenge',
    ];

    public function hasCompleted(Student $student, string $assessment): bool
    {
        return DB::table('course_assessment_results')->where('student_id', $student->id)->where('assessment', $assessment)->exists();
    }

    public function nextLessonAssessment(Student $student, string $course = 'cpp'): ?string
    {
        foreach ([self::REASONING, $course === 'arduino-uno' ? self::ARDUINO_PRE : self::PRE] as $assessment) {
            if (! $this->hasCompleted($student, $assessment)) {
                return $assessment;
            }
        }

        return null;
    }

    public function results(Student $student): Collection
    {
        return DB::table('course_assessment_results')->where('student_id', $student->id)->get()->keyBy('assessment');
    }

    public static function selectedCourse(): string
    {
        return request()->query('course') === 'arduino-uno' ? 'arduino-uno' : 'cpp';
    }

    public static function courseQuery(?string $course = null): array
    {
        return ($course ?? self::selectedCourse()) === 'arduino-uno' ? ['course' => 'arduino-uno'] : [];
    }

    public function progress(Student $student, string $course = 'cpp'): array
    {
        $keys = array_keys(config($course === 'arduino-uno' ? 'arduino_curriculum.lessons' : 'lessons'));
        $completed = LessonProgress::where('student_id', $student->id)->whereIn('lesson_key', $keys)->whereNotNull('completed_at')->count();

        return ['completed' => $completed, 'total' => count($keys)];
    }

    public function canTakePostTest(Student $student, string $course = 'cpp'): bool
    {
        $progress = $this->progress($student, $course);

        return $progress['total'] > 0 && $progress['completed'] === $progress['total'] && $this->nextLessonAssessment($student, $course) === null;
    }

    /** @return list<array{skill: string, type: string, prompt: string, options: list<string>, correct: list<string>}> */
    public function questions(string $assessment): array
    {
        if (in_array($assessment, [self::ARDUINO_PRE, self::ARDUINO_POST], true)) {
            $questions = [];
            $offset = $assessment === self::ARDUINO_PRE ? 0 : 2;
            foreach (app(ReadyMadeActivities::class)->catalog('arduino-uno') as $lesson) {
                foreach (array_slice($lesson['quiz']['questions'], $offset, 2) as $question) {
                    $questions[] = Arr::only($question, ['type', 'prompt', 'options', 'correct']) + ['skill' => $lesson['term'].' · '.$lesson['topic']];
                }
            }

            return $questions;
        }

        $questions = [];
        foreach (config('course_assessments.'.$assessment, []) as $skill => $bank) {
            foreach (explode("\n", trim($bank)) as $row) {
                [$type, $prompt, $answer] = $parts = explode(' | ', trim($row));
                $correct = $type === 'multiple_answer' ? explode(' / ', $answer) : [$answer];
                $options = array_slice($parts, 3);
                if ($options && $type !== 'multiple_answer') {
                    array_splice($options, crc32($prompt) % (count($options) + 1), 0, $correct);
                }
                $questions[] = ['skill' => $skill, 'type' => $type, 'prompt' => $prompt, 'options' => $options, 'correct' => $correct];
            }
        }

        return $questions;
    }

    /** @return list<array{skill: string, type: string, prompt: string, options: list<string>}> */
    public function publicQuestions(string $assessment): array
    {
        return array_map(static fn (array $question): array => Arr::except($question, ['correct']), $this->questions($assessment));
    }

    public function answerRules(array $questions): array
    {
        $rules = ['answers' => ['required', 'array:'.implode(',', array_keys($questions)), 'size:'.count($questions)]];
        foreach ($questions as $index => $question) {
            $field = 'answers.'.$index;
            if ($question['type'] === 'multiple_answer') {
                $rules[$field] = ['required', 'array', 'min:1', 'max:'.count($question['options'])];
                $rules[$field.'.*'] = ['required', 'string', 'distinct', Rule::in($question['options'])];
            } elseif ($question['type'] === 'identification') {
                $rules[$field] = ['required', 'string', 'max:80'];
            } else {
                $rules[$field] = ['required', 'string', Rule::in($question['options'])];
            }
        }

        return $rules;
    }

    public function submit(Student $student, string $assessment, array $questions, array $answers, QuizGrader $grader): void
    {
        abort_if(in_array($assessment, [self::POST, self::ARDUINO_POST], true), 403);
        DB::transaction(function () use ($student, $assessment, $questions, $answers, $grader): void {
            $current = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->classroom && ! $current->classroom->archived_at, 403);
            if ($this->hasCompleted($current, $assessment)) {
                return;
            }
            $grade = $grader->grade($questions, $answers);
            $details = json_decode($grade['grading_results'], true, flags: JSON_THROW_ON_ERROR);
            $skills = [];
            foreach ($questions as $index => $question) {
                $skill = $question['skill'];
                $skills[$skill] ??= ['score' => 0, 'total' => 0];
                $skills[$skill]['total']++;
                $skills[$skill]['score'] += (int) $details[$index]['correct'];
            }

            $completedAt = now();
            $resultId = DB::table('course_assessment_results')->insertGetId([
                'student_id' => $current->id, 'assessment' => $assessment, 'score' => $grade['score'],
                'total_questions' => $grade['total_questions'], 'skill_scores' => json_encode($skills, JSON_THROW_ON_ERROR), 'completed_at' => $completedAt,
            ]);
            $ledger = app(XpLedger::class);
            foreach ([true, false] as $isCorrect) {
                $numbers = array_keys(array_filter($details, static fn (array $result): bool => $result['correct'] === $isCorrect));
                if ($numbers === []) {
                    continue;
                }
                $points = count($numbers) * ($isCorrect ? self::STARTING_CORRECT_XP : -self::STARTING_WRONG_XP);
                $ledger->change($current->id, 'starting-assessment:'.$assessment.':'.($isCorrect ? 'correct' : 'wrong'), $points,
                    ['type' => 'course_assessment', 'id' => $resultId, 'title' => self::TITLES[$assessment],
                        'reason' => $isCorrect ? 'Correct starting-assessment answers' : 'Incorrect starting-assessment answers',
                        'details' => [count($numbers).($isCorrect ? ' correct × 5 XP = +' : ' incorrect × 3 XP = −').abs($points).' XP.',
                            'Question numbers: '.implode(', ', array_map(static fn (int $index): int => $index + 1, $numbers)).'.',
                            'Applied once on first submission. XP does not change your assessment score or completion requirement.']]);
            }
        }, 3);
    }

    public function certificate(Student $student, string $course = 'cpp'): ?stdClass
    {
        return DB::table('course_certificates')->where('student_id', $student->id)->where('course', $course)->orderByDesc('award_type')->first();
    }

    public function awardExcellence(Student $student, string $course = 'cpp'): void
    {
        $teacher = $student->classroom->teacher;
        $recipient = trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->surname])));
        DB::table('course_certificates')->insert([
            'student_id' => $student->id, 'teacher_account_id' => $teacher?->id, 'award_type' => 'excellence', 'course' => $course,
            'certificate_code' => (string) Str::ulid(), 'recipient' => $recipient ?: $student->nickname,
            'school' => $teacher?->school ?: 'NGF_C++CodeQuest', 'issuer' => $teacher?->fullName() ?: 'NGF_C++CodeQuest',
            'title' => $course === 'arduino-uno' ? 'Certificate of Excellence in Arduino Uno Robotics' : 'Certificate of Excellence in C++ Average Programmer',
            'message' => $course === 'arduino-uno' ? 'Awarded for completing the Arduino Uno Robotics course and passing the timed 50-question certification exam with at least 45 correct answers. Practical skills remain subject to teacher review.' : 'Awarded for completing the C++ Programming course and passing the timed C++ certification exam.',
            'issued_at' => now(),
        ]);
    }

    public static function practiceBand(int $score, int $total): string
    {
        return match (true) {
            $score * 10 >= $total * 9 => 'Ready for a stretch task',
            $score * 10 >= $total * 7 => 'Secure on these items',
            $score * 10 >= $total * 4 => 'Developing with practice',
            default => 'Start with guided examples',
        };
    }
}
