@extends('layouts.teacher-learning')

@section('content')
<p class="text-xs font-bold uppercase tracking-widest text-secondary">C++ Certification</p>
<h1 class="mt-2 text-3xl font-extrabold text-primary">Post-test codes</h1>
<p class="mt-2 max-w-3xl text-sm leading-6">Generate a personal code when a student in your class is ready. Share it privately with that student. A code expires after 24 hours or when the student starts the exam. Generating a new code replaces the old one.</p>
<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @forelse($requests as $request)
        <article id="request-{{ $request->id }}" class="scroll-mt-24 rounded-2xl border border-green-100 bg-white p-5">
            <p class="text-xs font-bold uppercase text-secondary">{{ $request->grade }} · {{ $request->section ?: $request->name }}</p>
            <h2 class="mt-1 text-lg font-extrabold text-primary">{{ $request->first_name }} {{ $request->surname }} · {{ $request->nickname }}</h2>
            <p class="mt-2 text-sm">{{ $request->attempt_number === 1 ? 'First attempt · 90 seconds per question' : 'Final attempt · 60 seconds per question' }}</p>
            @if(session('post_test_code.id') === $request->id)
                <p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-sm">Share this code privately <strong class="block font-mono text-2xl tracking-wider text-primary">{{ session('post_test_code.code') }}</strong></p>
            @elseif((int) $request->code_issued_by === $teacher->id && $request->code_expires_at)
                <p class="mt-3 text-xs font-bold text-secondary">{{ now()->lt(\Illuminate\Support\Carbon::parse($request->code_expires_at)) ? 'Code issued' : 'Code expired' }}</p>
            @endif
            <form method="POST" action="{{ route('teacher.post-test.issue', $request->id) }}" class="mt-4">
                @csrf
                <button class="rounded-xl border border-green-200 px-4 py-3 text-sm font-bold text-primary">{{ $request->code_expires_at ? 'Generate replacement code' : 'Generate post-test code' }}</button>
            </form>
        </article>
    @empty
        <p class="rounded-2xl border border-dashed border-green-200 bg-white p-6">There are no pending requests from your students.</p>
    @endforelse
</div>
<div class="mt-6">{{ $requests->links() }}</div>
@endsection
