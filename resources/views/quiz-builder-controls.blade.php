<script>
const editableQuizzes = @json($editableQuizzes);
let editingQuizId = null;
let editingQuizStatus = 'draft';
const timingPanel=document.createElement('div');timingPanel.className='my-4 grid gap-3 rounded-xl border border-green-200 bg-green-50 p-4 sm:grid-cols-2';timingPanel.innerHTML='<label class="text-sm font-bold">Quiz timing<select name="untimed" class="mt-2 w-full rounded-lg border p-3"><option value="0">Timed questions</option><option value="1">Untimed practice assessment</option></select></label><label class="text-sm font-bold">Seconds per question<input name="question_seconds" type="number" min="30" max="600" value="60" class="mt-2 w-full rounded-lg border p-3"></label><p class="text-xs sm:col-span-2">Schedule closing times still apply to untimed quizzes. Speed XP is disabled for untimed attempts. Existing attempts keep their original settings.</p>';
document.getElementById('quizForm').prepend(timingPanel);
const legacyTimeField=document.querySelector('#quizForm [name="time_limit"]');if(legacyTimeField){legacyTimeField.type='hidden';legacyTimeField.required=false;legacyTimeField.value='1';legacyTimeField.closest('label')?.classList.add('hidden');}
const legacyXpField = document.querySelector('#quizForm [name="xp"]');
if (legacyXpField) {
    const rewardInfo = document.createElement('div');
    rewardInfo.className = 'rounded-xl bg-amber-50 p-4 text-sm text-primary';
    rewardInfo.textContent = 'Quiz rewards: 10 XP per correct answer, +5 extra XP for a correct answer within 30 seconds, and +100 XP for a perfect score. Rewards are credited once after grading.';
    legacyXpField.closest('label').replaceWith(rewardInfo);
    const xpCompatibility = document.createElement('input');
    xpCompatibility.type = 'hidden'; xpCompatibility.name = 'xp'; xpCompatibility.value = '0';
    document.getElementById('quizForm').append(xpCompatibility);
}
const sectionBoxes = Array.from(document.querySelectorAll('.quiz-section'));
const gradeFilter = document.getElementById('quizGradeFilter');
function showGradeSections() {
    document.querySelectorAll('.quiz-section-card').forEach(card => { card.hidden = card.dataset.grade !== gradeFilter.value; });
}
function updateSectionCount() {
    const selected = sectionBoxes.filter(box => box.checked);
    const students = selected.reduce((sum, box) => sum + Number(box.dataset.students || 0), 0);
    document.getElementById('sectionCount').textContent = selected.length + ' section(s) selected · ' + students + ' students';
    const summary = document.getElementById('selectedSectionSummary');
    summary.replaceChildren();
    selected.forEach(box => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'rounded-lg border border-green-200 bg-white px-3 py-2 text-left text-xs font-semibold text-primary';
        chip.textContent = box.dataset.label + ' ×';
        chip.setAttribute('aria-label', 'Remove ' + box.dataset.label);
        chip.onclick = () => { box.checked = false; updateSectionCount(); };
        summary.append(chip);
    });
    if (!selected.length) summary.textContent = 'Select at least one section to assign this quiz.';
}
sectionBoxes.forEach(box => box.addEventListener('change', updateSectionCount));
gradeFilter.onchange = showGradeSections;
document.getElementById('selectAllSections').onclick = () => { sectionBoxes.filter(box => box.dataset.grade === gradeFilter.value).forEach(box => box.checked = true); updateSectionCount(); };
document.getElementById('clearGradeSections').onclick = () => { sectionBoxes.filter(box => box.dataset.grade === gradeFilter.value).forEach(box => box.checked = false); updateSectionCount(); };
document.getElementById('manageSectionsLink').onclick = () => { document.getElementById('gradeSectionManager').open = true; };
showGradeSections();
updateSectionCount();
const oldQuestions = document.querySelector('textarea[name="questions"]');
const questionArea = document.createElement('div');
oldQuestions.parentElement.replaceWith(questionArea);
questionArea.innerHTML = '<h2 class="font-bold text-primary">Questions</h2><div id="questionCards" class="space-y-4 my-4"></div><button type="button" class="rounded-xl border p-3" id="addQuestion">+ Add question</button>';
const editBar = document.createElement('div');
editBar.className = 'mb-5 rounded-xl bg-green-100 p-4';
editBar.innerHTML = '<h2 id="editorHeading" class="font-bold">Create a new quiz</h2><p id="editNote" class="text-sm mt-2">Add questions and save a draft or publish to your class.</p><div class="flex gap-3 mt-3"><button type="button" id="saveChanges" hidden class="rounded-xl bg-primary text-white px-4 py-2">Save Changes</button><button type="button" id="newQuiz" class="rounded-xl border bg-white px-4 py-2">New Quiz / Cancel Edit</button></div>';
document.getElementById('quizForm').prepend(editBar);
let questionNumber = 0;
function addQuestion(initial = null) {
    const card = document.createElement('section');
    card.className = 'question-card rounded-xl border bg-green-50 p-4 space-y-3';
    card.innerHTML = `<label class="block">Question type<select class="type w-full border rounded-lg p-2"><option value="multiple_choice">Multiple choice</option><option value="identification">Identification</option><option value="true_false">True or false</option><option value="multiple_answer">Multiple answers (two or more)</option><option value="code_output">Predict code output</option><option value="fill_code">Fill in the code</option><option value="debugging">Debugging exercise</option><option value="ordering">Order the logic steps</option></select></label><label class="block">Question<textarea required maxlength="2000" rows="4" class="prompt w-full border rounded-lg p-2 font-mono" placeholder="Enter the question or C++ snippet"></textarea></label><div class="choices"></div><button type="button" class="remove text-red-700">Remove question</button>`;
    const number = questionNumber++;
    function renderChoices(initialOptions = null, correctAnswers = []) {
        const type = card.querySelector('.type').value;
        const area = card.querySelector('.choices');
        if (['identification','code_output','fill_code','debugging'].includes(type)) {
            area.innerHTML = '<label class="block font-bold text-primary">Correct answer *<input required maxlength="500" class="key w-full border rounded-lg p-2 font-normal" placeholder="Type the expected answer, e.g. while"></label><p class="mt-2 text-xs text-muted">Automatically checked. Identification ignores case; C++ and output answers are case sensitive. Extra whitespace is normalized.</p>';
            area.querySelector('.key').value = correctAnswers[0] || '';
            return;
        }
        if(type==='ordering'){area.innerHTML='<label class="block font-bold">Steps in the correct order (one per line; 2–8 steps)<textarea class="ordering-steps mt-2 w-full rounded-lg border p-3" rows="5" required></textarea></label><p class="mt-2 text-xs">Students arrange these steps. The order here is the answer key.</p>';area.querySelector('.ordering-steps').value=(correctAnswers.length?correctAnswers:initialOptions||[]).join('\n');return;}
        area.innerHTML = '<p class="font-bold text-primary mb-2">Correct answer' + (type === 'multiple_answer' ? 's * — check at least two choices' : ' * — select one choice') + '</p><p class="text-xs mb-3">The marked choices form the answer key for automatic checking. Each question is worth one point.</p>';
        const values = type === 'true_false' ? ['True','False'] : (initialOptions?.length ? initialOptions : ['','','','']);
        values.forEach((value, index) => {
            const row = document.createElement('div');
            row.className = 'flex gap-3 items-center mb-2';
            const key = document.createElement('input');
            key.type = type === 'multiple_answer' ? 'checkbox' : 'radio';
            key.name = 'key-' + number;
            key.className = 'key';
            key.checked = correctAnswers.includes(value);
            key.required = type !== 'multiple_answer';
            key.setAttribute('aria-label', 'Correct option ' + (index + 1));
            const option = document.createElement('input');
            option.className = 'option border rounded-lg p-2 w-full';
            option.required = true;
            option.maxLength = 500;
            option.value = value;
            option.placeholder = 'Option ' + (index + 1);
            option.readOnly = type === 'true_false';
            row.append(key, option);
            area.append(row);
        });
    }
    card.querySelector('.type').onchange = () => renderChoices();
    card.querySelector('.remove').onclick = () => card.remove();
    document.getElementById('questionCards').append(card);
    card.querySelector('.type').value = initial?.type || 'identification';
    if (!initial) card.querySelector('.type').value = 'multiple_choice';
    card.querySelector('.prompt').value = initial?.prompt || '';
    renderChoices(initial?.options, initial?.correct || []);
}
document.getElementById('addQuestion').onclick = () => addQuestion();
addQuestion();
function editQuiz(id) {
    const quiz = editableQuizzes.find(quiz => quiz.id === id);
    if (!quiz) return;
    editingQuizId = quiz.id;
    editingQuizStatus = quiz.status;
    const form = document.getElementById('quizForm');
    ['title','instructions','xp','time_limit','opens_at','closes_at','untimed','question_seconds'].forEach(name => form.elements[name].value = name==='untimed' ? (quiz[name]?'1':'0') : (quiz[name] ?? (name==='question_seconds'?'60':'')));
    sectionBoxes.forEach(box => box.checked = quiz.classroom_ids.map(String).includes(box.value));
    const firstSelected = sectionBoxes.find(box => box.checked);
    if (firstSelected) gradeFilter.value = firstSelected.dataset.grade;
    showGradeSections();
    updateSectionCount();
    document.getElementById('questionCards').replaceChildren();
    quiz.questions.forEach(question => addQuestion(question));
    document.getElementById('editorHeading').textContent = 'Editing: ' + quiz.title;
    document.getElementById('editNote').textContent = 'Save Changes keeps this quiz’s current status (' + quiz.status + '). Existing student scores stay as recorded; future submissions use the updated questions and answer keys.';
    document.getElementById('saveChanges').hidden = false;
    document.getElementById('message').classList.add('hidden');
    form.scrollIntoView({behavior:'smooth',block:'start'});
    form.elements.title.focus({preventScroll:true});
}
document.getElementById('newQuiz').onclick = () => {
    editingQuizId = null;
    editingQuizStatus = 'draft';
    const form = document.getElementById('quizForm');
    form.reset();
    showGradeSections();
    updateSectionCount();
    document.getElementById('questionCards').replaceChildren();
    addQuestion();
    document.getElementById('editorHeading').textContent = 'Create a new quiz';
    document.getElementById('editNote').textContent = 'Add questions and save a draft or publish to your class.';
    document.getElementById('saveChanges').hidden = true;
};
document.getElementById('saveChanges').onclick = () => saveQuiz(editingQuizStatus);
document.getElementById('quizForm').addEventListener('submit', event => {
    event.preventDefault();
    saveQuiz(editingQuizId ? editingQuizStatus : 'draft');
});
async function deleteQuiz(id, button) {
    if (!confirm('Delete this quiz? It will no longer be available to students. Saved student results will be retained.')) return;
    button.disabled = true;
    try {
        const response = await fetch('{{ url('/teacher-quizzes') }}/' + id, {method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Unable to delete quiz.');
        location.reload();
    } catch(error) {
        const message = document.getElementById('message');
        message.className = 'mb-5 rounded-xl bg-red-50 text-red-800 p-4';
        message.textContent = error.message;
        button.disabled = false;
    }
}
window.saveQuiz = async function(status) {
    const form = document.getElementById('quizForm');
    if (!form.reportValidity()) return;
    const data = Object.fromEntries(new FormData(form));
    data.classroom_ids = sectionBoxes.filter(box => box.checked).map(box => Number(box.value));
    delete data['classroom_ids[]'];
    if (!data.classroom_ids.length) {
        const message = document.getElementById('message');
        message.className = 'mb-5 rounded-xl bg-red-50 text-red-800 p-4';
        message.textContent = 'Select at least one grade and section.';
        return;
    }
    data.status = status;
    data.questions = Array.from(document.querySelectorAll('.question-card')).map(card => {
        const type = card.querySelector('.type').value;
        if(type==='ordering'){const steps=card.querySelector('.ordering-steps').value.split('\n').map(x=>x.trim()).filter(Boolean);return {type,prompt:card.querySelector('.prompt').value.trim(),options:steps,correct:steps};}
        const options = Array.from(card.querySelectorAll('.option')).map(x => x.value.trim());
        const correct = ['identification','code_output','fill_code','debugging'].includes(type) ? [card.querySelector('.key').value.trim()] : Array.from(card.querySelectorAll('.key')).flatMap((x,i) => x.checked ? [options[i]] : []);
        return {type, prompt:card.querySelector('.prompt').value.trim(), options, correct};
    });
    data.time_limit = Math.max(1, data.questions.length);
    const buttons = form.querySelectorAll('button');
    buttons.forEach(x => x.disabled = true);
    const message = document.getElementById('message');
    try {
        const url = editingQuizId ? '{{ url('/teacher-quizzes') }}/' + editingQuizId : '{{ route('teacher.quizzes.store') }}';
        const response = await fetch(url, {method:editingQuizId ? 'PUT' : 'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(data)});
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
        location.reload();
    } catch(error) {
        message.className = 'mb-5 rounded-xl bg-red-50 text-red-800 p-4';
        message.textContent = error.message;
        buttons.forEach(x => x.disabled = false);
    }
};
</script>
