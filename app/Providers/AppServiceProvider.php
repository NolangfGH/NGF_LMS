<?php

namespace App\Providers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogResponse;
use App\Models\LabSubmission;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Observers\LearningActivityObserver;
use App\Observers\XpEventObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Student::observe(XpEventObserver::class);
        RateLimiter::for('compiler-run', fn (Request $request) => Limit::perMinute(6)->by('compiler:'.$request->session()->get('student_id', $request->ip())));
        RateLimiter::for('recitation-submit', fn (Request $request) => Limit::perMinute(12)->by('recitation:'.$request->session()->get('student_id', $request->ip())));
        RateLimiter::for('assessment-read', fn (Request $request): Limit => Limit::perMinute(60)->by('assessment-read:'.($request->session()->get('student_id') ?? $request->ip())));
        RateLimiter::for('assessment-write', fn (Request $request): Limit => Limit::perMinute(90)->by('assessment-write:'.($request->session()->get('student_id') ?? $request->ip())));
        RateLimiter::for('attendance-photo', fn (Request $request) => Limit::perMinute(6)->by('attendance-photo:'.$request->session()->get('student_id', $request->ip())));
        foreach ([BlogPost::class, BlogComment::class, BlogResponse::class, LabSubmission::class, LessonProgress::class, Student::class] as $model) {
            $model::observe(LearningActivityObserver::class);
        }
        RateLimiter::for('home-live', fn (Request $request) => Limit::perMinute(12)->by(hash('sha256', $request->session()->getId().'|'.$request->ip())));
        RateLimiter::for('match-read', fn (Request $request) => Limit::perMinute(240)->by(hash('sha256', $request->session()->getId().'|'.$request->ip())));
        RateLimiter::for('match-write', fn (Request $request) => Limit::perMinute(120)->by(hash('sha256', $request->session()->getId().'|'.$request->ip())));
        RateLimiter::for('staff-reset-request', fn (Request $request): Limit => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('staff-reset-submit', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('post-test-code', fn (Request $request): Limit => Limit::perMinute(8)->by($request->session()->get('student_id') ?? $request->ip()));
        RateLimiter::for('system-feedback', function (Request $request): Limit {
            $session = $request->session();
            $actor = $session->has('teacher_id') ? 'staff:'.$session->get('teacher_id')
                : ($session->has('student_id') ? 'student:'.$session->get('student_id') : 'guest:'.$request->ip());

            return Limit::perMinute(6)->by($actor);
        });
        $blogKey = function (Request $request) {
            $role = $request->routeIs('teacher.blog*') ? 'teacher' : 'student';
            $id = $request->session()->get($role.'_id');

            return hash('sha256', $role.':'.($id ?: $request->session()->getId().'|'.$request->ip()));
        };
        RateLimiter::for('blog-write', fn (Request $request) => Limit::perMinute(120)->by($blogKey($request)));
        RateLimiter::for('blog-draft', fn (Request $request) => Limit::perMinute(240)->by($blogKey($request)));
    }
}
