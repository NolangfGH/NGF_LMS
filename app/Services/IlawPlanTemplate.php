<?php

namespace App\Services;

class IlawPlanTemplate
{
    public const ORDER_URL = 'https://www.deped.gov.ph/wp-content/uploads/DO_s2026_016r.pdf';

    public const GROUPS = [
        'Lesson details' => [
            'title' => ['Lesson title', 'Name this lesson.'],
            'learning_area' => ['Learning area', 'Identify the subject or integrated learning areas.'],
            'grade_section' => ['Grade level and section', 'Specify the class you are planning for.'],
            'sessions' => ['Number of sessions', 'Record the planned number of meetings.'],
            'references' => ['References', 'List the curriculum and teaching resources you consulted.'],
            'ai_declaration' => ['AI use declaration', 'If you used AI for permitted support, identify the tool and describe how you used and checked it.'],
        ],
        'I · Intentions' => [
            'competency' => ['Competency and curriculum standards', 'Identify the relevant competency and applicable standards from your curriculum.'],
            'intentions' => ['Learning objectives', 'Write what learners should be able to demonstrate by the end.'],
            'learner_context' => ['Learner context', 'Record relevant prior learning, strengths and barriers. Use class-level observations.'],
        ],
        'L · Learning Experience' => [
            'pre_lesson' => ['Pre-lesson activity', 'Plan how you will check readiness and recall prior learning.'],
            'learning_experience' => ['Lesson flow', 'Describe how you will model, guide practice and support independent work.'],
            'resources' => ['Learning resources', 'List materials, accessibility supports and an offline alternative where relevant.'],
            'integration' => ['Integration and contextualization', 'Identify meaningful classroom, community or cross-subject connections.'],
        ],
        'A · Assessing Learning' => [
            'assessing_learning' => ['Checks for understanding', 'Identify when you will check learning, what evidence you expect and how you will respond.'],
        ],
        'W · Ways Forward' => [
            'ways_forward' => ['Reflection and next steps', 'Plan how evidence will inform reteaching, enrichment and follow-up practice.'],
        ],
    ];

    public static function defaults(array $lesson): array
    {
        return [
            'title' => $lesson['title'],
            'learning_area' => 'C++ Programming',
            'sessions' => 1,
            'references' => 'NGF_C++CodeQuest · '.$lesson['topic']."\n".($lesson['reference_url'] ?? config('cpp_curriculum.reference_url')),
        ];
    }
}
