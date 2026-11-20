<?php

namespace App\Services;

class AcademicCurriculum
{
    public const REFERENCE = 'https://www.deped.gov.ph/revised-k-to-10-curriculum/';

    public const GUIDE = 'Original learning units aligned to DepEd Revised K–10 (MATATAG) learning-area strands; adapted to the three-term calendar under DO 009, s. 2026. Teacher review of the locally adopted competencies and pacing is required.';

    public const ELECTIVE_GUIDE = 'School-adaptable RSHS enrichment draft. Not an official nationally prescribed separate elective curriculum; the school verifies its approved competencies, sequence and resources.';

    public const UNIT = 'Academic Study Notebook';

    public const STARTER = "Topic and task:\nMy answer / proposed solution:\nSupporting evidence or steps:\nExplanation of my reasoning:\nCheck and revision:\n";

    private static function rows(string $course, string $source): array
    {
        $rows = [];
        foreach (explode("\n", trim($source)) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode('|', trim($line));
            [$slug, $topic, $example, $task] = $parts;
            $facts = array_slice($parts, 4);
            $key = $course.'-'.$slug;
            $rows[$key] = ['topic' => $topic, 'example' => $example, 'task' => $task,
                'term' => 'Term'.(intdiv(count($rows), 3) + 1),
                'facts' => array_map(static fn (string $fact): array => explode('~', $fact, 2), $facts)];
        }

        return $rows;
    }

    public static function practiceActivities(array $sources): array
    {
        $activities = [];
        foreach ($sources as $course => $source) {
            foreach (self::rows($course, $source) as $key => $row) {
                $activities[$key] = ['title' => LearningCourses::title($course).' · '.$row['topic'], 'objective' => $row['task'], 'sample_output' => $row['example'],
                    'hint' => 'Show your reasoning, identify relevant evidence and check your answer against the example. Explain a limitation or revision.', 'code' => self::STARTER, 'environment' => 'academic'];
            }
        }

        return $activities;
    }

    public static function pack(string $course): array
    {
        static $packs = [];
        if (isset($packs[$course])) {
            return $packs[$course];
        }
        $source = config('academic_curriculum.'.$course, '');
        if ($source === '') {
            return ['lessons' => [], 'activities' => [], 'articles' => [], 'recitations' => [], 'exam' => [], 'bee' => [], 'pre' => [], 'post' => []];
        }
        $title = LearningCourses::title($course);
        $grade = LearningCourses::grade($course);
        $guide = LearningCourses::isElective($course) ? self::ELECTIVE_GUIDE : self::GUIDE;
        $filipino = preg_match('/^(ap|ve|filipino)/', $course) === 1;
        $lessons = $activities = $articles = $recitations = $exam = $bee = $pre = $post = [];
        $application = [];
        foreach (explode("\n", trim(config('academic_application_questions.'.$course, ''))) as $line) {
            if (trim($line) !== '') {
                [$slug, $prompt, $answer, $wrong1, $wrong2, $wrong3, $why] = explode('|', $line);
                $application[$course.'-'.$slug] = self::question($prompt, $answer, [$wrong1, $wrong2, $wrong3], $why);
            }
        }
        foreach (self::rows($course, $source) as $key => $row) {
            ['topic' => $topic, 'example' => $example, 'task' => $task, 'term' => $term, 'facts' => $facts] = $row;
            $terms = array_column($facts, 0);
            $definitions = array_column($facts, 1);
            $notes = implode("\n\n", array_map(static fn (array $fact): string => $fact[0].' — '.$fact[1], $facts));
            $checks = $questions = $recitation = $rounds = [];
            foreach ($facts as $index => [$word, $definition]) {
                $alternativeTerms = array_values(array_diff($terms, [$word]));
                $alternativeDefinitions = array_values(array_diff($definitions, [$definition]));
                $correctPair = $word.' — '.$definition;
                $wrongPairs = array_map(fn (string $other): string => $word.' — '.$other, array_slice($alternativeDefinitions, 0, 3));
                $check = self::question($topic.' · '.($filipino ? 'Alin ang tinutukoy? '.$definition : 'Which concept is described? '.$definition), $word, array_slice($alternativeTerms, 0, 3), $correctPair);
                $explain = self::question($topic.' · '.($filipino ? 'Ano ang wastong paliwanag sa '.$word.'?' : 'Which explanation best describes '.$word.'?'), $definition, array_slice($alternativeDefinitions, 0, 3), $correctPair);
                $pair = self::question($topic.' · '.($filipino ? 'Aling pares ng konsepto at paliwanag ang wasto para sa '.$word.'?' : 'Which concept–description pair is accurate for '.$word.'?'), $correctPair, $wrongPairs, $correctPair);
                $distinguish = self::question($filipino ? 'Sa pagtalakay ng '.$topic.', alin ang katawagang angkop sa paliwanag na ito? '.$definition : 'In '.$topic.', which concept specifically fits this description? '.$definition, $word, array_slice(array_reverse($alternativeTerms), 0, 3), $correctPair);
                $misconception = self::question($topic.' · '.($filipino ? 'Alin sa mga pares na ito ang may maling pag-uugnay? Suriin ang '.$word.' at kaugnay na konsepto.' : 'Which of these pairs is mismatched? Review '.$word.' and related concepts.'), $word.' — '.$alternativeDefinitions[0], [$correctPair, $alternativeTerms[1].' — '.$facts[array_search($alternativeTerms[1], $terms, true)][1], $alternativeTerms[2].' — '.$facts[array_search($alternativeTerms[2], $terms, true)][1]], $filipino ? 'Tandaan: '.$correctPair : 'Remember: '.$correctPair);
                $checks[] = self::question($check['prompt'], $word, array_slice($alternativeTerms, 0, 2), $correctPair);
                $questions[] = $distinguish;
                $questions[] = $explain;
                $recitation[] = ['question' => ($filipino ? 'Tukuyin ang konsepto: ' : 'Identify the concept: ').$definition, 'accepted_answers' => [$word], 'case_sensitive' => false];
                foreach ([$check, $explain, $pair, $distinguish, $misconception] as $variant => $question) {
                    $rounds[$variant][] = $question;
                }
            }
            if (isset($application[$key])) {
                $questions[0] = $rounds[3][0] = $application[$key];
            }
            foreach ($rounds as $variant => $set) {
                $exam[$term][$variant] = [...($exam[$term][$variant] ?? []), ...$set];
            }
            $position = count($lessons);
            $addSkill = static fn (array $question): array => $question + ['skill' => $term.' · '.$topic];
            $pre = [...$pre, ...array_map($addSkill, $rounds[0]), ...($position < 5 ? [$addSkill($rounds[1][0])] : [])];
            $postSet = $rounds[2];
            if (isset($application[$key])) {
                $postSet[0] = $application[$key];
            }
            $post = [...$post, ...array_map($addSkill, $postSet), ...($position < 5 ? [$addSkill($rounds[4][0])] : [])];
            $sections = [
                ['title' => $filipino ? 'Mahahalagang konsepto' : 'Key concepts and explanations', 'text' => $notes],
                ['title' => $filipino ? 'Halimbawa at pagsusuri' : 'Worked classroom example', 'text' => $example],
                ['title' => $filipino ? 'Sariling pagsasanay' : 'Apply and explain', 'text' => $task],
            ];
            $lessons[$key] = ['title' => $title.' · '.$topic, 'topic' => $topic, 'objective' => $task, 'course' => $course, 'grade_level' => $grade, 'term' => $term,
                'practice' => $key, 'minutes' => 25, 'icon' => LearningCourses::isElective($course) ? 'science' : 'menu_book', 'version' => 1,
                'reference_url' => self::REFERENCE, 'subtopics' => $terms, 'sections' => $sections, 'code' => $example,
                'output' => $filipino ? 'Wastong paliwanag na may halimbawa at batayan.' : 'A justified answer showing relevant concepts, steps and evidence.',
                'mistake' => $filipino ? 'Huwag magbigay ng sagot na walang katuwiran o batayan.' : 'A correct label alone is not an explanation. Show why it fits the evidence and check your reasoning.', 'checks' => $checks];
            $lessons[$key]['ilaw'] = ['title' => $lessons[$key]['title'], 'learning_area' => $title, 'grade_section' => $grade.' · teacher selects section', 'sessions' => 2,
                'references' => $guide."\n".self::REFERENCE, 'ai_declaration' => 'AI-assisted original draft; teacher checks accuracy, grade-level competency alignment, accommodations and school-approved pacing before use.',
                'competency' => $topic.': '.$task, 'intentions' => $task, 'learner_context' => 'Check readiness with a familiar example and allow accessible response formats, pair work or printed materials as appropriate.',
                'pre_lesson' => 'Elicit prior ideas and ask learners to explain a relevant example or misconception.', 'learning_experience' => "Session 1: retrieval 10 min; discuss and model 20 min; guided application 15 min; exit response 5 min.\nSession 2: recall 5 min; independent task 25 min; feedback 10 min; checkpoints and reflection 10 min.\n".$task,
                'resources' => 'CodeQuest lesson + Study Notebook; teacher-approved textbooks, manipulatives, reference materials or accessible print alternatives.', 'integration' => 'Use a relevant school or MIMAROPA community context; respect different perspectives and verify information.',
                'assessing_learning' => 'Use five checkpoint items and the editable 10-question quiz. Assess the independent task for accuracy, reasoning, presentation and explanation: 0–4 per criterion. Worked example: '.$example,
                'ways_forward' => 'Use item analysis to identify concepts for reteaching; give an alternate example, scaffold the task and request a revised explanation.'];
            $activities[$key] = ['term' => $term, 'difficulty' => ['Term1' => 'Easy', 'Term2' => 'Mid', 'Term3' => 'Hard'][$term], 'questions' => $questions];
            foreach (['assignment', 'challenge'] as $kind) {
                $activities[$key][$kind] = ['title' => $title.' · '.($kind === 'assignment' ? 'Practice: ' : 'Challenge: ').$topic, 'unit' => self::UNIT,
                    'objective' => $task.($kind === 'challenge' ? ' Create a different example and justify your answer using relevant evidence. Compare one alternative.' : ' Explain the steps or evidence in your own words.'),
                    'sample_input' => '', 'sample_output' => $example, 'starter_code' => self::STARTER, 'test_cases' => [], 'hints' => ['Review the key ideas.', 'Show evidence or intermediate steps.', 'Check your result and identify one revision.']];
            }
            $articles[$key] = ['title' => $title.' · '.$topic, 'category' => 'Projects', 'introduction' => $filipino ? 'Talakayin at iugnay ang aralin sa sariling karanasan.' : 'Connect these ideas to a new classroom context.',
                'prompts' => [$task, $filipino ? 'Ipaliwanag ang batayan ng iyong sagot at magbigay ng sariling halimbawa.' : 'Explain one of your choices with evidence, and provide a new example.'], 'teacher_notes' => 'Review accuracy, evidence and clarity; allow an accessible equivalent response.'];
            $recitations[$key] = $recitation;
        }
        foreach (['easy', 'average', 'difficult'] as $index => $level) {
            $term = 'Term'.($index + 1);
            $bank = [];
            foreach ([0, 1, $level === 'difficult' ? 4 : 2, 3] as $variant) {
                foreach ([0, 5, 10, 1, 6] as $position) {
                    $bank[] = $exam[$term][$variant][$position];
                }
            }
            $questions = array_map(static fn (array $question): array => ['type' => 'multiple_choice', ...$question], $bank);
            if ($level === 'difficult') {
                $pool = array_values(array_filter($recitations, fn (array $round, string $key): bool => $lessons[$key]['term'] === $term, ARRAY_FILTER_USE_BOTH));
                foreach ([2, 10, 17] as $i => $position) {
                    $fact = $pool[$i][0];
                    $questions[$position] = ['type' => 'fill_blank', 'prompt' => $fact['question'], 'accepted_answers' => $fact['accepted_answers'], 'case_sensitive' => false, 'why' => 'Review the lesson terminology.'];
                }
            }
            $bee[$level] = ['regular' => array_slice($questions, 0, 15), 'tiebreak' => array_slice($questions, 15, 5)];
        }

        return $packs[$course] = compact('lessons', 'activities', 'articles', 'recitations', 'exam', 'bee', 'pre', 'post');
    }

    private static function question(string $prompt, string $answer, array $distractors, string $why): array
    {
        $correct = crc32($prompt) % (count($distractors) + 1);
        array_splice($distractors, $correct, 0, [$answer]);

        return ['prompt' => $prompt, 'options' => $distractors, 'correct' => $correct, 'why' => $why];
    }
}
