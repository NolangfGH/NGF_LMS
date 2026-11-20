<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LessonMedia
{
    public static function embedUrl(?string $url): ?string
    {
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

            return is_string($id) && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0' : null;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('~^(?:video/)?([0-9]{1,12})(?:/([A-Za-z0-9]{1,64}))?$~', $path, $matches)) {
            $hash = $query['h'] ?? $matches[2] ?? null;
            if ($hash !== null && (! is_string($hash) || ! preg_match('/\A[A-Za-z0-9]{1,64}\z/', $hash))) {
                return null;
            }

            return 'https://player.vimeo.com/video/'.$matches[1].'?'.http_build_query(array_filter(['h' => $hash, 'dnt' => '1']), '', '&', PHP_QUERY_RFC3986);
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
