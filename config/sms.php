<?php

return [
    /*
    | How customer SMS are sent.
    | "log" = no SMS provider yet: every message is saved in the admin under Store > SMS outbox
    |         (and in storage/logs/laravel.log) but nothing reaches the phone.
    | A real provider (Beem Africa, NextSMS, Africa's Talking...) is added as one new driver class
    | once the shop has an SMS account and an approved sender name.
    */
    'driver' => env('SMS_DRIVER', 'log'),

    // The approved sender name customers see, e.g. MEGAMALL (max 11 characters).
    'sender_id' => env('SMS_SENDER_ID', 'MEGAMALL'),
];
