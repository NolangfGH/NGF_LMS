<div class="grid gap-3 sm:grid-cols-2">
@forelse($codingMatches as $record)
<article class="rounded-2xl border border-green-100 bg-paper p-4">
<div class="flex justify-between gap-2"><h3 class="font-bold text-primary">{{ $record->title }}</h3><span class="text-xs font-bold uppercase text-secondary">{{ $record->status }}</span></div>
<p class="mt-2 text-xs leading-6 text-secondary">{{ $record->time_limit_minutes }} minutes · {{ $record->winner_xp }} XP winner award<br>{{ $record->participants->pluck('section_label')->unique()->join(' ↔ ') }}</p>
@if($matchRole==='student')
@php($myInvitation=$record->participants->firstWhere('student_id',session('student_id')))
<p class="mt-2 text-sm font-bold text-primary">{{ $record->status==='inviting' && $myInvitation?->response==='invited' ? 'Invitation: your response is needed' : ($record->status==='inviting' ? 'Waiting for the other coder' : ($record->status==='active' ? 'Open the live arena' : 'View result and feedback')) }}</p>
@else
<p class="mt-2 text-xs text-muted">{{ $record->participants->where('response','accepted')->count() }}/2 accepted</p>
@endif
<a class="mt-3 inline-block rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white" href="{{ route($matchRole.'.matches.show',$record) }}">{{ $matchRole==='teacher' ? 'Monitor & review' : 'Open challenge' }} →</a>
</article>
@empty
<p class="col-span-full rounded-xl border border-dashed border-green-200 p-6 text-sm text-secondary">{{ $matchRole==='teacher' ? 'No two-student challenges yet. Choose two coders below to create one.' : 'No two-student invitations yet. Your teacher can invite you from Challenge Studio.' }}</p>
@endforelse
</div><div class="mt-4">{{ $codingMatches->links() }}</div>
