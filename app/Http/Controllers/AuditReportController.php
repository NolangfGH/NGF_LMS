<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Student;
use App\Models\TeacherAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuditReportController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = TeacherAccount::find(session('teacher_id'));
        $teacherMode = $request->routeIs('teacher.assessment-activity', 'teacher.assessment-activity.export');
        abort_unless($teacher?->hasStaffAccess() && ($teacherMode ? ! $teacher->isPrincipal() : $teacher->isSchoolAdmin()), 403);
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'actor' => ['nullable', Rule::in(['teacher', 'student', 'parent', 'guest'])], 'action' => ['nullable', 'string', 'max:180'], 'failed' => ['nullable', 'boolean']]);
        $query = AuditEvent::query()->leftJoin('audit_events as login_entry', 'audit_events.login_event_id', '=', 'login_entry.id')
            ->select('audit_events.*', 'login_entry.logout_at as session_logout_at')
            ->when($teacherMode, fn ($q) => $q->where('audit_events.actor_type', 'student')->where('audit_events.action', 'like', 'assessment.%')
                ->where('audit_events.details->teacher_account_id', $teacher->id)
                ->whereIn('audit_events.actor_id', Student::whereHas('classroom', fn ($rooms) => $rooms->where('teacher_account_id', $teacher->id))->select('students.id')))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('audit_events.created_at', '>=', Carbon::parse($date, 'Asia/Singapore')->utc()))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('audit_events.created_at', '<', Carbon::parse($date, 'Asia/Singapore')->addDay()->utc()))
            ->when($filters['actor'] ?? null, fn ($q, $actor) => $q->where('audit_events.actor_type', $actor))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('audit_events.action', 'like', '%'.$action.'%'))
            ->when($filters['failed'] ?? false, fn ($q) => $q->where('audit_events.outcome', '!=', 'success'))->latest('audit_events.id');
        if ($request->routeIs('school.audit.export', 'teacher.assessment-activity.export')) {
            return response()->streamDownload(function () use ($query, $teacherMode): void {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Received time (UTC+08)', 'Login time (UTC+08)', 'Logout time (UTC+08)', 'Actor type', 'Account ID', 'Action', 'Method', 'HTTP status', 'Outcome', 'Record IDs', 'Activity context', ...($teacherMode ? [] : ['Optional approximate location shared at sign-in'])], ',', '"', '');
                foreach ($query->limit(10000)->get() as $event) {
                    fputcsv($file, [$event->created_at->timezone('Asia/Singapore')->format('Y-m-d H:i:s'), $event->recordedLoginAt()?->copy()->timezone('Asia/Singapore')->format('Y-m-d H:i:s'), $event->recordedLogoutAt()?->copy()->timezone('Asia/Singapore')->format('Y-m-d H:i:s'), $event->actor_type, $event->actor_id, $event->action, $event->method, $event->status, $event->outcome, json_encode($event->targets), json_encode($event->details), ...($teacherMode ? [] : [$event->approximateArea()])], ',', '"', '');
                }
                fclose($file);
            }, $teacherMode ? 'NGF-assessment-activity.csv' : 'NGF-audit-trail.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
        }
        $events = $query->paginate(40)->withQueryString();
        $studentNames = Student::withUnapproved()->withTrashed()->whereIn('id', $events->where('actor_type', 'student')->pluck('actor_id'))->pluck('nickname', 'id');

        $exportRoute = $teacherMode ? 'teacher.assessment-activity.export' : 'school.audit.export';
        $reportRoute = $teacherMode ? 'teacher.assessment-activity' : 'school.audit';

        return response()->view('audit-report', compact('teacher', 'filters', 'events', 'teacherMode', 'exportRoute', 'reportRoute', 'studentNames'))->header('Cache-Control', 'private, no-store');
    }
}
