<?php

namespace App\Providers;

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
        \App\Models\Student::observe(\App\Observers\XpEventObserver::class);
        RateLimiter::for('compiler-run', fn (Request $request) => Limit::perMinute(6)->by('compiler:'.$request->session()->get('student_id', $request->ip())));
        foreach ([\App\Models\BlogPost::class,\App\Models\BlogComment::class,\App\Models\BlogResponse::class,\App\Models\LabSubmission::class,\App\Models\LessonProgress::class,\App\Models\Student::class] as $model) {
            $model::observe(\App\Observers\LearningActivityObserver::class);
        }
        RateLimiter::for('home-live', fn (Request $request) => Limit::perMinute(12)->by(hash('sha256', $request->session()->getId().'|'.$request->ip())));
        RateLimiter::for('match-read', fn (Request $request) => Limit::perMinute(240)->by(hash('sha256', $request->session()->getId().'|'.$request->ip())));
        RateLimiter::for('match-write', fn (Request $request) => Limit::perMinute(120)->by(hash('sha256', $request->session()->getId().'|'.$request->ip())));
        $blogKey = function (Request $request) {
            $role = $request->routeIs('teacher.blog*') ? 'teacher' : 'student';
            $id = $request->session()->get($role.'_id');
            return hash('sha256', $role.':'.($id ?: $request->session()->getId().'|'.$request->ip()));
        };
        RateLimiter::for('blog-write', fn (Request $request) => Limit::perMinute(120)->by($blogKey($request)));
        RateLimiter::for('blog-draft', fn (Request $request) => Limit::perMinute(240)->by($blogKey($request)));
    }
}
