<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LessonMedia
{
    public const OPTIONS = [
        'image_width' => ['full' => '100% · full width', 'large' => '75% · large', 'medium' => '50% · medium', 'small' => '35% · small'],
        'image_alignment' => ['center' => 'Center', 'left' => 'Left', 'right' => 'Right'],
        'image_aspect' => ['natural' => 'Original proportions', 'wide' => '16:9 landscape', 'standard' => '4:3 landscape', 'square' => '1:1 square', 'portrait' => '3:4 portrait'],
        'image_fit' => ['contain' => 'Fit whole image', 'cover' => 'Crop to fill'],
        'image_position' => ['center' => 'Center', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right'],
        'image_frame' => ['card' => 'Soft card', 'border' => 'Simple border', 'plain' => 'No frame'],
        'video_width' => ['full' => '100% · full width', 'large' => '75% · large', 'medium' => '50% · medium', 'small' => '35% · small'],
        'video_alignment' => ['center' => 'Center', 'left' => 'Left', 'right' => 'Right'],
        'video_aspect' => ['wide' => '16:9 landscape', 'standard' => '4:3 landscape', 'square' => '1:1 square', 'portrait' => '3:4 portrait'],
        'video_frame' => ['card' => 'Soft card', 'border' => 'Simple border', 'plain' => 'No frame'],
    ];

    public const TEXT_FIELDS = ['image_caption', 'video_title', 'video_caption'];

    public static function formatFields(): array
    {
        return [...array_keys(self::OPTIONS), ...self::TEXT_FIELDS, 'video_start'];
    }

    public static function formatRules(): array
    {
        $rules = [];
        foreach (self::OPTIONS as $field => $choices) {
            $rules['sections.*.'.$field] = ['nullable', Rule::in(array_keys($choices))];
        }
        foreach (self::TEXT_FIELDS as $field) {
            $rules['sections.*.'.$field] = ['nullable', 'string', 'max:'.($field === 'video_title' ? 120 : 500)];
        }
        $rules['sections.*.video_start'] = ['nullable', 'integer', 'between:0,14400'];

        return $rules;
    }

    public static function option(array $section, string $field): string
    {
        $choices = self::OPTIONS[$field];
        $value = $section[$field] ?? null;

        return is_string($value) && isset($choices[$value]) ? $value : array_key_first($choices);
    }

    /** Return only controlled CSS class names, never arbitrary saved styles. */
    public static function layout(array $section, string $kind): string
    {
        $class = 'lesson-media lesson-media-width-'.self::option($section, $kind.'_width').' lesson-media-align-'.self::option($section, $kind.'_alignment').' lesson-media-frame-'.self::option($section, $kind.'_frame');
        if ($kind === 'image') {
            $class .= ' lesson-media-aspect-'.self::option($section, 'image_aspect').' lesson-media-fit-'.self::option($section, 'image_fit').' lesson-media-position-'.self::option($section, 'image_position');
        } else {
            $class .= ' lesson-media-aspect-'.self::option($section, 'video_aspect');
        }

        return $class;
    }

    public static function embedUrl(?string $url, int $start = 0): ?string
    {
        $start = max(0, min(14400, $start));
        $parts = parse_url(trim($url ?? ''));
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['pass'])
            || isset($parts['user']) || isset($parts['port']) && $parts['port'] !== 443) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        $id = null;
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
            if ($host === 'youtu.be') {
                $id = $path;
            } elseif ($path === 'watch') {
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^(?:embed|shorts|live)/([A-Za-z0-9_-]{11})$~', $path, $matches)) {
                $id = $matches[1];
            }

            return is_string($id) && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0'.($start ? '&start='.$start : '') : null;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('~^(?:video/)?([0-9]{1,12})(?:/([A-Za-z0-9]{1,64}))?$~', $path, $matches)) {
            $hash = $query['h'] ?? $matches[2] ?? null;
            if ($hash !== null && (! is_string($hash) || ! preg_match('/\A[A-Za-z0-9]{1,64}\z/', $hash))) {
                return null;
            }

            return 'https://player.vimeo.com/video/'.$matches[1].'?'.http_build_query(array_filter(['h' => $hash, 'dnt' => '1']), '', '&', PHP_QUERY_RFC3986).($start ? '#t='.$start.'s' : '');
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  list<array<string, mixed>>  $previous
     * @param  list<string>  $uploaded
     * @return list<array<string, mixed>>
     */
    public function sections(array $sections, array $previous, array &$uploaded): array
    {
        $owned = collect($previous)->filter(fn (array $section): bool => filled($section['media_key'] ?? null))->keyBy('media_key');
        $result = [];
        foreach ($sections as $index => $section) {
            $key = $section['media_key'] ?? null;
            if ($key && ! $owned->has($key)) {
                throw ValidationException::withMessages(['sections.'.$index.'.media_key' => 'This explanation changed. Refresh the lesson before saving.']);
            }
            $old = $key ? $owned->get($key) : [];
            $value = LessonTextFormatting::normalize(Arr::only($section, ['title', 'text', 'formatted_text', 'code', 'output', 'image_alt', 'video_url']), $index);
            $value += Arr::only($section, self::formatFields()) + Arr::only($old, self::formatFields());
            foreach (self::OPTIONS as $field => $choices) {
                if (array_key_exists($field, $value)) {
                    $value[$field] = self::option($value, $field);
                }
            }
            if (isset($value['video_start'])) {
                $value['video_start'] = (int) $value['video_start'];
            }
            $value['media_key'] = $key ?: (string) Str::uuid();
            if (empty($section['remove_image']) && ! empty($old['image_path'])) {
                $value += Arr::only($old, ['image_path', 'image_mime']);
            }
            if (($section['image'] ?? null) instanceof UploadedFile) {
                $path = $section['image']->store('lesson-resources', 'local');
                if (! is_string($path)) {
                    throw new \RuntimeException('The explanation image could not be stored.');
                }
                $uploaded[] = $path;
                $value['image_path'] = $path;
                $value['image_mime'] = $section['image']->getMimeType();
            }
            $result[] = $value;
        }

        return $result;
    }
}
