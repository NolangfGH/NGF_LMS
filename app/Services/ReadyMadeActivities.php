<?php

namespace App\Services;

use Illuminate\Support\Arr;

class ReadyMadeActivities
{
    /** @return array<string, array<string, mixed>> */
    public function catalog(string $course = 'cpp'): array
    {
        $catalog = [];
        $arduino = LearningCourses::environment($course) === 'arduino-uno';
        $creative = LearningCourses::isCreative($course);
        $academic = LearningCourses::isAcademic($course);
        $pack = $academic ? AcademicCurriculum::pack($course) : null;
        $prefix = $creative ? 'creative_technology.'.$course : 'arduino_curriculum';
        foreach (LearningCourses::lessons($course) as $key => $lesson) {
            $template = $academic ? $pack['activities'][$key] : config($course === 'cpp' ? 'ready_made_activities.'.$key : $prefix.'.activities.'.$key);
            if ($template === null) {
                continue;
            }
            $template['term'] = $lesson['term'] ?? $template['term'];
            $questions = array_map(static fn (array $question): array => [
                'type' => 'multiple_choice',
                'prompt' => $question['prompt'],
                'options' => $question['options'],
                'correct' => [$question['options'][$question['correct']]],
                'explanation' => $question['why'],
            ], $course !== 'cpp' ? $template['questions'] : [...$lesson['checks'], ...$template['questions']]);
            $catalog[$key] = [
                'key' => $key, 'title' => $lesson['title'], 'topic' => $lesson['topic'], 'course' => $course,
                'objective' => $lesson['objective'], 'term' => $template['term'],
                'subtopics' => $lesson['subtopics'] ?? [], 'reference_url' => $lesson['reference_url'] ?? null,
                'quiz' => [
                    'title' => $lesson['topic'].' — 10-question quiz',
                    'instructions' => 'Choose one answer for each question. Review your lesson examples before starting. Each question has a 60-second limit; your teacher can change the timing before publishing.',
                    'questions' => $questions,
                    'xp' => 0, 'time_limit' => 10, 'untimed' => false, 'question_seconds' => 60,
                ],
            ];
            $catalog[$key]['recitation'] = array_map(static fn (array $question, int $index): array => [
                'title' => $lesson['topic'].' · Recitation '.($index + 1),
                'question' => $question['question'],
                'accepted_answers' => implode("\n", $question['accepted_answers']),
                'case_sensitive' => $question['case_sensitive'],
            ], $recitations = $academic ? $pack['recitations'][$key] : config($creative ? $prefix.'.recitations.'.$key : 'ready_made_recitations.'.$key, []), array_keys($recitations));
            foreach (['assignment', 'challenge'] as $kind) {
                $catalog[$key][$kind] = $template[$kind] + [
                    'kind' => $kind,
                    'school_term' => $template['term'],
                    'difficulty' => $template['difficulty'],
                    'time_limit_minutes' => $kind === 'challenge' ? 40 : 30,
                    'xp' => $kind === 'challenge' ? 200 : 150,
                    'csta_standard' => null,
                ];
            }
            $article = $academic ? $pack['articles'][$key] : config($course === 'cpp' ? 'ready_made_articles.'.$key : $prefix.'.articles.'.$key);
            if ($article !== null) {
                $parts = ['Learning goal', $lesson['objective'], $article['introduction']];
                foreach ($lesson['sections'] as $section) {
                    $parts[] = $section['title']."\n".$section['text'];
                }
                $parts[] = ($academic ? "Worked classroom example\n" : ($arduino ? "Arduino sketch (.ino)\n" : ($creative ? "Worked design example\n" : "C++ example\n"))).$lesson['code'];
                $parts[] = ($arduino ? "Expected behavior / output\n" : "Expected output\n").$lesson['output'];
                $parts[] = "Your private response\n".implode("\n\n", array_map(static fn (string $prompt, int $index): string => ($index + 1).'. '.$prompt, $article['prompts'], array_keys($article['prompts'])));
                $parts[] = 'Explain your reasoning in your own words. You may attach a code screenshot or record a voice response. Your comments and teacher replies stay private between you and your teacher.';
                $catalog[$key]['article'] = [
                    'title' => $article['title'], 'category' => $article['category'],
                    'body' => implode("\n\n", $parts),
                    'is_graded' => false, 'response_time_limit_minutes' => 5,
                    'teacher_notes' => $article['teacher_notes'],
                ];
            }
        }

        return $catalog;
    }

    /** @return array<string, mixed> */
    public function lesson(string $key): array
    {
        $catalog = $this->catalog(LearningCourses::lessonCourse($key));
        abort_unless(isset($catalog[$key]), 404);

        return $catalog[$key];
    }

    /** @return array<string, array<string, mixed>> */
    public function termExams(string $course = 'cpp'): array
    {
        if ($course !== 'cpp') {
            $exams = [];
            $catalog = $this->catalog($course);
            foreach (['Term1', 'Term2', 'Term3'] as $term) {
                $lessons = array_filter($catalog, static fn (array $lesson): bool => $lesson['term'] === $term);
                $questions = [];
                if (LearningCourses::isAcademic($course)) {
                    $balanced = [[], [], []];
                    $position = 0;
                    foreach (AcademicCurriculum::pack($course)['exam'][$term] as $variant => $round) {
                        foreach ($round as $item => $question) {
                            $lesson = array_values($lessons)[intdiv($item, 5)];
                            $level = match ($variant) {
                                0 => 'remember',
                                1, 2 => 'understand',
                                3 => str_starts_with($question['prompt'], 'In ') || str_starts_with($question['prompt'], 'Sa pagtalakay') ? 'remember' : 'apply',
                                default => 'analyze',
                            };
                            $balanced[$position++ % 3][] = ['type' => 'multiple_choice', 'prompt' => $question['prompt'], 'options' => $question['options'], 'correct' => [$question['options'][$question['correct']]], 'explanation' => $question['why'], 'item_spec' => ExamSpecification::item($lesson, $level)];
                        }
                    }
                    $questions = array_merge(...$balanced);
                } else {
                    for ($position = 0; $position < 10; $position++) {
                        foreach ($lessons as $lesson) {
                            $question = $lesson['quiz']['questions'][$position];
                            $questions[] = $question + ['item_spec' => ExamSpecification::item($lesson, ExamSpecification::suggestLevel($question['prompt']))];
                        }
                    }
                }
                foreach (['summative-1' => 'Summative Test 1', 'summative-2' => 'Summative Test 2', 'term-exam' => 'Term Exam'] as $index => $label) {
                    $key = ($course === 'arduino-uno' ? 'arduino' : $course).'-'.strtolower($term).'-'.$index;
                    $set = array_splice($questions, 0, 25);
                    $title = ($course === 'arduino-uno' ? 'Arduino' : LearningCourses::title($course)).' · '.str_replace('Term', 'Term ', $term).' · '.$label;
                    $exams[$key] = ['key' => $key, 'title' => $title, 'topic' => $label, 'term' => $term,
                        'course' => $course, 'assessment' => true, 'subtopics' => array_column($lessons, 'topic'), 'item_specs' => array_column($set, 'item_spec'),
                        'quiz' => ['title' => $title, 'instructions' => 'Answer 25 multiple-choice questions about the lessons in this term. Read the examples carefully. Your teacher reviews this editable draft before publishing.',
                            'questions' => array_map(fn (array $question): array => Arr::except($question, ['item_spec']), $set), 'xp' => 0, 'time_limit' => 25, 'untimed' => false, 'question_seconds' => 60]];
                }
            }

            return $exams;
        }

        $rounds = [];
        $topics = [];
        foreach (config('lessons') as $key => $lesson) {
            $term = $lesson['term'];
            $topics[$term][] = $lesson['topic'];
            foreach (explode("\n", config('term_exams.'.$key)) as $position => $row) {
                [$prompt, $answer, $choiceOne, $choiceTwo, $choiceThree] = explode(' | ', trim($row));
                $options = [$choiceOne, $choiceTwo, $choiceThree];
                array_splice($options, crc32($prompt) % 4, 0, [$answer]);
                $rounds[$term][$position][] = [
                    'type' => 'multiple_choice', 'prompt' => $prompt, 'options' => $options, 'correct' => [$answer],
                    'item_spec' => ExamSpecification::item($lesson, ExamSpecification::suggestLevel($prompt)),
                ];
            }
        }

        $exams = [];
        foreach ($rounds as $term => $questionsByRound) {
            ksort($questionsByRound);
            $questionSets = array_chunk(array_merge(...$questionsByRound), 25);
            foreach (['summative-1' => 'Summative Exam 1', 'summative-2' => 'Summative Exam 2', 'term-exam' => 'Term Exam'] as $type => $label) {
                $key = strtolower($term).'-'.$type;
                $title = 'Term '.substr($term, -1).' · '.$label;
                $set = array_shift($questionSets);
                $exams[$key] = [
                    'key' => $key, 'title' => $title, 'topic' => $label, 'term' => $term,
                    'course' => 'cpp', 'subtopics' => $topics[$term], 'assessment' => true, 'item_specs' => array_column($set, 'item_spec'),
                    'quiz' => [
                        'title' => $title, 'instructions' => 'Choose one answer for each of the 25 questions. Read code carefully and trace variable values before answering.',
                        'questions' => array_map(fn (array $question): array => Arr::except($question, ['item_spec']), $set),
                        'xp' => 0, 'time_limit' => 25, 'untimed' => false, 'question_seconds' => 60,
                    ],
                ];
            }
        }

        return $exams;
    }
}
