@if(!empty($overview['subjectProgress']))
<section class="mb-6 rounded-2xl border border-green-100 bg-white p-5 shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-2"><div><p class="text-xs font-bold uppercase tracking-widest text-secondary">Your grade-level curriculum</p><h2 class="mt-2 text-xl font-extrabold text-primary">Choose a subject</h2></div><a href="{{ route('student.learn') }}" class="text-sm font-bold text-primary underline">Browse all grades →</a></div>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($overview['subjectProgress'] as $subject)
            <article class="rounded-xl border border-green-100 bg-paper p-4"><p class="text-xs font-semibold text-secondary">{{ \App\Services\LearningCourses::isElective($subject['course']) ? 'RSHS elective' : 'Learning area' }}</p><h3 class="mt-1 font-bold text-primary">{{ $subject['title'] }}</h3><p class="mt-2 text-xs font-semibold">{{ $subject['completed'] }} / {{ $subject['total'] }} checkpoints completed</p><progress class="mt-2 w-full" value="{{ $subject['completed'] }}" max="{{ max(1,$subject['total']) }}"></progress><div class="mt-3 flex flex-wrap gap-3 text-xs font-bold text-primary"><a href="{{ route('student.learn',\App\Services\CourseAssessments::courseQuery($subject['course'])) }}" class="underline">Learn →</a><a href="{{ route('student.assessments.index',\App\Services\CourseAssessments::courseQuery($subject['course'])) }}" class="underline">Assessments →</a></div></article>
        @endforeach
    </div>
</section>
@endif
