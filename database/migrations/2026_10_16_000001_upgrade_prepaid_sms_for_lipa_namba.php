<?php

use App\Models\Setting;
use App\Support\SmsTemplates;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the Lipa Namba to the "order placed, waiting for payment" SMS, but only if staff never reworded it.
 * No table changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::where('key', 'sms_templates')->first();
        $templates = $setting?->value;

        if (is_array($templates) && ($templates['order_placed_prepaid'] ?? null) === SmsTemplates::OLD_PREPAID) {
            $templates['order_placed_prepaid'] = SmsTemplates::DEFAULTS['order_placed_prepaid'];
            $setting->update(['value' => $templates]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the wording can be changed under Store settings.
    }
};
