@php($arduinoAssignment = in_array($editingAssignment?->unit, ['Arduino UNO R3', \App\Services\CreativeTechnologyCurriculum::ROBOTICS_UNIT], true) || \App\Services\LearningCourses::environment($selectedAssignmentCourse??'cpp')==='arduino-uno')
@php($academicAssignment = $editingAssignment?->unit === \App\Services\AcademicCurriculum::UNIT || \App\Services\LearningCourses::isAcademic($selectedAssignmentCourse??'cpp'))
@php($designAssignment = $editingAssignment?->unit === \App\Services\CreativeTechnologyCurriculum::DESIGN_UNIT || ($selectedAssignmentCourse??'')==='ct7' || $academicAssignment)
<section class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5" id="assignmentTests"><h3 class="font-bold text-primary">{{ $academicAssignment ? 'Study notebook & learning hints' : ($designAssignment ? 'Design notebook & learning hints' : ($arduinoAssignment ? 'Arduino sketch & learning hints' : 'Automatic C++ checks')) }}</h3><p class="mt-2 text-xs leading-6 text-secondary">{{ $academicAssignment ? 'Students submit their reasoning, solution and evidence for teacher review.' : ($designAssignment ? 'Students submit their design notes and observed evidence. Use your learning rubric to review the actual design.' : ($arduinoAssignment ? 'Students save their sketch and submit it with an explanation. Verify the circuit and observed behavior in Arduino IDE or a compatible Uno simulator, then use your learning rubric.' : "Add up to eight input/output tests. Hidden tests stay private. Weights give partial credit; these checks support the teacher's final rubric.")) }}</p>
<details class="mt-4"><summary class="cursor-pointer text-sm font-bold">{{ $academicAssignment ? 'Optional study notebook template' : ($designAssignment ? 'Optional design notebook template' : ($arduinoAssignment ? 'Optional Arduino starter sketch' : 'Optional C++ starter code')) }}</summary><label class="mt-3 block text-xs font-bold">{{ $academicAssignment ? 'Starter study-notebook.txt' : ($designAssignment ? 'Starter design-notebook.txt' : ($arduinoAssignment ? 'Starter sketch.ino' : 'Starter main.cpp')) }}<textarea id="assignment-starter-editor" name="starter_code" maxlength="20000" rows="6" class="mt-2 w-full rounded-xl border bg-white p-3 font-mono text-xs">{{ $editingAssignment?->starter_code }}</textarea></label>
@if($arduinoAssignment)
@include('wokwi-button', ['wokwiEditor'=>'assignment-starter-editor'])
@elseif(!$designAssignment)
@include('onecompiler-button', ['compilerEditor'=>'assignment-starter-editor'])
@endif
</details>
<div id="testCaseRows" @if($arduinoAssignment || $designAssignment) hidden @endif class="mt-4 space-y-3"></div><button type="button" id="addTestCase" @if($arduinoAssignment || $designAssignment) hidden @endif class="mt-3 rounded-xl border border-green-200 bg-white px-4 py-3 text-xs font-bold text-primary">＋ Add input/output test</button>
<input type="hidden" name="tests_included" value="1"><label class="mt-5 block text-xs font-bold">Progressive hints (one per line; up to five)<textarea id="assignmentHints" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border bg-white p-3 text-sm">{{ implode("\n", $editingAssignment?->hints ?? []) }}</textarea></label>
</section>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const form=document.getElementById('assignmentFormElement'), rows=document.getElementById('testCaseRows');
    const initial=@json($editingAssignment?->test_cases ?? []);
    function reindex(){[...rows.children].forEach((row,index)=>row.querySelectorAll('[data-test-field]').forEach(input=>input.name=`test_cases[${index}][${input.dataset.testField}]`));}
    function add(test={input:'',output:'',hidden:false,weight:1}){
        if(rows.children.length>=8)return;
        const row=document.createElement('fieldset');row.className='rounded-xl border border-green-200 bg-white p-4';
        const grid=document.createElement('div');grid.className='grid gap-3 sm:grid-cols-2';
        for(const [key,label] of [['input','Program input (stdin)'],['output','Expected output']]){const holder=document.createElement('label');holder.className='text-xs font-bold';holder.textContent=label;const input=document.createElement('textarea');input.rows=3;input.maxLength=5000;input.className='mt-2 w-full rounded-lg border p-2 font-mono text-xs';input.dataset.testField=key;input.value=test[key]??'';holder.append(input);grid.append(holder);}
        row.append(grid);
        const visibility=document.createElement('select');visibility.dataset.testField='hidden';visibility.className='mt-3 rounded-lg border p-2 text-xs';visibility.setAttribute('aria-label','Test visibility');for(const [value,label]of [['0','Visible sample test'],['1','Hidden test']]){const option=new Option(label,value);visibility.append(option);}visibility.value=test.hidden?'1':'0';
        const weight=document.createElement('input');weight.type='number';weight.min='1';weight.max='100';weight.required=true;weight.value=test.weight??1;weight.dataset.testField='weight';weight.className='ml-3 w-20 rounded-lg border p-2 text-xs';weight.setAttribute('aria-label','Test point weight');
        const remove=document.createElement('button');remove.type='button';remove.textContent='Remove test';remove.className='ml-3 text-xs font-bold text-red-800 underline';remove.onclick=()=>{row.remove();reindex();};row.append(visibility,weight,remove);rows.append(row);reindex();
    }
    initial.forEach(add);document.getElementById('addTestCase').onclick=()=>add();
    form.addEventListener('formdata',event=>{document.getElementById('assignmentHints').value.split('\n').map(x=>x.trim()).filter(Boolean).forEach((hint,index)=>event.formData.append(`hints[${index}]`,hint));});
});
</script>
