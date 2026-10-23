<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/** The shop's colours, chosen under Store settings > Website colours. */
class Theme
{
    /** Setting key => default colour. */
    public const DEFAULTS = [
        'theme_primary' => '#e8650c', // buttons, prices, links, top bar
        'theme_dark' => '#0b1120',    // home page slider and footer background
        'theme_sale' => '#e11d48',    // discount badges and sale prices
    ];

    /** Ready-made colour sets the admin can start from. */
    public const PRESETS = [
        'orange' => ['label' => 'Mega Mall orange (original)', 'theme_primary' => '#e8650c', 'theme_dark' => '#0b1120', 'theme_sale' => '#e11d48'],
        'green' => ['label' => 'Fresh green', 'theme_primary' => '#15803d', 'theme_dark' => '#0f2a1d', 'theme_sale' => '#dc2626'],
        'blue' => ['label' => 'Ocean blue', 'theme_primary' => '#1d4ed8', 'theme_dark' => '#0b1736', 'theme_sale' => '#e11d48'],
        'red' => ['label' => 'Bold red', 'theme_primary' => '#c81e1e', 'theme_dark' => '#1c1917', 'theme_sale' => '#ea580c'],
        'purple' => ['label' => 'Royal purple', 'theme_primary' => '#7e22ce', 'theme_dark' => '#1e1033', 'theme_sale' => '#e11d48'],
        'teal' => ['label' => 'Zanzibar teal', 'theme_primary' => '#0f766e', 'theme_dark' => '#0c1f24', 'theme_sale' => '#e11d48'],
    ];

    /** Current colours, falling back to the defaults for anything missing or invalid. */
    public static function colours(): array
    {
        try {
            $saved = Setting::allValues();
        } catch (Throwable) {
            $saved = []; // database not ready yet (fresh install)
        }

        $colours = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = strtolower((string) ($saved[$key] ?? ''));
            $colours[$key] = self::isHex($value) ? $value : $default;
        }

        return $colours;
    }

    public static function isHex(?string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', (string) $value);
    }

    /** Contrast ratio of white text on this colour (1 = invisible, 21 = black). */
    public static function contrastWithWhite(string $hex): float
    {
        $channel = function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };
        $hex = ltrim($hex, '#');
        $luminance = 0.2126 * $channel(substr($hex, 0, 2)) + 0.7152 * $channel(substr($hex, 2, 2)) + 0.0722 * $channel(substr($hex, 4, 2));

        return 1.05 / ($luminance + 0.05);
    }
}
