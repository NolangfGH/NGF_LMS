@extends('layouts.teacher-learning')

@section('content')
<a href="{{ route('teacher.students.show', $student->id) }}" class="text-sm font-bold text-primary">← Student details</a>
<h1 class="mt-4 text-3xl font-extrabold text-primary">Course certificate</h1>
<p class="mt-2 text-sm">{{ $student->nickname }} · {{ $courseProgress['completed'] }} / {{ $courseProgress['total'] }} core checkpoints completed</p>
@if($certificate)
    <div class="mt-6 max-w-2xl rounded-2xl border border-green-100 bg-white p-6">
        <h2 class="text-lg font-extrabold text-primary">{{ $certificate->recipient }}</h2>
        <p class="mt-2 text-sm">Certificate {{ $certificate->certificate_code }}</p>
        <a href="{{ route('teacher.certificates.print', $student->id) }}" class="mt-4 inline-block rounded-xl border border-green-200 px-4 py-3 text-sm font-bold text-primary">Preview and print →</a>
        @if((int) $certificate->teacher_account_id === $teacher->id)
            <form method="POST" action="{{ route('teacher.certificates.update', $student->id) }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="certificate_id" value="{{ $certificate->id }}">
                <input type="hidden" name="revision" value="{{ old('revision', $certificate->revision) }}">
                <label for="certificate-title" class="block text-sm font-bold text-primary">Certificate title
                    <input id="certificate-title" name="title" value="{{ old('title', $certificate->title) }}" required maxlength="120" class="mt-2 w-full rounded-xl border border-green-200 p-3 font-normal">
                </label>
                <label for="certificate-message" class="block text-sm font-bold text-primary">Certificate wording
                    <textarea id="certificate-message" name="message" rows="5" required maxlength="600" class="mt-2 w-full rounded-xl border border-green-200 p-3 font-normal">{{ old('message', $certificate->message) }}</textarea>
                </label>
                <button class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white">Save certificate wording</button>
            </form>
        @else
            <p class="mt-5 text-sm">This certificate was issued in a previous classroom. You can preview the recorded award.</p>
        @endif
    </div>
@else
    <p class="mt-6 rounded-2xl border border-green-100 bg-white p-6 text-sm">The excellence certificate becomes available after the student finishes the course and passes the timed post-test with at least 45 correct answers.</p>
@endif
@endsection
