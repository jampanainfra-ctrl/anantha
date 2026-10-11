<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();

$date = trim((string) ($_GET['date'] ?? ''));
$excludeBookingId = max(0, (int) ($_GET['exclude_booking_id'] ?? 0));

if ($date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'error' => 'Use date format YYYY-MM-DD.'], 422);
    }

    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        json_response(['ok' => false, 'error' => 'Use a valid date.'], 422);
    }

    $params = [$date];
    $excludeSql = '';
    if ($excludeBookingId > 0) {
        $excludeSql = ' AND id <> ?';
        $params[] = $excludeBookingId;
    }
    $stmt = db()->prepare("SELECT event_date, client_name, event_type, booking_status FROM event_bookings WHERE event_date=? AND booking_status <> 'cancelled'{$excludeSql} ORDER BY id DESC LIMIT 1");
    $stmt->execute($params);
    $booking = $stmt->fetch() ?: null;
    $isPast = $dateObject < new DateTimeImmutable('today');

    json_response([
        'ok' => true,
        'date' => $date,
        'available' => !$isPast && !$booking,
        'status' => $isPast ? 'past' : ($booking ? 'booked' : 'available'),
        'booking' => $booking,
    ]);
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    json_response(['ok' => false, 'error' => 'Use month format YYYY-MM.'], 422);
}

$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));
$params = [$start, $end];
$excludeSql = '';
if ($excludeBookingId > 0) {
    $excludeSql = ' AND id <> ?';
    $params[] = $excludeBookingId;
}
$stmt = db()->prepare("SELECT event_date, client_name, event_type, booking_status FROM event_bookings WHERE event_date BETWEEN ? AND ? AND booking_status <> 'cancelled'{$excludeSql} ORDER BY event_date");
$stmt->execute($params);
$booked = $stmt->fetchAll();
json_response(['ok' => true, 'month' => $month, 'booked_dates' => $booked]);
