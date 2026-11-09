<?php

return [
    /*
    | WhatsApp alerts to staff about new orders, through CallMeBot (free, for a few staff phones).
    | Each staff phone activates itself once (see Store settings > Staff alerts) and gets its own key.
    | "log" writes the messages to storage/logs instead of sending them.
    */
    'driver' => env('STAFF_WHATSAPP_DRIVER', 'callmebot'),

    'callmebot_url' => env('CALLMEBOT_URL', 'https://api.callmebot.com/whatsapp.php'),

    // Seconds to wait for CallMeBot. Messages are sent after the customer's page has loaded, so a
    // slow reply never slows down checkout.
    'timeout' => (int) env('STAFF_WHATSAPP_TIMEOUT', 15),
];
