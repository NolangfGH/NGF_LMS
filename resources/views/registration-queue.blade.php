<section aria-label="Registration totals" class="mb-5 grid gap-3 sm:grid-cols-3">
    @foreach(['pending'=>'Awaiting approval','approved'=>'Approved accounts','rejected'=>'Declined requests'] as $value=>$label)
    <article class="rounded-2xl border border-green-100 bg-white p-5"><p class="text-xs font-bold text-secondary">{{ $label }}</p><strong class="mt-2 block text-3xl font-extrabold text-primary">{{ $counts[$value] ?? 0 }}</strong></article>
    @endforeach
</section>
<div class="space-y-4">
@forelse($records as $record)
    <article id="registration-{{ $record->id }}" class="scroll-mt-24 rounded-2xl border border-green-100 bg-white p-5 shadow-card sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-xl font-bold text-primary">{{ trim($record->first_name.' '.$record->middle_name.' '.$record->surname) }}</h2><p class="mt-2 text-sm">Student ID: <b class="font-mono">{{ $record->student_id }}</b> · Nickname: {{ $record->nickname }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $record->registration_status === 'pending' ? 'bg-amber-50 text-amber-900' : ($record->registration_status === 'approved' ? 'bg-green-50 text-primary' : 'bg-red-50 text-red-800') }}">{{ ['pending'=>'Awaiting approval','approved'=>'Approved','rejected'=>'Declined'][$record->registration_status] }}</span></div>
        <div class="mt-4 flex flex-wrap gap-3 rounded-xl bg-paper p-4 text-xs"><span><b>Class:</b> {{ $record->classroom->grade }} · {{ $record->classroom->section ?: $record->classroom->name }}</span><span><b>Submitted:</b> {{ $record->created_at->timezone('Asia/Singapore')->format('M j, Y · g:i A') }} (UTC+08:00)</span>@if($record->classroom->archived_at)<b class="text-red-800">Archived class · approval unavailable</b>@endif</div>
        @if($record->registration_review_note)<p class="mt-4 whitespace-pre-wrap rounded-xl border p-4 text-sm"><b>Review note:</b> {{ $record->registration_review_note }}</p>@endif
        @if($record->registration_status === 'pending')
        <form method="POST" action="{{ route('teacher.registrations.review', $record->id) }}" class="registration-review mt-5 border-t border-green-100 pt-5">
            @csrf<input type="hidden" name="revision" value="{{ $record->registration_revision }}">
            <label for="review-note-{{ $record->id }}" class="block text-sm font-bold text-primary">Review note <span class="font-normal text-secondary">(required when declining)</span></label>
            <textarea id="review-note-{{ $record->id }}" name="note" maxlength="1000" rows="2" class="mt-2 w-full rounded-xl border p-3 text-sm" placeholder="Explain any details the student needs to correct."></textarea>
            <p class="mt-2 text-xs text-secondary">Check the student’s identity and section with your class list. Never request their password.</p>
            <div class="mt-4 flex flex-wrap gap-3"><button name="decision" value="approved" @disabled($record->classroom->archived_at) class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">Approve & enable account</button><button name="decision" value="rejected" class="rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-800">Decline registration</button></div>
        </form>
        @elseif($record->registration_status === 'rejected')
        <form method="POST" action="{{ route('teacher.registrations.review', $record->id) }}" class="registration-review mt-5">@csrf<input type="hidden" name="revision" value="{{ $record->registration_revision }}"><input type="hidden" name="decision" value="pending"><button class="rounded-xl border px-4 py-3 text-sm font-bold text-primary">Reopen for review</button><p class="mt-2 text-xs">Reopening does not grant access. Verify the corrected details with the student before approving.</p></form>
        @else
        <p class="mt-4 text-sm text-secondary">{{ $record->registration_reviewed_at ? 'Reviewed '.$record->registration_reviewed_at->timezone('Asia/Singapore')->format('M j, Y · g:i A') : 'Existing account · access preserved' }} · <a class="font-bold text-primary underline" href="{{ route('teacher.students.show', $record->id) }}">View student profile →</a></p>
        @endif
        @if(($history[$record->id] ?? collect())->isNotEmpty())
        <details class="mt-5 text-xs"><summary class="cursor-pointer font-bold text-secondary">Review history</summary><ul class="mt-3 space-y-3">@foreach($history[$record->id] as $review)<li class="rounded-xl bg-paper p-3"><b>{{ ucfirst($review->decision === 'rejected' ? 'declined' : $review->decision) }}</b> · {{ $review->first_name }} {{ $review->last_name }} · {{ \Illuminate\Support\Carbon::parse($review->created_at)->timezone('Asia/Singapore')->format('M j, Y · g:i A') }}@if($review->note)<p class="mt-2 whitespace-pre-wrap">{{ $review->note }}</p>@endif</li>@endforeach</ul></details>
        @endif
    </article>
@empty
    <div class="rounded-2xl border border-dashed border-green-200 bg-white p-10 text-center"><span class="material-symbols-outlined text-4xl text-secondary" aria-hidden="true">verified_user</span><h2 class="mt-3 text-xl font-bold text-primary">No matching registrations</h2><p class="mt-3 text-sm">New student submissions from your sections will appear here. Try another review status or clear your filters.</p></div>
@endforelse
</div>
<div class="mt-5">{{ $records->links() }}</div>
