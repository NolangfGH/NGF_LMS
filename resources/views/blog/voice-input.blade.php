<div data-blog-voice class="rounded-2xl border border-green-200 bg-green-50/60 p-4">
    <p class="text-xs font-bold text-primary">Voice message (optional)</p>
    <div class="mt-3 flex flex-wrap items-center gap-2"><button type="button" data-voice-record class="rounded-xl border border-green-200 bg-white px-3 py-2 text-xs font-bold text-primary">🎙 Record voice</button><button type="button" data-voice-stop hidden class="rounded-xl bg-red-700 px-3 py-2 text-xs font-bold text-white">Stop recording</button><button type="button" data-voice-remove hidden class="rounded-xl border border-green-200 px-3 py-2 text-xs">Remove voice</button><span data-voice-status role="status" class="text-xs text-secondary"></span></div>
    <label class="mt-3 block text-xs text-secondary">Or upload a voice clip<input type="file" name="audio" accept="audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,.webm,.ogg,.mp3,.m4a,.mp4,.wav" data-voice-file class="mt-2 block w-full text-xs"></label>
    <audio controls hidden preload="none" data-voice-preview class="mt-3 w-full"></audio>
    <p class="mt-3 text-[11px] leading-5 text-secondary">Record up to 90 seconds, or upload a clip up to 2 MB. Your microphone is used only when you press Record. Voice messages stay private.</p>
</div>
