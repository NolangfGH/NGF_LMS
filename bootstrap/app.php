<?php

use App\Http\Middleware\CheckStudentSessionVersion;
use App\Http\Middleware\CheckTeacherSessionVersion;
use App\Http\Middleware\RecordAuditTrail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['sample_output', 'code', 'observed_output', 'stdin', 'test_cases.*.input', 'test_cases.*.output']);
        $middleware->web(append: [RecordAuditTrail::class, CheckTeacherSessionVersion::class, CheckStudentSessionVersion::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
