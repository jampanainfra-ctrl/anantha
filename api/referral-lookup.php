<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();
api_require_admin();

$input = trim((string) ($_GET['q'] ?? ''));
$excludeId = (int) ($_GET['exclude_id'] ?? 0);
if ($input === '') {
    json_response(['ok' => true, 'found' => false]);
}

ensure_referral_schema();
$reference = resolve_referral_reference($input, $excludeId > 0 ? $excludeId : null);
if (!$reference) {
    json_response(['ok' => true, 'found' => false, 'message' => 'No eligible member found for this reference or phone number.']);
}

$stmt = db()->prepare('SELECT b.id, c.code AS booking_reference, b.client_name, b.phone, b.whatsapp_number, b.event_date, b.event_type FROM referral_codes c JOIN event_bookings b ON b.id=c.booking_id WHERE c.code=? LIMIT 1');
$stmt->execute([$reference]);
$booking = $stmt->fetch();
if (!$booking) {
    json_response(['ok' => true, 'found' => false]);
}

json_response([
    'ok' => true,
    'found' => true,
    'booking' => [
        'id' => (int) $booking['id'],
        'booking_reference' => $booking['booking_reference'],
        'client_name' => $booking['client_name'],
        'phone' => $booking['phone'],
        'whatsapp_number' => $booking['whatsapp_number'],
        'event_date' => $booking['event_date'],
        'event_type' => $booking['event_type'],
    ],
]);
