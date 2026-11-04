<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $request->attributes->set('audit_started_at', $started);
        $request->attributes->set('audit_request_id', $request->header('X-Request-ID') ?: (string) Str::uuid());

        $response = $next($request);

        app(ActivityLogger::class)->logRequest($request, $response, $started);

        return $response;
    }
}
