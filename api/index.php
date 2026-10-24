<?php
require_once __DIR__ . '/_bootstrap.php';
json_response([
    'ok' => true,
    'endpoints' => [
        'GET api/availability/?month=2026-09',
        'GET api/gallery/',
        'GET api/pincode/?pin=530048',
        'GET api/reviews/',
        'POST api/reviews/',
        'GET api/referral-lookup/?q=ANT-RAM-1234-FY26 admin session required',
        'GET api/bookings/?filter=upcoming admin session required',
        'GET api/payments/ admin session required',
        'POST api/customer-lookup/ mobile, previous reference and session token required',
        'GET api/client-document/?booking_id=ID admin session required',
    ],
]);
