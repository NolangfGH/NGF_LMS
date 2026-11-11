<div class="onecompiler-launcher">
    <button type="button" class="onecompiler-open" data-onecompiler-editor="{{ $compilerEditor }}" @if($compilerReview ?? false) data-onecompiler-review="true" @endif @if(!empty($compilerExpected)) data-onecompiler-expected="{{ $compilerExpected }}" @endif>Open in OneCompiler ↗</button>
    <p class="onecompiler-note">{{ ($compilerReview ?? false) ? 'Opens this C++ code in the external OneCompiler editor for testing.' : 'Sends your current C++ code to OneCompiler when clicked. Bring edits back to an open LMS draft.' }}</p>
    <noscript><a href="https://onecompiler.com/cpp" target="_blank" rel="noopener noreferrer">Open OneCompiler and paste your code manually</a></noscript>
</div>
