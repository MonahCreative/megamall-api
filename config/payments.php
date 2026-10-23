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

    /*
    | AzamPay (https://developers.azampay.co.tz). Sandbox credentials come from the AzamPay sandbox portal;
    | live ones after the business KYC is approved. Set PAYMENT_DRIVER=azampay to use it.
    */
    'azampay' => [
        'environment' => env('AZAMPAY_ENVIRONMENT', 'sandbox'), // sandbox | live
        'app_name' => env('AZAMPAY_APP_NAME'),
        'client_id' => env('AZAMPAY_CLIENT_ID'),
        'client_secret' => env('AZAMPAY_CLIENT_SECRET'),
        'api_key' => env('AZAMPAY_API_KEY'), // the "X-API-Key" from the portal
        // Secret added to the callback URL you register in the AzamPay portal:
        //   https://api.yourshop.co.tz/api/payments/callback/azampay?key=THIS_VALUE
        'callback_key' => env('AZAMPAY_CALLBACK_KEY'),
        'urls' => [
            'sandbox' => ['auth' => 'https://authenticator-sandbox.azampay.co.tz', 'checkout' => 'https://sandbox.azampay.co.tz'],
            'live' => ['auth' => 'https://authenticator.azampay.co.tz', 'checkout' => 'https://checkout.azampay.co.tz'],
        ],
        // Our channel => AzamPay provider name. Mixx by Yas is the former Tigo Pesa.
        'providers' => [
            'mpesa' => 'Mpesa',
            'mixx' => 'Tigo',
            'airtel' => 'Airtel',
            'halopesa' => 'Halopesa',
        ],
    ],

    'fake' => [
        // Seconds before a fake payment flips to "paid" (simulates the customer entering their PIN).
        'approve_after' => (int) env('FAKE_PAYMENT_SECONDS', 6),
        // Any paying phone number ending in these digits fails, so you can test the failure screen.
        'fail_suffix' => env('FAKE_PAYMENT_FAIL_SUFFIX', '999'),
    ],
];
