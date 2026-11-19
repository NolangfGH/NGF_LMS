@extends('layouts.teacher-learning', ['pageTitle'=>'Review Peer Coding Request'])
@section('content')
<a href="{{ route('teacher.challenges') }}" class="text-sm font-bold text-primary underline">← Challenge Studio</a>
<section class="mt-4 rounded-3xl border border-green-200 bg-white p-5 shadow-card sm:p-8">
    <p class="text-xs font-bold uppercase tracking-widest text-secondary">Student-requested match · {{ $match->requested_grade }} · {{ $match->course==='arduino-uno' ? 'Arduino Uno Robotics' : 'C++ Programming' }}</p>
    <h1 class="mt-2 text-3xl font-extrabold text-primary">{{ $match->title }}</h1><p class="mt-3 text-sm leading-7">Approve participation only after reviewing the task. Different supervising teachers must each approve. The host teacher verifies submissions and awards the winner; the other supervising teacher can monitor the shared match.</p>
    @if(session('peer_notice'))<p role="status" class="mt-4 rounded-xl bg-paper p-4 text-sm font-bold">{{ session('peer_notice') }}</p>@endif
    @if($errors->any())<div role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-900"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="mt-5 flex flex-wrap gap-3">@foreach($match->participants as $participant)<span class="rounded-xl border border-green-100 bg-paper px-4 py-3 text-sm font-bold">{{ $participant->student_name }} · {{ $participant->section_label }}</span>@endforeach</div>
    <p class="mt-4 text-sm font-bold">{{ $approvalRows->where('decision','approved')->count() }}/{{ $approvalRows->count() }} supervising teachers approved · Your decision: {{ $approval->decision }}</p>
    @if($match->status!=='awaiting_approval')<p class="mt-4 rounded-xl bg-paper p-4 text-sm">This request is {{ str_replace('_',' ',$match->status) }}. <a href="{{ route('teacher.matches.show',$match) }}" class="font-bold underline">Open the monitor or result →</a></p>
    @elseif(!$isHost && !$hostApproved)<p class="mt-4 rounded-xl bg-paper p-4 text-sm">Waiting for the host teacher to finalize and approve the task. You can decline participation now, or return after the host approves.</p>@endif
    <form method="POST" action="{{ route('teacher.peer-challenges.decide',$match) }}" class="mt-6 space-y-4">@csrf
        @if($isHost && $canReview)
            <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-bold">Match title<input name="title" required maxlength="160" value="{{ old('title',$match->title) }}" class="mt-2 block w-full rounded-xl border p-3 font-normal"></label><label class="block text-sm font-bold">Award name<input name="award_title" required maxlength="80" value="{{ old('award_title',$match->award_title) }}" class="mt-2 block w-full rounded-xl border p-3 font-normal"></label></div>
            <label class="block text-sm font-bold">Coding task and success criteria<textarea name="instructions" required minlength="10" maxlength="10000" rows="6" class="mt-2 block w-full rounded-xl border p-3 text-sm font-normal">{{ old('instructions',$match->instructions) }}</textarea></label>
            <label class="block text-sm font-bold">Expected output / behavior<textarea name="sample_output" required maxlength="10000" rows="5" spellcheck="false" class="mt-2 block w-full rounded-xl border bg-paper p-3 font-mono text-sm">{{ old('sample_output',$match->sample_output) }}</textarea></label>
            <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold">Time limit (minutes)<input type="number" name="time_limit_minutes" required min="1" max="180" value="{{ old('time_limit_minutes',$match->time_limit_minutes) }}" class="mt-2 block w-full rounded-xl border p-3 font-normal"></label><label class="text-sm font-bold">Winner XP<input type="number" name="winner_xp" required min="0" max="10000" value="{{ old('winner_xp',$match->winner_xp) }}" class="mt-2 block w-full rounded-xl border p-3 font-normal"></label></div>
        @else
            <section class="rounded-xl bg-paper p-4"><h2 class="font-bold">Coding task</h2><p class="mt-3 whitespace-pre-wrap text-sm leading-7">{{ $match->instructions }}</p><h3 class="mt-4 font-bold">Expected output / behavior</h3><pre class="mt-2 overflow-auto whitespace-pre-wrap font-mono text-sm">{{ $match->sample_output }}</pre><p class="mt-4 text-xs">{{ $match->time_limit_minutes }} minutes · {{ $match->winner_xp }} XP winner reward</p></section>
        @endif
        @if($match->status==='awaiting_approval' && $approval->decision==='pending')
            <label class="block text-sm font-bold">Suggestion or reason (shared with the learners if declined)<textarea name="reason" maxlength="2000" rows="3" class="mt-2 block w-full rounded-xl border p-3 font-normal">{{ old('reason') }}</textarea></label>
            <p class="text-xs leading-6 text-secondary">A decline needs a reason of at least five characters. Every learner must have unlocked the selected topic before approval.</p>
            <div class="flex flex-wrap gap-3">@if($canReview)<button name="decision" value="approved" class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white">Approve participation →</button>@endif<button name="decision" value="rejected" formnovalidate class="rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-800">Decline request</button></div>
        @endif
    </form>
</section>
@endsection
