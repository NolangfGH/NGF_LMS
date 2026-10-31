<form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-green-200 bg-white p-4">
    <label class="text-sm font-bold">Activity type<select name="activity" class="ml-3 rounded-xl border bg-white p-3 text-sm"><option value="">All submissions</option>@foreach(['assignment'=>'Assignments','challenge'=>'Teacher challenges','practice'=>'Guided practice'] as $value=>$label)<option value="{{ $value }}" @selected($reviewActivity === $value)>{{ $label }}</option>@endforeach</select></label>
    <button class="rounded-xl bg-green-900 px-4 py-3 text-sm font-bold text-white">Filter reviews</button>
    <a href="{{ route('teacher.challenges') }}" class="px-2 py-3 text-sm font-bold underline">Create a challenge →</a>
</form>
