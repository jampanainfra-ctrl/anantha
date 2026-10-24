<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = admin_page_start('Dashboard', 'dashboard');
$ready = db_ready();
$stats = ['pending' => 0, 'upcoming' => 0, 'reviews' => 0, 'revenue' => 0.0];
$statusMetrics = [];
$monthMetrics = [];
$calendarBookings = [];
$yearlyMonths = [];
$dashboardLeads = [];
$nextEvent = null;
$today = new DateTimeImmutable('today');
$currentHour = (int) (new DateTimeImmutable())->format('G');
$greeting = $currentHour < 12 ? 'Good morning' : ($currentHour < 17 ? 'Good afternoon' : 'Good evening');
$selectedMonth = $_GET['month'] ?? $today->format('Y-m');
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $selectedMonth) ?: $today->modify('first day of this month');
$selectedMonth = $monthDate->format('Y-m');
$currentYear = (int) $today->format('Y');
$defaultFyStart = (int) $today->format('n') >= 4 ? $currentYear : $currentYear - 1;
$fyStart = (int) ($_GET['fy'] ?? $defaultFyStart);
if ($fyStart < 2020 || $fyStart > $currentYear + 2) {
    $fyStart = $defaultFyStart;
}
$fyEnd = $fyStart + 1;
$fyStartDate = sprintf('%04d-04-01', $fyStart);
$fyEndDate = sprintf('%04d-03-31', $fyEnd);
$monthStart = $monthDate->format('Y-m-01');
$monthEnd = $monthDate->format('Y-m-t');
for ($offset = 0; $offset < 12; $offset++) {
    $monthPoint = (new DateTimeImmutable($fyStartDate))->modify('+' . $offset . ' months');
    $yearlyMonths[$monthPoint->format('Y-m')] = [
        'label' => $monthPoint->format('M'),
        'total' => 0,
        'confirmed' => 0,
    ];
}
if ($ready) {
    ensure_payment_verification_schema();
    ensure_booking_enquiry_schema();
    $stats = [
        'pending' => (int) db()->query("SELECT COUNT(*) FROM event_bookings WHERE booking_status='pending'")->fetchColumn(),
        'upcoming' => (int) db()->query("SELECT COUNT(*) FROM event_bookings WHERE event_date >= CURDATE() AND booking_status <> 'event_over' AND booking_status <> 'cancelled'")->fetchColumn(),
        'reviews' => (int) db()->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn(),
        'revenue' => (float) db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE verification_status='verified'")->fetchColumn(),
    ];
    $dashboardLeads = db()->query("SELECT id, booking_reference, client_name, phone, event_date, event_type, guests, booking_status, payment_status, created_by, created_at FROM event_bookings WHERE booking_status NOT IN ('event_over','cancelled') ORDER BY created_at DESC LIMIT 10")->fetchAll();
    $nextEvent = db()->query("SELECT id, booking_reference, client_name, event_date, event_type, guests, booking_status FROM event_bookings WHERE event_date >= CURDATE() AND booking_status NOT IN ('event_over','cancelled') ORDER BY event_date ASC, id ASC LIMIT 1")->fetch();
    $stmt = db()->prepare("SELECT booking_status, COUNT(*) AS total FROM event_bookings WHERE event_date BETWEEN ? AND ? GROUP BY booking_status");
    $stmt->execute([$fyStartDate, $fyEndDate]);
    foreach ($stmt->fetchAll() as $row) {
        $statusMetrics[$row['booking_status']] = (int) $row['total'];
    }
    $stmt = db()->prepare("SELECT DATE_FORMAT(event_date, '%Y-%m') AS month_key, COUNT(*) AS total, SUM(CASE WHEN booking_status IN ('confirmed','event_happening','event_over') THEN 1 ELSE 0 END) AS confirmed FROM event_bookings WHERE event_date BETWEEN ? AND ? AND booking_status <> 'cancelled' GROUP BY month_key ORDER BY month_key");
    $stmt->execute([$fyStartDate, $fyEndDate]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($yearlyMonths[$row['month_key']])) {
            $yearlyMonths[$row['month_key']]['total'] = (int) $row['total'];
            $yearlyMonths[$row['month_key']]['confirmed'] = (int) $row['confirmed'];
        }
    }
    $stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN booking_status IN ('confirmed','event_happening','event_over') THEN 1 ELSE 0 END) AS confirmed, SUM(CASE WHEN booking_status='pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN booking_status='cancelled' THEN 1 ELSE 0 END) AS cancelled FROM event_bookings WHERE event_date BETWEEN ? AND ?");
    $stmt->execute([$monthStart, $monthEnd]);
    $monthRow = $stmt->fetch() ?: [];
    $stmt = db()->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN event_bookings b ON b.id=p.booking_id WHERE p.verification_status='verified' AND b.event_date BETWEEN ? AND ?");
    $stmt->execute([$monthStart, $monthEnd]);
    $monthRevenue = (float) $stmt->fetchColumn();
    $monthMetrics = [
        ['Month enquiries', (string) (int) ($monthRow['total'] ?? 0)],
        ['Booked / active', (string) (int) ($monthRow['confirmed'] ?? 0)],
        ['Pending follow-up', (string) (int) ($monthRow['pending'] ?? 0)],
        ['Cancelled', (string) (int) ($monthRow['cancelled'] ?? 0)],
        ['Verified payments', 'Rs ' . number_format($monthRevenue)],
    ];
    $stmt = db()->prepare("SELECT id, client_name, event_date, booking_status FROM event_bookings WHERE event_date BETWEEN ? AND ? ORDER BY event_date ASC");
    $stmt->execute([$monthStart, $monthEnd]);
    foreach ($stmt->fetchAll() as $row) {
        $calendarBookings[$row['event_date']][] = $row;
    }
}
$yearGraphMax = max(1, ...array_column($yearlyMonths, 'total'));
$fyBookingTotal = array_sum(array_column($yearlyMonths, 'total'));
$fyConfirmedTotal = array_sum(array_column($yearlyMonths, 'confirmed'));
$followUpCount = (int) $stats['pending'] + (int) $stats['reviews'];
$calendarFirstDay = $monthDate->modify('first day of this month');
$calendarDays = (int) $calendarFirstDay->format('t');
$calendarOffset = ((int) $calendarFirstDay->format('N')) - 1;
$calendarPrev = $monthDate->modify('-1 month');
$calendarNext = $monthDate->modify('+1 month');
?>
<header class="dashboard-page-heading"><div><span class="eyebrow-text">ANANTHA ADMINISTRATION</span><h2>Dashboard</h2></div><span class="dashboard-date"><?php echo h($today->format('l, d F Y')); ?></span></header>
<section class="dashboard-top">
  <section class="dashboard-hero">
    <div>
      <span class="eyebrow-text">Today at Anantha</span>
      <h1><?php echo h($greeting); ?>, <?php echo h($admin['name']); ?></h1>
      <p>Here’s your venue at a glance. Keep every celebration on track.</p>
      <div class="hero-pulse">
        <span><strong><?php echo h((string) $followUpCount); ?></strong> follow-ups</span>
        <span><strong><?php echo h((string) $fyBookingTotal); ?></strong> FY bookings</span>
        <span><strong><?php echo h((string) $fyConfirmedTotal); ?></strong> booked</span>
      </div>
    </div>
    <div class="hero-actions">
      <a class="btn gold" href="<?php echo h(app_url('booking-form.php', 'admin')); ?>"><span aria-hidden="true">+</span> New Booking</a>
      <a class="btn" href="<?php echo h(app_url('bookings.php', 'admin')); ?>">Bookings <span aria-hidden="true">&rarr;</span></a>
    </div>
  </section>

  <section class="card availability-widget">
    <div class="calendar-title">
      <div><span class="eyebrow-text">Availability</span><h2><?php echo h($monthDate->format('F Y')); ?></h2></div>
      <div class="calendar-arrows">
        <a href="<?php echo h(app_url('dashboard.php', 'admin')); ?>?fy=<?php echo (int) $fyStart; ?>&month=<?php echo h($calendarPrev->format('Y-m')); ?>" aria-label="Previous month">&lsaquo;</a>
        <a href="<?php echo h(app_url('dashboard.php', 'admin')); ?>?fy=<?php echo (int) $fyStart; ?>&month=<?php echo h($calendarNext->format('Y-m')); ?>" aria-label="Next month">&rsaquo;</a>
      </div>
    </div>
    <div class="mini-month compact-calendar">
      <?php foreach (['M','T','W','T','F','S','S'] as $dayName): ?><span class="mini-week"><?php echo h($dayName); ?></span><?php endforeach; ?>
      <?php for ($i = 0; $i < $calendarOffset; $i++): ?><span class="mini-day empty"></span><?php endfor; ?>
      <?php for ($day = 1; $day <= $calendarDays; $day++): ?>
        <?php
          $dateObject = $calendarFirstDay->setDate((int) $calendarFirstDay->format('Y'), (int) $calendarFirstDay->format('m'), $day);
          $dateValue = $dateObject->format('Y-m-d');
          $dayBookings = $calendarBookings[$dateValue] ?? [];
          $isPast = $dateObject < $today;
        ?>
        <?php if ($dayBookings): ?>
          <?php $booking = $dayBookings[0]; ?>
	          <a class="mini-day booked <?php echo $dateValue === $today->format('Y-m-d') ? 'is-today' : ''; ?> <?php echo h($booking['booking_status']); ?> <?php echo $isPast ? 'past' : ''; ?>" href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $booking['id']; ?>" title="<?php echo h($booking['client_name'] . ' - ' . status_label($booking['booking_status'])); ?>"><strong><?php echo $day; ?></strong><small><?php echo h(substr(status_label($booking['booking_status']), 0, 1)); ?></small></a>
	        <?php elseif ($isPast): ?>
	          <span class="mini-day unavailable past"><strong><?php echo $day; ?></strong></span>
        <?php else: ?>
          <a class="mini-day available <?php echo $dateValue === $today->format('Y-m-d') ? 'is-today' : ''; ?>" href="<?php echo h(app_url('../availability.php', 'admin')); ?>?month=<?php echo h($selectedMonth); ?>&date=<?php echo h($dateValue); ?>" target="_blank" title="Available"><strong><?php echo $day; ?></strong><small>A</small></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
    <div class="calendar-legend"><span><i class="available"></i>Available</span><span><i class="pending"></i>Pending</span><span><i class="confirmed"></i>Booked</span><span><i class="over"></i>Past / over</span></div>
  </section>
</section>

<section class="dashboard-brief">
  <a href="<?php echo h(app_url('bookings.php', 'admin')); ?>?filter=pending"><span>Needs attention</span><strong><?php echo h((string) $stats['pending']); ?> enquiries</strong></a>
  <?php if ($nextEvent): ?>
    <a href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $nextEvent['id']; ?>"><span>Next event</span><strong><?php echo h(date('d M', strtotime($nextEvent['event_date']))); ?> - <?php echo h($nextEvent['client_name']); ?></strong></a>
  <?php else: ?>
    <a href="<?php echo h(app_url('booking-form.php', 'admin')); ?>"><span>Next event</span><strong>Add upcoming booking</strong></a>
  <?php endif; ?>
  <a href="<?php echo h(app_url('reviews.php', 'admin')); ?>"><span>Reviews queue</span><strong><?php echo h((string) $stats['reviews']); ?> waiting</strong></a>
</section>

<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql in phpMyAdmin first.</div><?php endif; ?>

<section class="prototype-header dashboard-toolbar">
  <div>
    <span class="eyebrow-text">Financial year overview</span>
    <h2>FY <?php echo h((string) $fyStart); ?>-<?php echo h(substr((string) $fyEnd, -2)); ?></h2>
  </div>
  <form class="dashboard-filter" method="GET" action="<?php echo h(app_url('dashboard.php', 'admin')); ?>">
    <label>Financial year
      <select name="fy">
        <?php for ($year = $defaultFyStart + 1; $year >= $defaultFyStart - 5; $year--): ?>
          <option value="<?php echo $year; ?>" <?php echo $fyStart === $year ? 'selected' : ''; ?>>FY <?php echo $year; ?>-<?php echo h(substr((string) ($year + 1), -2)); ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>Calendar month<input type="month" name="month" value="<?php echo h($selectedMonth); ?>"></label>
    <button class="btn gold">Apply</button>
  </form>
</section>

<section class="dashboard-stats">
  <a class="dash-stat warning" href="<?php echo h(app_url('bookings.php', 'admin')); ?>?filter=pending"><span>Need follow up</span><strong><?php echo h((string) $stats['pending']); ?></strong><small>Pending enquiries</small></a>
  <a class="dash-stat success" href="<?php echo h(app_url('bookings.php', 'admin')); ?>?filter=upcoming"><span>Upcoming events</span><strong><?php echo h((string) $stats['upcoming']); ?></strong><small>Today and future</small></a>
  <a class="dash-stat money" href="<?php echo h(app_url('bookings.php', 'admin')); ?>"><span>Verified payments</span><strong>Rs <?php echo h(number_format((float) $stats['revenue'])); ?></strong><small>Total received</small></a>
  <a class="dash-stat danger" href="<?php echo h(app_url('reviews.php', 'admin')); ?>"><span>Reviews waiting</span><strong><?php echo h((string) $stats['reviews']); ?></strong><small>Awaiting approval</small></a>
</section>

<section class="dashboard-analysis">
  <div class="card year-graph-card">
    <div class="section-head graph-heading"><div><h2>Booking activity</h2><p>Monthly bookings across the selected financial year.</p></div><div class="graph-legend"><span><i class="total"></i>Total</span><span><i class="confirmed"></i>Booked</span></div></div>
    <?php if ($fyBookingTotal > 0): ?>
      <div class="year-chart" aria-label="Monthly booking graph">
        <?php foreach ($yearlyMonths as $yearMonth): ?>
          <?php $totalHeight = (int) round(($yearMonth['total'] / $yearGraphMax) * 100); $confirmedHeight = (int) round(($yearMonth['confirmed'] / $yearGraphMax) * 100); ?>
          <div class="chart-month" title="<?php echo h($yearMonth['label'] . ': ' . $yearMonth['total'] . ' enquiries, ' . $yearMonth['confirmed'] . ' booked'); ?>">
            <div class="chart-value"><?php echo (int) $yearMonth['total']; ?></div>
            <div class="chart-bars"><span class="bar total" style="height:<?php echo $yearMonth['total'] > 0 ? max(3, $totalHeight) : 0; ?>%"></span><span class="bar confirmed" style="height:<?php echo $yearMonth['confirmed'] > 0 ? max(3, $confirmedHeight) : 0; ?>%"></span></div>
            <strong><?php echo h($yearMonth['label']); ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="chart-empty"><strong>No booking data yet</strong><span>New bookings will appear here automatically.</span></div>
    <?php endif; ?>
  </div>

  <div class="card summary-card">
    <div class="section-head"><div><h2><?php echo h($monthDate->format('F Y')); ?></h2><p>Selected month summary.</p></div></div>
    <div class="metric-list compact">
      <?php foreach ($monthMetrics as $metric): ?><div><span><?php echo h($metric[0]); ?></span><strong><?php echo h($metric[1]); ?></strong></div><?php endforeach; ?>
    </div>
    <h3 class="status-title">Financial year status</h3>
    <div class="status-breakdown">
      <?php foreach (booking_statuses() as $key => $label): ?><div><span><?php echo h($label); ?></span><strong><?php echo h((string) ($statusMetrics[$key] ?? 0)); ?></strong></div><?php endforeach; ?>
    </div>
  </div>
</section>
<section class="dashboard-actions">
  <a href="<?php echo h(app_url('booking-form.php', 'admin')); ?>"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg></span><strong>Create booking</strong><small>Add customer, event, quote, and package details.</small></a>
  <a href="<?php echo h(app_url('bookings.php', 'admin')); ?>?filter=upcoming"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span><strong>Check upcoming</strong><small>Review active events and balances before the day.</small></a>
  <a href="<?php echo h(app_url('../availability.php', 'admin')); ?>" target="_blank"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-12 4h2"/></svg></span><strong>Public calendar</strong><small>Open the customer-facing availability view.</small></a>
  <a href="<?php echo h(app_url('gallery.php', 'admin')); ?>"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 17 5-5 4 4 3-3 6 6"/></svg></span><strong>Update gallery</strong><small>Keep venue photos and categories fresh.</small></a>
</section>
<section class="card dashboard-leads">
  <div class="section-head">
    <div><h2>Active leads</h2><p>Latest enquiries and upcoming bookings requiring attention.</p></div>
    <a class="btn" href="<?php echo h(app_url('bookings.php', 'admin')); ?>">View all leads</a>
  </div>
  <div class="table-wrap">
    <table class="leads-table">
      <thead><tr><th>Client</th><th>Event</th><th>Guests</th><th>Booking</th><th>Payment</th><th>Received</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($dashboardLeads as $lead): ?>
          <tr>
            <td><strong><?php echo h($lead['client_name']); ?></strong><br><span class="muted-text"><?php echo h($lead['booking_reference'] ?? ('#' . (int) $lead['id'])); ?></span><br><a href="tel:<?php echo h($lead['phone']); ?>"><?php echo h($lead['phone']); ?></a></td>
            <td><strong><?php echo h($lead['event_type'] ?: 'Event'); ?></strong><br><span class="muted-text"><?php echo h(date('d M Y', strtotime($lead['event_date']))); ?></span></td>
            <td><?php echo $lead['guests'] ? h((string) $lead['guests']) : '-'; ?></td>
            <td><span class="badge <?php echo h($lead['booking_status']); ?>"><?php echo h(status_label($lead['booking_status'])); ?></span></td>
            <td><span class="badge <?php echo h($lead['payment_status']); ?>"><?php echo h(payment_statuses()[$lead['payment_status']] ?? status_label($lead['payment_status'])); ?></span></td>
            <td><?php echo h(date('d M Y', strtotime($lead['created_at']))); ?><br><span class="muted-text"><?php echo h(ucfirst($lead['created_by'])); ?></span></td>
            <td><a class="btn gold" href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $lead['id']; ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$dashboardLeads): ?><tr><td colspan="7">No active leads found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_page_end(); ?>
