<?php

namespace App\Services;

class LessonCodeNotes
{
    /** @param array<string, string> $notes */
    public static function annotate(string $code, array $notes): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $code));
        foreach ($lines as $line) {
            $statement = trim($line);
            if ($statement !== '' && ! str_starts_with($statement, '//') && ! isset($notes[$statement])) {
                return $code;
            }
        }
        $annotated = [];
        foreach ($lines as $index => $line) {
            $statement = trim($line);
            if (isset($notes[$statement]) && ! str_starts_with(trim($lines[$index - 1] ?? ''), '// Explain: ')) {
                preg_match('/^\s*/', $line, $indent);
                $annotated[] = ($indent[0] ?? '').'// Explain: '.$notes[$statement];
            }
            $annotated[] = $line;
        }

        return implode("\n", $annotated);
    }

    public static function definition(array $definition): array
    {
        $notes = config('lesson_code_notes');
        if (isset($definition['code'])) {
            $definition['code'] = self::annotate($definition['code'], $notes);
        }
        foreach ($definition['sections'] ?? [] as $index => $section) {
            if (! empty($section['code'])) {
                $definition['sections'][$index]['code'] = self::annotate($section['code'], $notes);
            }
        }

        return $definition;
    }
}
