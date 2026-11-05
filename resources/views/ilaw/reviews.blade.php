@extends('layouts.teacher-learning')

@section('content')
<p class="text-xs font-bold uppercase tracking-widest text-secondary">Instructional coaching · {{ $teacher->school }}</p>
<h1 class="mt-2 text-3xl font-extrabold text-primary">ILAW lesson plan reviews</h1>
<p class="mt-2 text-sm leading-6">Open a submitted lesson plan, leave an optional coaching comment, and mark it evaluated. The teacher receives a private notification.</p>
<div class="mt-6 grid gap-4 md:grid-cols-2">
    @forelse($plans as $plan)
        @php($details = json_decode($plan->content, true, flags: JSON_THROW_ON_ERROR))
        <article class="rounded-2xl border border-green-100 bg-white p-5 shadow-card">
            <span class="rounded-full bg-paper px-3 py-1 text-xs font-bold text-secondary">{{ $plan->status === 'submitted' ? 'Awaiting review' : 'Evaluated' }}</span>
            <h2 class="mt-3 text-lg font-extrabold text-primary">{{ $details['title'] }}</h2>
            <p class="mt-1 text-sm">{{ $plan->first_name }} {{ $plan->last_name }} · {{ $details['grade_section'] }}</p>
            <a href="{{ route('school.ilaw.show', $plan->id) }}" class="mt-4 inline-block rounded-xl border border-green-200 px-4 py-2 text-sm font-bold text-primary">Open plan →</a>
        </article>
    @empty
        <p class="rounded-2xl border border-dashed border-green-200 bg-white p-6">No lesson plans from your school have been submitted yet.</p>
    @endforelse
</div>
<div class="mt-6">{{ $plans->links() }}</div>
@endsection
