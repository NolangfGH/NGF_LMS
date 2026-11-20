const {test} = require('node:test');
const assert = require('node:assert/strict');
const {parseFormula, describe, splitFormula, mathElement} = require('../../public/js/lesson-equations.js');
const tags = tree => [tree.tag,...tree.children.flatMap(tags)];

test('nested fractions, powers, roots, scripts and integrals create native mathematical structures', () => {
    const tree = parseFormula(String.raw`x=\frac{-b\pm\sqrt{b^{2}-4ac}}{2a}`);
    assert.ok(tags(tree).includes('mfrac'));
    assert.ok(tags(tree).includes('msqrt'));
    assert.ok(tags(tree).includes('msup'));
    assert.match(describe(tree), /fraction .*square root.* over .*end fraction/);
    assert.ok(tags(parseFormula(String.raw`\root{3}{x}`)).includes('mroot'));
    assert.ok(tags(parseFormula(String.raw`\int_{0}^{1}x^{2}`)).includes('msubsup'));
});

test('chemical, vector, scientific and isotope notation preserve scripts and upright chemical symbols', () => {
    const reaction = parseFormula(String.raw`\chem{2H_2+O_2\to2H_2O}`);
    assert.ok(tags(reaction).includes('msub'));
    assert.match(describe(reaction), /H subscript 2 .*yields/);
    assert.equal(reaction.children[0].children[1].children[0].attrs.mathvariant,'normal');
    assert.ok(tags(parseFormula(String.raw`\chem{SO_4^{2-}}`)).includes('msubsup'));
    assert.ok(tags(parseFormula(String.raw`\isotope{14}{6}{C}`)).includes('mmultiscripts'));
    assert.ok(tags(parseFormula(String.raw`\vec{F}=m\vec{a}`)).includes('mover'));
    assert.ok(tags(parseFormula(String.raw`6.02\times10^{23}\text{ mol}^{-1}`)).includes('mtext'));
});

test('invalid and oversized inputs remain plain source, without evaluating or loading anything', () => {
    for (const source of [String.raw`\frac{a}`, String.raw`x^{2`, String.raw`\unsupported{x}`, '^2', 'a'.repeat(501)]) assert.throws(() => parseFormula(source));
    const parts = splitFormula(String.raw`Before \(\frac{1}{2}\) and \[x^{2}\]. Invalid \(\sqrt{\).`);
    assert.equal(parts[1].tree.children[0].tag,'mfrac');
    assert.equal(parts[3].display,true);
    assert.equal(parts.at(-2).text,String.raw`\(\sqrt{\)`);
    assert.equal(parts.at(-1).text,'.');
});

test('rendering uses namespace elements and text nodes, including accessibility narration', () => {
    const factory = tag => ({tag,children:[],attrs:{},dataset:{},append(child){this.children.push(child);},setAttribute(key,value){this.attrs[key]=value;}});
    const document = {createElement:factory,createElementNS(ns,tag){assert.equal(ns,'http://www.w3.org/1998/Math/MathML');return factory(tag);}};
    const wrapper = mathElement(document,parseFormula(String.raw`\frac{1}{2}`),true);
    assert.equal(wrapper.children[0].tag,'math');
    assert.equal(wrapper.children[0].attrs.display,'block');
    assert.equal(wrapper.dataset.equationSpeech,'fraction 1 over 2 end fraction');
    assert.equal(wrapper.children[0].children[0].children[0].tag,'mfrac');
});
