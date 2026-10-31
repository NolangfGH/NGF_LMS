(() => {
    const editor=document.getElementById('codeEditor');if(!editor)return;
    const wrapper=document.createElement('div');wrapper.style.cssText='position:relative;min-width:0;flex:1';editor.before(wrapper);wrapper.append(editor);
    const highlight=document.createElement('pre');highlight.setAttribute('aria-hidden','true');highlight.style.cssText='pointer-events:none;position:absolute;inset:0;overflow:hidden;margin:0;padding:16px;white-space:pre;font:14px/28px "JetBrains Mono",monospace;color:#f0fdf4;tab-size:4';wrapper.prepend(highlight);
    editor.style.cssText+=';position:relative;color:transparent;caret-color:#bbf7d0;tab-size:4';
    const tokens=/\/\/[^\n]*|\/\*[\s\S]*?\*\/|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\b(?:int|double|float|char|bool|void|return|if|else|for|while|do|break|continue|const|true|false|using|namespace|std|string|include)\b|\b\d+(?:\.\d+)?\b/g;
    function render(){highlight.replaceChildren();const source=editor.value;let end=0;for(const match of source.matchAll(tokens)){highlight.append(document.createTextNode(source.slice(end,match.index)));const span=document.createElement('span');span.textContent=match[0];span.style.color=match[0].startsWith('/')?'#8da988':/^["']/.test(match[0])?'#f9d77c':/^\d/.test(match[0])?'#9dd8ff':'#c4b5fd';highlight.append(span);end=match.index+match[0].length;}highlight.append(document.createTextNode(source.slice(end)+'\n'));highlight.scrollTop=editor.scrollTop;highlight.scrollLeft=editor.scrollLeft;}
    editor.addEventListener('input',render);editor.addEventListener('codequest:editor-change',render);editor.addEventListener('scroll',()=>{highlight.scrollTop=editor.scrollTop;highlight.scrollLeft=editor.scrollLeft;});render();
})();
