<?php

namespace App\Http\Middleware;

use App\Services\AuditTrail;
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
        $trail = app(AuditTrail::class);
        $actor = $trail->snapshot($request);
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
                if (Schema::hasTable('audit_events') && ! $request->attributes->get('audit_event_recorded')) {
                    $targets = [];
                    foreach ($request->route()->parameters() as $key => $target) {
                        $id = $target instanceof Model ? $target->getKey() : $target;
                        if (is_numeric($id)) {
                            $targets[$key] = (int) $id;
                        }
                    }
                    $validationFailed = in_array('errors', $request->session()->get('_flash.new', []), true);
                    $trail->record($request, $actor, $action, $status, $status >= 500 ? 'error' : (($status >= 400 || $validationFailed) ? 'rejected' : 'success'), $targets);
                }
            } catch (Throwable) {
                logger()->warning('An audit event could not be stored.');
            }
        }
    }
}
