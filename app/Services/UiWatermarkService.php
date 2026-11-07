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
        'opacity' => '0.065',
        'color' => '#0a2540',
        'speed' => '70',
        'font_size' => '12',
        'login_enabled' => '0',
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

    public function opacity(): float
    {
        return min(0.5, max(0.01, (float) AppSetting::get('watermark.opacity', self::DEFAULTS['opacity'])));
    }

    public function color(): string
    {
        $color = AppSetting::get('watermark.color', self::DEFAULTS['color']) ?: self::DEFAULTS['color'];

        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : self::DEFAULTS['color'];
    }

    public function speed(): int
    {
        return min(300, max(10, (int) AppSetting::get('watermark.speed', self::DEFAULTS['speed'])));
    }

    public function fontSize(): int
    {
        return min(24, max(8, (int) AppSetting::get('watermark.font_size', self::DEFAULTS['font_size'])));
    }

    public function loginEnabled(): bool
    {
        return AppSetting::get('watermark.login_enabled', '0') === '1';
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
            'opacity' => $this->opacity(),
            'color' => $this->color(),
            'speed' => $this->speed(),
            'font_size' => $this->fontSize(),
            'login_enabled' => $this->loginEnabled(),
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
