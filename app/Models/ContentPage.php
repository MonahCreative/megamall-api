<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A page of the shop written in the admin: Terms, Privacy, Return policy, About, Contact... */
class ContentPage extends Model
{
    protected $fillable = ['title', 'slug', 'body', 'is_published', 'show_in_footer', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'show_in_footer' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (ContentPage $page) {
            $page->slug = Str::slug($page->slug ?: $page->title);
        });
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * The page text with shop details filled in, so a phone number changed in Store settings
     * updates every page: {store_name}, {phone}, {whatsapp}, {email}, {return_window_days}.
     */
    public function renderedBody(): string
    {
        $settings = Setting::allValues();
        $replacements = [
            '{store_name}' => e($settings['store_name'] ?? config('app.name')),
            '{phone}' => e($settings['phone'] ?? ''),
            '{whatsapp}' => e(self::international($settings['whatsapp'] ?? '')),
            '{email}' => e($settings['email'] ?? ''),
            '{return_window_days}' => (string) (int) ($settings['return_window_days'] ?? 7),
        ];

        return strtr(Str::sanitizeHtml((string) $this->body), $replacements);
    }

    /** 255712345678 -> +255 712 345 678 */
    private static function international(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        return strlen($digits) === 12 ? '+'.substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 3).' '.substr($digits, 9) : $number;
    }
}
