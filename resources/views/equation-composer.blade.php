<details data-equation-builder @isset($equationTarget) data-equation-target="{{ $equationTarget }}" @endisset class="equation-composer mt-3">
    <summary><span aria-hidden="true">∑</span> Equation &amp; scientific notation <small>Math editor</small></summary>
    <div class="equation-composer-body">
        <p>Choose a template, enter the values, then insert the equation at your cursor. The saved source is editable; the published lesson or response displays formatted mathematics.</p>
        <label>Equation templates
            <select data-equation-template>
                <option value="">Choose a template…</option>
                @foreach([
                    '\frac{a}{b}' => 'Fraction · a over b',
                    '\sqrt{x}' => 'Square root',
                    '\root{3}{x}' => 'Nth root',
                    'x^{2}+y^{2}=r^{2}' => 'Exponents · equation of a circle',
                    'a_{n}=a_{1}+(n-1)d' => 'Subscripts · arithmetic sequence',
                    'x=\frac{-b\pm\sqrt{b^{2}-4ac}}{2a}' => 'Quadratic formula',
                    '\sin{\theta}=\frac{a}{c}' => 'Trigonometry',
                    '\sum_{i=1}^{n}x_i' => 'Summation',
                    '\int_{0}^{1}x^{2}\,dx' => 'Integral',
                    '\lim_{x\to0}\frac{\sin{x}}{x}' => 'Limit',
                    '\vec{F}=m\vec{a}' => 'Vector · Newton’s second law',
                    '6.02\times10^{23}\text{ mol}^{-1}' => 'Scientific notation & units',
                    '\chem{H_2O}' => 'Molecular formula',
                    '\chem{2H_2+O_2\to2H_2O}' => 'Chemical reaction',
                    '\chem{SO_4^{2-}}' => 'Chemical ion',
                    '\isotope{14}{6}{C}' => 'Isotope notation',
                    'v=\frac{d}{t}\text{ m/s}' => 'Scientific formula and units',
                ] as $formula=>$label)<option value="{{ str_replace('\\,',' ',$formula) }}">{{ $label }}</option>@endforeach
            </select>
        </label>
        <div class="equation-symbols" aria-label="Mathematical and scientific symbols">
            @foreach(['\alpha'=>'α','\beta'=>'β','\theta'=>'θ','\pi'=>'π','\Delta'=>'Δ','\mu'=>'μ','\lambda'=>'λ','\times'=>'×','\div'=>'÷','\pm'=>'±','\approx'=>'≈','\neq'=>'≠','\le'=>'≤','\ge'=>'≥','\infty'=>'∞','\to'=>'→','\reversible'=>'⇌','\degree'=>'°','\sum'=>'∑','\int'=>'∫'] as $notation=>$symbol)
                <button type="button" data-equation-symbol="{{ $notation }} " title="{{ $notation }}" aria-label="Insert {{ $symbol }}">{{ $symbol }}</button>
            @endforeach
        </div>
        <label>Equation source<textarea data-equation-source rows="2" maxlength="500" spellcheck="false" placeholder="\frac{a}{b} + \sqrt{x} or \chem{H_2O}"></textarea></label>
        <div class="equation-display-preview" data-equation-preview aria-live="polite">Your equation preview appears here.</div>
        <p data-equation-status class="equation-status" role="status">Choose a template or type your equation.</p>
        <div class="equation-actions"><button type="button" data-equation-insert="inline" disabled>Insert inline</button><button type="button" data-equation-insert="display" disabled>Insert display equation</button></div>
        <details class="equation-reference"><summary>Typing guide & response preview</summary><p>Use <code>^&#123;2&#125;</code> for powers, <code>_&#123;2&#125;</code> for subscripts, <code>\frac&#123;a&#125;&#123;b&#125;</code> for stacked fractions, <code>\sqrt&#123;x&#125;</code> for roots, and <code>\chem&#123;H_2O&#125;</code> for chemical notation. Inserted formulas use <code>\( … \)</code> or <code>\[ … \]</code> delimiters.</p><p class="equation-preview-label">Formatted response preview</p><div data-equation-document-preview class="equation-document-preview"></div></details>
    </div>
</details>
