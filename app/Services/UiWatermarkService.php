<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class UiWatermarkService
{
    public const DEFAULTS = [
        'line1' => 'Copyright of Alenkosa - Your IT Solutions',
        'line2' => '{ip} - {device} - {timestamp} - {user}',
    ];

    public function enabled(): bool
    {
        return AppSetting::get('watermark.enabled', '0') === '1';
    }

    public function line1(): string
    {
        return AppSetting::get('watermark.line1', self::DEFAULTS['line1']) ?: self::DEFAULTS['line1'];
    }

    public function line2(): string
    {
        return AppSetting::get('watermark.line2', self::DEFAULTS['line2']) ?: self::DEFAULTS['line2'];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(?User $user = null, ?Request $request = null): array
    {
        $context = $this->context($user, $request);

        return [
            'enabled' => $this->enabled(),
            'line1' => $this->replace($this->line1(), $context),
            'line2' => $this->replace($this->line2(), $context),
            'context' => $context,
        ];
    }

    /**
     * @return array{ip: string, device: string, timestamp: string, user: string}
     */
    public function context(?User $user = null, ?Request $request = null): array
    {
        $request ??= request();
        $user ??= auth()->user();

        $agent = new Agent();
        $agent->setUserAgent($request?->userAgent() ?? '');

        $device = array_filter([
            $agent->browser() ?: null,
            $agent->platform() ?: null,
            $agent->isTablet() ? 'tablet' : ($agent->isMobile() ? 'mobile' : 'desktop'),
        ]);

        return [
            'ip' => $request?->ip() ?: '—',
            'device' => implode(' · ', $device) ?: 'Unknown',
            'timestamp' => now()->format('d/m/Y H:i:s'),
            'user' => $user?->name ?: 'Guest',
        ];
    }

    /**
     * @param  array<string, string>  $context
     */
    private function replace(string $template, array $context): string
    {
        return strtr($template, [
            '{ip}' => $context['ip'],
            '{device}' => $context['device'],
            '{timestamp}' => $context['timestamp'],
            '{user}' => $context['user'],
            '$IP_Address' => $context['ip'],
            '$Devices' => $context['device'],
            '$Timestamps' => $context['timestamp'],
            '$User' => $context['user'],
        ]);
    }
}
