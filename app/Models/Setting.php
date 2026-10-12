<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    /** All settings as key => value, cached. */
    public static function allValues(): array
    {
        return Cache::rememberForever('settings.all', fn () => static::query()
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value])
            ->all());
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }
}
