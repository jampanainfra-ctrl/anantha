<?php
require_once __DIR__ . '/../includes/admin_layout.php';
admin_page_start('Bookings & Payments', 'bookings');
$ready = db_ready();
if ($ready) {
    ensure_booking_enquiry_schema();
    ensure_payment_verification_schema();
    ensure_package_schema();
}

$filter = $_GET['filter'] ?? 'active';
$search = trim($_GET['q'] ?? '');
$whereParts = ["b.booking_status <> 'cancelled'", "b.booking_status <> 'event_over'"];
$params = [];
if ($filter === 'pending') {
    $whereParts = ["b.booking_status = 'pending'"];
} elseif ($filter === 'upcoming') {
    $whereParts = ["b.event_date >= CURDATE()", "b.booking_status <> 'event_over'", "b.booking_status <> 'cancelled'"];
} elseif ($filter === 'over') {
    $whereParts = ["(b.booking_status = 'event_over' OR b.event_date < CURDATE())"];
}
if ($search !== '') {
    $whereParts[] = "(b.booking_reference LIKE ? OR b.client_name LIKE ? OR b.phone LIKE ? OR b.whatsapp_number LIKE ? OR b.event_type LIKE ? OR b.area_from LIKE ? OR b.city_district LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_fill(0, 7, $searchTerm);
}
$where = 'WHERE ' . implode(' AND ', $whereParts);

$bookings = [];
if ($ready) {
    $stmt = db()->prepare("SELECT b.*, p.package_name, p.guest_range, COALESCE(pay.verified_total, 0) AS verified_total, COALESCE(pay.received_total, 0) AS received_total, COALESCE(pay.payment_count, 0) AS payment_count, COALESCE(pay.pending_count, 0) AS pending_payment_count, COALESCE(pay.rejected_count, 0) AS rejected_payment_count FROM event_bookings b LEFT JOIN event_packages p ON p.id=b.package_id LEFT JOIN (SELECT booking_id, SUM(CASE WHEN verification_status='verified' THEN amount ELSE 0 END) AS verified_total, SUM(CASE WHEN verification_status <> 'rejected' THEN amount ELSE 0 END) AS received_total, COUNT(*) AS payment_count, SUM(CASE WHEN verification_status='pending' THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN verification_status='rejected' THEN 1 ELSE 0 END) AS rejected_count FROM payments GROUP BY booking_id) pay ON pay.booking_id=b.id $where ORDER BY b.event_date ASC, b.id DESC");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
}
?>
<div class="top">
  <div><h1>Bookings</h1><p>Start here. View every booking, payment total, and balance in one easy grid.</p></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql in phpMyAdmin first.</div><?php endif; ?>

<section class="card">
  <div class="booking-grid-toolbar">
    <form class="search-bar compact-search" method="GET" action="bookings.php">
      <input type="hidden" name="filter" value="<?php echo h($filter); ?>">
      <label><span class="sr-only">Search booking</span><input name="q" value="<?php echo h($search); ?>" placeholder="Search booking"></label>
      <button class="btn gold">Search</button>
      <?php if ($search !== ''): ?><a class="btn" href="bookings.php?filter=<?php echo h($filter); ?>">Clear</a><?php endif; ?>
    </form>
    <a class="btn gold" href="booking-form.php">Add New Booking</a>
  </div>
  <div class="filters">
    <?php foreach (['active'=>'Current','pending'=>'Need follow up','upcoming'=>'Today & future','over'=>'Past events'] as $key => $label): ?>
      <a class="<?php echo $filter === $key ? 'on' : ''; ?>" href="?filter=<?php echo h($key); ?><?php echo $search !== '' ? '&q=' . h(rawurlencode($search)) : ''; ?>"><?php echo h($label); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap booking-table-wrap">
    <table class="booking-table">
      <thead>
        <tr>
          <th>No.</th>
          <th>Customer</th>
          <th>Event</th>
          <th>Place / Guests</th>
          <th>Total</th>
          <th>Paid</th>
          <th>Balance</th>
          <th>Status</th>
          <th>Open</th>
        </tr>
      </thead>
      <tbody>
    <?php foreach ($bookings as $index => $booking): ?>
      <?php
        $quoteTotal = (float) ($booking['proposal_amount'] ?? 0);
        $paidTotal = (float) ($booking['verified_total'] ?? 0);
        $receivedTotal = (float) ($booking['received_total'] ?? 0);
        $paymentCount = (int) ($booking['payment_count'] ?? 0);
        $pendingPaymentCount = (int) ($booking['pending_payment_count'] ?? 0);
        $rejectedPaymentCount = (int) ($booking['rejected_payment_count'] ?? 0);
        $balance = max(0, $quoteTotal - $paidTotal);
      ?>
      <tr>
        <td><span class="grid-row-number"><?php echo $index + 1; ?></span></td>
        <td>
	          <div class="grid-client"><strong><?php echo h($booking['client_name']); ?></strong><small><?php echo h($booking['booking_reference'] ?? ('#' . (int) $booking['id'])); ?></small><a href="tel:<?php echo h($booking['phone']); ?>"><?php echo h($booking['phone']); ?></a><?php if (!empty($booking['whatsapp_number'])): ?><span>WA: <?php echo h($booking['whatsapp_number']); ?></span><?php endif; ?></div>
        </td>
        <td>
          <div class="grid-event"><strong><?php echo h(date('d M Y', strtotime($booking['event_date']))); ?></strong><span><?php echo h($booking['event_type'] ?: 'Event'); ?></span><small><?php echo h($booking['package_name'] ? $booking['package_name'] . ' - ' . $booking['guest_range'] : 'No package'); ?></small></div>
        </td>
        <td>
          <div class="grid-place"><span><?php echo h(trim(($booking['area_from'] ?? '') . ', ' . ($booking['city_district'] ?? ''), ', ') ?: 'Not added'); ?></span><small>Guests: <?php echo h($booking['guests'] ?: 'Not added'); ?></small></div>
        </td>
        <td><strong class="money-value">Rs <?php echo h(number_format($quoteTotal)); ?></strong></td>
        <td>
          <strong class="money-value">Rs <?php echo h(number_format($paidTotal)); ?></strong><br>
          <span class="payment-count"><?php echo h((string) $paymentCount); ?> payment<?php echo $paymentCount === 1 ? '' : 's'; ?></span>
          <?php if ($receivedTotal > $paidTotal): ?><br><span class="muted-text">Received: Rs <?php echo h(number_format($receivedTotal)); ?></span><?php endif; ?>
          <?php if ($pendingPaymentCount > 0): ?><br><span class="badge pending"><?php echo $pendingPaymentCount; ?> not checked</span><?php endif; ?>
          <?php if ($rejectedPaymentCount > 0): ?><br><span class="badge rejected"><?php echo $rejectedPaymentCount; ?> rejected</span><?php endif; ?>
        </td>
        <td><strong class="money-value <?php echo $balance > 0 ? 'due' : 'settled'; ?>">Rs <?php echo h(number_format($balance)); ?></strong></td>
        <td>
          <span class="badge <?php echo h($booking['booking_status']); ?>"><?php echo h(status_label($booking['booking_status'])); ?></span>
        </td>
        <td><a class="btn gold grid-manage" href="booking-form.php?id=<?php echo (int) $booking['id']; ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$bookings): ?>
      <tr><td colspan="9">No bookings found. Use Add New Booking to create one.</td></tr>
    <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
