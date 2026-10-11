<?php
require_once __DIR__ . '/includes/config.php';

$ready = db_ready();
if ($ready) {
    ensure_booking_enquiry_schema();
    ensure_payment_verification_schema();
    ensure_package_schema();
    ensure_rate_card_schema();
    ensure_terms_schema();
}

$today = new DateTimeImmutable('today');
$maxCapacity = $ready ? max(1, (int) rate_setting_value('maximum_capacity', 300)) : 300;
$referralEligibilityDays = $ready ? referral_eligibility_days() : 60;
$monthParam = $_GET['month'] ?? $today->format('Y-m');
$month = DateTimeImmutable::createFromFormat('!Y-m', $monthParam) ?: $today->modify('first day of this month');
$minMonth = $today->modify('first day of this month');
if ($month < $minMonth) {
    $month = $minMonth;
}

$selectedDate = trim(strip_tags($_GET['date'] ?? $_POST['event_date'] ?? $_POST['event_date_visible'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = '';
}

$packages = $ready ? active_packages() : [];
$packageMap = [];
$packageRules = [];
foreach ($packages as $package) {
    $packageMap[(int) $package['id']] = $package;
    [$minGuests, $maxGuests] = package_guest_bounds((string) $package['guest_range'], $maxCapacity);
    $packageRules[(int) $package['id']] = ['min' => $minGuests, 'max' => $maxGuests];
}
$bookingRequiredTerms = $ready ? booking_required_terms(3) : [];

$name = $phone = $whatsapp = $email = $pin = $area = $city = $state = $eventType = $guests = $note = $referredBy = '';
$packageId = 0;
$formError = $success = $waLink = '';
$bookingReference = '';
$referenceQuery = trim(strip_tags($_GET['ref'] ?? $_POST['booking_reference'] ?? ''));
$referenceResult = null;
$referenceError = '';
$acceptedTermIds = [];

if ($ready && $referenceQuery !== '') {
    $referenceQuery = normalize_booking_reference($referenceQuery);
    $stmt = db()->prepare("SELECT booking_reference, event_date, event_type, guests, booking_status, payment_status, created_at FROM event_bookings WHERE booking_reference = ? LIMIT 1");
    $stmt->execute([$referenceQuery]);
    $referenceResult = $stmt->fetch() ?: null;
    if (!$referenceResult) {
        $referenceError = 'No booking found for this reference code.';
    }
}

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enquire'])) {
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $phone = preg_replace('/\D+/', '', trim(strip_tags($_POST['phone'] ?? '')));
    $whatsapp = preg_replace('/\D+/', '', trim(strip_tags($_POST['whatsapp_number'] ?? '')));
    $email = trim(strip_tags($_POST['email'] ?? ''));
    $pin = preg_replace('/\D+/', '', trim(strip_tags($_POST['pin_code'] ?? '')));
    $area = trim(strip_tags($_POST['area_from'] ?? ''));
    $city = trim(strip_tags($_POST['city_district'] ?? ''));
    $state = trim(strip_tags($_POST['client_state'] ?? ''));
    $eventType = trim(strip_tags($_POST['event_type'] ?? ''));
    $guests = trim(strip_tags($_POST['guests'] ?? ''));
    $packageId = (int) ($_POST['package_id'] ?? 0);
    $note = trim(strip_tags($_POST['note'] ?? ''));
    $referralInput = trim(strip_tags($_POST['referred_by_reference'] ?? ''));
    $referredBy = $referralInput !== '' ? (resolve_referral_reference($referralInput) ?? '') : '';
    $acceptedTermIds = array_map('intval', (array) ($_POST['accept_terms'] ?? []));
    $requiredTermIds = array_map(static fn ($term) => (int) $term['id'], $bookingRequiredTerms);
    $acceptedRequiredIds = array_intersect($requiredTermIds, $acceptedTermIds);
    $acceptedTermsSnapshot = json_encode(array_map(static fn ($term) => [
        'id' => (int) $term['id'],
        'title' => (string) $term['title'],
        'term_text' => (string) $term['term_text'],
        'accepted_at' => date('c'),
    ], $bookingRequiredTerms), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $requestedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
    $guestCount = (int) $guests;
    $duplicateContactMessage = duplicate_booking_contact_message($phone, $email, null, $whatsapp);

    $check = db()->prepare("SELECT COUNT(*) FROM event_bookings WHERE event_date = ? AND booking_status <> 'cancelled'");
    $check->execute([$selectedDate]);
    $referrerExists = $referralInput === '' || $referredBy !== '';

    if ($name === '' || $phone === '' || $email === '' || $pin === '' || $selectedDate === '' || $eventType === '' || $guests === '') {
        $formError = 'Please complete all required fields and select an available date.';
    } elseif (!preg_match('/^\d{10}$/', $phone)) {
        $formError = 'Please enter a valid 10-digit mobile number.';
    } elseif ($whatsapp !== '' && !preg_match('/^\d{10}$/', $whatsapp)) {
        $formError = 'Please enter a valid 10-digit WhatsApp number.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, '@') === false) {
        $formError = 'Please enter a valid email address with @.';
    } elseif ($duplicateContactMessage !== '') {
        $formError = 'This contact is already used.';
    } elseif (!preg_match('/^\d{6}$/', $pin)) {
        $formError = 'Please enter a valid 6-digit PIN code.';
    } elseif ($guestCount < 1) {
        $formError = 'Please enter the expected guest count.';
    } elseif ($guestCount > $maxCapacity) {
        $formError = 'Maximum allowed guest count is ' . $maxCapacity . '. Please reduce the guest count or contact us directly.';
    } elseif (!$requestedDate || $requestedDate->format('Y-m-d') !== $selectedDate || $requestedDate < $today) {
        $formError = 'Please choose a valid upcoming available date.';
    } elseif ((int) $check->fetchColumn() > 0) {
        $formError = 'This date is already on hold or booked. Please choose another available date.';
    } elseif ($packageId <= 0 || !isset($packageMap[$packageId])) {
        $formError = 'Please select a package.';
    } elseif ($guestCount < $packageRules[$packageId]['min'] || $guestCount > $packageRules[$packageId]['max']) {
        $formError = 'Your guest count is outside the selected package range. Please use the package suggested by guest count.';
    } elseif (!$referrerExists) {
        $formError = 'Please enter a valid eligible referral reference, or leave it blank.';
    } elseif ($requiredTermIds && count($acceptedRequiredIds) !== count($requiredTermIds)) {
        $formError = 'Please accept all three booking terms.';
	    } else {
	        $package = $packageMap[$packageId];
	        $bookingReference = generate_unique_booking_reference($name);
	        $referrerBonus = $referredBy !== '' ? referral_referrer_bonus_for($referredBy) : 0;
	        $newMemberBonus = $referredBy !== '' ? referral_new_member_bonus_default() : 0;
	        $stmt = db()->prepare("INSERT INTO event_bookings
	            (booking_reference, referred_by_reference, client_name, phone, whatsapp_number, email, pin_code, area_from, city_district, client_state, event_date, event_type, guests, package_id, note, accepted_terms_snapshot, payment_status, proposal_amount, referral_eligible, referral_referrer_bonus, referral_referred_bonus, created_by)
	            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'public')");
	        $stmt->execute([
	            $bookingReference,
	            $referredBy ?: null,
            $name,
            $phone,
            $whatsapp ?: null,
            $email ?: null,
            $pin,
            $area ?: null,
            $city ?: null,
            $state ?: null,
            $selectedDate,
            $eventType,
            $guests ?: null,
            $packageId,
            $note ?: null,
	            $acceptedTermsSnapshot,
	            'pending',
	            null,
	            $referredBy !== '' ? 1 : 0,
	            $referrerBonus,
	            $newMemberBonus,
	        ]);
        $bookingId = (int) db()->lastInsertId();
        $message = "Hi Anantha Event Lawns, I submitted enquiry {$bookingReference}.\n"
                 . "Name: {$name}\nMobile: {$phone}\nWhatsApp: " . ($whatsapp ?: $phone) . "\n"
	                 . "Email: " . ($email ?: 'Not shared') . "\nPIN: {$pin}\n"
	                 . "Area: " . ($area ?: 'Not shared') . "\nCity/District: " . ($city ?: 'Not shared') . "\nState: " . ($state ?: 'Not shared') . "\n"
	                 . "Referred by: " . ($referredBy ?: 'Not shared') . "\n"
	                 . "Date: {$selectedDate}\nOccasion: {$eventType}\nGuests: {$guests}\n"
                 . "Package: {$package['package_name']} - {$package['guest_range']}\n"
                 . "Please verify my mobile number and share the quotation.";
        $waLink = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
        $success = 'Your enquiry is saved. Reference: ' . $bookingReference . '. Send it on WhatsApp to verify your mobile number and receive the quotation.';
    }
}

$bookedByDate = [];
if ($ready) {
    $start = $month->format('Y-m-01');
    $end = $month->format('Y-m-t');
    $stmt = db()->prepare("SELECT event_date, booking_status FROM event_bookings WHERE event_date BETWEEN ? AND ? AND booking_status <> 'cancelled'");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll() as $row) {
        $bookedByDate[$row['event_date']] = in_array($row['booking_status'], ['confirmed', 'event_happening', 'event_over'], true) ? 'confirmed' : 'hold';
    }
}

$firstDay = $month->modify('first day of this month');
$daysInMonth = (int) $firstDay->format('t');
$startOffset = ((int) $firstDay->format('N')) - 1;
$prevMonth = $month->modify('-1 month');
$nextMonth = $month->modify('+1 month');
$selectedLabel = 'Select an available date';
if ($selectedDate) {
    $selectedObject = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
    if ($selectedObject) {
        $selectedLabel = $selectedObject->format('d M Y');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Availability | <?php echo h(SITE_NAME); ?></title>
<link rel="icon" href="assets/logo.jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--leaf:#2e6b58;--gold:#d9a050;--paper:#f8f3e8;--ink:#1b2a24;--muted:#65736c;--line:rgba(20,47,40,.14);--cream:#e4dac3;--green:#2e6b58;--yellow:#d9a050;--red:#bd453d;--gray:#918b80;--shadow:0 26px 64px -36px rgba(20,47,40,.62)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:linear-gradient(180deg,#fffaf0 0,#f8f3e8 62%,#eef5ef 100%);color:var(--ink);overflow-x:hidden}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1180px,calc(100% - 36px));margin:auto}
header{position:fixed;top:0;left:0;right:0;z-index:100;padding:18px 0;transition:background .4s ease,padding .4s ease,box-shadow .4s ease}header.scrolled{background:rgba(23,51,43,.92);backdrop-filter:blur(10px);padding:12px 0;box-shadow:0 10px 30px -20px rgba(0,0,0,.4)}.nav{display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:10px}.brand img{height:44px;width:44px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(228,218,195,.28)}.brand span{font-family:'Inter',sans-serif;letter-spacing:.04em;font-size:1.08rem;color:var(--cream)}.nav-links{display:flex;gap:24px;list-style:none;margin:0;padding:0}.nav-links a{color:var(--cream);font-size:.88rem;font-weight:500;position:relative;padding-bottom:4px}.nav-links a:after{content:"";position:absolute;left:0;bottom:0;width:0;height:1px;background:var(--gold);transition:width .3s ease}.nav-links a:hover:after,.nav-links a.active:after{width:100%}.menu-toggle{display:none;width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(23,51,43,.28);color:var(--cream);cursor:pointer;place-items:center}.menu-toggle span,.menu-toggle span:before,.menu-toggle span:after{display:block;width:19px;height:2px;border-radius:2px;background:currentColor;content:"";transition:transform .3s ease,opacity .3s ease}.menu-toggle span:before{transform:translateY(-7px)}.menu-toggle span:after{transform:translateY(5px)}.menu-toggle.is-open span{background:transparent}.menu-toggle.is-open span:before{transform:translateY(2px) rotate(45deg)}.menu-toggle.is-open span:after{transform:translateY(0) rotate(-45deg)}.menu-close-row{display:none}.menu-close{width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(255,255,255,.06);color:var(--cream);font-size:1.7rem;line-height:1;cursor:pointer}.nav-backdrop{position:fixed;inset:0;z-index:99;background:rgba(8,28,23,.54);opacity:0;pointer-events:none;transition:opacity .3s ease}.nav-backdrop.open{opacity:1;pointer-events:auto}
.hero{padding:120px 0 30px;background:linear-gradient(90deg,rgba(20,47,40,.9),rgba(20,47,40,.58)),url('assets/hero-nature-event-optimized.jpg') center/cover;color:#fff}.eyebrow{font-size:.78rem;letter-spacing:.13em;text-transform:uppercase;color:#f1cf8f;font-weight:800}.hero h1{font-family:'Inter',sans-serif;font-size:clamp(2.35rem,5vw,4.5rem);line-height:1;margin:8px 0 12px}.hero p{max-width:620px;color:rgba(255,255,255,.88);line-height:1.7}
.steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:26px;max-width:760px}.step{display:flex;gap:12px;align-items:center;border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:14px;background:rgba(255,255,255,.1);color:#fff}.step strong{font-family:'Inter',sans-serif;color:#f1cf8f}.step span{display:block;font-weight:800}.step small{display:block;color:rgba(255,255,255,.68)}
.screen{padding:30px 0 74px}.flow{display:grid;grid-template-columns:1fr;gap:22px;align-items:start}.panel{background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.84);border-radius:8px;box-shadow:var(--shadow);padding:24px}.panel h2{font-family:'Inter',sans-serif;color:var(--deep);font-size:1.7rem;margin:0}.panel p{color:var(--muted);line-height:1.65}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:20px}.field-group{grid-column:1/-1;border:1px solid var(--line);border-radius:8px;background:#fffdf8;padding:16px}.field-group-title{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}.field-group-title strong{color:var(--deep);font-family:'Inter',sans-serif;font-size:1.1rem}.field-group-title span{color:var(--muted);font-size:.82rem}.field-group .form-grid{margin-top:0}.field-group label.full{grid-column:auto}.full{grid-column:1/-1}label{display:block;font-size:.78rem;font-weight:800;color:#4f5d56}input,select,textarea{width:100%;margin-top:7px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);padding:0 13px;font:inherit}input,select{height:48px}textarea{min-height:96px;padding:12px 13px;resize:vertical}.hint{display:block;font-size:.82rem;color:var(--muted);margin-top:6px}.same-number{display:flex;align-items:center;gap:7px;margin-top:7px;color:var(--muted);font-size:.82rem;font-weight:800}.same-number input{width:auto;height:auto;margin:0}.selected-date{display:flex;justify-content:space-between;gap:14px;align-items:center;border:1px solid var(--line);border-radius:8px;background:#fbfaf5;padding:14px;margin-top:16px}.selected-date strong{color:var(--deep)}.btn{border:0;border-radius:999px;background:var(--deep);color:#fff;padding:12px 18px;font-weight:800;cursor:pointer;display:inline-flex;justify-content:center;align-items:center;text-align:center}.btn.gold{background:var(--gold);color:#25190b}.btn.full{width:100%}.msg{padding:13px 14px;border-radius:8px;margin-top:14px;line-height:1.45}.err{background:#fae2de;color:#9a2f27}.ok{background:#e4f4ec;color:#176244}.terms-check{display:flex;gap:10px;align-items:flex-start}.terms-check input{width:auto;margin-top:4px}.terms-check a{text-decoration:underline;color:var(--deep)}
.side-card{position:sticky;top:96px}.price-box{margin-top:16px;border-radius:8px;background:var(--deep);color:var(--cream);padding:18px}.price-box strong{display:block;color:#fff;font-size:1.15rem}.price-box span{display:block;margin-top:6px;color:rgba(228,218,195,.76);line-height:1.5}.price-value{font-family:'Inter',sans-serif;font-size:2rem;color:#fff;margin-top:12px}.lock{margin-top:12px;color:#f1cf8f;font-weight:800}
.calendar{margin-top:20px;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}.cal-head{display:flex;justify-content:space-between;align-items:center;gap:14px;background:var(--deep);color:#fff;padding:18px}.cal-head h2{font-family:'Inter',sans-serif;margin:3px 0 0;font-size:1.9rem}.arrows{display:flex;gap:8px}.arrows a{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:1.3rem}.cal-body{padding:14px}.week,.days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}.week{margin-bottom:8px}.week span{text-align:center;font-size:.76rem;font-weight:900;color:#68766f}.day{min-height:76px;border:1px solid var(--line);border-radius:8px;background:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:9px;position:relative}.day.empty{background:transparent;border:0}.day.available{background:#eef8f1;border-color:rgba(46,107,88,.26)}.day.hold,.day.confirmed{background:#fff4d8;color:#6f4b00;border-color:#e6c879}.day.past{background:#f1e5d6;color:#8a7356;border-color:#ddc8ad}.day.selected{outline:4px solid var(--gold);box-shadow:0 0 0 5px rgba(217,160,80,.18),0 18px 30px -24px rgba(20,47,40,.85)}.day.available:hover{transform:translateY(-3px);box-shadow:0 14px 28px -22px rgba(46,107,88,.8)}.day .num{font-weight:900}.day .tag{font-size:.62rem;font-weight:900;text-transform:uppercase}.available .tag{color:var(--green)}.hold .tag,.confirmed .tag{color:#6f4b00}.past .tag{color:#8a7356}.legend{display:flex;flex-wrap:wrap;gap:14px;padding:0 14px 16px;color:#5e6d66;font-size:.9rem}.dot{width:12px;height:12px;border-radius:50%;display:inline-block;margin-right:7px}.green{background:var(--green)}.yellow{background:#d9a050}.red{background:var(--red)}.gray{background:#c9aa84}.calendar-note{padding:0 14px 16px;color:var(--muted);font-size:.9rem}
@media(max-width:980px){.flow{grid-template-columns:1fr}.side-card{position:static}.steps{grid-template-columns:1fr}}@media(max-width:860px){.nav-links{position:fixed;top:0;right:0;z-index:101;height:100vh;width:min(320px,86vw);background:#17332b;flex-direction:column;padding:100px 32px 32px;transform:translateX(100%);transition:transform .4s ease;gap:22px;box-shadow:-24px 0 50px -28px rgba(0,0,0,.65)}.nav-links.open{transform:translateX(0)}.menu-toggle{display:grid}.menu-close-row{display:flex;justify-content:flex-end;position:absolute;top:22px;right:24px}body.menu-open{overflow:hidden}}@media(max-width:640px){.container,.wrap{width:min(100% - 26px,1180px)}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.hero{padding-top:116px}.day{min-height:58px;padding:6px}.week,.days{gap:5px}.day .tag{display:none}.panel{padding:18px}.cal-head{align-items:flex-start;flex-direction:column}}
	.field-warning{display:none;margin-top:8px;border-radius:8px;padding:10px 12px;font-size:.88rem;line-height:1.45}.field-warning.show{display:block}.field-warning.err{background:#fae2de;color:#9a2f27}.field-warning.ok{background:#e4f4ec;color:#176244}.package-summary{grid-column:1/-1;border:1px solid var(--line);border-radius:8px;background:#fbfaf5;padding:14px;display:block}.package-summary strong{display:block;color:var(--deep)}.package-summary span{display:block;color:var(--muted);font-size:.9rem;margin-top:4px}.discount-line{display:none;color:#176244;font-weight:800;font-size:.88rem;line-height:1.45;margin-top:7px}.discount-line.show{display:block}.venue-facts{display:grid;gap:10px;margin-top:16px}.venue-facts div{border:1px solid var(--line);border-radius:8px;background:#fbfaf5;padding:12px}.venue-facts strong{display:block;color:var(--deep)}.venue-facts span{display:block;color:var(--muted);font-size:.88rem;margin-top:3px}
		.booking-terms{border:1px solid var(--line);border-radius:8px;background:#fffdf8;padding:10px;display:grid;gap:7px}.booking-terms .field-group-title{margin-bottom:0}.booking-terms .terms-check{display:grid;grid-template-columns:auto 1fr;gap:8px;align-items:center;border:1px solid rgba(20,47,40,.1);border-radius:8px;background:#fff;padding:8px 10px;min-height:42px}.booking-terms .terms-check input{width:14px;height:14px;margin:0}.booking-terms .terms-check strong{display:block;color:var(--deep);font-size:.82rem;margin-bottom:0}.booking-terms .terms-check span{color:var(--muted);font-size:.82rem;line-height:1.35}.terms-link{width:max-content;color:var(--deep);font-size:.9rem;font-weight:800;text-decoration:underline}
	.reference-check{display:grid;gap:10px;margin-top:16px;border:1px solid var(--line);border-radius:8px;background:#fffdf8;padding:14px}.reference-check .btn{border-radius:8px}.reference-result{display:grid;gap:4px;margin-top:12px;border-radius:8px;padding:12px;line-height:1.45}.reference-result strong{color:var(--deep)}.reference-result span{color:inherit;font-size:.9rem}
	.nav-links a:hover,.nav-links a.active{color:var(--gold);font-weight:800}
/* Wide booking workspace */
.screen{width:min(1540px,calc(100% - 32px));max-width:none}
@media(min-width:1251px){
  .flow{grid-template-columns:1fr;gap:18px}
  .flow>.panel:first-child{padding:28px}
  .field-group:first-child>.form-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
  .field-group:nth-child(2)>.form-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
  .day{min-height:88px}
}
#submitEnquiry{min-height:54px;border-radius:4px;font-size:1rem;letter-spacing:0}
#submitEnquiry:not(:disabled):hover{background:#f1cf8f;transform:translateY(-1px)}
#submitEnquiry:disabled{cursor:not-allowed;opacity:.55}
@media(max-width:980px){.screen{width:min(100% - 26px,900px)}}
</style>
</head>
<body>
<?php $publicHeaderActive = 'availability'; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>

<section class="hero">
  <div class="wrap">
    <div class="eyebrow">Enquire</div>
    <h1>Check availability</h1>
    <p>One form. Pricing appears once your mobile number is verified.</p>
  </div>
</section>

<main class="wrap screen">
  <?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql in phpMyAdmin to enable bookings.</div><?php endif; ?>
  <section class="flow">
    <div class="panel">
      <h2>Your enquiry</h2>
      <?php if ($formError): ?><div class="msg err"><?php echo h($formError); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="msg ok"><?php echo h($success); ?></div><p style="margin-top:12px"><a class="btn gold" href="<?php echo h($waLink); ?>" target="_blank" rel="noopener">Verify on WhatsApp</a></p><?php endif; ?>

	      <form method="POST" action="#enquiry" id="enquiry">
	        <input type="hidden" name="event_date" id="eventDate" value="<?php echo h($selectedDate); ?>">
	        <div class="form-grid">
	          <div class="field-group">
	            <div class="field-group-title"><strong>Contact details</strong><span>We use this to call and verify on WhatsApp.</span></div>
	            <div class="form-grid">
		              <label>Full name (required)<input name="name" required value="<?php echo h($name); ?>" placeholder="Sai Kumar"></label>
			              <label>Mobile number (required)<input type="tel" name="phone" id="phoneInput" required inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="tel" value="<?php echo h($phone); ?>" placeholder="10-digit mobile"><span class="field-warning err" id="contactWarning"></span></label>
					              <label>WhatsApp number (optional)<input type="tel" name="whatsapp_number" id="whatsappInput" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="tel" value="<?php echo h($whatsapp); ?>" placeholder="WhatsApp number"><span class="same-number"><input type="checkbox" id="sameAsMobile"> Same as mobile number</span><span class="field-warning err" id="whatsappWarning"></span></label>
				              <label>Email (required)<input type="email" name="email" id="emailInput" required autocomplete="email" value="<?php echo h($email); ?>" placeholder="name@example.com"><span class="field-warning err" id="emailWarning"></span></label>
			              <label>PIN code (required)<input type="tel" name="pin_code" id="pinCode" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="<?php echo h($pin); ?>" placeholder="530045"><span class="hint" id="pinHint">Area, city and state auto-fill after 6 digits.</span></label>
		              <label>Area<input name="area_from" id="areaFrom" list="areaOptions" value="<?php echo h($area); ?>" placeholder="Kommadi"><datalist id="areaOptions"></datalist></label>
			              <label>City / district<input name="city_district" id="cityDistrict" value="<?php echo h($city); ?>" placeholder="Visakhapatnam"></label>
			              <label>State<input name="client_state" id="clientState" value="<?php echo h($state); ?>" placeholder="Andhra Pradesh"></label>
				              <label class="full">Referred by reference number<input name="referred_by_reference" value="<?php echo h($referredBy); ?>" placeholder="ANT-RAM-1234 or 10-digit phone"><span class="hint">Optional. Enter an eligible member reference<?php echo $referralEligibilityDays > 0 ? ' within ' . (int) $referralEligibilityDays . ' days of their event' : ''; ?>.</span></label>
		            </div>
	          </div>
	          <div class="field-group">
	            <div class="field-group-title"><strong>Event details</strong><span>Date, occasion, guests and package.</span></div>
	            <div class="form-grid">
			              <label class="full">Event date (required)<input type="date" name="event_date_visible" id="eventDateVisible" min="<?php echo h($today->format('Y-m-d')); ?>" value="<?php echo h($selectedDate); ?>"><span class="hint" id="eventDateHint">Choose a date, or pick one from the month below.</span><span class="field-warning err" id="eventDateWarning"></span></label>
	              <label>Occasion (required)<select name="event_type" required><option value="">Select an occasion</option><?php foreach (['Wedding','Reception','Engagement','Haldi','Mehendi','Sangeet','Birthday','Anniversary','Family celebration','Corporate gathering'] as $type): ?><option value="<?php echo h($type); ?>" <?php echo $eventType === $type ? 'selected' : ''; ?>><?php echo h($type); ?></option><?php endforeach; ?></select></label>
		              <label>Expected guests (required)<input type="number" min="1" max="<?php echo (int) $maxCapacity; ?>" name="guests" id="guestInput" required value="<?php echo h($guests); ?>" placeholder="300"><span class="hint">Maximum <?php echo (int) $maxCapacity; ?> guests allowed.</span><span class="field-warning err" id="guestWarning"></span></label>
	              <label>Package<select name="package_id" id="packageSelect" required><option value="">Auto selected by guests</option><?php foreach ($packages as $package): ?><?php $rule = $packageRules[(int) $package['id']]; ?><option value="<?php echo (int) $package['id']; ?>" data-description="<?php echo h($package['discount_reason'] ?? ''); ?>" data-range="<?php echo h($package['guest_range']); ?>" data-min="<?php echo (int) $rule['min']; ?>" data-max="<?php echo (int) $rule['max']; ?>" <?php echo $packageId === (int) $package['id'] ? 'selected' : ''; ?>><?php echo h($package['package_name'] . ' - ' . $package['guest_range']); ?></option><?php endforeach; ?></select><span class="hint">This updates automatically from guest count.</span><span class="field-warning err" id="packageWarning"></span></label>
		            </div>
	          </div>
	          <label class="full">Anything else we should know?<textarea name="note" rows="4" placeholder="Timing, rituals, food counters, decor notes"><?php echo h($note); ?></textarea></label>
			          <div class="full booking-terms">
			            <?php foreach ($bookingRequiredTerms as $term): ?>
		              <label class="terms-check">
		                <input type="checkbox" name="accept_terms[]" value="<?php echo (int) $term['id']; ?>" required <?php echo in_array((int) $term['id'], $acceptedTermIds, true) ? 'checked' : ''; ?>>
			                <span><?php echo h($term['term_text']); ?></span>
		              </label>
		            <?php endforeach; ?>
		            <a class="terms-link" href="terms.php" target="_blank" rel="noopener">View full terms and conditions</a>
		          </div>
		        </div>

        <div class="calendar">
          <div class="cal-head">
            <div><div class="eyebrow">Event date</div><h2><?php echo h($firstDay->format('F Y')); ?></h2></div>
            <div class="arrows">
              <?php if ($prevMonth >= $minMonth): ?><a href="?month=<?php echo h($prevMonth->format('Y-m')); ?>">&lsaquo;</a><?php endif; ?>
              <a href="?month=<?php echo h($nextMonth->format('Y-m')); ?>">&rsaquo;</a>
            </div>
          </div>
          <div class="cal-body">
            <div class="week"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
            <div class="days">
              <?php for ($i = 0; $i < $startOffset; $i++): ?><span class="day empty"></span><?php endfor; ?>
              <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                  $date = $firstDay->setDate((int) $firstDay->format('Y'), (int) $firstDay->format('m'), $day);
                  $dateValue = $date->format('Y-m-d');
                  $isPast = $date < $today;
                  $bookedStatus = $bookedByDate[$dateValue] ?? '';
                  $class = $isPast ? 'past' : ($bookedStatus ?: 'available');
                  $label = $isPast ? 'Blocked' : ($bookedStatus ? 'Booked' : 'Available');
                ?>
                <?php if ($class === 'available'): ?>
                  <a class="day available<?php echo $selectedDate === $dateValue ? ' selected' : ''; ?>" data-date="<?php echo h($dateValue); ?>" href="?month=<?php echo h($month->format('Y-m')); ?>&date=<?php echo h($dateValue); ?>#enquiry"><span class="num"><?php echo $day; ?></span><span class="tag">Available</span></a>
                <?php else: ?>
                  <span class="day <?php echo h($class); ?>"><span class="num"><?php echo $day; ?></span><span class="tag"><?php echo h($label); ?></span></span>
                <?php endif; ?>
              <?php endfor; ?>
            </div>
          </div>
          <div class="legend"><span><i class="dot green"></i>Available</span><span><i class="dot yellow"></i>Booked</span><span><i class="dot gray"></i>Past / blocked</span></div>
          <div class="calendar-note">One event is held per day, so a booking holds the whole date.</div>
        </div>

        <div class="selected-date"><span id="selectedDateLabel"><?php echo h($selectedLabel); ?></span><strong id="selectedDateState"><?php echo $selectedDate ? 'Selected' : 'Select an available date to continue'; ?></strong></div>
        <p style="margin-top:18px"><button class="btn gold full" id="submitEnquiry" type="submit" name="enquire" value="1" <?php echo !$ready || !$selectedDate ? 'disabled' : ''; ?>>Submit Enquiry</button></p>
      </form>
    </div>
	  </section>
</main>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
<?php include __DIR__ . '/includes/chatbot.php'; ?>

<script>
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 40));
  const menuToggle = document.getElementById('menuToggle');
  const menuClose = document.getElementById('menuClose');
  const navLinks = document.getElementById('navLinks');
  const navBackdrop = document.getElementById('navBackdrop');
  const setMenuOpen = (isOpen) => {
    navLinks.classList.toggle('open', isOpen);
    navBackdrop.classList.toggle('open', isOpen);
    menuToggle.classList.toggle('is-open', isOpen);
    menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    document.body.classList.toggle('menu-open', isOpen);
  };
  menuToggle.addEventListener('click', () => setMenuOpen(!navLinks.classList.contains('open')));
  menuClose.addEventListener('click', () => setMenuOpen(false));
  navBackdrop.addEventListener('click', () => setMenuOpen(false));
  navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenuOpen(false)));

  const enquiryForm = document.getElementById('enquiry');
  const eventDate = document.getElementById('eventDate');
  const eventDateVisible = document.getElementById('eventDateVisible');
  const eventDateHint = document.getElementById('eventDateHint');
  const eventDateWarning = document.getElementById('eventDateWarning');
  const selectedDateLabel = document.getElementById('selectedDateLabel');
  const selectedDateState = document.getElementById('selectedDateState');
	  const submitEnquiry = document.getElementById('submitEnquiry');
	  const requiredTerms = Array.from(document.querySelectorAll('input[name="accept_terms[]"]'));
	  const displayedMonth = '<?php echo h($month->format('Y-m')); ?>';
	  const availabilityReady = <?php echo $ready ? 'true' : 'false'; ?>;
	  const termsAccepted = () => requiredTerms.length === 0 || requiredTerms.every(term => term.checked);
  const updateSubmitState = () => {
    submitEnquiry.disabled = !availabilityReady || !eventDate.value || !termsAccepted();
  };
  const setEventDateWarning = (message = '') => {
    eventDateWarning.textContent = message;
    eventDateWarning.classList.toggle('show', message !== '');
  };
  const savedForm = sessionStorage.getItem('ananthaEnquiryForm');
  if (savedForm) {
    try {
      const savedFields = JSON.parse(savedForm);
      savedFields.forEach(({ name, value, checked }) => {
        if (name === 'event_date' || name === 'event_date_visible') return;
        const field = enquiryForm.elements.namedItem(name);
        if (!field || field instanceof RadioNodeList) return;
        if (field.type === 'checkbox' || field.type === 'radio') field.checked = checked;
        else field.value = value;
      });
    } catch (error) {
      // Ignore stale browser data and continue with the server-rendered form.
    }
    sessionStorage.removeItem('ananthaEnquiryForm');
  }
  const saveEnquiryBeforeMonthChange = () => {
    const fields = Array.from(enquiryForm.elements).filter(field => field.name && field.name !== 'event_date' && field.name !== 'event_date_visible');
    sessionStorage.setItem('ananthaEnquiryForm', JSON.stringify(fields.map(field => ({ name: field.name, value: field.value, checked: field.checked }))));
  };
  const clearSelectedDate = (message = 'Select an available date to continue', showWarning = false) => {
    eventDate.value = '';
    document.querySelectorAll('.day.available.selected').forEach(day => day.classList.remove('selected'));
	    selectedDateLabel.textContent = 'Select an available date';
	    selectedDateState.textContent = message;
	    setEventDateWarning(showWarning ? message : '');
		    updateSubmitState();
	  };
  const selectEventDate = (value, updateUrl = true) => {
    const calendarDay = document.querySelector('.day.available[data-date="' + value + '"]');
	    if (!calendarDay) {
	      clearSelectedDate('This date is blocked, on hold or booked', true);
	      eventDateHint.textContent = 'Choose a green available date from the calendar.';
	      setEventDateWarning('Please choose an available date shown in the calendar.');
	      return false;
	    }
    eventDate.value = value;
    eventDateVisible.value = value;
    document.querySelectorAll('.day.available.selected').forEach(day => day.classList.remove('selected'));
    calendarDay.classList.add('selected');
    const date = new Date(value + 'T00:00:00');
    selectedDateLabel.textContent = date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
	    selectedDateState.textContent = 'Selected';
	    eventDateHint.textContent = 'This date is selected in the calendar below.';
	    setEventDateWarning('');
		    updateSubmitState();
    if (updateUrl) history.replaceState(null, '', '?month=' + displayedMonth + '&date=' + value + '#enquiry');
    return true;
  };
	  document.querySelectorAll('.day.available[data-date]').forEach(day => {
	    day.addEventListener('click', event => {
	      event.preventDefault();
	      selectEventDate(day.dataset.date);
		  });
	  });
	  requiredTerms.forEach(term => term.addEventListener('change', updateSubmitState));
  eventDateVisible.addEventListener('change', () => {
    const value = eventDateVisible.value;
    if (!value) {
      clearSelectedDate();
      eventDateHint.textContent = 'Choose a date, or pick one from the month below.';
      return;
    }
    const chosenMonth = value.slice(0, 7);
    if (chosenMonth !== displayedMonth) {
      saveEnquiryBeforeMonthChange();
      window.location.href = '?month=' + chosenMonth + '&date=' + value + '#enquiry';
      return;
    }
    selectEventDate(value);
  });
	  if (eventDate.value && eventDate.value.slice(0, 7) === displayedMonth) selectEventDate(eventDate.value, false);
	  updateSubmitState();

	  const packageSelect = document.getElementById('packageSelect');
	  const guestInput = document.getElementById('guestInput');
	  const maxCapacity = <?php echo (int) $maxCapacity; ?>;
  const guestWarning = document.getElementById('guestWarning');
	  const packageWarning = document.getElementById('packageWarning');
		  const paintPackage = () => {};
  const setWarning = (node, text, type = 'err') => {
    node.textContent = text;
    node.classList.remove('err', 'ok', 'show');
    if (text) node.classList.add(type, 'show');
  };
  const choosePackageByGuests = () => {
    const guests = Number(guestInput.value || 0);
    setWarning(guestWarning, '');
    setWarning(packageWarning, '');
    if (!guests) {
      packageSelect.value = '';
      paintPackage();
      return;
    }
	    if (guests > maxCapacity) {
	      packageSelect.value = '';
	      setWarning(guestWarning, 'Maximum capacity is ' + maxCapacity + ' guests. Please enter ' + maxCapacity + ' or less.');
      setWarning(packageWarning, 'You are out of the venue package limit.');
      paintPackage();
      return;
    }
    let matched = null;
    Array.from(packageSelect.options).forEach((option) => {
      if (!option.value) return;
      const min = Number(option.dataset.min || 0);
      const max = Number(option.dataset.max || 0);
      if (guests >= min && guests <= max) {
        const matchedMin = matched ? Number(matched.dataset.min || 0) : 0;
        const matchedMax = matched ? Number(matched.dataset.max || 0) : 0;
        if (!matched || (guests === min && min > matchedMin) || (guests !== min && max < matchedMax) || (max === matchedMax && min > matchedMin)) {
          matched = option;
        }
      }
    });
    if (matched) {
      packageSelect.value = matched.value;
      setWarning(packageWarning, 'Package selected for ' + guests + ' guests.', 'ok');
    } else {
      packageSelect.value = '';
      setWarning(packageWarning, 'No package found for this guest count. Please contact us.');
    }
    paintPackage();
  };
  const validateManualPackage = () => {
    const guests = Number(guestInput.value || 0);
    const option = packageSelect.options[packageSelect.selectedIndex];
    setWarning(packageWarning, '');
    if (!guests || !option || !option.value) {
      paintPackage();
      return;
    }
    const min = Number(option.dataset.min || 0);
    const max = Number(option.dataset.max || 0);
    if (guests < min || guests > max) {
      setWarning(packageWarning, 'This package is for ' + min + ' to ' + max + ' guests. Please select the suggested package.');
    } else {
      setWarning(packageWarning, 'Package selected for ' + guests + ' guests.', 'ok');
    }
    paintPackage();
  };
	  guestInput.addEventListener('input', choosePackageByGuests);
	  packageSelect.addEventListener('change', validateManualPackage);
	  choosePackageByGuests();

		  const pinCode = document.getElementById('pinCode');
		  const phoneInput = document.getElementById('phoneInput');
		  const whatsappInput = document.getElementById('whatsappInput');
		  const emailInput = document.getElementById('emailInput');
		  const contactWarning = document.getElementById('contactWarning');
		  const whatsappWarning = document.getElementById('whatsappWarning');
		  const emailWarning = document.getElementById('emailWarning');
		  const sameAsMobile = document.getElementById('sameAsMobile');
		  const pinHint = document.getElementById('pinHint');
	  const areaFrom = document.getElementById('areaFrom');
	  const areaOptions = document.getElementById('areaOptions');
	  const cityDistrict = document.getElementById('cityDistrict');
	  const clientState = document.getElementById('clientState');
	  let lastPinLookup = '';
	  let pinLookupRequest = 0;
	  const keepDigitsOnly = (input, maxLength = 10) => {
	    if (!input) return;
	    input.value = input.value.replace(/\D+/g, '').slice(0, maxLength);
	  };
	  const syncWhatsappFromMobile = () => {
	    keepDigitsOnly(phoneInput);
	    if (sameAsMobile && sameAsMobile.checked) {
	      whatsappInput.value = phoneInput.value;
	    }
	  };
	  phoneInput.addEventListener('input', syncWhatsappFromMobile);
	  whatsappInput.addEventListener('input', () => {
	    keepDigitsOnly(whatsappInput);
	    if (sameAsMobile && whatsappInput.value !== phoneInput.value) {
	      sameAsMobile.checked = false;
	    }
	  });
		  if (sameAsMobile) {
		    sameAsMobile.addEventListener('change', () => {
		      if (sameAsMobile.checked) {
		        keepDigitsOnly(phoneInput);
		        whatsappInput.value = phoneInput.value;
		      }
		    });
		  }
			  if (phoneInput && whatsappInput && emailInput && contactWarning && whatsappWarning && emailWarning) {
			    let contactCheckTimer = 0;
			    let contactCheckRun = 0;
			    const setFieldWarning = (element, input, message = '') => {
			      element.textContent = message;
			      element.classList.toggle('show', Boolean(message));
			      input.setCustomValidity(message);
			    };
			    const setContactWarnings = (fields = {}) => {
			      setFieldWarning(contactWarning, phoneInput, fields.phone || '');
			      setFieldWarning(whatsappWarning, whatsappInput, fields.whatsapp_number || '');
			      setFieldWarning(emailWarning, emailInput, fields.email || '');
			    };
			    const checkDuplicateContact = async () => {
		      const phone = phoneInput.value.trim();
		      const whatsapp = whatsappInput.value.trim();
			      const email = emailInput.value.trim();
			      if (phone.length < 10 && whatsapp.length < 10 && !email.includes('@')) {
			        setContactWarnings();
			        return;
			      }
		      const run = ++contactCheckRun;
		      const params = new URLSearchParams({
		        phone,
		        whatsapp_number: whatsapp,
		        email
		      });
		      try {
		        const response = await fetch('api/contact-check.php?' + params.toString(), {
		          headers: { 'X-Requested-With': 'fetch' }
		        });
			        const data = await response.json();
			        if (run !== contactCheckRun) return;
			        setContactWarnings(data.fields || {});
			      } catch (error) {
			        if (run === contactCheckRun) setContactWarnings();
			      }
		    };
		    const scheduleContactCheck = () => {
		      clearTimeout(contactCheckTimer);
		      contactCheckTimer = setTimeout(checkDuplicateContact, 260);
		    };
		    phoneInput.addEventListener('input', scheduleContactCheck);
		    whatsappInput.addEventListener('input', scheduleContactCheck);
		    emailInput.addEventListener('input', scheduleContactCheck);
		    emailInput.addEventListener('change', scheduleContactCheck);
		    scheduleContactCheck();
		  }
		  const renderAreaOptions = (areas) => {
	    areaOptions.innerHTML = '';
	    areas.forEach((area) => {
	      const option = document.createElement('option');
	      option.value = area;
	      areaOptions.appendChild(option);
	    });
	  };
	  const lookupPin = async () => {
	    keepDigitsOnly(pinCode, 6);
	    const pin = pinCode.value;
	    if (pin.length !== 6) {
	      lastPinLookup = '';
	      renderAreaOptions([]);
	      pinHint.textContent = 'Area, city and state auto-fill after 6 digits.';
	      return;
	    }
	    if (pin === lastPinLookup) return;
	    lastPinLookup = pin;
	    const requestId = ++pinLookupRequest;
	    pinHint.textContent = 'Fetching area details...';
	    try {
		      const response = await fetch('api/pincode.php?pin=' + encodeURIComponent(pin), {
		        headers: { Accept: 'application/json' },
		        cache: 'no-store'
		      });
		      const data = await response.json();
		      if (requestId !== pinLookupRequest) return;
		      if (!response.ok || !data.ok) throw new Error(data.error || 'No PIN data');
		      const areas = Array.isArray(data.areas) ? data.areas : [];
		      renderAreaOptions(areas);
		      areaFrom.value = data.area || areas[0] || '';
		      cityDistrict.value = data.district || '';
		      clientState.value = data.state || '';
		      pinHint.textContent = areas.length > 1 ? 'Area filled. You can choose another suggestion if needed.' : 'Area details filled from PIN code.';
	    } catch (error) {
	      if (requestId !== pinLookupRequest) return;
	      lastPinLookup = '';
	      renderAreaOptions([]);
	      areaFrom.value = '';
	      cityDistrict.value = '';
	      clientState.value = '';
	      pinHint.textContent = error.message || 'Could not fetch PIN details. Please enter city and state manually.';
	    }
	  };
	  pinCode.addEventListener('input', lookupPin);
  lookupPin();
</script>
</body>
</html>



