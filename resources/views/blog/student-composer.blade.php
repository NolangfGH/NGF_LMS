@php
    $targetId = $targetComment?->id;
    $timed = (bool)$post->response_time_limit_minutes || ($activeAttempt && $activeAttempt['comment_id'] === $targetId);
@endphp
<form method="POST" action="{{ $targetComment ? route('student.blog.reply',['post'=>$post,'comment'=>$targetComment]) : route('student.blog.comment',$post) }}" enctype="multipart/form-data" data-blog-composer data-blog-timed="{{ $timed ? 'true' : 'false' }}" data-comment-id="{{ $targetId }}" data-start-url="{{ route('student.blog.timer.start',$post) }}" class="mt-5 space-y-4">@csrf
    @if($targetComment)<input type="hidden" name="conversation_id" value="{{ $targetId }}">@endif
    @if($timed)
    <div class="rounded-2xl border border-green-200 bg-paper p-4"><div class="flex flex-wrap items-center justify-between gap-3"><p class="text-xs font-bold text-primary">Timed response · {{ $post->response_time_limit_minutes ?? 'Saved' }} minutes</p><button type="button" data-blog-start class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white">Start response</button><span data-blog-time-label hidden class="font-mono text-sm font-bold text-primary"></span></div><div data-blog-time-track hidden class="mt-3 h-3 overflow-hidden rounded-full bg-green-100" role="progressbar" aria-label="Response time remaining" aria-valuemin="0" aria-valuemax="100"><div data-blog-time-bar class="h-full w-full rounded-full bg-green-700 transition-[width] duration-500"></div></div><p data-blog-save-status role="status" class="mt-3 text-xs leading-6 text-secondary">The timer starts when you press Start response. Drafts are saved while you write and sent automatically when time ends.</p></div>
    @endif
    <fieldset data-blog-response-fields @disabled($timed) class="space-y-4 disabled:opacity-60">
        <label class="block text-sm font-bold text-primary">{{ $targetComment ? 'Your next response' : 'Your comment / insight' }}<textarea name="body" maxlength="5000" rows="5" placeholder="Explain your idea, ask a question, or describe what you tried…" class="mt-2 w-full rounded-xl border border-green-200 p-4 text-sm font-normal leading-7">{{ ($targetComment && (int)old('conversation_id') === $targetId) || (!$targetComment && !old('conversation_id')) ? old('body') : '' }}</textarea></label>
        <div class="rounded-2xl border border-dashed border-green-300 bg-paper p-4"><label class="text-xs font-bold text-primary">Image (optional)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-3 block w-full text-xs font-normal" data-blog-image-input></label><p class="mt-3 text-xs text-secondary">JPEG, PNG or WebP · up to 2 MB.</p><img data-blog-image-preview hidden alt="Selected response image preview" class="mt-4 max-h-60 w-full rounded-xl object-contain"><span data-blog-saved-media class="mt-2 block text-xs text-secondary"></span></div>
        @include('blog.voice-input')
        <p class="text-xs leading-6 text-secondary">Send text, an image, a voice message, or a combination. {{ $post->is_graded ? 'Your own first and second comments can earn XP, up to 10 XP per article. If your first comment earns 10 XP, your second earns no additional XP. Each student has a separate allowance.' : 'This is an ungraded discussion.' }}</p>
        <button type="submit" class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-tactile">Send private response →</button>
    </fieldset>
    <p data-blog-form-error role="alert" hidden class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900"></p>
</form>
