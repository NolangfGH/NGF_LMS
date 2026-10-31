@php
    $part = $participations->get($comment->student_id);
    $number = $entry->submission_number;
    $eligible = $post->is_graded && in_array($number,[1,2],true);
    $gradingOwner = $isTeacher ? ($comment->student?->nickname ?? 'This student') : 'You';
@endphp
<div class="mt-4 border-t border-green-200/70 pt-3">
@if($entry->auto_submitted)<p class="mb-2 text-[11px] font-bold text-amber-900">Automatically submitted when time ended</p>@if(!filled($entry->body) && !$entry->image_path && !$entry->audio_path)<p class="mb-2 text-xs text-secondary">No answer was entered before time ended.</p>@endif @endif
@if($entry->assessment)
    <p class="text-xs font-bold text-primary">{{ config('blog.assessments.'.$entry->assessment.'.label') }} · +{{ $entry->xp_credited }} XP credited</p>@if($entry->grade_note)<p class="mt-2 whitespace-pre-wrap break-words text-xs leading-6">{{ $entry->grade_note }}</p>@endif
@elseif(!$post->is_graded)
    <p class="text-[11px] text-secondary">Ungraded discussion · no XP assessment</p>
@elseif(!$eligible)
    <p class="text-[11px] text-secondary">Comment {{ $number ?? '—' }} · discussion only. Only the first and second comments of each student can earn XP for this article.</p>
@elseif(($part?->xp_awarded ?? 0) >= 10)
    <p class="text-xs font-bold text-primary">Maximum 10 XP already earned for this article by {{ $isTeacher ? $gradingOwner : 'you' }}. This response is discussion only.</p>
@elseif($number === 2 && !$part?->first_assessment)
    <p class="text-xs text-secondary">Second comment · waiting for {{ $isTeacher ? $gradingOwner.'’s' : 'your' }} first comment’s assessment.</p>
@elseif($isTeacher)
    <form method="POST" action="{{ $entry instanceof \App\Models\BlogResponse ? route('teacher.blog.grade-response',['post'=>$post,'comment'=>$comment,'response'=>$entry]) : route('teacher.blog.grade-comment',['post'=>$post,'comment'=>$comment]) }}" class="mt-2 space-y-3" data-blog-grade-form>@csrf
        <p class="text-xs font-bold text-primary">Assess {{ $gradingOwner }}’s comment {{ $number }} of 2 · this student’s article XP: {{ $part?->xp_awarded ?? 0 }}/10</p>
        <label class="block text-xs font-bold text-primary">Correctness<select name="assessment" required class="mt-2 block w-full rounded-xl border border-green-200 bg-white p-3 text-xs font-normal"><option value="">Choose your assessment</option>@foreach(config('blog.assessments') as $key=>$grade)<option value="{{ $key }}">{{ $grade['label'] }} · {{ $grade['xp'] }} XP</option>@endforeach</select></label>
        <label class="block text-xs font-bold text-primary">Learning feedback (optional)<textarea name="grade_note" maxlength="1500" rows="2" class="mt-2 w-full rounded-xl border border-green-200 bg-white p-3 text-xs font-normal"></textarea></label>
        <p class="text-[11px] leading-5 text-secondary">The assessment is final when saved. This student has a separate maximum of 10 XP for this article. If their first comment earns 10 XP, their second comment earns no additional XP.</p><button class="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white">Save assessment & award XP</button>
    </form>
@else
    <p class="text-xs text-secondary">Your comment {{ $number }} of 2 · awaiting teacher assessment</p>
@endif
</div>
