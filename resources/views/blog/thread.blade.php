<p class="mb-4 text-xs leading-6 text-secondary">{{ $comments->total() }} {{ $isTeacher ? 'private student' : 'of your private' }} {{ Str::plural('conversation', $comments->total()) }} · Up to {{ config('blog.responses_per_person') }} responses each for the student and teacher, after the opening insight.</p>
<div class="space-y-6">
@if(!$isTeacher && $activeAttempt && $activeAttempt['comment_id'] && !$comments->contains('id',$activeAttempt['comment_id']))
<p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">You have a timed response in progress on another page. <a class="font-bold underline" href="{{ route('student.blog.show',['post'=>$post,'page'=>1]) }}#comment-{{ $activeAttempt['comment_id'] }}">Resume your timed response →</a></p>
@endif
@forelse($comments as $comment)
    @include('blog.conversation')
@empty
<div class="rounded-2xl border-2 border-dashed border-secondary-soft bg-white p-10 text-center"><span class="material-symbols-outlined text-4xl text-secondary" aria-hidden="true">forum</span><h3 class="mt-4 font-bold text-primary">{{ $isTeacher ? 'No student comments yet' : 'You have not commented yet' }}</h3><p class="mt-3 text-sm leading-7">{{ $isTeacher ? 'Student insights will appear here after they respond to your topic.' : 'Send an insight, a question or an image above to begin a private conversation with your teacher.' }}</p></div>
@endforelse
</div>
<div class="mt-6">{{ $comments->appends(['thread' => null])->links() }}</div>
