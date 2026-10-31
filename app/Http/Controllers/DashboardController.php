<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\TeacherAccount;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function status(DashboardService $service)
    {
        $role = request()->routeIs('teacher.dashboard.status') ? 'teacher' : 'student';
        $actor = $role === 'teacher' ? TeacherAccount::find(session('teacher_id')) : Student::with('classroom')->find(session('student_id'));
        abort_unless($actor, 403);
        $overview = $service->{$role}($actor);

        return response()->json(['html' => view('dashboard.'.$role.'-overview', [$role => $actor, 'overview' => $overview])->render()])->header('Cache-Control', 'private, no-store');
    }
}
