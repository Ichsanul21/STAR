<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\ClientPayment;
use App\Models\Event;
use App\Models\ReportVersion;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogger
{
    public function log(
        string $action,
        ?string $description = null,
        ?Event $event = null,
        mixed $subject = null,
        array $meta = [],
        array $oldValues = [],
        array $newValues = [],
        string $severity = 'info',
        ?int $userId = null,
        bool $isSystem = false
    ): ?ActivityLog {
        if (! config('audit.enabled', true)) {
            return null;
        }

        $request = app()->bound('request') ? request() : null;
        $userAgent = $request?->userAgent();
        $agent = $this->agent($userAgent);
        $started = $request?->attributes->get('audit_started_at');
        $duration = $started ? (int) round((microtime(true) - (float) $started) * 1000) : null;
        $requestId = $request?->attributes->get('audit_request_id');

        return ActivityLog::create([
            'user_id' => $userId ?? auth()->id(),
            'event_id' => $event?->id ?? $this->resolveEventId($subject),
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues ? $this->redact($oldValues) : null,
            'new_values' => $newValues ? $this->redact($newValues) : null,
            'meta' => $meta ? $this->redact($meta) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent,
            'device_type' => $agent['device_type'],
            'browser' => $agent['browser'],
            'platform' => $agent['platform'],
            'route' => $request?->route()?->getName() ?? $request?->path(),
            'http_method' => $request?->method(),
            'duration_ms' => $duration,
            'request_id' => $requestId,
            'session_id' => $request && $request->hasSession() ? $request->session()->getId() : null,
            'severity' => $severity,
            'is_system' => $isSystem || ! auth()->check(),
        ]);
    }

    public function logRequest(Request $request, Response $response, float $started): void
    {
        if (! config('audit.enabled', true) || ! $request->user()) {
            return;
        }

        $route = $request->route()?->getName();
        if (! $route) {
            return;
        }

        $sensitiveGet = (bool) preg_match('/(reports\.(pdf|xlsx|preview)|media\.file|attachments\.show|expenses\.receipt|activity-logs)/', $route);
        if ($request->isMethod('GET') && ! $sensitiveGet && ! config('audit.log_reads', false)) {
            return;
        }

        $request->attributes->set('audit_started_at', $started);
        $request->attributes->set('audit_request_id', $request->header('X-Request-ID') ?: (string) Str::uuid());

        $this->log(
            'http.request',
            $request->method().' '.$route,
            null,
            null,
            [
                'route' => $route,
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ],
            [],
            [],
            $response->getStatusCode() >= 500 ? 'danger' : ($response->getStatusCode() >= 400 ? 'warning' : 'info')
        );
    }

    private function resolveEventId(mixed $subject): ?int
    {
        if ($subject instanceof Event) {
            return $subject->id;
        }

        if ($subject instanceof BudgetItem) {
            return $subject->version?->event_id;
        }

        if ($subject instanceof BudgetVersion) {
            return $subject->event_id;
        }

        if ($subject instanceof ClientPayment) {
            return $subject->invoice?->event_id;
        }

        if ($subject instanceof ReportVersion) {
            return $subject->event_id;
        }

        if ($subject instanceof Task) {
            return $subject->event_id;
        }

        if ($subject instanceof \App\Models\TaskChecklist) {
            return $subject->task?->event_id;
        }

        if ($subject instanceof \App\Models\TaskDependency) {
            return $subject->task?->event_id;
        }

        return $subject?->event_id ?? null;
    }

    private function agent(?string $userAgent): array
    {
        $agent = new Agent();
        $agent->setUserAgent($userAgent ?? '');

        return [
            'device_type' => $agent->isTablet() ? 'tablet' : ($agent->isMobile() ? 'mobile' : 'desktop'),
            'browser' => $agent->browser() ?: null,
            'platform' => $agent->platform() ?: null,
        ];
    }

    private function redact(array $data): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', '_token', 'authorization', 'cookie', 'secret'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
                continue;
            }

            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }
}
