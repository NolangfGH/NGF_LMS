<script>
const teacherNav=document.querySelector('aside nav');
if (teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.library') }}"]')) {
    const libraryLink = document.createElement('a');
    libraryLink.href = '{{ route('teacher.library') }}';
    libraryLink.className = 'flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    libraryLink.innerHTML = '<span class="material-symbols-outlined">library_books</span>Teaching Library';
    const reportLink = teacherNav.querySelector('a[href="{{ route('teacher.reports') }}"]');
    if (reportLink) { reportLink.before(libraryLink); } else { teacherNav.append(libraryLink); }
}
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.recitations') }}"]')) { const link=document.createElement('a'); link.href='{{ route('teacher.recitations') }}'; link.className='portal-menu-link'; link.innerHTML='<span class="material-symbols-outlined" aria-hidden="true">record_voice_over</span>Recitation'; teacherNav.append(link); }
if(teacherNav) {
    let studentsLink=[...teacherNav.querySelectorAll('a')].find(link=>link.textContent.trim().endsWith('Students'));
    if(!studentsLink) {
        studentsLink=document.createElement('a');
        studentsLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
        studentsLink.innerHTML='<span class="material-symbols-outlined">group</span>Students';
        teacherNav.append(studentsLink);
    }
    studentsLink.href='{{ route('teacher.students') }}';
    if(!teacherNav.querySelector('a[href="{{ route('teacher.classes') }}"]')) {
        const classesLink=document.createElement('a');
        classesLink.href='{{ route('teacher.classes') }}';
        classesLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
        classesLink.innerHTML='<span class="material-symbols-outlined">school</span>My Classes';
        studentsLink.before(classesLink);
    }
    if(!teacherNav.querySelector('a[href="{{ route('teacher.attendance') }}"]')) {
        const attendanceLink=document.createElement('a');
        attendanceLink.href='{{ route('teacher.attendance') }}';
        attendanceLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
        attendanceLink.innerHTML='<span class="material-symbols-outlined">event_available</span>Class Attendance';
        studentsLink.after(attendanceLink);
    }
    let lessonsLink=[...teacherNav.querySelectorAll('a')].find(link=>link.textContent.trim().endsWith('Lessons'));
    if(!lessonsLink) {
        lessonsLink=document.createElement('a');
        lessonsLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
        lessonsLink.innerHTML='<span class="material-symbols-outlined">menu_book</span>Lessons';
        teacherNav.append(lessonsLink);
    }
    lessonsLink.href='{{ route('teacher.lessons') }}';
    let reportsLink=[...teacherNav.querySelectorAll('a')].find(link=>link.textContent.trim().endsWith('Reports'));
    if(!reportsLink) {
        reportsLink=document.createElement('a');
        reportsLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
        reportsLink.innerHTML='<span class="material-symbols-outlined">monitoring</span>Reports';
        teacherNav.append(reportsLink);
    }
    reportsLink.href='{{ route('teacher.reports') }}';
    let settingsLink=[...teacherNav.querySelectorAll('a')].find(link=>link.textContent.trim().endsWith('Settings'));
    if(!settingsLink) {
        settingsLink=document.createElement('a');
        settingsLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
        settingsLink.innerHTML='<span class="material-symbols-outlined">settings</span>Settings';
        teacherNav.append(settingsLink);
    }
    settingsLink.href='{{ route('teacher.settings') }}';
}
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.code-lab') }}"]')) {
    const labLink=document.createElement('a');labLink.href='{{ route('teacher.code-lab') }}';
    labLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    labLink.innerHTML='<span class="material-symbols-outlined">code</span>Code Lab Reviews';
    teacherNav.append(labLink);
}
</script>
<script>
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.grading') }}"]')) {
    const gradingLink=document.createElement('a');
    gradingLink.href='{{ route('teacher.grading') }}';
    gradingLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    gradingLink.innerHTML='<span class="material-symbols-outlined">fact_check</span>Grading Sheet';
    const reportAnchor=teacherNav.querySelector('a[href="{{ route('teacher.reports') }}"]');
    if(reportAnchor) reportAnchor.before(gradingLink); else teacherNav.append(gradingLink);
}
</script>
<script>
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.challenges') }}"]')){
    const challengeLink=document.createElement('a');challengeLink.href='{{ route('teacher.challenges') }}';
    challengeLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    challengeLink.innerHTML='<span class="material-symbols-outlined">sports_esports</span>Challenges';
    teacherNav.append(challengeLink);
}
</script>
<script>
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.achievements') }}"]')){
    const achievementLink=document.createElement('a');achievementLink.href='{{ route('teacher.achievements') }}';
    achievementLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    achievementLink.innerHTML='<span class="material-symbols-outlined">military_tech</span>Student Achievements';
    teacherNav.append(achievementLink);
}
</script>
<script>
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.excuses') }}"]')){
    const excuseLink=document.createElement('a');excuseLink.href='{{ route('teacher.excuses') }}';
    excuseLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    excuseLink.innerHTML='<span class="material-symbols-outlined">assignment_late</span>Excuse Requests';
    teacherNav.append(excuseLink);
}
if(teacherNav) {
    for(const href of ['{{ route('teacher.reports') }}','{{ route('teacher.settings') }}']) {
        const menuLink=teacherNav.querySelector(`a[href="${href}"]`);
        if(menuLink) teacherNav.append(menuLink);
    }
}
</script>
<script>
if(teacherNav && !teacherNav.querySelector('a[href="{{ route('teacher.blog') }}"]')) {
    const blogLink=document.createElement('a');
    blogLink.href='{{ route('teacher.blog') }}';
    blogLink.className='flex items-center gap-3 rounded-xl px-4 py-3 text-sm text-muted hover:bg-secondary-soft';
    blogLink.innerHTML='<span class="material-symbols-outlined">auto_stories</span>Blog';
    const reportsLink=teacherNav.querySelector('a[href="{{ route('teacher.reports') }}"]');
    if(reportsLink) reportsLink.before(blogLink); else teacherNav.append(blogLink);
}
</script>
