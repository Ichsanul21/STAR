<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberingService
{
    public const DEFAULTS = [
        'event' => 'EVT-{YYYY}{MM}-{seq:4}',
        'invoice' => 'INV-{event}-{seq:2}',
        'spk' => 'SPK-{YYYY}{MM}-{seq:4}',
        'report' => 'RPT-{event}-{seq:2}',
    ];

    public const LABELS = [
        'event' => 'Event',
        'invoice' => 'Invoice',
        'spk' => 'SPK',
        'report' => 'Report',
    ];

    public const PREFIXES = [
        'event' => 'EVT',
        'invoice' => 'INV',
        'spk' => 'SPK',
        'report' => 'RPT',
    ];

    public function format(string $type): string
    {
        return AppSetting::get("numbering.{$type}", self::DEFAULTS[$type] ?? '{seq:4}') ?? '{seq:4}';
    }

    public function preview(string $type, array $context = []): string
    {
        $context += [
            'event_code' => 'EVT-DEMO',
            'dealer_code' => 'DEALER',
            'prefix' => self::PREFIXES[$type] ?? '',
        ];

        return $this->render($this->format($type), 1, $context);
    }

    public function peek(string $type, array $context = [], ?callable $isTaken = null): string
    {
        $format = $this->format($type);
        $scope = $this->scope($type, $format, $context);
        $context += ['prefix' => self::PREFIXES[$type] ?? ''];
        $number = (int) NumberSequence::query()
            ->where('type', $type)
            ->where('scope', $scope)
            ->value('last_number');

        do {
            $number++;
            $rendered = $this->render($format, $number, $context);
        } while ($isTaken && $isTaken($rendered));

        return $rendered;
    }

    public function next(string $type, array $context = [], ?callable $isTaken = null): string
    {
        $format = $this->format($type);
        $scope = $this->scope($type, $format, $context);
        $context += ['prefix' => self::PREFIXES[$type] ?? ''];

        return DB::transaction(function () use ($type, $scope, $format, $context, $isTaken) {
            NumberSequence::query()->firstOrCreate(
                ['type' => $type, 'scope' => $scope],
                ['last_number' => 0],
            );

            $sequence = NumberSequence::query()
                ->where('type', $type)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->firstOrFail();

            do {
                $sequence->last_number++;
                $number = $this->render($format, $sequence->last_number, $context);
            } while ($isTaken && $isTaken($number));

            $sequence->save();

            return $number;
        });
    }

    public function isValidFormat(string $format): bool
    {
        if ($format === '' || strlen($format) > 80 || ! str_contains($format, '{seq')) {
            return false;
        }

        $remaining = preg_replace(
            '/\{(?:seq(?::\d+)?|prefix|YYYY|YY|MM|DD|event|dealer|random:\d+)\}/',
            '',
            $format,
        );

        return ! str_contains($remaining, '{') && ! str_contains($remaining, '}');
    }

    public function reset(string $type): void
    {
        AppSetting::forget("numbering.{$type}");
    }

    private function scope(string $type, string $format, array $context): string
    {
        $eventId = $context['event_id'] ?? ($context['event'] ?? null)?->id;

        return match ($type) {
            'event' => 'period:'.now()->format('Ym'),
            'invoice', 'report' => str_contains($format, '{event}') && $eventId
                ? 'event:'.$eventId
                : 'period:'.now()->format('Ym'),
            'spk' => $eventId ? 'event:'.$eventId : 'period:'.now()->format('Ym'),
            default => 'global',
        };
    }

    private function render(string $format, int $number, array $context): string
    {
        $event = $context['event'] ?? null;
        $now = now();

        $replacements = [
            '{prefix}' => strtoupper((string) ($context['prefix'] ?? '')),
            '{YYYY}' => $now->format('Y'),
            '{YY}' => $now->format('y'),
            '{MM}' => $now->format('m'),
            '{DD}' => $now->format('d'),
            '{event}' => (string) ($context['event_code'] ?? ($event instanceof Event ? $event->code : '')),
            '{dealer}' => (string) ($context['dealer_code'] ?? ''),
        ];

        $rendered = preg_replace_callback(
            '/\{seq(?::(\d+))?\}/',
            fn (array $matches) => str_pad((string) $number, (int) ($matches[1] ?? 0), '0', STR_PAD_LEFT),
            $format,
        );

        $rendered = strtr($rendered, $replacements);

        return preg_replace_callback(
            '/\{random:(\d+)\}/',
            fn (array $matches) => Str::upper(Str::random((int) $matches[1])),
            $rendered,
        );
    }
}
