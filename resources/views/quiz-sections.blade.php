@if(session('section_saved'))<p role="status" class="mb-4 rounded-xl bg-green-100 p-4">{{ session('section_saved') }}</p>@endif
@if($errors->any())<p role="alert" class="mb-4 rounded-xl bg-red-100 p-4">{{ $errors->first() }}</p>@endif
<details id="gradeSectionManager" class="mb-6 rounded-2xl bg-white p-5 shadow-card">
<summary class="cursor-pointer font-bold text-primary">Manage grade sections and student join codes</summary>
<p class="mt-3 text-sm">Name your current section first to keep existing students enrolled. Add your other sections with separate join codes. Students must register with the code for their own section.</p>
<ul class="my-4 space-y-2">@foreach($classrooms as $room)<li class="rounded-xl bg-paper p-3 text-sm"><b>{{ $room->grade }} · {{ $room->section ?: 'Current class (section not named)' }}</b> · {{ $room->name }} · Join code: <strong class="font-mono">{{ $room->join_code }}</strong></li>@endforeach</ul>
<form action="{{ route('teacher.quiz.sections.store') }}" method="POST" class="space-y-3">@csrf
<label class="block font-bold">Existing classroom<select required name="classroom_id" class="mt-2 block w-full rounded-xl border p-3 font-normal">@foreach($classrooms as $room)<option value="{{ $room->id }}">{{ $room->grade }} · {{ $room->section ?: 'Current class (section not named)' }} · {{ $room->name }}</option>@endforeach</select></label>
<label class="block font-bold">Section name<input required maxlength="80" name="section" placeholder="e.g. Section A / Sapphire" class="mt-2 w-full rounded-xl border p-3 font-normal"></label>
<p class="text-xs text-muted">If the selected classroom has no section name, this names it. Otherwise a new section and join code will be created.</p>
<button class="rounded-xl bg-primary px-4 py-3 font-bold text-white">Save grade section</button></form>
</details>
