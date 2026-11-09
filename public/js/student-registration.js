(() => {
    const root = document.querySelector('[data-student-registration]');
    if (!root) return;
    let enrollmentOptions = JSON.parse(document.getElementById('enrollmentOptions').textContent);
    const gradeSelect = document.getElementById('gradeLevel');
    const sectionSelect = document.getElementById('section');
    const classCode = document.getElementById('classCode');
    const enrollmentForm = document.getElementById('enrollmentForm');
    const accountForm = document.getElementById('accountForm');
    const feedback = document.getElementById('feedback');
    const sectionHelp = document.getElementById('sectionHelp');
    const refreshButton = document.getElementById('refreshClasses');
    const schoolOrigin = document.getElementById('schoolOrigin');
    const schoolName = document.getElementById('schoolName');
    const ictTeacherName = document.getElementById('ictTeacherName');
    const ictTeacherContact = document.getElementById('ictTeacherContact');
    const externalSection = root.dataset.externalSection;
    const externalGrades = JSON.parse(root.dataset.externalGrades);
    const rshsSchool = schoolName.value;
    const otherSchoolDetails = {school: '', teacher: '', contact: ''};
    const isOtherSchool = () => schoolOrigin.value === 'other';
    const collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
    let verifiedDetails = null;
    let verifying = false;
    let submitting = false;

    function showError(message) {
        feedback.textContent = message;
        feedback.classList.remove('hidden');
        feedback.focus();
    }

    function setStep(step) {
        enrollmentForm.classList.toggle('hidden', step !== 1);
        accountForm.classList.toggle('hidden', step !== 2);
        document.getElementById('success').classList.toggle('hidden', step !== 3);
        for (let index = 1; index <= 3; index++) {
            const indicator = document.getElementById('indicator' + index);
            indicator.classList.toggle('text-primary', index <= step);
            indicator.classList.toggle('text-slate-400', index > step);
            if (index === step) indicator.setAttribute('aria-current', 'step');
            else indicator.removeAttribute('aria-current');
        }
        feedback.classList.add('hidden');
    }

    function renderSections(selectedSection = '') {
        verifiedDetails = null;
        if (isOtherSchool()) {
            sectionSelect.replaceChildren(new Option(externalSection, externalSection));
            sectionSelect.value = externalSection;
            sectionSelect.disabled = false;
            sectionHelp.textContent = 'Use the SFOS class code for your selected grade. Ask the platform teacher/admin for this code; registration still needs approval.';
            return;
        }
        const names = new Map();
        for (const room of enrollmentOptions.filter(room => room.grade === gradeSelect.value && room.section !== externalSection)) {
            const name = room.section.trim();
            if (name && !names.has(name.toUpperCase())) names.set(name.toUpperCase(), name);
        }
        const sections = [...names.values()].sort(collator.compare);
        sectionSelect.replaceChildren(new Option(gradeSelect.value ? 'Choose section' : 'Choose grade level first', ''));
        sections.forEach(section => sectionSelect.add(new Option(section, section)));
        sectionSelect.value = sections.includes(selectedSection) ? selectedSection : '';
        sectionSelect.disabled = sections.length === 0;
        sectionHelp.textContent = !gradeSelect.value
            ? 'Choose your grade to see its sections. Refresh the class list if your teacher just added a class.'
            : sections.length
                ? 'Choose your ' + gradeSelect.value + ' section and use the class code provided by your teacher.'
                : 'No open classes are available for this grade. Ask your teacher to open enrollment.';
    }

    function renderGrades(selectedGrade, selectedSection = '') {
        const grades = isOtherSchool() ? externalGrades : [...new Set(enrollmentOptions.filter(room => room.section !== externalSection).map(room => room.grade))].sort(collator.compare);
        gradeSelect.replaceChildren(new Option('Choose grade level', ''));
        grades.forEach(grade => gradeSelect.add(new Option(grade, grade)));
        gradeSelect.value = grades.includes(selectedGrade) ? selectedGrade : '';
        renderSections(selectedSection);
    }

    gradeSelect.onchange = () => renderSections();
    sectionSelect.onchange = () => { verifiedDetails = null; };
    classCode.oninput = () => { verifiedDetails = null; };
    schoolOrigin.onchange = () => {
        const other = isOtherSchool();
        if (!other) {
            otherSchoolDetails.school = schoolName.value;
            otherSchoolDetails.teacher = ictTeacherName.value;
            otherSchoolDetails.contact = ictTeacherContact.value;
        }
        schoolName.value = other ? otherSchoolDetails.school : rshsSchool;
        ictTeacherName.value = other ? otherSchoolDetails.teacher : '';
        ictTeacherContact.value = other ? otherSchoolDetails.contact : '';
        for (const field of [schoolName, ictTeacherName, ictTeacherContact]) {
            field.disabled = !other;
            field.required = other;
        }
        ictTeacherName.placeholder = other ? 'Your school ICT teacher’s full name' : 'From your verified class';
        classCode.value = '';
        renderGrades(gradeSelect.value);
    };
    if (isOtherSchool()) schoolOrigin.onchange();
    else renderGrades(gradeSelect.value);

    refreshButton.onclick = async () => {
        if (refreshButton.disabled || verifying) return;
        refreshButton.disabled = true;
        refreshButton.textContent = 'Refreshing…';
        try {
            const response = await fetch(root.dataset.optionsUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unable to refresh the class list. Please try again.');
            const data = await response.json();
            enrollmentOptions = data.enrollment_options;
            renderGrades(gradeSelect.value, sectionSelect.value);
            feedback.classList.add('hidden');
        } catch (error) {
            showError(error.message);
        } finally {
            refreshButton.disabled = false;
            refreshButton.textContent = 'Refresh class list';
        }
    };

    async function postForm(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            body: data,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save. Please try again.');
        return result;
    }

    enrollmentForm.onsubmit = async event => {
        event.preventDefault();
        if (verifying) return;
        if (!sectionSelect.value) return showError('Select a grade and section that your teacher has set up.');
        verifying = true;
        verifiedDetails = null;
        const button = enrollmentForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const data = new FormData(enrollmentForm);
            const result = await postForm(root.dataset.verifyUrl, data);
            if (JSON.stringify([...data]) !== JSON.stringify([...new FormData(enrollmentForm)])) {
                throw new Error('Your class details changed. Verify your selected grade, section and code again.');
            }
            verifiedDetails = {...Object.fromEntries(data), classroom_id: result.classroom.id, grade_level: result.classroom.grade, section: result.classroom.section};
            document.getElementById('verifiedClass').textContent = result.classroom.grade + ' · ' + result.classroom.section;
            setStep(2);
        } catch (error) {
            showError(error.message);
        } finally {
            verifying = false;
            button.disabled = false;
        }
    };

    document.getElementById('backButton').onclick = () => { verifiedDetails = null; setStep(1); };
    accountForm.onsubmit = async event => {
        event.preventDefault();
        if (submitting) return;
        if (!verifiedDetails) { setStep(1); return showError('Verify your grade, section and class code first.'); }
        submitting = true;
        const button = accountForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const data = new FormData(accountForm);
            Object.entries(verifiedDetails).forEach(([key, value]) => data.set(key, value));
            const result = await postForm(root.dataset.registerUrl, data);
            setStep(3);
            window.location.assign(result.redirect);
        } catch (error) {
            showError(error.message);
        } finally {
            submitting = false;
            button.disabled = false;
        }
    };
})();
