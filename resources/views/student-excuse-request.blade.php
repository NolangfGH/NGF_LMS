@php
    $excuse=$secondChanceRequests->get($excuseType.':'.$excuseActivity->id);
@endphp
@if($excuse)
<section class="mt-5 rounded-xl border {{ $excuse->isActive() ? 'border-green-300 bg-green-50' : 'border-amber-200 bg-amber-50' }} p-4">
    <h3 class="font-bold text-primary">Final opportunity request · {{ ucfirst($excuse->status) }}</h3>
    @if($excuse->status === 'pending')<p data-excuse-pending class="mt-2 text-sm">Your teacher is reviewing your reason and supporting image. The activity stays closed until approval. Status refreshes automatically while you are not editing a form.</p>@endif
    @if($excuse->status === 'approved')
    @php
        $finishedFinal=false;
        if ($excuseType === 'assignment') {
            $finalWorkspace=$assignmentWorkspaces->get($excuseActivity->id);
            $finishedFinal=$finalWorkspace?->second_chance_request_id === $excuse->id && app(\App\Services\CodeLabTimer::class)->describe($finalWorkspace)['expired'];
        } else {
            $finishedFinal=$quizFinalResults->has($excuseActivity->id);
        }
    @endphp
    <p class="mt-2 text-sm font-bold">{{ !$excuse->isActive() ? 'Your approved final opportunity has expired. No further opportunity can be requested.' : ($finishedFinal ? 'Your second and final opportunity has ended. No further opportunity can be requested.' : 'Approved by your teacher — your second and final opportunity is available.') }}</p>
    <p class="mt-2 text-sm">Final deadline: {{ $excuse->expires_at->timezone(config('learning.timezone'))->format('M j, Y · g:i A') }} (UTC+08:00)</p>
    @if($excuse->duration_minutes)
    <p class="mt-2 text-sm">{{ $excuse->duration_minutes }} minutes from opening Code Lab.</p>
    @endif
    <p class="mt-2 text-xs">{{ $excuse->used_at ? 'Your final opportunity has started. Its timer does not reset on refresh.' : 'Use the activity button above to start. This approval can only be used once.' }}</p>
    @endif
    @if($excuse->status === 'declined')<p class="mt-2 text-sm">Your teacher declined the request. The activity remains closed.</p>@endif
    @if($excuse->teacher_note)<p class="mt-2 whitespace-pre-wrap text-sm"><b>Teacher feedback:</b> {{ $excuse->teacher_note }}</p>@endif
    <div class="mt-3 flex flex-wrap gap-4 text-xs font-bold text-primary"><a href="{{ route('excuses.proof',$excuse) }}" target="_blank" rel="noopener" class="underline">View my supporting image</a><a href="{{ request()->url() }}" class="underline">Refresh request status</a></div>
</section>
@elseif(app(\App\Services\SecondChanceService::class)->eligible($student,$excuseType,$excuseActivity))
<details class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
    <summary class="cursor-pointer text-sm font-bold text-primary">Missed the deadline? Request one final opportunity</summary>
    <p class="mt-3 text-sm leading-6 text-muted">Explain your valid reason and attach a medical certificate or excuse letter image. Only your teacher and you can view it. Your teacher must approve before you can continue. You may submit this request once per activity.</p>
    <form method="POST" enctype="multipart/form-data" action="{{ route('student.excuses.store',['type'=>$excuseType,'activity'=>$excuseActivity->id]) }}" class="mt-4 space-y-4">
        @csrf
        <label class="block text-sm font-semibold">Reason for missing the activity *<textarea name="reason" required minlength="15" maxlength="3000" rows="3" class="mt-2 w-full rounded-xl border bg-white p-3" placeholder="Explain why you could not complete this activity on time.">{{ old('reason') }}</textarea></label>
        <label class="block text-sm font-semibold">Medical certificate or excuse letter image *<input type="file" name="proof" required accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-xl border bg-white p-3 text-sm"></label>
        <p class="text-xs text-muted">JPG, PNG or WebP · Maximum 2 MB · Share only the information your teacher needs to consider this request.</p>
        <button class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Send excuse request</button>
    </form>
</details>
@endif
