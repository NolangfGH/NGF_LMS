<section class="mb-5 overflow-hidden rounded-2xl border border-green-200 bg-white shadow-card" aria-labelledby="assignmentSampleOutputHeading">
    <div class="border-b border-green-100 p-5">
        <h2 id="assignmentSampleOutputHeading" class="text-lg font-bold text-primary">{{ $labActivity['assignment'] && $labActivity['assignment']->kind !== 'challenge' ? 'Assignment Sample Output' : 'Challenge Sample Output' }}</h2>
        <p class="mt-2 text-sm leading-6 text-muted">Write your own C++ code to produce this output. Use the assignment instructions to decide which inputs and calculations your program needs.</p>
    </div>
    <div class="p-5">
        <p class="mb-2 font-mono text-[10px] font-bold uppercase tracking-widest text-secondary">Expected program output</p>
        <pre class="max-h-80 overflow-auto rounded-xl bg-[#102416] p-4 font-mono text-sm leading-7 text-green-50" tabindex="0" aria-label="Expected program output">{{ $labActivity['sample_output'] ?? $labActivity['assignment']?->sample_output }}</pre>
    </div>
</section>
