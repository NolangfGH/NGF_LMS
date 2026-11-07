<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
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
        abort_unless($teacher?->hasStaffAccess() && $teacher->isSchoolAdmin(), 403);
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'actor' => ['nullable', Rule::in(['teacher', 'student', 'parent', 'guest'])], 'action' => ['nullable', 'string', 'max:180'], 'failed' => ['nullable', 'boolean']]);
        $query = AuditEvent::query()->when($filters['from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', Carbon::parse($date, 'Asia/Singapore')->utc()))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('created_at', '<', Carbon::parse($date, 'Asia/Singapore')->addDay()->utc()))
            ->when($filters['actor'] ?? null, fn ($q, $actor) => $q->where('actor_type', $actor))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', '%'.$action.'%'))
            ->when($filters['failed'] ?? false, fn ($q) => $q->where('outcome', '!=', 'success'))->latest('id');
        if ($request->routeIs('school.audit.export')) {
            return response()->streamDownload(function () use ($query): void {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Time (UTC+08)', 'Actor type', 'Account ID', 'Action', 'Method', 'HTTP status', 'Outcome', 'Record IDs'], ',', '"', '');
                foreach ($query->limit(10000)->get() as $event) {
                    fputcsv($file, [$event->created_at->timezone('Asia/Singapore')->format('Y-m-d H:i:s'), $event->actor_type, $event->actor_id, $event->action, $event->method, $event->status, $event->outcome, json_encode($event->targets)], ',', '"', '');
                }
                fclose($file);
            }, 'NGF-audit-trail.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
        }
        $events = $query->paginate(40)->withQueryString();

        return response()->view('audit-report', compact('teacher', 'filters', 'events'))->header('Cache-Control', 'private, no-store');
    }
}
