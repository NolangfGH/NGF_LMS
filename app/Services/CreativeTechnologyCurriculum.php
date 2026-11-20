<?php

namespace App\Services;

class CreativeTechnologyCurriculum
{
    public const REFERENCE = 'https://nighgeeelllllll.github.io/NighgelFirstWebSite/PHOTOS/Creative-Technology-CG-1.pdf';

    public const GUIDE = 'DepEd Special Science Program Creative Technologies Curriculum Guide (August 2017); domain alignment, locally arranged into three terms.';

    public const DESIGN_UNIT = 'Creative Technology 7 Design';

    public const ROBOTICS_UNIT = 'Creative Technology 10 Robotics';

    public static function build(string $course, array $topics, array $arduinoLessons): array
    {
        $design = $course === 'ct7';
        $title = LearningCourses::title($course);
        $grade = $design ? 'Grade 7' : 'Grade 10';
        $starter = $design
            ? "Project title:\nUser need and criteria:\nDesign / measurements (units):\nSteps and decisions:\nExpected and observed results:\nRevision and limitation:\n"
            : CodingMatchService::starterCode($course);
        $lessons = $activities = $articles = $practice = $recitations = [];
        $number = 0;
        foreach ($topics as $slug => [$topic, $domain, $goal, $task, $example, $rows]) {
            $key = $course.'-'.$slug;
            $number++;
            $term = $number <= 9 ? 'Term1' : ($number <= 17 ? 'Term2' : 'Term3');
            $facts = array_map(static fn (string $row): array => explode('|', $row), $rows);
            $terms = array_column($facts, 0);
            $definitions = array_column($facts, 1);
            $checks = $quiz = $recitation = [];
            foreach ($facts as [$word, $definition, $scenario]) {
                $others = array_values(array_diff($terms, [$word]));
                $checks[] = self::question($topic.': Which term means “'.mb_strtolower($definition).'”?', $word, array_slice($others, 0, 2), $definition);
                $quiz[] = self::question($scenario.' Which concept best matches this situation?', $word, array_slice($others, 0, 3), $definition);
                $recitation[] = ['question' => 'In '.$topic.', identify the term: '.$definition.'.', 'accepted_answers' => [$word], 'case_sensitive' => false];
            }
            foreach ($facts as [$word, $definition]) {
                $quiz[] = self::question('In '.$topic.', which description best explains '.$word.'?', $definition, array_slice(array_values(array_diff($definitions, [$definition])), 0, 3), $definition);
            }
            $concepts = implode("\n", array_map(static fn (array $fact): string => $fact[0].' — '.$fact[1].'.', $facts));
            $worked = $design ? ['code' => $example, 'output' => 'A documented design decision with labeled evidence.']
                : $arduinoLessons['arduino-'.$topics[$slug][6]];
            $sections = [
                ['title' => 'Key ideas and vocabulary', 'text' => $concepts],
                ['title' => 'Worked classroom example', 'text' => $example],
                ['title' => 'Try, test and improve', 'text' => $task.($design
                    ? ' Use a teacher-approved drawing, productivity or CAD tool, or paper if needed. Record what you actually observed in the Design Notebook.'
                    : ' First trace the example sketch. Adapt it to the task; use simulated data or Wokwi, then test teacher-inspected low-voltage hardware only if available. Never connect mains or unapproved loads.')],
            ];
            $lesson = [
                'title' => $title.' · '.$topic, 'topic' => $topic, 'objective' => $goal, 'course' => $course, 'grade_level' => $grade,
                'term' => $term, 'practice' => $key, 'minutes' => 18, 'icon' => $design ? 'design_services' : 'memory', 'version' => 1,
                'reference_url' => self::REFERENCE, 'subtopics' => [$domain, ...$terms], 'sections' => $sections,
                'code' => $worked['code'], 'output' => $worked['output'], 'mistake' => $design
                    ? 'A neat drawing alone is not proof that the product fits or meets its user’s needs. Label units and distinguish predicted from observed evidence.'
                    : 'A compiled sketch alone does not prove that wiring, sensors or physical behavior are correct. Check the exact hardware ratings and record actual or simulated results.',
                'checks' => $checks,
            ];
            $lesson['ilaw'] = [
                'title' => $lesson['title'], 'learning_area' => $title, 'grade_section' => $grade.' · teacher selects section', 'sessions' => $slug === 'capstone' ? 4 : 2,
                'references' => self::GUIDE."\n".self::REFERENCE, 'ai_declaration' => 'AI-assisted draft. Teacher verifies the curriculum alignment, local competencies, examples, assessment and safety before teaching.',
                'competency' => $grade.' · '.$domain.' strand. Suggested learning outcome: '.$goal,
                'intentions' => $goal."\nUse evidence to explain one design decision and improve one result.",
                'learner_context' => 'Check prior knowledge using a familiar school problem. Offer a paired demonstration, printed materials and individual explanation where devices are shared.',
                'pre_lesson' => 'Ask learners to predict the outcome of the worked example. Review safe tool use and relevant prior vocabulary.',
                'learning_experience' => "Suggested session 1: readiness 10 min; discussion and example 15 min; guided task 20 min; exit question 5 min.\nSession 2: retrieval 5 min; independent/pair task 25 min; evidence review 10 min; checkpoint and reflection 10 min.\n".$task,
                'resources' => 'CodeQuest lesson and private workspace; '.($design ? 'paper, ruler and teacher-approved productivity/graphics/CAD software, with printed alternatives.' : 'Wokwi or teacher-inspected low-voltage controller and compatible parts, with supplied-data alternative.'),
                'integration' => 'Connect the task to a school need, mathematics and responsible technology. Include accessible explanations and avoid collecting unnecessary personal information.',
                'assessing_learning' => 'Ask for a labeled prediction and observation. Check the five lesson questions, then use the editable ten-question quiz. Practical evidence: '.$example."\nRubric: correctness, logic, readability and explanation, 0–4 each. XP is a learning reward, not an official grade.",
                'ways_forward' => 'Review actual class evidence; reteach the least mastered concept with a worked example. Ask learners to revise their design or sketch, explain changes and retest.',
            ];
            $lessons[$key] = $lesson;
            $activities[$key] = ['term' => $term, 'difficulty' => $number <= 9 ? 'Easy' : ($number <= 17 ? 'Mid' : 'Hard'), 'questions' => $quiz];
            foreach (['assignment', 'challenge'] as $kind) {
                $activities[$key][$kind] = [
                    'title' => $title.' · '.($kind === 'assignment' ? 'Practice: ' : 'Design challenge: ').$topic,
                    'unit' => $design ? self::DESIGN_UNIT : self::ROBOTICS_UNIT,
                    'objective' => $task.($kind === 'challenge' ? ' Create a different example for a school need, compare two approaches, and explain one improvement using a test.' : ' Record your own steps and show how your output meets the requirements.'),
                    'sample_input' => '', 'sample_output' => $example, 'starter_code' => $starter, 'test_cases' => [],
                    'hints' => ['Review the worked example and vocabulary.', 'Label measurements or signals and record expected versus observed evidence.', 'Explain one limitation and a tested revision.'],
                ];
            }
            $recitations[$key] = $recitation;
            $articles[$key] = ['title' => $title.' notebook · '.$topic, 'category' => 'Projects',
                'introduction' => 'Use this discussion to connect '.$topic.' to a practical school problem.',
                'prompts' => ['Explain the worked example using two terms from this lesson.', 'Design your own example: '.$task, 'What evidence would show success? Explain one limitation and potential revision.'],
                'teacher_notes' => 'Look for the stated learning outcome: '.$goal.' Ask learners to justify a decision and distinguish actual observations from predictions.'];
            $practice[$key] = ['title' => $title.' practice · '.$topic, 'objective' => $activities[$key]['assignment']['objective'],
                'sample_output' => $example, 'hint' => implode(' ', $activities[$key]['assignment']['hints']),
                'code' => $starter, 'environment' => LearningCourses::environment($course)];
        }
        $bee = [];
        foreach (['easy', 'average', 'difficult'] as $index => $level) {
            $term = 'Term'.($index + 1);
            $pool = array_filter($activities, fn (array $activity): bool => $activity['term'] === $term);
            $questions = [];
            for ($position = 0; $position < 10 && count($questions) < 20; $position++) {
                foreach ($pool as $key => $activity) {
                    $question = $activity['questions'][$position];
                    $questions[] = ['type' => 'multiple_choice', ...$question];
                }
            }
            if ($level === 'difficult') {
                foreach ([2, 10, 17] as $position) {
                    $key = array_keys($pool)[$position % count($pool)];
                    $fact = $recitations[$key][intdiv($position, count($pool)) % 5];
                    $questions[$position] = ['type' => 'fill_blank', 'prompt' => $fact['question'], 'accepted_answers' => $fact['accepted_answers'], 'case_sensitive' => false, 'why' => 'Review the relevant topic vocabulary.'];
                }
            }
            $bee[$level] = ['regular' => array_slice($questions, 0, 15), 'tiebreak' => array_slice($questions, 15, 5)];
        }

        return compact('title', 'grade', 'lessons', 'activities', 'articles', 'practice', 'recitations', 'bee') + ['reference_url' => self::REFERENCE];
    }

    private static function question(string $prompt, string $answer, array $distractors, string $why): array
    {
        $correct = crc32($prompt) % (count($distractors) + 1);
        array_splice($distractors, $correct, 0, [$answer]);

        return ['prompt' => $prompt, 'options' => $distractors, 'correct' => $correct, 'why' => $answer.' — '.$why.'.'];
    }
}
