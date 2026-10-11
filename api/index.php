<?php
require_once __DIR__ . '/_bootstrap.php';
json_response([
    'ok' => true,
    'endpoints' => [
        'GET api/availability.php?month=2026-09',
        'GET api/gallery.php',
        'GET api/pincode.php?pin=530048',
        'GET api/reviews.php',
        'POST api/reviews.php',
        'GET api/referral-lookup.php?q=ANT-RAM-1234-FY26 admin session required',
        'GET api/bookings.php?filter=upcoming admin session required',
        'GET api/payments.php admin session required',
    ],
]);
