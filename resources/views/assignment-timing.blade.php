<section class="rounded-2xl border border-green-200 bg-green-50 p-5">
    <label for="assignmentTimeLimit" class="block text-sm font-bold text-primary">Allotted time limit (minutes)</label>
    <div class="mt-3 flex max-w-xs items-center gap-3">
        <input id="assignmentTimeLimit" name="time_limit_minutes" type="number" min="1" max="240" step="1" value="30" placeholder="For example, 30" aria-describedby="assignmentTimeHelp" class="min-w-0 w-full rounded-xl border border-green-200 bg-white px-4 py-3 text-sm outline-none focus:border-primary">
        <span class="text-sm font-bold text-secondary">minutes</span>
    </div>
    <p id="assignmentTimeHelp" class="mt-3 text-xs leading-6 text-muted">Choose 1–240 minutes, or leave blank for no countdown. Each student's timer starts when they first open this assignment in Code Lab and continues if they leave or refresh. Work closes when the time limit or due date is reached, whichever comes first. A new time limit applies to sessions that have not started.</p>
</section>
