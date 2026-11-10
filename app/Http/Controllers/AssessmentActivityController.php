<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Student;
use App\Services\AssessmentActivity;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssessmentActivityController extends Controller
{
    private function student(): Student
    {
        return Student::with('classroom')->find(session('student_id')) ?? abort(401);
    }

    public function status(Request $request, AssessmentActivity $activities): JsonResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::in(AssessmentActivity::KINDS)], 'id' => ['required', 'integer', 'min:1']]);

        return response()->json($activities->state($this->student(), $data['kind'], (int) $data['id']))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AssessmentActivity $activities, AuditTrail $trail): JsonResponse
    {
        $student = $this->student();
        $data = $request->validate(['kind' => ['required', Rule::in(AssessmentActivity::KINDS)], 'id' => ['required', 'integer', 'min:1'],
            'signal' => ['required', Rule::in(AssessmentActivity::SIGNALS)], 'request_key' => ['required', 'uuid'],
            'observed_at' => ['required', 'string', 'max:40', 'date'], 'away_seconds' => ['required', 'integer', 'between:0,86400']]);
        $state = $activities->state($student, $data['kind'], (int) $data['id']);
        $key = hash_hmac('sha256', $student->id.':'.$data['kind'].':'.$data['id'].':'.$data['request_key'], config('app.key'));
        $existing = AuditEvent::where('event_key', $key)->first();
        if ($existing) {
            abort_unless(($existing->details['signal'] ?? null) === $data['signal'], 409, 'This activity key was already used.');
            $request->attributes->set('audit_event_recorded', true);

            return response()->json(['saved' => true]);
        }
        abort_unless($state['active'], 409, 'This assessment is no longer active.');
        $trail->record($request, $trail->snapshot($request), 'assessment.'.$data['signal'], 200, 'success', [$data['kind'] => (int) $data['id']],
            ['activity_type' => $data['kind'], 'activity_id' => (int) $data['id'], 'activity_title' => $state['title'], 'signal' => $data['signal'],
                'teacher_account_id' => $student->classroom->teacher_account_id, 'classroom_id' => $student->classroom_id,
                'device_observed_at' => $data['observed_at'], 'reported_away_seconds' => (int) $data['away_seconds'], 'source' => 'browser report; requires context'], $key);
        $request->attributes->set('audit_event_recorded', true);

        return response()->json(['saved' => true]);
    }
}
