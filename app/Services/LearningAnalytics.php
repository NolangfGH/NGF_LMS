<?php

namespace App\Services;

use App\Models\LessonProgress;
use App\Models\QuizBeeMatch;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LearningAnalytics
{
    public const KINDS = ['quiz' => 'Quizzes and exams', 'summative-1' => '1st summative test', 'summative-2' => '2nd summative test', 'term-exam' => 'Term exam',
        'reasoning' => 'Student Reasoning Challenge', 'pre' => 'Pre-test', 'post' => 'Post-test', 'checkpoint' => 'Lesson checkpoints',
        'bee-individual' => 'Individual Quiz Bee', 'bee-group' => 'Group Quiz Bee'];

    /** @param Collection<int, int> $classroomIds */
    public function courseResults(Collection $classroomIds, string $kind, string $course, ?string $version, string $attempt = 'first'): array
    {
        $students = Student::whereIn('classroom_id', $classroomIds)->select('id');
        if ($kind === 'post') {
            $rows = DB::table('course_post_test_attempts')->whereIn('student_id', $students)->where('course', $course)->whereNotNull('finished_at')
                ->orderBy('attempt_number', $attempt === 'latest' ? 'desc' : 'asc')->get()->unique('student_id');
            $source = 'post_test';
        } else {
            $assessment = $kind === 'reasoning' ? CourseAssessments::REASONING : ($course === 'arduino-uno' ? CourseAssessments::ARDUINO_PRE : CourseAssessments::PRE);
            $rows = DB::table('course_assessment_results')->whereIn('student_id', $students)->where('assessment', $assessment)->get();
            $source = 'course_assessment';
        }
        $snapshots = $this->snapshots($source, $rows->pluck('id'));
        $records = $rows->map(fn (object $row): array => ['score' => (int) $row->score, 'total' => (int) ($row->total_questions ?? CoursePostTest::QUESTIONS),
            'items' => $this->decode($snapshots->get($row->id)?->items), 'version' => $snapshots->get($row->id)?->version,
            'skills' => $this->decode($row->skill_scores ?? null)])->values()->all();

        return $this->summarize($records, $version) + ['unit' => 'Learners', 'basis' => $kind === 'post' ? ucfirst($attempt).' finished attempt per learner. In-progress attempts are excluded.' : 'One completed assessment per learner.'];
    }

    /** @param Collection<int, int> $classroomIds */
    public function checkpointResults(Collection $classroomIds, string $key, array $lesson, ?string $version): array
    {
        $rows = LessonProgress::where('lesson_key', $key)->where('attempts', '>', 0)->whereIn('student_id', Student::whereIn('classroom_id', $classroomIds)->select('id'))->get();
        $snapshots = $this->snapshots('checkpoint', $rows->pluck('id'));
        $records = [];
        $excluded = 0;
        foreach ($rows as $row) {
            $snapshot = $snapshots->get($row->id);
            $total = $snapshot?->question_count;
            if (! $total || $row->latest_score === null || $row->latest_score > $total) {
                $excluded++;

                continue;
            }
            $records[] = ['score' => (int) $row->latest_score, 'total' => (int) $total, 'items' => $this->decode($snapshot?->items), 'version' => $snapshot?->version,
                'skills' => [$lesson['topic'] => ['score' => (int) $row->latest_score, 'total' => (int) $total]]];
        }

        return $this->summarize($records, $version) + ['unit' => 'Learners', 'basis' => 'Latest saved checkpoint per learner. Retrying replaces the learner’s checkpoint statistics. Historical records without a reliable question count are excluded.', 'excluded' => $excluded];
    }

    /** @param Collection<int, int> $classroomIds */
    public function beeResults(QuizBeeMatch $match, Collection $classroomIds, ?string $version): array
    {
        $students = Student::whereIn('classroom_id', $classroomIds)->select('id');
        $teams = DB::table('quiz_bee_teams')->where('quiz_bee_match_id', $match->id)->whereIn('id', DB::table('quiz_bee_members')->whereIn('student_id', $students)->select('quiz_bee_team_id'))->get();
        $runs = DB::table('quiz_bee_runs')->where('quiz_bee_match_id', $match->id)->where('kind', 'regular')->whereNotNull('closed_at')->orderBy('sequence')->get();
        $answers = DB::table('quiz_bee_answers')->whereIn('quiz_bee_team_id', $teams->pluck('id'))->whereIn('quiz_bee_run_id', $runs->pluck('id'))->get()->keyBy(fn (object $row): string => $row->quiz_bee_team_id.':'.$row->quiz_bee_run_id);
        $records = [];
        foreach ($teams as $team) {
            $items = [];
            foreach ($runs as $index => $run) {
                $question = $this->decode($run->question);
                $answer = $answers->get($team->id.':'.$run->id);
                $items[] = ['key' => hash('sha256', 'bee-run:'.$run->id), 'number' => $index + 1, 'prompt' => $question['prompt'], 'skill' => ucfirst($run->level),
                    'correct' => (bool) $answer?->correct, 'blank' => ! $answer?->submitted_at];
            }
            $records[] = ['score' => count(array_filter($items, static fn (array $item): bool => $item['correct'])), 'total' => count($items), 'items' => $items,
                'version' => hash('sha256', json_encode(array_column($items, 'key'), JSON_THROW_ON_ERROR)), 'skills' => []];
        }

        return $this->summarize($records, $version) + ['unit' => $match->category === 'group' ? 'Teams' : 'Individuals',
            'basis' => 'Completed match; regular questions only. Tie-breakers and XP speed/perfect bonuses are excluded from scores. Each team counts once, including mixed-section teams with a member in the selected section.'];
    }

    /** @param list<array{score: int, total: int, items: array, version: ?string, skills: array}> $records */
    public function summarize(array $records, ?string $version = null): array
    {
        $versions = [];
        foreach ($records as &$record) {
            $record['version'] ??= hash('sha256', 'historical:'.$record['total']);
            $key = $record['version'];
            $versions[$key] ??= ['count' => 0, 'has_items' => $record['items'] !== []];
            $versions[$key]['count']++;
        }
        unset($record);
        abort_if($version && ! isset($versions[$version]), 404);
        $selected = $version ? array_values(array_filter($records, static fn (array $row): bool => $row['version'] === $version)) : $records;
        $items = [];
        $skills = [];
        $missing = 0;
        foreach ($selected as $record) {
            if ($record['items'] === []) {
                $missing++;
                foreach ($record['skills'] as $title => $skill) {
                    $skills[$title] ??= ['prompt' => $title, 'correct' => 0, 'total' => 0];
                    $skills[$title]['correct'] += (int) $skill['score'];
                    $skills[$title]['total'] += (int) $skill['total'];
                }
            }
            foreach ($record['items'] as $item) {
                $key = $item['key'];
                $items[$key] ??= ['prompt' => $item['prompt'], 'skill' => $item['skill'], 'correct' => 0, 'wrong' => 0, 'blank' => 0, 'total' => 0];
                $items[$key][$item['correct'] ? 'correct' : ($item['blank'] ? 'blank' : 'wrong')]++;
                $items[$key]['total']++;
                if ($item['skill'] !== '') {
                    $title = $item['skill'];
                    $skills[$title] ??= ['prompt' => $title, 'correct' => 0, 'total' => 0];
                    $skills[$title]['correct'] += (int) $item['correct'];
                    $skills[$title]['total']++;
                }
            }
        }
        $summarize = static fn (array $item): array => $item + ['percent_correct' => $item['total'] ? round($item['correct'] / $item['total'] * 100, 2) : null];
        $items = array_values(array_map($summarize, $items));
        $skills = array_values(array_map($summarize, $skills));

        return ['metrics' => AssessmentStatistics::metrics($selected), 'items' => $items, 'skills' => $skills,
            'least_mastered' => collect($items)->filter(static fn (array $item): bool => $item['correct'] < $item['total'])->sortBy('percent_correct')->take(5)->values()->all(),
            'versions' => $versions, 'selected_version' => $version, 'missing_items' => $missing];
    }

    /** @param Collection<int, int> $ids */
    private function snapshots(string $source, Collection $ids): Collection
    {
        return DB::table('assessment_item_snapshots')->where('source_type', $source)->whereIn('source_id', $ids)->get()->keyBy('source_id');
    }

    private function decode(?string $json): array
    {
        $value = json_decode($json ?? 'null', true);

        return is_array($value) ? $value : [];
    }
}
