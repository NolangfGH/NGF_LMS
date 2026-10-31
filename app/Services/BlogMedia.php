<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BlogMedia
{
    public static function audioRules(): array
    {
        $allowed = ['audio/webm', 'video/webm', 'audio/ogg', 'application/ogg', 'audio/mpeg', 'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav'];

        return ['bail', 'nullable', 'file', 'max:2048', 'extensions:webm,ogg,mp3,m4a,mp4,wav',
            function ($attribute, $file, $fail) use ($allowed) {
                if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()), $allowed, true)) {
                    $fail('Upload a valid WebM, Ogg, MP3, M4A or WAV voice message.');
                }
            }];
    }

    public static function upload(Request $request, string $field, string $folder): array
    {
        if (! $request->hasFile($field)) {
            return [null, null];
        }
        $file = $request->file($field);
        $path = $file->store('blog/'.$folder, 'local');
        if (! $path) {
            throw ValidationException::withMessages([$field => 'The attachment could not be saved. Try again.']);
        }

        return [$path, $file->getMimeType()];
    }

    public static function discard(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
