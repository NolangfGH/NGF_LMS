<?php

use App\Models\TeacherAccount;
use App\Services\BlogLearningService;
use App\Services\Judge0Client;
use App\Services\StaffAccessService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('compiler:check', function (Judge0Client $client) {
    try {
        $this->info('Compiler connected. C++ language ID '.$client->health()['language_id'].' is available.');
    } catch (Throwable $error) {
        $this->error('Compiler unavailable. Configure JUDGE0_URL, JUDGE0_AUTH_TOKEN and JUDGE0_CPP_LANGUAGE_ID, then start the separate service.');

        return 1;
    }
})->purpose('Check the private school compiler connection without running student code');

Artisan::command('school:promote-admin {email}', function () {
    $teacher = TeacherAccount::where('email', $this->argument('email'))->first();
    if (! $teacher) {
        $this->error('No registered teacher has this email.');

        return 1;
    }
    app(StaffAccessService::class)->bootstrapAdmin($teacher);
    $this->info('School administrator role assigned. Sign in again to use School Administration.');
})->purpose('Bootstrap an administrator from an existing trusted teacher account using server access');

Artisan::command('blog:finalize-responses', function (BlogLearningService $service) {
    $this->info($service->finalizeExpired().' expired blog responses finalized.');
})->purpose('Send saved blog responses when their timers expire');
Schedule::command('blog:finalize-responses')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
