<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuditTrail
{
    /** @return array{actor_type: string, actor_id: ?int} */
    public function actor(Request $request): array
    {
        $prefix = explode('.', $request->route()?->getName() ?? '')[0];
        $preferred = in_array($prefix, ['teacher', 'school', 'staff'], true) ? 'teacher' : $prefix;
        foreach (array_unique([...(in_array($preferred, ['teacher', 'student', 'parent'], true) ? [$preferred] : []), 'teacher', 'student', 'parent']) as $type) {
            if ($id = $request->session()->get($type.'_id')) {
                return ['actor_type' => $type, 'actor_id' => (int) $id];
            }
        }

        return ['actor_type' => 'guest', 'actor_id' => null];
    }

    public function snapshot(Request $request): array
    {
        $actor = $this->actor($request);
        $context = $request->session()->get('audit_contexts.'.$actor['actor_type'], []);

        return $actor + (($context['actor_type'] ?? null) === $actor['actor_type'] && ($context['actor_id'] ?? null) === $actor['actor_id'] ? $context : []);
    }

    private function sharedLocation(Request $request): array
    {
        $empty = ['location_latitude' => null, 'location_longitude' => null, 'location_accuracy_km' => null];
        if (! $request->boolean('audit_location_opt_in')) {
            return $empty;
        }
        $data = $request->only(['audit_location_opt_in', 'audit_latitude', 'audit_longitude', 'audit_accuracy_km']);
        $validator = Validator::make($data, ['audit_location_opt_in' => ['required', 'boolean'], 'audit_latitude' => ['required', 'numeric', 'between:-90,90'],
            'audit_longitude' => ['required', 'numeric', 'between:-180,180'], 'audit_accuracy_km' => ['required', 'integer', 'between:1,40000']]);
        if ($validator->fails()) {
            return $empty;
        }

        return ['location_latitude' => round((float) $data['audit_latitude'], 1), 'location_longitude' => round((float) $data['audit_longitude'], 1), 'location_accuracy_km' => max(10, (int) $data['audit_accuracy_km'])];
    }

    public function record(Request $request, array $before, string $action, int $status, string $outcome, array $targets = [], ?array $details = null, ?string $key = null): AuditEvent
    {
        return DB::transaction(function () use ($request, $before, $action, $status, $outcome, $targets, $details, $key): AuditEvent {
            $after = $this->actor($request);
            $login = $outcome === 'success' && $after['actor_type'] !== 'guest'
                && (str_ends_with($action, '.login.store') || $before['actor_type'] === 'guest');
            $logout = $outcome === 'success' && str_ends_with($action, '.logout') && $before['actor_type'] !== 'guest';
            $context = $login ? ($after + $this->sharedLocation($request) + ['login_at' => now()->toDateTimeString()])
                : ($before['actor_type'] === 'guest' ? $this->snapshot($request) : $before);
            $attributes = [
                'actor_type' => $context['actor_type'], 'actor_id' => $context['actor_id'], 'action' => $action, 'method' => $request->method(),
                'status' => $status, 'outcome' => $outcome, 'targets' => $targets, 'details' => $details,
                'login_event_id' => $context['login_event_id'] ?? null, 'login_at' => $context['login_at'] ?? null, 'logout_at' => $logout ? now() : null,
                'location_latitude' => $context['location_latitude'] ?? null, 'location_longitude' => $context['location_longitude'] ?? null, 'location_accuracy_km' => $context['location_accuracy_km'] ?? null,
            ];
            $event = $key ? AuditEvent::firstOrCreate(['event_key' => $key], $attributes) : AuditEvent::create($attributes);
            if ($login) {
                $request->session()->put('audit_contexts.'.$context['actor_type'], $context + ['login_event_id' => $event->id]);
            }
            if ($logout && isset($context['login_event_id'])) {
                AuditEvent::whereKey($context['login_event_id'])->where('actor_type', $context['actor_type'])->where('actor_id', $context['actor_id'])->whereNull('logout_at')->update(['logout_at' => $event->logout_at]);
            }

            return $event;
        });
    }
}
