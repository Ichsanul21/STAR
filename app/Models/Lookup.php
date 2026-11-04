<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    protected $fillable = ['group', 'value', 'label', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Option map (value => label) for a group, merged with config defaults and historical values.
     *
     * @param  array<int, string>|null  $historical
     * @return array<string, string>
     */
    public static function options(string $group, ?array $historical = null): array
    {
        $defaults = config("star_options.{$group}", []);
        $stored = static::query()->group($group)->orderBy('sort_order')->orderBy('label')->get();

        $options = $defaults;

        foreach ($stored as $row) {
            if (! $row->is_active) {
                unset($options[$row->value]);

                continue;
            }

            $options[$row->value] = $row->label;
        }

        foreach ($historical ?? [] as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $options[$value] ??= ucfirst(str_replace('_', ' ', $value));
        }

        return $options;
    }

    /**
     * Values that are selectable for new input: active stored rows plus config defaults not overridden.
     *
     * @return array<int, string>
     */
    public static function activeValues(string $group): array
    {
        $defaults = array_keys(config("star_options.{$group}", []));
        $stored = static::query()->group($group)->get();

        $disabled = $stored->where('is_active', false)->pluck('value')->all();
        $active = $stored->where('is_active', true)->pluck('value')->all();

        return array_values(array_unique(array_merge(array_diff($defaults, $disabled), $active)));
    }
}
