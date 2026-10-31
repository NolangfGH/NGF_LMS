@if($post->is_graded)
@php($studentGrading = $participations->get($comment->student_id))
<section class="mt-4 rounded-2xl border border-green-200 bg-white p-4" aria-label="Individual student grading">
    <div class="flex flex-wrap items-center justify-between gap-3"><h4 class="text-xs font-bold text-primary">{{ $isTeacher ? 'Grading for '.($comment->student?->nickname ?? 'this student') : 'Your individual grading' }}</h4><span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-primary">{{ $studentGrading?->xp_awarded ?? 0 }}/10 XP for this student</span></div>
    <p class="mt-2 text-xs leading-6 text-secondary">Each student has their own first and second graded comments and a separate 10 XP maximum per article.</p>
    @if($studentGrading?->first_assessment === 'totally_correct')
        <p class="mt-2 text-xs font-bold leading-6 text-primary">First comment: totally correct · 10 XP earned. Second comment: no additional XP; this student already earned the maximum.</p>
    @else
        <p class="mt-2 text-xs leading-6 text-secondary">The opening comment is comment 1. The student’s next comment or follow-up is comment 2. Other students’ comments do not use these grading opportunities.</p>
    @endif
</section>
@endif
