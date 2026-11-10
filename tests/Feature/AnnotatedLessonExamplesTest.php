<?php

namespace Tests\Feature;

use App\Services\LessonCodeNotes;
use Tests\TestCase;

class AnnotatedLessonExamplesTest extends TestCase
{
    public function test_all_fifty_default_worked_programs_explain_every_source_line_without_changing_code(): void
    {
        $courses = ['cpp' => config('lessons'), 'arduino' => config('arduino_curriculum.lessons')];
        foreach ($courses as $lessons) {
            $this->assertCount(25, $lessons);
            foreach ($lessons as $key => $lesson) {
                $lines = explode("\n", $lesson['code']);
                foreach ($lines as $index => $line) {
                    if (trim($line) !== '' && ! str_starts_with(trim($line), '//')) {
                        $this->assertStringStartsWith('// Explain: ', trim($lines[$index - 1] ?? ''), $key.': '.trim($line));
                    }
                }
                $this->assertSame($lesson['code'], LessonCodeNotes::annotate($lesson['code'], config('lesson_code_notes')), $key);
                $raw = preg_replace('/^[\t ]*\/\/ Explain: .*\n?/m', '', $lesson['code']);
                $this->assertSame($lesson['code'], LessonCodeNotes::annotate($raw, config('lesson_code_notes')), $key);
            }
        }
        if ($export = getenv('ANNOTATED_CODE_EXPORT')) {
            file_put_contents($export, json_encode($courses, JSON_THROW_ON_ERROR));
        }
    }

    public function test_custom_teacher_code_is_preserved_and_known_examples_can_be_annotated_in_sections(): void
    {
        $custom = "// A teacher-authored program\nint main() {\n  int teacherValue = 2026;\n}";
        $this->assertSame($custom, LessonCodeNotes::annotate($custom, config('lesson_code_notes')));
        $definition = ['code' => $custom, 'sections' => [['title' => 'Trace it', 'text' => 'Read the output.', 'code' => 'std::cout << total;', 'output' => '12']]];
        $annotated = LessonCodeNotes::definition($definition);
        $this->assertSame($custom, $annotated['code']);
        $this->assertSame('12', $annotated['sections'][0]['output']);
        $this->assertStringContainsString('// Explain: Print the current value stored in total.', $annotated['sections'][0]['code']);
        $this->assertSame($annotated, LessonCodeNotes::definition($annotated));
    }
}
