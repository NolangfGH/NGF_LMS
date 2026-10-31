<aside id="learner-detail" class="grading-card grading-student-detail">
    <div class="grading-section-heading"><div><p class="grading-eyebrow">Learner evidence</p><h2><span class="grading-real-name">{{ $selectedStudent['name'] ?: $selectedStudent['nickname'] }}</span><span class="grading-alias">{{ $selectedStudent['alias'] }}</span></h2><p class="grading-private-id">{{ $selectedStudent['student_id'] }} · {{ $selectedStudent['nickname'] }}</p></div><a class="grading-link grading-controls" href="{{ route('teacher.grading', array_diff_key($query, ['student' => true])) }}">Close</a></div>
    <div class="grading-learner-stats"><div><small>Learning rewards</small><strong>{{ number_format($selectedStudent['xp']) }} XP</strong></div><div><small>Recorded items</small><strong>{{ $selectedStudent['resolved'] }} / {{ $selectedStudent['total'] }}</strong></div></div>
    <div class="grading-trajectory">@foreach($trajectory as $item)<div><small>{{ $item['label'] }}</small><strong>{{ isset($item['student']['average']) ? number_format($item['student']['average'], 2).'%' : '—' }}</strong><small>{{ ($item['student']['complete'] ?? false) ? 'Complete' : 'Provisional / no scores' }}</small></div>@endforeach</div>
    @if($sheet)
    <form method="POST" action="{{ route('teacher.grading.scores', [$sheet, $selectedStudent['id']]) }}" data-grading-edit class="grading-form grading-private-detail">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $sheet->revision }}"><input type="hidden" name="grading_student" value="{{ $selectedStudent['id'] }}">
        @forelse($book['assessments'] as $assessment)
            @php
                $cell = $selectedStudent['cells'][$assessment->id];
                if ((int) old('grading_student') === $selectedStudent['id']) {
                    $cell['mode'] = old('scores.'.$assessment->id.'.status', $cell['mode']);
                    $cell['score'] = old('scores.'.$assessment->id.'.score', $cell['score']);
                }
            @endphp
            <fieldset class="grading-score-item"><legend>{{ $assessment->title }}</legend><p>{{ $assessment->category }} · Maximum {{ $assessment->max_score }} @if($assessment->due_date) · Due {{ $assessment->due_date->format('M j, Y') }}@endif</p>
                @if($assessment->competency)<p class="grading-help">{{ $assessment->competency }}</p>@endif
                @if($assessment->instructions)<p class="grading-help whitespace-pre-wrap">{{ $assessment->instructions }}</p>@endif
                @if($cell['automatic'])<p class="grading-evidence"><a href="{{ $cell['automatic']['url'] }}">{{ $cell['automatic']['label'] }} →</a><strong>{{ $cell['automatic']['score'] }} / {{ $assessment->max_score }}</strong></p>@elseif($assessment->source_type !== 'manual')<p class="grading-help">Awaiting a valid fully graded result from the linked activity.</p>@endif
                <label>Grading method<select name="scores[{{ $assessment->id }}][status]" data-score-mode>@if($assessment->source_type !== 'manual')<option value="source" @selected($cell['mode'] === 'source')>Use linked result</option>@endif<option value="scored" @selected($cell['mode'] === 'scored')>{{ $assessment->source_type === 'manual' ? 'Enter score' : 'Teacher override' }}</option><option value="excused" @selected($cell['mode'] === 'excused')>Excused · excluded from grade</option>@if($assessment->source_type === 'manual')<option value="pending" @selected($cell['mode'] === 'pending')>Pending · not graded yet</option>@endif</select></label>
                <label>Score<input name="scores[{{ $assessment->id }}][score]" data-score-value type="number" min="0" max="{{ $assessment->max_score }}" step="0.01" value="{{ $cell['score'] }}"><small>Zero is a recorded score; blank manual scores stay pending.</small></label>
            </fieldset>
        @empty<p class="grading-help">Add an assessment before entering scores.</p>@endforelse
        <label>Private teacher observation<textarea name="note" rows="4" maxlength="3000" placeholder="Evidence of progress, a misconception to revisit, or your next teaching step.">{{ (int) old('grading_student') === $selectedStudent['id'] ? old('note', $selectedStudent['note']) : $selectedStudent['note'] }}</textarea></label>
        <button class="grading-button grading-primary grading-controls">Save scores & teacher note</button><p class="grading-help">Changes take effect after you save. These records are visible only to the teaching account that owns this section.</p>
    </form>
    @endif
    <a href="{{ route('teacher.students.show', $selectedStudent['id']) }}" class="grading-link grading-controls grading-private-detail">Open student learning profile →</a>
</aside>
