<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Validation\ValidationException;

class LessonTextFormatting
{
    private const TAGS = ['p', 'div', 'br', 'strong', 'em', 'u', 's', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'span', 'sub', 'sup', 'code'];

    private const CLASSES = ['lesson-bold', 'lesson-italic', 'lesson-underline', 'lesson-strike', 'lesson-align-left', 'lesson-align-center', 'lesson-align-right', 'lesson-color-green', 'lesson-color-blue', 'lesson-color-red', 'lesson-highlight'];

    private const COLORS = ['#14532d' => 'lesson-color-green', 'rgb(20,83,45)' => 'lesson-color-green', '#1e40af' => 'lesson-color-blue', 'rgb(30,64,175)' => 'lesson-color-blue', '#991b1b' => 'lesson-color-red', 'rgb(153,27,27)' => 'lesson-color-red'];

    private const DISCARD = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'textarea', 'button', 'select', 'input', 'link', 'meta', 'head', 'title'];

    public static function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);

        return $body ? self::children($body) : '';
    }

    private static function children(DOMNode $parent): string
    {
        $result = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) {
                $result .= htmlspecialchars($node->data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (in_array($tag, self::DISCARD, true)) {
                    continue;
                }
                $tag = match ($tag) {
                    'b' => 'strong', 'i' => 'em', 'strike', 'del' => 's',
                    'h1', 'h2' => 'h3', 'h5', 'h6' => 'h4', 'font' => 'span',
                    default => $tag,
                };
                $content = self::children($node);
                if (! in_array($tag, self::TAGS, true)) {
                    $result .= $content;

                    continue;
                }
                if ($tag === 'br') {
                    $result .= '<br>';

                    continue;
                }
                $classes = self::classes($node);
                $attribute = $classes ? ' class="'.implode(' ', $classes).'"' : '';
                $result .= '<'.$tag.$attribute.'>'.$content.'</'.$tag.'>';
            }
        }

        return $result;
    }

    /** @return list<string> */
    private static function classes(DOMElement $element): array
    {
        $classes = preg_split('/\s+/', $element->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY);
        $color = strtolower(str_replace(' ', '', $element->getAttribute('color')));
        $classes[] = self::COLORS[$color] ?? '';
        $classes[] = 'lesson-align-'.strtolower($element->getAttribute('align'));
        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $value = strtolower(trim($value));
            $classes[] = match (strtolower(trim($property))) {
                'font-weight' => in_array($value, ['bold', '700'], true) ? 'lesson-bold' : '',
                'font-style' => $value === 'italic' ? 'lesson-italic' : '',
                'text-decoration', 'text-decoration-line' => match ($value) {
                    'underline' => 'lesson-underline', 'line-through' => 'lesson-strike', default => '',
                },
                'text-align' => 'lesson-align-'.$value,
                'color' => self::COLORS[str_replace(' ', '', $value)] ?? '',
                'background-color' => in_array(str_replace(' ', '', $value), ['#fef08a', 'rgb(254,240,138)'], true) ? 'lesson-highlight' : '',
                default => '',
            };
        }

        return array_values(array_unique(array_intersect($classes, self::CLASSES)));
    }

    public static function plainText(string $safeHtml): string
    {
        $separated = preg_replace('~<br>|</(?:p|div|h3|h4|li|ul|ol|blockquote)>~', "\n", $safeHtml);

        return trim(str_replace("\u{00a0}", ' ', html_entity_decode(strip_tags($separated), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public static function normalize(array $section, int $index): array
    {
        if (! filled($section['formatted_text'] ?? null)) {
            unset($section['formatted_text']);

            return $section;
        }
        $html = self::sanitize($section['formatted_text']);
        $plain = self::plainText($html);
        if ($plain === '' || mb_strlen($plain) > 5000 || mb_strlen($html) > 20000) {
            throw ValidationException::withMessages(['sections.'.$index.'.text' => 'Keep each explanation between 1 and 5,000 readable characters with simple formatting.']);
        }
        $section['formatted_text'] = $html;
        $section['text'] = $plain;

        return $section;
    }

    /** @param array<string, mixed> $section */
    public static function render(array $section): string
    {
        if (is_string($section['formatted_text'] ?? null) && filled($section['formatted_text'])) {
            $html = self::sanitize($section['formatted_text']);
            if (self::plainText($html) !== '') {
                return $html;
            }
        }

        return str_replace(["\r\n", "\r", "\n"], '<br>', e(is_string($section['text'] ?? null) ? $section['text'] : ''));
    }
}
