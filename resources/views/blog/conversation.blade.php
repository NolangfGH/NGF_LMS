@php
    $responseLimit = config('blog.responses_per_person');
    $studentResponses = $comment->responses->where('author_role', 'student')->count();
    $teacherResponses = $comment->responses->where('author_role', 'teacher')->count();
    $myResponses = $isTeacher ? $teacherResponses : $studentResponses;
    $complete = $teacherResponses >= $responseLimit && $studentResponses >= $responseLimit;
    $awaitingTeacher = $comment->last_response_role === 'student' && $teacherResponses < $responseLimit;
    $statusLabel = $complete ? 'Conversation complete' : ($awaitingTeacher ? 'Awaiting teacher response' : ($comment->last_response_role === 'student' ? 'Teacher response limit reached' : ($isTeacher ? 'Response sent to student' : 'Teacher replied')));
@endphp
<article id="comment-{{ $comment->id }}" class="scroll-mt-24 overflow-hidden rounded-3xl border border-green-200 bg-white shadow-card">
    <div class="border-b border-green-100 bg-green-50/70 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-base font-bold text-primary">{{ $isTeacher ? ($comment->student?->nickname ?? 'Archived learner') : 'Your private discussion' }}</h3>@if($isTeacher)<p class="mt-1 text-xs text-secondary">{{ $comment->student?->student_id }} · {{ $comment->student?->classroom?->grade }} {{ $comment->student?->classroom?->section }} @if($comment->student?->trashed()) · Archived account @endif</p>@endif<p class="mt-2 text-[11px] text-secondary">Started {{ $comment->created_at->timezone(config('learning.timezone'))->format('M j, Y · g:i A') }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $awaitingTeacher ? 'bg-amber-50 text-amber-900' : 'bg-green-100 text-green-900' }}">{{ $statusLabel }}</span></div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full border border-green-200 bg-white px-3 py-1.5 text-primary">Student responses: {{ $studentResponses }}/{{ $responseLimit }}</span><span class="rounded-full border border-green-200 bg-white px-3 py-1.5 text-primary">Teacher responses: {{ $teacherResponses }}/{{ $responseLimit }}</span></div>
        @include('blog.student-grading-status')
    </div>
    <ol class="space-y-5 p-5 sm:p-6" aria-label="Private conversation history">
        <li class="mr-3 rounded-2xl border border-black/5 bg-paper p-4 sm:mr-10 sm:p-5"><div class="flex flex-wrap justify-between gap-2 text-xs"><h4 class="font-bold text-primary">{{ $isTeacher ? 'Student’s opening insight' : 'Your opening insight' }}</h4><time class="text-secondary" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->timezone(config('learning.timezone'))->format('M j · g:i A') }}</time></div>
            @if(filled($comment->body))<p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 [overflow-wrap:anywhere]">{{ $comment->body }}</p>@endif
            @if($comment->image_path)<a href="{{ route($blogRoute.'.comment-image', ['comment' => $comment, 'kind' => 'comment']) }}" target="_blank" rel="noopener" class="mt-4 block"><img src="{{ route($blogRoute.'.comment-image', ['comment' => $comment, 'kind' => 'comment']) }}" alt="Private student comment attachment" loading="lazy" class="max-h-80 max-w-full rounded-xl border border-green-100 object-contain"></a>@endif
            @if($comment->audio_path)<audio controls preload="none" src="{{ route($blogRoute.'.voice',['kind'=>'comment','message'=>$comment->id]) }}" class="mt-4 w-full" aria-label="Private student voice message"></audio>@endif @include('blog.assessment',['entry'=>$comment])        </li>
        @foreach($comment->responses as $response)
        <li class="{{ $response->author_role === 'teacher' ? 'ml-3 border-green-200 bg-green-50 sm:ml-10' : 'mr-3 border-black/5 bg-paper sm:mr-10' }} rounded-2xl border p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2">@if($response->author_role === 'teacher') @include('admin-avatar') @else <span class="material-symbols-outlined text-xl text-primary" aria-hidden="true">person</span> @endif<div><h4 class="text-xs font-bold text-primary">{{ $response->author_role === 'teacher' ? 'Teacher’s response' : ($isTeacher ? 'Student’s response' : 'Your response') }}</h4><span class="text-[10px] text-secondary">Private response</span></div></div><time class="text-[11px] text-secondary" datetime="{{ $response->created_at->toIso8601String() }}">{{ $response->created_at->timezone(config('learning.timezone'))->format('M j · g:i A') }}</time></div>
            @if(filled($response->body))<p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 [overflow-wrap:anywhere]">{{ $response->body }}</p>@endif
            @if($response->image_path)<a href="{{ route($blogRoute.'.response-image', $response) }}" target="_blank" rel="noopener" class="mt-4 block"><img src="{{ route($blogRoute.'.response-image', $response) }}" alt="Private {{ $response->author_role }} response attachment" loading="lazy" class="max-h-80 max-w-full rounded-xl border border-green-100 object-contain"></a>@endif
            @if($response->audio_path)<audio controls preload="none" src="{{ route($blogRoute.'.voice',['kind'=>'response','message'=>$response->id]) }}" class="mt-4 w-full" aria-label="Private voice response"></audio>@endif @if($response->author_role === 'student') @include('blog.assessment',['entry'=>$response]) @endif        </li>
        @endforeach
    </ol>
    <div class="border-t border-green-100 p-5 sm:p-6">
    @if($myResponses < $responseLimit)
        <details @if(($isTeacher && $awaitingTeacher) || (int)old('conversation_id') === $comment->id) open @endif><summary class="cursor-pointer text-sm font-bold text-primary">{{ $isTeacher ? 'Respond privately to this student' : 'Continue this conversation' }} <span class="ml-2 text-xs font-normal text-secondary">{{ $responseLimit - $myResponses }} {{ Str::plural('response', $responseLimit - $myResponses) }} left for you</span></summary>
            @if(!$isTeacher) @include('blog.student-composer',['targetComment'=>$comment]) @else             <form method="POST" action="{{ route($blogRoute.'.reply', ['post' => $post, 'comment' => $comment]) }}" enctype="multipart/form-data" class="mt-4 space-y-4">@csrf<input type="hidden" name="conversation_id" value="{{ $comment->id }}">
                <label class="block text-xs font-bold text-primary">Your next response<textarea name="body" maxlength="5000" rows="4" placeholder="{{ $isTeacher ? 'Answer the question or suggest the next learning step…' : 'Ask a follow-up question, explain your revised idea, or share what you tried…' }}" class="mt-2 w-full rounded-xl border border-green-200 p-3 text-sm font-normal leading-7">{{ (int)old('conversation_id') === $comment->id ? old('body') : '' }}</textarea></label>
                <div><label class="text-xs font-bold text-primary">Response image (optional)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-xs font-normal" data-blog-image-input></label><p class="mt-2 text-xs text-secondary">JPEG, PNG or WebP · up to 2 MB. Send text, an image, or both.</p><img data-blog-image-preview hidden alt="Selected response image preview" class="mt-3 max-h-52 w-full rounded-xl object-contain"></div>
                @include('blog.voice-input')<p class="text-xs leading-6 text-secondary">This adds a new message to the history. Only you and {{ $isTeacher ? 'this student' : 'the article’s teacher' }} can read it.</p>
                <button class="rounded-xl bg-primary px-4 py-3 text-xs font-bold text-white">Send private response {{ $myResponses + 1 }} of {{ $responseLimit }}</button>
            </form> @endif
        </details>
    @else
        <p role="status" class="rounded-xl bg-paper p-4 text-sm font-bold text-primary">You have used all {{ $responseLimit }} responses in this conversation. You can still read the full history and any further responses from {{ $isTeacher ? 'the student' : 'your teacher' }}.</p>
    @endif
    </div>
</article>
