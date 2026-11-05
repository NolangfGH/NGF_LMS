<div class="space-y-4">
    @forelse($entries as $entry)
        <article class="rounded-2xl border border-green-100 bg-white p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-bold text-primary">{{ $categories[$entry->category] }}</h3>
                <time datetime="{{ \Illuminate\Support\Carbon::parse($entry->created_at)->toIso8601String() }}" class="text-xs text-secondary">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->timezone(config('learning.timezone'))->format('M j, Y g:i A') }}</time>
            </div>
            @if($showAuthor ?? false)
                <p class="mt-1 text-xs font-bold text-secondary">{{ $entry->student_id ? 'Student · '.$entry->nickname : 'Staff · '.$entry->first_name.' '.$entry->last_name }}</p>
            @endif
            @if($entry->message)<p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6">{{ $entry->message }}</p>@endif
            @if($entry->image_path)
                <a href="{{ route('system.feedback.image', $entry->id) }}" class="mt-3 inline-block text-sm font-bold text-primary underline">Open attached image →</a>
            @endif
        </article>
    @empty
        <p class="rounded-2xl border border-dashed border-green-200 bg-white p-5 text-sm">No feedback submissions yet.</p>
    @endforelse
</div>
<div class="mt-5">{{ $entries->links() }}</div>
