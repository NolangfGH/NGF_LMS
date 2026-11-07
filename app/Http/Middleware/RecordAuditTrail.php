<?php

namespace App\Http\Middleware;

use App\Models\AuditEvent;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordAuditTrail
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $action = $request->route()?->getName();
        $tracked = $action && (! $request->isMethodSafe() || preg_match('/\.(logout|export|download)$/', $action));
        if (! $tracked) {
            return $next($request);
        }
        $actor = $this->actor($request);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } catch (Throwable $error) {
            $status = $error instanceof ValidationException ? 422 : (method_exists($error, 'getStatusCode') ? $error->getStatusCode() : 500);
            throw $error;
        } finally {
            try {
                if (Schema::hasTable('audit_events')) {
                    $targets = [];
                    foreach ($request->route()->parameters() as $key => $target) {
                        $id = $target instanceof Model ? $target->getKey() : $target;
                        if (is_numeric($id)) {
                            $targets[$key] = (int) $id;
                        }
                    }
                    $current = $this->actor($request);
                    $validationFailed = in_array('errors', $request->session()->get('_flash.new', []), true);
                    AuditEvent::create(['actor_type' => $actor['actor_type'] === 'guest' ? $current['actor_type'] : $actor['actor_type'], 'actor_id' => $actor['actor_id'] ?? $current['actor_id'], 'action' => $action, 'method' => $request->method(), 'status' => $status, 'outcome' => $status >= 500 ? 'error' : (($status >= 400 || $validationFailed) ? 'rejected' : 'success'), 'targets' => $targets]);
                }
            } catch (Throwable) {
                logger()->warning('An audit event could not be stored.');
            }
        }
    }

    /** @return array{actor_type: string, actor_id: ?int} */
    private function actor(Request $request): array
    {
        foreach (['teacher', 'student', 'parent'] as $type) {
            if ($id = $request->session()->get($type.'_id')) {
                return ['actor_type' => $type, 'actor_id' => (int) $id];
            }
        }

        return ['actor_type' => 'guest', 'actor_id' => null];
    }
}
