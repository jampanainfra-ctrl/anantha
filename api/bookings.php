<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();
api_require_admin();

$filter = $_GET['filter'] ?? 'active';
$where = "WHERE booking_status <> 'cancelled'";
if ($filter === 'pending') {
    $where = "WHERE booking_status = 'pending'";
} elseif ($filter === 'upcoming') {
    $where = "WHERE event_date >= CURDATE() AND booking_status <> 'event_over' AND booking_status <> 'cancelled'";
} elseif ($filter === 'over') {
    $where = "WHERE booking_status = 'event_over' OR event_date < CURDATE()";
}
$bookings = db()->query("SELECT * FROM event_bookings $where ORDER BY event_date ASC, id DESC")->fetchAll();
json_response(['ok' => true, 'filter' => $filter, 'bookings' => $bookings]);
