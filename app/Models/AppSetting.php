<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private static ?bool $available = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! self::available()) {
            return $default;
        }

        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }

    private static function available(): bool
    {
        return self::$available ??= Schema::hasTable('app_settings');
    }
}
