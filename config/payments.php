<?php

return [
    /*
    | Which mobile money gateway to use for prepaid (non-Dar) orders.
    | "fake"  = local development: no money moves, the payment succeeds after a few seconds.
    | Real gateways (azampay, selcom, clickpesa) are added in a later step.
    */
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    // Mobile money channels shown at checkout.
    'channels' => [
        'mpesa' => 'M-Pesa',
        'mixx' => 'Mixx by Yas',
        'airtel' => 'Airtel Money',
        'halopesa' => 'HaloPesa',
    ],

    'fake' => [
        // Seconds before a fake payment flips to "paid" (simulates the customer entering their PIN).
        'approve_after' => (int) env('FAKE_PAYMENT_SECONDS', 6),
        // Any paying phone number ending in these digits fails, so you can test the failure screen.
        'fail_suffix' => env('FAKE_PAYMENT_FAIL_SUFFIX', '999'),
    ],
];
