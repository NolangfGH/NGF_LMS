(function () {
    'use strict';
    const symbols = {
        alpha:'α', beta:'β', gamma:'γ', delta:'δ', epsilon:'ε', theta:'θ', lambda:'λ', mu:'μ', pi:'π', rho:'ρ', sigma:'σ', tau:'τ', phi:'φ', omega:'ω',
        Gamma:'Γ', Delta:'Δ', Theta:'Θ', Lambda:'Λ', Sigma:'Σ', Phi:'Φ', Omega:'Ω',
        times:'×', div:'÷', cdot:'·', pm:'±', mp:'∓', approx:'≈', neq:'≠', le:'≤', ge:'≥', infty:'∞', to:'→', rightarrow:'→',
        leftarrow:'←', leftrightarrow:'↔', reversible:'⇌', degree:'°', perp:'⊥', parallel:'∥', angle:'∠', therefore:'∴', partial:'∂', nabla:'∇',
        sum:'∑', prod:'∏', int:'∫', oint:'∮', lim:'lim', sin:'sin', cos:'cos', tan:'tan', log:'log', ln:'ln',
    };
    const spokenSymbols = {'×':'times','÷':'divided by','·':'times','±':'plus or minus','≈':'approximately','≠':'not equal to','≤':'less than or equal to','≥':'greater than or equal to','∞':'infinity','→':'yields','←':'left arrow','⇌':'reversible reaction','∑':'sum','∏':'product','∫':'integral','∂':'partial','°':'degrees','=':'equals','+':'plus','-':'minus','−':'minus','/':'divided by'};
    const sourceLimit = 500;
    const node = (tag, children = [], text = '', attrs = {}) => ({tag, children, text, attrs});
    const row = children => node('mrow', children);
    function parseFormula(source) {
        source = String(source);
        if (!source.trim() || [...source].length > sourceLimit) throw new Error('Use 1–500 characters per equation.');
        let index = 0, count = 0;
        const error = message => { throw new Error(message); };
        const spaces = () => { while (/\s/u.test(source[index] || '') && index < source.length) index++; };
        const group = (depth, upright = false) => {
            spaces();
            if (source[index++] !== '{') error('Use braces { } around a formula part.');
            const children = sequence(depth + 1, upright);
            if (source[index++] !== '}') error('Close each opening brace.');
            if (!children.length) error('Fill each formula part.');
            return row(children);
        };
        const literalGroup = () => {
            spaces();
            if (source[index++] !== '{') error('Use braces { } around words or units.');
            const end = source.indexOf('}', index);
            if (end < 0) error('Close each opening brace.');
            const text = source.slice(index,end); index = end + 1;
            if (!text.trim() || /[{}\\]/u.test(text)) error('Enter plain text inside text braces.');
            return node('mtext', [], text);
        };
        function atom(depth, upright) {
            if (++count > 300 || depth > 14) error('The equation is too deeply nested or long.');
            spaces();
            const character = source[index];
            if (!character || character === '}') error('Finish the formula part.');
            if (character === '{') return group(depth, upright);
            if (character === '\\') {
                index++;
                const command = /^[A-Za-z]+/.exec(source.slice(index))?.[0];
                if (!command) error('Choose a supported symbol or formula template.');
                index += command.length;
                if (command === 'frac') return node('mfrac',[group(depth,upright),group(depth,upright)]);
                if (command === 'sqrt') return node('msqrt',[group(depth,upright)]);
                if (command === 'root') { const degree = group(depth,upright); return node('mroot',[group(depth,upright),degree]); }
                if (command === 'chem' || command === 'unit') return group(depth,true);
                if (command === 'text') return literalGroup();
                if (['vec','bar','hat'].includes(command)) return node('mover',[group(depth,upright),node('mo',[],{vec:'→',bar:'¯',hat:'^'}[command])],'',{accent:'true'});
                if (command === 'isotope') {
                    const mass = group(depth,true), atomic = group(depth,true), element = group(depth,true);
                    return node('mmultiscripts',[element,node('mprescripts'),atomic,mass]);
                }
                if (!Object.hasOwn(symbols,command)) error('Unsupported command: \\'+command+'. Use the templates or symbol buttons.');
                const text = symbols[command];
                return node(/^[\p{L}]+$/u.test(text) ? 'mi' : 'mo',[],text, text.length > 1 || upright ? {mathvariant:'normal'} : {});
            }
            if (character === '^' || character === '_') error('An exponent or subscript needs a base.');
            const number = /^\d+(?:\.\d+)?/.exec(source.slice(index))?.[0];
            if (number) { index += number.length; return node('mn',[],number); }
            const char = String.fromCodePoint(source.codePointAt(index));
            index += char.length;
            if (/\p{L}/u.test(char)) return node('mi',[],char,upright ? {mathvariant:'normal'} : {});
            return node('mo',[],char);
        }
        function sequence(depth = 0, upright = false) {
            const result = [];
            spaces();
            while (index < source.length && source[index] !== '}') {
                let base = atom(depth,upright), sub = null, sup = null;
                spaces();
                while (source[index] === '_' || source[index] === '^') {
                    const operator = source[index++];
                    if (operator === '_' && sub || operator === '^' && sup) error('Use braces to group a single exponent or subscript.');
                    const argument = atom(depth + 1,upright);
                    if (operator === '_') sub = argument; else sup = argument;
                    spaces();
                }
                if (sub && sup) base = node('msubsup',[base,sub,sup]);
                else if (sub) base = node('msub',[base,sub]);
                else if (sup) base = node('msup',[base,sup]);
                result.push(base);
                spaces();
            }
            return result;
        }
        const tree = row(sequence());
        if (index !== source.length) error('Remove the extra closing brace.');
        return tree;
    }
    function describe(tree) {
        const words = tree.children.map(describe);
        if (tree.tag === 'mfrac') return 'fraction '+words[0]+' over '+words[1]+' end fraction';
        if (tree.tag === 'msqrt') return 'square root of '+words[0];
        if (tree.tag === 'mroot') return words[1]+' root of '+words[0];
        if (tree.tag === 'msub') return words[0]+' subscript '+words[1];
        if (tree.tag === 'msup') return words[0]+' to the power '+words[1];
        if (tree.tag === 'msubsup') return words[0]+' subscript '+words[1]+' to the power '+words[2];
        if (tree.tag === 'mmultiscripts') return words[3]+', atomic number '+words[2]+', '+words[0];
        if (tree.tag === 'mover') return words[0]+' with '+words[1]+' above';
        return spokenSymbols[tree.text] || tree.text || words.filter(Boolean).join(' ');
    }
    function mathElement(document, tree, display = false) {
        const ns = 'http://www.w3.org/1998/Math/MathML';
        const make = item => {
            const element = document.createElementNS(ns,item.tag);
            Object.entries(item.attrs).forEach(([key,value]) => element.setAttribute(key,value));
            if (item.text) element.textContent = item.text;
            item.children.forEach(child => element.append(make(child)));
            return element;
        };
        const wrapper = document.createElement('span');
        wrapper.className = display ? 'lesson-equation lesson-equation-display' : 'lesson-equation';
        wrapper.dataset.equationSpeech = describe(tree);
        const math = document.createElementNS(ns,'math');
        math.setAttribute('display',display ? 'block' : 'inline');
        math.setAttribute('aria-label',describe(tree));
        math.append(make(tree)); wrapper.append(math);
        return wrapper;
    }
    function splitFormula(text) {
        const result = [];
        let position = 0;
        const pattern = /\\\(([^\r\n]{1,500}?)\\\)|\\\[([^\r\n]{1,500}?)\\\]/gu;
        for (const match of text.matchAll(pattern)) {
            if (match.index > position) result.push({text:text.slice(position,match.index)});
            try { result.push({tree:parseFormula(match[1] ?? match[2]),display:match[2] !== undefined}); }
            catch { result.push({text:match[0]}); }
            position = match.index + match[0].length;
        }
        if (position < text.length) result.push({text:text.slice(position)});
        return result;
    }
    function renderDelimited(document, root) {
        const walker = document.createTreeWalker(root,4);
        const textNodes = [];
        while (walker.nextNode()) if (!walker.currentNode.parentElement?.closest('.lesson-equation')) textNodes.push(walker.currentNode);
        for (const original of textNodes) {
            if (!original.textContent.includes('\\(') && !original.textContent.includes('\\[')) continue;
            const parts = splitFormula(original.textContent);
            if (!parts.some(part => part.tree)) continue;
            const fragment = document.createDocumentFragment();
            parts.forEach(part => fragment.append(part.tree ? mathElement(document,part.tree,part.display) : document.createTextNode(part.text)));
            original.replaceWith(fragment);
        }
    }
    function attachBuilder(document, root) {
        if (root.dataset.equationReady) return;
        root.dataset.equationReady = 'true';
        const source = root.querySelector('[data-equation-source]');
        const preview = root.querySelector('[data-equation-preview]');
        const previewText = root.querySelector('[data-equation-status]');
        const inserts = [...root.querySelectorAll('[data-equation-insert]')];
        const richRoot = root.closest('[data-lesson-formatting]');
        const target = richRoot?.querySelector('[data-format-editor]') || document.getElementById(root.dataset.equationTarget);
        const documentPreview = root.querySelector('[data-equation-document-preview]');
        let range = null, tree = null;
        const remember = () => {
            if (target instanceof document.defaultView.HTMLTextAreaElement) range = [target.selectionStart,target.selectionEnd];
        };
        const updateDocument = () => {
            const value = richRoot ? target.innerText : target.value;
            documentPreview.textContent = value || 'Insert an equation to see how your response will look.';
            renderDelimited(document,documentPreview);
        };
        const update = () => {
            try {
                tree = parseFormula(source.value);
                preview.replaceChildren(mathElement(document,tree,true));
                previewText.textContent = 'Ready to insert · '+describe(tree);
            } catch (error) {
                tree = null;
                preview.textContent = 'Your equation preview appears here.';
                previewText.textContent = source.value.trim() ? error.message : 'Choose a template or type your equation.';
            }
            inserts.forEach(button => { button.disabled = !tree; });
        };
        const insertSource = text => {
            source.setRangeText(text,source.selectionStart,source.selectionEnd,'end');
            source.focus(); update();
        };
        root.querySelector('[data-equation-template]').addEventListener('change', event => {
            if (!event.target.value) return;
            source.value = event.target.value; source.focus(); update(); event.target.value = '';
        });
        root.querySelectorAll('[data-equation-symbol]').forEach(button => button.addEventListener('click',() => insertSource(button.dataset.equationSymbol)));
        source.addEventListener('input',update);
        inserts.forEach(button => button.addEventListener('click',() => {
            if (!tree || !target) return;
            const display = button.dataset.equationInsert === 'display';
            const notation = (display ? '\\[' : '\\(')+source.value.trim()+(display ? '\\]' : '\\)');
            if (richRoot) {
                richRoot.dispatchEvent(new CustomEvent('ngf:insert-equation',{detail:notation}));
            } else {
                const [start,end] = range || [target.selectionStart,target.selectionEnd];
                if (target.maxLength >= 0 && target.value.length-(end-start)+notation.length > target.maxLength) {
                    previewText.textContent = 'This equation would exceed the response character limit.'; return;
                }
                target.setRangeText(notation,start,end,'end');
                target.dispatchEvent(new Event('input',{bubbles:true}));
                target.focus(); remember();
            }
            updateDocument();
        }));
        if (target) {
            ['input','select','keyup','click'].forEach(event => target.addEventListener(event,() => { remember(); if(event==='input') updateDocument(); }));
            updateDocument();
        }
        update();
    }
    function initialize(document, window) {
        document.querySelectorAll('[data-equation-display]').forEach(root => renderDelimited(document,root));
        document.querySelectorAll('[data-equation-builder]').forEach(root => attachBuilder(document,root));
        window.addEventListener('ngf:explanation-added',event => event.detail.querySelectorAll('[data-equation-builder]').forEach(root => attachBuilder(document,root)));
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { parseFormula, describe, splitFormula, mathElement };
    if (typeof document !== 'undefined' && typeof window !== 'undefined') initialize(document,window);
})();
