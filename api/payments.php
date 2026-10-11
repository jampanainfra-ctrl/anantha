<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();
api_require_admin();

$payments = db()->query("SELECT p.*, b.client_name, b.event_date FROM payments p JOIN event_bookings b ON b.id=p.booking_id ORDER BY p.paid_on DESC, p.id DESC")->fetchAll();
json_response(['ok' => true, 'payments' => $payments]);
