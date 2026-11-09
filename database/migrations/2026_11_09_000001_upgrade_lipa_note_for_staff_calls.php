<?php

use App\Models\Setting;
use App\Support\LipaNamba;
use Illuminate\Database\Migrations\Migration;

/**
 * Customers no longer type a transaction ID: staff call them once the money arrives.
 * Updates the note under the Lipa Namba, but only if staff never reworded it. No table changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::where('key', 'lipa_note')->first();

        if ($setting && $setting->value === LipaNamba::OLD_NOTE) {
            $setting->update(['value' => LipaNamba::DEFAULT_NOTE]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the note can be changed under Store settings.
    }
};
