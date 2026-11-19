<div class="wokwi-launcher" data-wokwi-launcher>
    <a class="wokwi-open" href="https://wokwi.com/projects/new/arduino-uno" target="_blank" rel="noopener noreferrer" data-wokwi-open data-wokwi-editor="{{ $wokwiEditor }}">Open in Wokwi ↗</a>
    <p class="wokwi-note">Copies this sketch when clicked. Paste it into Wokwi’s sketch.ino, add the circuit parts, wiring and required libraries, then start the simulation. {{ ($wokwiReview ?? false) ? 'Testing a copy keeps the submitted code unchanged.' : 'Paste your tested code back below before saving or submitting it in the LMS.' }}</p>
    <p class="wokwi-status" data-wokwi-status role="status" aria-live="polite"></p>
    <details data-wokwi-help>
        <summary>Copy sketch manually{{ ($wokwiReview ?? false) ? '' : ' / bring tested code back' }}</summary>
        <label class="wokwi-label">Sketch to copy<textarea data-wokwi-source readonly rows="5" spellcheck="false" aria-label="Sketch to copy">Click Open in Wokwi to prepare the current sketch here.</textarea></label>
        <button type="button" class="wokwi-secondary" data-wokwi-copy data-wokwi-editor="{{ $wokwiEditor }}">Copy current sketch</button>
        @unless($wokwiReview ?? false)
            <label class="wokwi-label">Paste your tested sketch.ino from Wokwi<textarea data-wokwi-return rows="5" maxlength="{{ $wokwiMaxLength ?? 20000 }}" spellcheck="false" placeholder="Copy all of sketch.ino from Wokwi, then paste it here."></textarea></label>
            <button type="button" class="wokwi-secondary" data-wokwi-apply data-wokwi-editor="{{ $wokwiEditor }}">Use tested sketch in this editor</button>
        @endunless
    </details>
    <noscript>Open Wokwi, then copy and paste your sketch manually. Bring edits back to the LMS editor before saving or submitting.</noscript>
</div>
