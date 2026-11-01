@php
    $linkedKeys = $book['assessments']->map(fn ($assessment) => $assessment->source_type.':'.$assessment->source_id);
    $unlinkedSources = $sources->reject(fn ($source) => $linkedKeys->contains($source['key']));
@endphp
<section class="grading-card grading-controls grading-automatic" aria-labelledby="automatic-entry-title">
    <div class="grading-section-heading"><div><p class="grading-eyebrow">Saved results → gradebook</p><h2 id="automatic-entry-title">Automatic score entry</h2></div><span class="grading-pill">Live · every 15 seconds</span></div>
    <p class="grading-help">Link activities once. Each student's saved quiz score or final teacher-reviewed coding score fills the gradebook automatically. Scores update while you are not editing. Teacher overrides stay in place until you choose Use linked result.</p>
    <div class="grading-automatic-stats"><p><strong>{{ $book['automatic']['linked'] }}</strong> linked activities</p><p><strong>{{ $book['automatic']['recorded'] }}</strong> automatic score entries</p><p><strong>{{ $book['automatic']['overrides'] }}</strong> teacher overrides / excused entries</p></div>
    <details><summary class="grading-link">Link multiple activities ({{ $unlinkedSources->count() }} available)</summary>
        <p class="grading-help">Select activities for {{ $schoolYear }} · {{ config('grading.terms.'.$term) }} · {{ $room->section ?: $room->name }}. Quizzes have no term label: choose only quizzes that belong to this term. Coding tasks are filtered by their school term.</p>
        @if($unlinkedSources->isNotEmpty())
        <form method="POST" action="{{ route('teacher.grading.assessments.store', $sheet) }}" data-grading-edit class="grading-form">
            @csrf<input type="hidden" name="revision" value="{{ $sheet->revision }}"><input type="hidden" name="entry_mode" value="automatic_batch">
            <fieldset class="grading-source-list"><legend>Select activities to link</legend>
            @foreach($unlinkedSources as $source)<label class="grading-checkbox"><input type="checkbox" name="source_keys[]" value="{{ $source['key'] }}" @checked(in_array($source['key'], (array) old('source_keys', []), true))><span><strong>{{ $source['title'] }}</strong><small>{{ config('grading.categories.'.$source['category']) }} · {{ $source['max'] }} points @if($source['due_date']) · Due {{ $source['due_date'] }} @endif</small></span></label>@endforeach
            </fieldset>
            <button class="grading-button grading-primary">Enable automatic score entry for selected activities</button>
            <small>Default categories: quizzes → Written Work; assignments → Performance Tasks; challenges → Programming Challenges. Review categories and maximum scores under Assessment setup. Existing manual scores, weights and XP stay unchanged.</small>
        </form>
        @else<p class="grading-empty">{{ $sources->isEmpty() ? 'No published quizzes or coding tasks are available for this section yet.' : 'All currently available activities are already linked.' }}</p>@endif
    </details>
</section>
