<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AiLabReviewController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BlogActivityController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChallengeNoticeController;
use App\Http\Controllers\ClassReportController;
use App\Http\Controllers\CodeExecutionController;
use App\Http\Controllers\CodeLabController;
use App\Http\Controllers\CodingMatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradingSheetController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LearningNotificationController;
use App\Http\Controllers\LessonContentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\QuizBeeController;
use App\Http\Controllers\QuizSubmissionController;
use App\Http\Controllers\SchoolAdminController;
use App\Http\Controllers\SecondChanceController;
use App\Http\Controllers\StaffRegistrationStatusController;
use App\Http\Controllers\StudentRegistrationApprovalController;
use App\Http\Controllers\StudentRegistrationStatusController;
use App\Http\Controllers\StudentSettingsController;
use App\Http\Controllers\TeacherLessonController;
use App\Http\Controllers\TeacherSettingsController;
use App\Http\Controllers\TeacherStudentController;
use App\Http\Controllers\TeachingLibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/staff-registration-status', [StaffRegistrationStatusController::class, 'show'])->name('staff.registration.status');
Route::get('/staff-registration-status/check', [StaffRegistrationStatusController::class, 'check'])->middleware('throttle:match-read')->name('staff.registration.check');
Route::get('/school-overview', [SchoolAdminController::class, 'overview'])->name('school.overview');
Route::get('/class-attendance', [AttendanceController::class, 'index'])->name('teacher.attendance');
Route::get('/class-attendance/status', [AttendanceController::class, 'status'])->middleware('throttle:match-read')->name('teacher.attendance.status');
Route::get('/class-attendance/export', [AttendanceController::class, 'export'])->middleware('throttle:20,1')->name('teacher.attendance.export');
Route::post('/class-attendance', [AttendanceController::class, 'store'])->middleware('throttle:match-write')->name('teacher.attendance.store');
Route::post('/class-attendance/{session}', [AttendanceController::class, 'action'])->whereNumber('session')->middleware('throttle:match-write')->name('teacher.attendance.action');
Route::get('/my-attendance', [AttendanceController::class, 'studentIndex'])->name('student.attendance');
Route::post('/my-attendance/{session}', [AttendanceController::class, 'studentAction'])->whereNumber('session')->middleware('throttle:10,1')->name('student.attendance.action');

Route::get('/student-registration-status', [StudentRegistrationStatusController::class, 'show'])->name('student.registration.status');
Route::get('/student-registration-status/check', [StudentRegistrationStatusController::class, 'check'])->middleware('throttle:match-read')->name('student.registration.check');
Route::get('/teacher-registrations', [StudentRegistrationApprovalController::class, 'index'])->name('teacher.registrations');
Route::get('/teacher-registrations/status', [StudentRegistrationApprovalController::class, 'status'])->middleware('throttle:match-read')->name('teacher.registrations.status');
Route::post('/teacher-registrations/{student}/review', [StudentRegistrationApprovalController::class, 'review'])->whereNumber('student')->middleware('throttle:30,1')->name('teacher.registrations.review');

Route::get('/student-dashboard/status', [DashboardController::class, 'status'])->middleware('throttle:match-read')->name('student.dashboard.status');
Route::get('/teacher-dashboard/status', [DashboardController::class, 'status'])->middleware('throttle:match-read')->name('teacher.dashboard.status');

Route::get('/school-admin', [SchoolAdminController::class, 'index'])->name('school.admin');
Route::post('/school-admin/accounts', [SchoolAdminController::class, 'store'])->name('school.accounts.store');
Route::put('/school-admin/accounts/{account}/role', [SchoolAdminController::class, 'role'])->name('school.roles');
Route::post('/school-admin/compiler', [SchoolAdminController::class, 'compiler'])->middleware('throttle:10,1')->name('school.compiler');
Route::post('/teacher-classes/{classroom}/archive', [SchoolAdminController::class, 'archive'])->name('teacher.classes.archive');

Route::get('/teacher-content', [LessonContentController::class, 'index'])->name('teacher.content');
Route::get('/teaching-library', [TeachingLibraryController::class, 'index'])->name('teacher.library');
Route::get('/teaching-library/{key}/{kind}', [TeachingLibraryController::class, 'show'])->where('key', '[a-z0-9-]+')->where('kind', 'quiz|assignment|challenge|article')->name('teacher.library.show');
Route::post('/teaching-library/{key}/{kind}', [TeachingLibraryController::class, 'store'])->where('key', '[a-z0-9-]+')->where('kind', 'quiz|assignment|challenge|article')->middleware('throttle:20,1')->name('teacher.library.store');
Route::get('/teacher-content/{key}/edit', [LessonContentController::class, 'edit'])->where('key', '[a-z0-9-]+')->name('teacher.content.edit');
Route::post('/teacher-content/{key}', [LessonContentController::class, 'save'])->where('key', '[a-z0-9-]+')->name('teacher.content.save');
Route::get('/teacher-content/{content}/image', [LessonContentController::class, 'image'])->whereNumber('content')->name('teacher.content.image');
Route::get('/lesson-resource/{content}', [LessonContentController::class, 'image'])->whereNumber('content')->name('student.lesson.image');

Route::post('/code-lab/{workspace}/run', [CodeExecutionController::class, 'store'])->middleware('throttle:compiler-run')->name('student.code-runs.store');
Route::get('/code-runs/{run}', [CodeExecutionController::class, 'show'])->middleware('throttle:match-read')->name('student.code-runs.show');

Route::prefix('grading-sheet')->name('teacher.grading')->controller(GradingSheetController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/status', 'status')->middleware('throttle:match-read')->name('.status');
    Route::get('/export', 'export')->middleware('throttle:20,1')->name('.export');
    Route::post('/', 'store')->middleware('throttle:match-write')->name('.store');
    Route::put('/{sheet}/weights', 'weights')->whereNumber('sheet')->name('.weights');
    Route::post('/{sheet}/assessments', 'assessment')->whereNumber('sheet')->name('.assessments.store');
    Route::put('/{sheet}/assessments/{assessment}', 'assessment')->whereNumber(['sheet', 'assessment'])->name('.assessments.update');
    Route::delete('/{sheet}/assessments/{assessment}', 'destroyAssessment')->whereNumber(['sheet', 'assessment'])->name('.assessments.destroy');
    Route::put('/{sheet}/students/{student}', 'scores')->whereNumber(['sheet', 'student'])->name('.scores');
    Route::post('/{sheet}/import', 'import')->whereNumber('sheet')->middleware('throttle:20,1')->name('.import');
});

foreach (['student', 'teacher'] as $notificationRole) {
    Route::get('/'.$notificationRole.'-notifications', [LearningNotificationController::class, 'index'])->middleware('throttle:match-read')->name($notificationRole.'.notifications');
    Route::post('/'.$notificationRole.'-notifications/read', [LearningNotificationController::class, 'read'])->middleware('throttle:match-write')->name($notificationRole.'.notifications.read');
}

Route::get('/teacher-blog/voice/{kind}/{message}', [BlogActivityController::class, 'voice'])->whereNumber('message')->name('teacher.blog.voice');
Route::get('/blog/voice/{kind}/{message}', [BlogActivityController::class, 'voice'])->whereNumber('message')->name('student.blog.voice');
Route::post('/teacher-blog/{post}/comments/{comment}/grade', [BlogActivityController::class, 'grade'])->whereNumber(['post', 'comment'])->middleware('throttle:blog-write')->name('teacher.blog.grade-comment');
Route::post('/teacher-blog/{post}/comments/{comment}/responses/{response}/grade', [BlogActivityController::class, 'grade'])->whereNumber(['post', 'comment', 'response'])->middleware('throttle:blog-write')->name('teacher.blog.grade-response');
Route::post('/blog/{post}/timer/start', [BlogActivityController::class, 'start'])->whereNumber('post')->middleware('throttle:blog-write')->name('student.blog.timer.start');
Route::post('/blog/{post}/timer/{attempt}/draft', [BlogActivityController::class, 'save'])->whereNumber(['post', 'attempt'])->middleware('throttle:blog-draft')->name('student.blog.timer.draft');
Route::post('/blog/{post}/timer/{attempt}/submit', [BlogActivityController::class, 'save'])->whereNumber(['post', 'attempt'])->middleware('throttle:blog-write')->name('student.blog.timer.submit');
Route::get('/blog/{post}/timer/{attempt}/media/{kind}', [BlogActivityController::class, 'draftMedia'])->whereNumber(['post', 'attempt'])->name('student.blog.timer.media');

Route::prefix('teacher-blog')->name('teacher.blog')->controller(BlogController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/create', 'create')->name('.create');
    Route::post('/', 'store')->middleware('throttle:blog-write')->name('.store');
    Route::get('/comments/{comment}/image/{kind}', 'commentImage')->whereNumber('comment')->name('.comment-image');
    Route::get('/responses/{response}/image', 'responseImage')->whereNumber('response')->name('.response-image');
    Route::get('/{post}', 'show')->whereNumber('post')->name('.show');
    Route::get('/{post}/edit', 'edit')->whereNumber('post')->name('.edit');
    Route::get('/{post}/image', 'postImage')->whereNumber('post')->name('.image');
    Route::put('/{post}', 'update')->whereNumber('post')->middleware('throttle:blog-write')->name('.update');
    Route::delete('/{post}', 'destroy')->whereNumber('post')->name('.destroy');
    Route::post('/{post}/comments/{comment}/reply', 'respond')->whereNumber(['post', 'comment'])->middleware('throttle:blog-write')->name('.reply');
});
Route::prefix('blog')->name('student.blog')->controller(BlogController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/comments/{comment}/image/{kind}', 'commentImage')->whereNumber('comment')->name('.comment-image');
    Route::get('/responses/{response}/image', 'responseImage')->whereNumber('response')->name('.response-image');
    Route::get('/{post}', 'show')->whereNumber('post')->name('.show');
    Route::get('/{post}/image', 'postImage')->whereNumber('post')->name('.image');
    Route::post('/{post}/comments', 'comment')->whereNumber('post')->middleware('throttle:blog-write')->name('.comment');
    Route::post('/{post}/comments/{comment}/reply', 'respond')->whereNumber(['post', 'comment'])->middleware('throttle:blog-write')->name('.reply');
});

Route::prefix('teacher-students')->group(function () {
    Route::get('/', [TeacherStudentController::class, 'index'])->name('teacher.students');
    Route::get('/status', [TeacherStudentController::class, 'status'])->middleware('throttle:match-read')->name('teacher.students.status');
    Route::get('/export', [TeacherStudentController::class, 'export'])->middleware('throttle:20,1')->name('teacher.students.export');
    Route::get('/create', [TeacherStudentController::class, 'create'])->name('teacher.students.create');
    Route::post('/', [TeacherStudentController::class, 'store'])->middleware('throttle:match-write')->name('teacher.students.store');
    Route::get('/{student}', [TeacherStudentController::class, 'show'])->whereNumber('student')->name('teacher.students.show');
    Route::get('/{student}/edit', [TeacherStudentController::class, 'edit'])->whereNumber('student')->name('teacher.students.edit');
    Route::put('/{student}', [TeacherStudentController::class, 'update'])->whereNumber('student')->middleware('throttle:match-write')->name('teacher.students.update');
    Route::delete('/{student}', [TeacherStudentController::class, 'destroy'])->whereNumber('student')->middleware('throttle:match-write')->name('teacher.students.destroy');
    Route::post('/{student}/restore', [TeacherStudentController::class, 'restore'])->whereNumber('student')->middleware('throttle:match-write')->name('teacher.students.restore');
});

Route::prefix('teacher-challenges')->name('teacher.matches.')->group(function () {
    Route::post('/', [CodingMatchController::class, 'store'])->middleware('throttle:match-write')->name('store');
    Route::get('/records', [CodingMatchController::class, 'records'])->middleware('throttle:match-read')->name('records');
    Route::get('/{match}', [CodingMatchController::class, 'teacherShow'])->whereNumber('match')->name('show');
    Route::get('/{match}/status', [CodingMatchController::class, 'status'])->middleware('throttle:match-read')->name('status');
    Route::post('/{match}/submissions/{submission}/review', [CodingMatchController::class, 'review'])->middleware('throttle:match-write')->name('review');
    Route::get('/{match}/submissions/{submission}/download', [CodingMatchController::class, 'download'])->name('download');
    Route::post('/{match}/award', [CodingMatchController::class, 'award'])->middleware('throttle:match-write')->name('award');
    Route::post('/{match}/cancel', [CodingMatchController::class, 'cancel'])->middleware('throttle:match-write')->name('cancel');
});
foreach (['teacher', 'student'] as $beeRole) {
    Route::prefix($beeRole.'-quiz-bee')->name($beeRole.'.quiz-bee.')->controller(QuizBeeController::class)->group(function () use ($beeRole) {
        Route::get('/', 'index')->name('index');
        if ($beeRole === 'teacher') {
            Route::get('/create', 'edit')->name('create');
            Route::post('/', 'save')->middleware('throttle:match-write')->name('store');
            Route::get('/{bee}/edit', 'edit')->whereNumber('bee')->name('edit');
            Route::put('/{bee}', 'save')->whereNumber('bee')->middleware('throttle:match-write')->name('update');
        }
        Route::get('/{bee}', 'show')->whereNumber('bee')->name('show');
        Route::get('/{bee}/status', 'status')->whereNumber('bee')->middleware('throttle:match-read')->name('status');
        Route::post('/{bee}/action', 'action')->whereNumber('bee')->middleware('throttle:match-write')->name('action');
    });
}
Route::get('/teacher-section-challenges', [HomeController::class, 'teacherSectionChallenges'])->name('teacher.section-challenges');
Route::prefix('coding-challenges')->name('student.matches.')->group(function () {
    Route::get('/records', [CodingMatchController::class, 'records'])->middleware('throttle:match-read')->name('records');
    Route::get('/{match}', [CodingMatchController::class, 'studentShow'])->whereNumber('match')->name('show');
    Route::get('/{match}/status', [CodingMatchController::class, 'status'])->middleware('throttle:match-read')->name('status');
    Route::post('/{match}/respond', [CodingMatchController::class, 'respond'])->middleware('throttle:match-write')->name('respond');
    Route::put('/{match}/draft', [CodingMatchController::class, 'save'])->middleware('throttle:match-write')->name('save');
    Route::post('/{match}/submit', [CodingMatchController::class, 'submit'])->middleware('throttle:match-write')->name('submit');
});
foreach (['teacher', 'student'] as $noticeRole) {
    Route::get('/'.$noticeRole.'-challenge-notices', [ChallengeNoticeController::class, 'index'])->middleware('throttle:match-read')->name($noticeRole.'.match-notices');
    Route::post('/'.$noticeRole.'-challenge-notices/read', [ChallengeNoticeController::class, 'read'])->middleware('throttle:match-write')->name($noticeRole.'.match-notices.read');
}

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home/status', [HomeController::class, 'homeStatus'])->middleware('throttle:home-live')->name('home.status');
Route::post('/excuse-requests/{type}/{activity}', [SecondChanceController::class, 'store'])->whereNumber('activity')->middleware('throttle:10,1')->name('student.excuses.store');
Route::get('/excuse-requests/{excuse}/proof', [SecondChanceController::class, 'proof'])->name('excuses.proof');
Route::get('/second-chance-requests', [SecondChanceController::class, 'index'])->name('teacher.excuses');
Route::post('/second-chance-requests/{excuse}', [SecondChanceController::class, 'review'])->middleware('throttle:30,1')->name('teacher.excuses.review');
Route::view('/student-login', 'student-login')->name('student.login');
Route::post('/student-login', [HomeController::class, 'loginStudent'])->name('student.login.store');
Route::get('/student-register', [HomeController::class, 'studentRegistration'])->name('student.register');
Route::get('/student-register/options', [HomeController::class, 'studentRegistrationOptions'])->middleware('throttle:30,1')->name('student.register.options');
Route::post('/student-register/verify-class', [HomeController::class, 'verifyStudentClassCode'])->name('student.register.verify');
Route::post('/student-register', [HomeController::class, 'registerStudent'])->name('student.register.store');
Route::get('/student-dashboard', [HomeController::class, 'studentDashboard'])->name('student.dashboard');
Route::get('/student-settings', [StudentSettingsController::class, 'index'])->name('student.settings');
Route::put('/student-settings/profile', [StudentSettingsController::class, 'profile'])->name('student.settings.profile');
Route::put('/student-settings/avatar', [StudentSettingsController::class, 'avatar'])->name('student.settings.avatar');
Route::put('/student-settings/notifications', [StudentSettingsController::class, 'notifications'])->name('student.settings.notifications');
Route::put('/student-settings/display', [StudentSettingsController::class, 'display'])->name('student.settings.display');
Route::put('/student-settings/privacy', [StudentSettingsController::class, 'privacy'])->name('student.settings.privacy');
Route::post('/student-settings/password', [StudentSettingsController::class, 'password'])->middleware('throttle:10,1')->name('student.settings.password');
Route::post('/student-logout', [StudentSettingsController::class, 'logout'])->name('student.logout');
Route::get('/learn', [HomeController::class, 'studentLearn'])->name('student.learn');
Route::get('/student-assignments', [HomeController::class, 'studentAssignments'])->name('student.assignments');
Route::post('/student-assignments/{assignment}/open', [CodeLabController::class, 'openAssignment'])->middleware('throttle:60,1')->name('student.assignments.open');
Route::get('/lesson', [LessonController::class, 'resume'])->name('student.lesson');
Route::get('/lesson/{lesson}', [LessonController::class, 'show'])->name('student.lesson.show');
Route::post('/lesson/{lesson}/bookmark', [LessonController::class, 'bookmark'])->middleware('throttle:60,1')->name('student.lesson.bookmark');
Route::post('/lesson/{lesson}/checkpoint', [LessonController::class, 'checkpoint'])->middleware('throttle:20,1')->name('student.lesson.checkpoint');
Route::post('/lesson/{lesson}/support', [LessonController::class, 'support'])->middleware('throttle:20,1')->name('student.lesson.support');
Route::get('/teacher-lessons', [TeacherLessonController::class, 'index'])->name('teacher.lessons');
Route::post('/teacher-lessons', [TeacherLessonController::class, 'store'])->name('teacher.lessons.store');
Route::put('/teacher-lessons/{plan}', [TeacherLessonController::class, 'update'])->name('teacher.lessons.update');
Route::delete('/teacher-lessons/{plan}', [TeacherLessonController::class, 'destroy'])->name('teacher.lessons.destroy');
Route::get('/teacher-lesson-progress', [TeacherLessonController::class, 'progress'])->name('teacher.lessons.progress');
Route::post('/teacher-lesson-progress/{progress}/feedback', [TeacherLessonController::class, 'feedback'])->name('teacher.lessons.feedback');
Route::get('/code-lab', [HomeController::class, 'studentCodeLab'])->name('student.code-lab');
Route::put('/code-lab/{workspace}', [CodeLabController::class, 'save'])->middleware('throttle:120,1')->name('student.code-lab.save');
Route::get('/code-lab/{workspace}/status', [CodeLabController::class, 'status'])->middleware('throttle:120,1')->name('student.code-lab.status');
Route::post('/code-lab/{workspace}/submit', [CodeLabController::class, 'submit'])->middleware('throttle:20,1')->name('student.code-lab.submit');
Route::get('/teacher-code-lab', [CodeLabController::class, 'reviews'])->name('teacher.code-lab');
Route::post('/teacher-code-lab/{submission}/review', [CodeLabController::class, 'review'])->name('teacher.code-lab.review');
Route::post('/teacher-code-lab/{submission}/ai-suggestion', [AiLabReviewController::class, 'suggest'])->middleware('throttle:20,1')->name('teacher.code-lab.ai');
Route::get('/quiz', [HomeController::class, 'studentQuiz'])->name('student.quiz');
Route::post('/quiz/{quiz}/submit', [QuizSubmissionController::class, 'store'])->middleware('throttle:20,1')->name('student.quiz.submit');
Route::post('/quiz/{quiz}/start', [QuizSubmissionController::class, 'start'])->middleware('throttle:20,1')->name('student.quiz.start');
Route::post('/quiz/{quiz}/draft', [QuizSubmissionController::class, 'draft'])->middleware('throttle:120,1')->name('student.quiz.draft');
Route::post('/quiz/{quiz}/question', [QuizSubmissionController::class, 'question'])->middleware('throttle:120,1')->name('student.quiz.question');
Route::get('/challenges', [HomeController::class, 'studentChallenges'])->name('student.challenges');
Route::get('/achievements', [HomeController::class, 'studentAchievements'])->name('student.achievements');
Route::post('/achievements/goal', [AchievementController::class, 'goal'])->name('student.achievements.goal');
Route::get('/teacher-achievements', [AchievementController::class, 'teacher'])->name('teacher.achievements');
Route::get('/teacher-achievements/export', [AchievementController::class, 'export'])->name('teacher.achievements.export');
Route::get('/leaderboard', [HomeController::class, 'studentLeaderboard'])->name('student.leaderboard');
Route::get('/progress', [HomeController::class, 'studentProgress'])->name('student.progress');
Route::get('/teacher-dashboard', [HomeController::class, 'teacherDashboard'])->name('teacher.dashboard');
Route::get('/teacher-settings', [TeacherSettingsController::class, 'index'])->name('teacher.settings');
Route::get('/teacher-classes', [TeacherSettingsController::class, 'classes'])->name('teacher.classes');
Route::post('/teacher-classes', [TeacherSettingsController::class, 'storeClasses'])->middleware('throttle:match-write')->name('teacher.classes.store');
Route::put('/teacher-classes/{classroom}', [TeacherSettingsController::class, 'updateClass'])->whereNumber('classroom')->name('teacher.classes.update');
Route::put('/teacher-settings/account', [TeacherSettingsController::class, 'account'])->name('teacher.settings.account');
Route::put('/teacher-settings/profile', [TeacherSettingsController::class, 'profile'])->name('teacher.settings.profile');
Route::put('/teacher-settings/preferences', [TeacherSettingsController::class, 'preferences'])->name('teacher.settings.preferences');
Route::post('/teacher-settings/password', [TeacherSettingsController::class, 'password'])->middleware('throttle:10,1')->name('teacher.settings.password');
Route::post('/teacher-settings/sessions/revoke', [TeacherSettingsController::class, 'revokeSessions'])->middleware('throttle:10,1')->name('teacher.settings.sessions');
Route::post('/teacher-settings/classes', [TeacherSettingsController::class, 'storeClass'])->name('teacher.settings.classes.store');
Route::put('/teacher-settings/classes/{classroom}', [TeacherSettingsController::class, 'updateClass'])->name('teacher.settings.classes.update');
Route::post('/teacher-settings/classes/{classroom}/join-code', [TeacherSettingsController::class, 'rotateCode'])->middleware('throttle:10,1')->name('teacher.settings.classes.code');
Route::post('/teacher-settings/avatar', [TeacherSettingsController::class, 'uploadAvatar'])->middleware('throttle:10,1')->name('teacher.settings.avatar.store');
Route::delete('/teacher-settings/avatar', [TeacherSettingsController::class, 'removeAvatar'])->name('teacher.settings.avatar.destroy');
Route::get('/teacher-settings/avatar/{teacher}', [TeacherSettingsController::class, 'avatar'])->name('teacher.settings.avatar');
Route::get('/reports', [ClassReportController::class, 'index'])->name('teacher.reports');
Route::get('/reports/export', [ClassReportController::class, 'export'])->name('teacher.reports.export');
Route::view('/teacher-login', 'teacher-login')->name('teacher.login');
Route::post('/teacher-login', [HomeController::class, 'loginTeacher'])->name('teacher.login.store');
Route::get('/teacher-logout', [HomeController::class, 'logoutTeacher'])->name('teacher.logout');
Route::view('/teacher-register', 'teacher-register')->name('teacher.register');
Route::post('/teacher-register', [HomeController::class, 'registerTeacher'])->name('teacher.register.store');
Route::get('/assignments', [HomeController::class, 'teacherAssignments'])->name('teacher.assignments');
Route::get('/teacher-challenges', [HomeController::class, 'teacherChallenges'])->name('teacher.challenges');
Route::post('/assignments', [HomeController::class, 'storeTeacherAssignment'])->name('teacher.assignments.store');
Route::put('/assignments/{assignment}', [HomeController::class, 'updateTeacherAssignment'])->name('teacher.assignments.update');
Route::delete('/assignments/{assignment}', [HomeController::class, 'deleteTeacherAssignment'])->name('teacher.assignments.destroy');
Route::get('/teacher-quizzes', [HomeController::class, 'teacherQuizzes'])->name('teacher.quizzes');
Route::get('/teacher-quiz-reviews', [QuizSubmissionController::class, 'index'])->name('teacher.quiz.reviews');
Route::post('/teacher-quizzes', [HomeController::class, 'storeTeacherQuiz'])->name('teacher.quizzes.store');
Route::put('/teacher-quizzes/{quiz}', [HomeController::class, 'updateTeacherQuiz'])->name('teacher.quizzes.update');
Route::delete('/teacher-quizzes/{quiz}', [HomeController::class, 'deleteTeacherQuiz'])->name('teacher.quizzes.destroy');
Route::post('/teacher-quiz-sections', [HomeController::class, 'storeQuizSection'])->name('teacher.quiz.sections.store');
Route::post('/generate', [HomeController::class, 'generate'])->name('generate');
