<script>
const csrf=document.querySelector('meta[name="csrf-token"]').content;
const assignmentForm=document.getElementById('assignmentFormElement');
const assignmentBoxes=Array.from(document.querySelectorAll('.assignment-section'));
const assignmentGrade=document.getElementById('assignmentGrade');
const assignmentEditor=@json($assignmentEditor);
const assignmentIndexUrl=@json($assignmentIndexUrl);
let savingAssignment=false;
function showFeedback(message,success=false){
    const box=document.getElementById('feedback');
    box.className='mb-6 rounded-xl border px-4 py-3 text-sm font-semibold '+(success?'border-green-200 bg-green-50 text-green-900':'border-red-200 bg-red-50 text-red-800');
    box.textContent=message;box.classList.remove('hidden');
}
function updateAudience(){
    document.querySelectorAll('.assignment-section-card').forEach(card=>card.hidden=card.dataset.grade!==assignmentGrade.value);
    const selected=assignmentBoxes.filter(box=>box.checked),count=selected.reduce((sum,box)=>sum+Number(box.dataset.count),0);
    document.getElementById('assignmentAudienceSummary').textContent=selected.length+' section(s) · '+count+' students selected';
    document.getElementById('selectedAssignmentStudents').textContent=count+' students selected';
}
if(assignmentEditor){
    ['title','school_term','objective','due_at','xp','sample_output','csta_standard','time_limit_minutes'].forEach(name=>{
        assignmentForm.elements[name].value=assignmentEditor[name]??'';
    });
    assignmentForm.querySelectorAll('[name="difficulty"]').forEach(radio=>radio.checked=radio.value===assignmentEditor.difficulty);
    const selectedIds=new Set(assignmentEditor.classroom_ids.map(String));
    assignmentBoxes.forEach(box=>box.checked=selectedIds.has(box.value));
    const first=assignmentBoxes.find(box=>box.checked);
    if(first)assignmentGrade.value=first.dataset.grade;
}
assignmentGrade.onchange=updateAudience;assignmentBoxes.forEach(box=>box.onchange=updateAudience);
document.getElementById('assignmentSelectAll').onclick=()=>{assignmentBoxes.filter(box=>box.dataset.grade===assignmentGrade.value).forEach(box=>box.checked=true);updateAudience();};
document.getElementById('assignmentClear').onclick=()=>{assignmentBoxes.filter(box=>box.dataset.grade===assignmentGrade.value).forEach(box=>box.checked=false);updateAudience();};
updateAudience();
const assignmentNotice=@json(session('assignment_notice'));
if(assignmentNotice)showFeedback(assignmentNotice,true);
function setAssignmentBusy(busy){
    savingAssignment=busy;
    assignmentForm.querySelectorAll('button,input,select,textarea').forEach(control=>control.disabled=busy);
    document.querySelectorAll('[data-delete-assignment]').forEach(button=>button.disabled=busy);
}
async function assignmentRequest(url,data){
    const response=await fetch(url,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:data});
    if(response.status===419)throw new Error('Your session expired. Reload the page and try again.');
    const result=await response.json();
    if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'Unable to save changes.');
    return result;
}
async function saveAssignment(status){
    if(savingAssignment||!assignmentForm.reportValidity())return;
    if(!assignmentBoxes.some(box=>box.checked))return showFeedback('Select at least one grade and section.');
    const data=new FormData(assignmentForm);data.set('status',status);
    if(assignmentEditor)data.set('_method','PUT');
    setAssignmentBusy(true);
    try{
        const result=await assignmentRequest(assignmentEditor?assignmentEditor.update_url:'{{ route('teacher.assignments.store') }}',data);
        showFeedback(result.message,true);
        location.assign(assignmentIndexUrl);
    }catch(error){setAssignmentBusy(false);showFeedback(error.message);document.getElementById('feedback').scrollIntoView({block:'center',behavior:'smooth'});}
}
assignmentForm.addEventListener('submit',event=>{event.preventDefault();saveAssignment(assignmentEditor?.status??'draft');});
document.querySelectorAll('[data-delete-assignment]').forEach(button=>{
    button.addEventListener('click',async()=>{
        if(savingAssignment)return;
        if(!confirm('Delete "'+button.dataset.title+'"? It will be removed from students’ assignments. Saved student work and previously earned XP will be kept. Unsaved builder changes will be discarded.'))return;
        const data=new FormData();data.set('_method','DELETE');
        setAssignmentBusy(true);
        try{
            const result=await assignmentRequest(button.dataset.deleteUrl,data);
            showFeedback(result.message,true);location.assign(assignmentIndexUrl);
        }catch(error){setAssignmentBusy(false);showFeedback(error.message);document.getElementById('feedback').scrollIntoView({block:'center',behavior:'smooth'});}
    });
});
function previewAssignment(){
    document.getElementById('previewTitle').textContent=assignmentForm.elements.title.value||'Untitled assignment';
    document.getElementById('previewAudience').textContent=assignmentBoxes.filter(box=>box.checked).map(box=>box.dataset.label).join(', ')||'No sections selected';
    document.getElementById('previewTerm').textContent=assignmentForm.elements.school_term.value||'Choose a school term';
    document.getElementById('previewObjective').textContent=assignmentForm.elements.objective.value||'No objective entered';
    document.getElementById('previewSampleOutput').textContent=assignmentForm.elements.sample_output.value||'No sample output provided.';
    document.getElementById('previewDue').textContent=assignmentForm.elements.due_at.value.replace('T',' ')||'No due date';
    document.getElementById('previewTimeLimit').textContent=assignmentForm.elements.time_limit_minutes.value ? assignmentForm.elements.time_limit_minutes.value+' minutes from first opening Code Lab' : 'No allotted countdown';
    document.getElementById('preview').classList.remove('hidden');document.getElementById('preview').classList.add('flex');
}
function closePreview(){document.getElementById('preview').classList.add('hidden');document.getElementById('preview').classList.remove('flex');}
document.addEventListener('keydown',event=>{if(event.key==='Escape')closePreview();});
document.getElementById('assignmentSearch').addEventListener('input',function(){const query=this.value.toLowerCase();document.querySelectorAll('.assignment-record').forEach(record=>record.classList.toggle('hidden',query&&!record.textContent.toLowerCase().includes(query)));});
</script>
