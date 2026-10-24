<?php
require_once __DIR__ . '/../includes/admin_layout.php';
admin_page_start('Booking Details', 'bookings');
$ready = db_ready();
if ($ready) {
    ensure_booking_enquiry_schema();
    ensure_payment_verification_schema();
    ensure_package_schema();
    ensure_event_inspection_schema();
    ensure_referral_schema();
    ensure_pricing_history_schema();
}

$bookingId = (int) ($_GET['id'] ?? 0);
$booking = null;
$payments = [];
$inspection = null;
$penalties = [];
$referrals = [];
$referrerBooking = null;
$packages = $ready ? active_packages() : [];
$packageRules = [];
foreach ($packages as $package) {
    [$minGuests, $maxGuests] = package_guest_bounds((string) $package['guest_range'], (int) rate_setting_value('maximum_capacity', 300));
    $packageRules[(int) $package['id']] = ['min' => $minGuests, 'max' => $maxGuests];
}
$referralEligibilityDays = $ready ? referral_eligibility_days() : 60;
$defaultReferralBonus = $ready ? rate_setting_value('referral_bonus_amount', 2000) : 2000;
$defaultNewMemberBonus = $ready ? rate_setting_value('referral_new_member_bonus', 2000) : 2000;
$referralBonusCap = $ready ? rate_setting_value('referral_bonus_cap', 5000) : 5000;
$referralMaxCount = $ready ? (int) rate_setting_value('referral_max_count', 5) : 5;
$paidTotal = 0.0;
$paymentHistoryTotal = 0.0;
$penaltyTotal = 0.0;
$acceptedTerms = [];
$today = new DateTimeImmutable('today');
$eventDateMin = $today->format('Y-m-d');

if ($ready && $bookingId > 0) {
    $stmt = db()->prepare("SELECT b.*, p.package_name, p.guest_range FROM event_bookings b LEFT JOIN event_packages p ON p.id=b.package_id WHERE b.id=?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch() ?: null;
    if (!$booking) {
        set_flash('error', 'Booking not found.');
        redirect('bookings.php');
    }

    $stmt = db()->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY paid_on DESC, id DESC");
    $stmt->execute([$bookingId]);
    $payments = $stmt->fetchAll();
    foreach ($payments as $payment) {
        if (($payment['verification_status'] ?? '') !== 'rejected') {
            $paymentHistoryTotal += (float) $payment['amount'];
        }
    }

    $stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=? AND verification_status='verified'");
    $stmt->execute([$bookingId]);
    $paidTotal = (float) $stmt->fetchColumn();

    $stmt = db()->prepare("SELECT * FROM event_inspections WHERE booking_id=?");
    $stmt->execute([$bookingId]);
    $inspection = $stmt->fetch() ?: null;

    $stmt = db()->prepare("SELECT * FROM event_penalties WHERE booking_id=? ORDER BY created_at DESC, id DESC");
    $stmt->execute([$bookingId]);
    $penalties = $stmt->fetchAll();
    foreach ($penalties as $penalty) {
        $penaltyTotal += (float) $penalty['amount'];
    }

    $acceptedTerms = json_decode((string) ($booking['accepted_terms_snapshot'] ?? ''), true);
    if (!is_array($acceptedTerms)) {
        $acceptedTerms = [];
    }

    if (!empty($booking['booking_reference'])) {
        $stmt = db()->prepare('SELECT b.id,b.booking_reference,b.client_name,b.event_date,b.event_type,b.referral_eligible,b.referral_referrer_bonus,b.referral_referred_bonus FROM event_bookings b JOIN referral_uses u ON u.referred_booking_id=b.id WHERE u.referrer_booking_id=? AND u.status="applied" ORDER BY b.created_at DESC,b.id DESC');
        $stmt->execute([$bookingId]);
        $referrals = $stmt->fetchAll();
    }
    if (!empty($booking['referred_by_reference'])) {
        $stmt = db()->prepare('SELECT b.id,b.booking_reference,b.client_name,b.event_date,b.event_type FROM referral_codes c JOIN event_bookings b ON b.id=c.booking_id WHERE c.code=? LIMIT 1');
        $stmt->execute([$booking['referred_by_reference']]);
        $referrerBooking = $stmt->fetch() ?: null;
    }
}

$memberReferralCode = $ready && $bookingId > 0 ? member_referral_code($bookingId) : null;
$isEdit = (bool) $booking;
if ($isEdit && !empty($booking['event_date']) && $booking['event_date'] < $eventDateMin) {
    $eventDateMin = $booking['event_date'];
}
$selectedBookingStatus = $booking['booking_status'] ?? 'pending';
if (in_array($selectedBookingStatus, ['proposal_sent', 'event_happening'], true)) {
    $selectedBookingStatus = 'confirmed';
}
$selectedPaymentStatus = $booking['payment_status'] ?? 'pending';
if ($selectedPaymentStatus === 'partial_paid') {
    $selectedPaymentStatus = 'advance_paid';
}
$pricing = booking_price_summary($booking);
$savedRateSnapshot=$pricing['rate_snapshot'];
$currentRateCatalog=$ready?booking_rate_catalog():[];
$previewRateCatalog=$savedRateSnapshot['catalog']??$currentRateCatalog;
$extraRateChoices=array_replace($currentRateCatalog['extras']??[],$savedRateSnapshot['catalog']['extras']??[]);
$selectedExtraCharges=[];$savedExtraQuantities=[];
foreach($savedRateSnapshot['items']??[]as$item){if(!in_array($item['key'],['cleaning','security_deposit','electricity'],true)){$selectedExtraCharges[]=$item['key'];$savedExtraQuantities[$item['key']]=$item['quantity'];}}
$pricingHistory=[];
if($ready&&$bookingId>0){$historyStmt=db()->prepare('SELECT * FROM booking_pricing_history WHERE booking_id=? ORDER BY id DESC LIMIT 10');$historyStmt->execute([$bookingId]);$pricingHistory=$historyStmt->fetchAll();}
$quoteTotal = $pricing['net'];
$balance = max(0, $quoteTotal - $paidTotal);
$clientDue = $balance + $penaltyTotal;
$pageTitle = $isEdit ? ($booking['client_name'] . ' Booking') : 'Add New Booking';
$penaltyMessage = '';
$clientWhatsappDigits = '';
if ($isEdit) {
    $clientWhatsappDigits = preg_replace('/\D+/', '', $booking['whatsapp_number'] ?: $booking['phone']);
    if (strlen($clientWhatsappDigits) === 10) {
        $clientWhatsappDigits = '91' . $clientWhatsappDigits;
    }
    $lines = [];
    foreach ($penalties as $penalty) {
        $lines[] = '- ' . $penalty['penalty_title'] . ': Rs ' . number_format((float) $penalty['amount']) . ' - ' . $penalty['penalty_reason'];
    }
    $penaltyMessage = "Hi {$booking['client_name']}, after the event inspection for {$booking['event_type']} on " . date('d M Y', strtotime($booking['event_date'])) . ", these recoverable charges were recorded:\n"
        . ($lines ? implode("\n", $lines) . "\n" : "No penalty items added yet.\n")
        . "Penalty total: Rs " . number_format($penaltyTotal) . "\n"
        . "Pending booking balance: Rs " . number_format($balance,2) . "\n"
        . "Total amount due: Rs " . number_format($clientDue,2) . "\n"
        . "Refundable security deposit is settled after inspection as per venue terms.";
}
?>
<div class="top">
  <div><h1><?php echo h($pageTitle); ?></h1><p><?php echo $isEdit ? 'Update booking information and payments from one full screen.' : 'First save the booking information, then add payment details.'; ?></p><?php if(!empty($booking['returning_customer_booking_id'])): ?><p>Returning customer &middot; <a href="<?php echo h(app_url('booking-form.php','admin')); ?>?id=<?php echo (int)$booking['returning_customer_booking_id']; ?>">View previous booking</a></p><?php endif; ?></div>
  <a class="btn" href="<?php echo h(app_url('bookings.php', 'admin')); ?>">Back to Grid</a>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql in phpMyAdmin first.</div><?php endif; ?>

<?php if ($isEdit): ?>
<section class="booking-overview" aria-label="Booking and payment summary" tabindex="0">
  <div class="booking-overview__item booking-overview__identity"><span>Customer ID</span><strong><?php echo h($booking['phone'] ?? ''); ?></strong></div>
  <div class="booking-overview__item booking-overview__reference"><span>Reference code</span><strong><?php echo h($booking['booking_reference'] ?? ('#' . (int) $bookingId)); ?></strong></div>
  <div class="booking-overview__item"><span>Original total</span><strong>Rs <?php echo h(number_format($pricing['gross'],2)); ?></strong></div>
  <div class="booking-overview__item booking-overview__discount"><span>Discount</span><strong>Rs <?php echo h(number_format($pricing['discount'],2)); ?></strong></div>
  <div class="booking-overview__item"><span>Rate Card charges</span><strong>Rs <?php echo h(number_format($pricing['charges'],2)); ?></strong></div>
  <div class="booking-overview__item booking-overview__total"><span>Grand total <small>(incl. deposit)</small></span><strong>Rs <?php echo h(number_format($quoteTotal,2)); ?></strong></div>
  <div class="booking-overview__item booking-overview__paid"><span>Paid amount</span><strong>Rs <?php echo h(number_format($paidTotal,2)); ?></strong></div>
  <div class="booking-overview__item"><span>Penalties</span><strong>Rs <?php echo h(number_format($penaltyTotal,2)); ?></strong></div>
  <div class="booking-overview__item booking-overview__due"><span>Total due</span><strong>Rs <?php echo h(number_format($clientDue,2)); ?></strong></div>
</section>
<?php endif; ?>

<section class="card tab-screen">
  <input class="tab-input" type="radio" name="booking_tabs" id="tab-info" checked>
  <input class="tab-input" type="radio" name="booking_tabs" id="tab-payments">
	  <input class="tab-input" type="radio" name="booking_tabs" id="tab-inspection">
	  <input class="tab-input" type="radio" name="booking_tabs" id="tab-terms">
	  <input class="tab-input" type="radio" name="booking_tabs" id="tab-referrals">
	  <div class="tab-buttons">
	    <label for="tab-info">Information</label>
	    <label for="tab-payments">Payments</label>
	    <label for="tab-inspection">Inspection</label>
	    <label for="tab-terms">Terms</label>
	    <label for="tab-referrals">Referrals</label>
	  </div>

  <div class="tab-panel info-panel">
    <div class="section-head"><div><h2>Customer & Event Information</h2><p>Fill the details clearly. These details appear in the booking grid.</p></div></div>
    <form class="form-grid friendly-form" method="POST" enctype="multipart/form-data" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
      <input type="hidden" name="action" value="save_booking_details">
      <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
      <label>Customer name<input name="client_name" value="<?php echo h($booking['client_name'] ?? ''); ?>" required></label>
		      <label>Phone number<input name="phone" id="adminPhoneNumber" value="<?php echo h($booking['phone'] ?? ''); ?>" required><span class="field-warning err" id="adminContactWarning"></span></label>
			      <label>WhatsApp number<input name="whatsapp_number" id="adminWhatsappNumber" value="<?php echo h($booking['whatsapp_number'] ?? ''); ?>"><span class="same-number"><input type="checkbox" id="adminSameAsPhone"> Same as phone number</span><span class="field-warning err" id="adminWhatsappWarning"></span></label>
			      <label>Email<input name="email" type="email" id="adminEmailAddress" value="<?php echo h($booking['email'] ?? ''); ?>"><span class="field-warning err" id="adminEmailWarning"></span></label>
	      <label>Date of birth<input name="date_of_birth" type="date" value="<?php echo h($booking['date_of_birth'] ?? ''); ?>"></label>
	      <label>Aadhaar number<input name="aadhaar_number" value="<?php echo h($booking['aadhaar_number'] ?? ''); ?>"></label>
	      <label>Occupation<input name="occupation" value="<?php echo h($booking['occupation'] ?? ''); ?>"></label>
      <label>Event date<input type="date" name="event_date" id="bookingEventDate" min="<?php echo h($eventDateMin); ?>" value="<?php echo h($booking['event_date'] ?? ''); ?>" required><span class="field-warning err" id="bookingEventDateError"></span></label>
      <label>Occasion<input name="event_type" value="<?php echo h($booking['event_type'] ?? ''); ?>"></label>
	      <label>Guests<input type="number" name="guests" id="adminGuestInput" min="1" value="<?php echo h($booking['guests'] ?? ''); ?>"></label>
		      <label>Package<select name="package_id" id="adminPackageSelect"><option value="">Select package</option><?php foreach ($packages as $package): ?><?php $rule = $packageRules[(int) $package['id']]; ?><option value="<?php echo (int) $package['id']; ?>" data-min="<?php echo (int) $rule['min']; ?>" data-max="<?php echo (int) $rule['max']; ?>" <?php echo (int) ($booking['package_id'] ?? 0) === (int) $package['id'] ? 'selected' : ''; ?>><?php echo h($package['package_name'] . ' - ' . $package['guest_range']); ?></option><?php endforeach; ?></select><span class="form-note" id="adminPackageHint">Package updates automatically from guests.</span></label>
			      <label>Referred by<input name="referred_by_reference" id="adminReferredBy" value="<?php echo h($booking['referred_by_reference'] ?? ''); ?>" placeholder="Please enter current referral code or member phone"><span class="form-note">Optional. Eligible for <?php echo $referralEligibilityDays > 0 ? (int) $referralEligibilityDays . ' days after the referrer event' : 'unlimited days'; ?>.</span><span class="field-warning" id="adminReferredByResult"></span></label>
	      <label>PIN code<input name="pin_code" id="adminPinCode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="<?php echo h($booking['pin_code'] ?? ''); ?>"><span class="form-note" id="adminPinHint">Enter 6 digits to auto-fill area, city and state.</span></label>
      <label>Area / village<input name="area_from" id="adminAreaFrom" list="adminAreaOptions" value="<?php echo h($booking['area_from'] ?? ''); ?>"><datalist id="adminAreaOptions"></datalist></label>
      <label>City / district<input name="city_district" id="adminCityDistrict" value="<?php echo h($booking['city_district'] ?? ''); ?>"></label>
      <label>State<input name="client_state" id="adminClientState" value="<?php echo h($booking['client_state'] ?? ''); ?>"></label>
	      <label>Aadhaar image<input type="file" name="aadhaar_image" accept="image/*"></label>
      <label>Booking status<select name="booking_status"><?php foreach (booking_statuses() as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $selectedBookingStatus === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select></label>
      <label class="full">Extra notes<textarea name="note" rows="4"><?php echo h($booking['note'] ?? ''); ?></textarea></label>
      <div class="booking-date-picker full" id="bookingDatePicker" data-initial-date="<?php echo h($booking['event_date'] ?? ''); ?>">
        <div class="booking-date-head">
          <div>
            <span class="eyebrow-text">Availability calendar</span>
            <h3 id="bookingCalendarTitle">Event Date</h3>
          </div>
          <div class="booking-date-actions">
            <button type="button" id="bookingPrevMonth" aria-label="Previous month">&lsaquo;</button>
            <button type="button" id="bookingNextMonth" aria-label="Next month">&rsaquo;</button>
          </div>
        </div>
        <div class="booking-date-week">
          <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
        </div>
        <div class="booking-date-grid" id="bookingDateGrid"></div>
        <div class="booking-date-legend"><span><i class="available"></i>Available</span><span><i class="booked"></i>Booked</span></div>
        <p class="form-note" id="bookingDateHint">Choose a green date. Previous dates are hidden.</p>
      </div>
      <?php if (!empty($booking['aadhaar_image'])): ?><p class="full form-note">Current Aadhaar image: <a href="<?php echo h(app_url('../api/client-document.php','admin')); ?>?booking_id=<?php echo (int)$bookingId; ?>" target="_blank" rel="noopener">View image</a></p><?php endif; ?>
      <button class="btn gold" <?php echo !$ready ? 'disabled' : ''; ?>>Save Information</button>
    </form>
  </div>

  <div class="tab-panel payment-panel" id="payments">
    <div class="payment-screen-heading"><div><h2>Payments</h2><p>Review the booking total, then record each payment as it arrives.</p></div><?php if($isEdit): ?><div class="payment-status-display"><span>Current payment status</span><span class="badge <?php echo h($selectedPaymentStatus); ?>"><?php echo h(payment_statuses()[$selectedPaymentStatus]??'Payment Pending'); ?></span></div><?php endif; ?></div>
    <?php if (!$isEdit): ?>
      <div class="empty-state">Save the booking information first to set pricing and record payments.</div>
    <?php else: ?>
    <form class="payment-entry-form payment-workspace" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
      <input type="hidden" name="action" value="save_payment_tab"><input type="hidden" name="apply_rate_card" value="1"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>"><input type="hidden" name="back" value="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int)$bookingId; ?>#payments"><input type="hidden" name="booking_id" value="<?php echo (int)$bookingId; ?>">
      <div class="payment-editor">
        <section class="payment-card" aria-labelledby="paymentPricingTitle">
          <div class="payment-card-heading"><span class="payment-step">01</span><div><h3 id="paymentPricingTitle">Venue &amp; discount</h3><p>Set the venue fee and any agreed discount.</p></div></div>
          <div class="payment-fields payment-fields--pricing"><label>Venue amount before discount<input type="number" min="0" max="9999999999.99" step="0.01" name="proposal_amount" id="paymentTotalAmount" value="<?php echo h($booking['proposal_amount'] ?? ''); ?>"></label><label>Discount type<select name="discount_type" id="paymentDiscountType"><option value="none" <?php echo ($booking['discount_type'] ?? 'none') === 'none' ? 'selected' : ''; ?>>No discount</option><option value="percent" <?php echo ($booking['discount_type'] ?? '') === 'percent' ? 'selected' : ''; ?>>Percentage (%)</option><option value="fixed" <?php echo ($booking['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Amount (Rs)</option></select></label><label><span id="paymentDiscountLabel">Discount value</span><span class="required-mark" id="paymentDiscountRequired" aria-hidden="true"> *</span><input type="number" min="0" step="0.01" name="discount_value" id="paymentDiscountValue" value="<?php echo h($booking['discount_value'] ?? '0'); ?>" placeholder="Please enter discount value"></label></div>
          <p class="payment-help">The discount applies to the venue fee. Cleaning, electricity and the refundable deposit are added separately.</p>
        </section>
        <section class="payment-card rate-charge-controls" aria-labelledby="paymentChargesTitle">
          <div class="payment-card-heading"><span class="payment-step">02</span><div><h3 id="paymentChargesTitle">Venue charges</h3><p id="paymentRateBasis">Review the rates and electricity readings.</p></div><a class="payment-text-link" href="<?php echo h(app_url('rate-card.php', 'admin')); ?>" target="_blank" rel="noopener">Rate Card ↗</a></div>
          <div class="payment-charge-context"><div class="payment-guests"><label>Guests used for cleaning<input type="number" value="<?php echo (int)($booking['guests']??0); ?>" readonly aria-label="Guests used for cleaning"></label><button type="button" class="payment-text-button" id="editPaymentGuests">Edit guests →</button></div><div class="payment-rate-choice"><label class="rate-refresh"><input type="checkbox" name="refresh_rate_card" id="paymentRefreshRates" value="1"> Use latest Rate Card for this save</label></div></div>
          <div class="payment-meter"><div class="payment-subheading"><h4>Electricity meter</h4><span>Units calculate automatically</span></div><div class="payment-fields payment-fields--meter"><label>Starting meter reading<span class="required-mark" id="electricityStartRequired" aria-hidden="true"> *</span><input type="number" name="electricity_meter_start" id="paymentElectricityStart" min="0" max="9999999999.99" step="0.01" value="<?php echo h($savedRateSnapshot['electricity_meter_start']??''); ?>" placeholder="Please enter starting meter reading"></label><label>Ending meter reading<span class="required-mark" id="electricityEndRequired" aria-hidden="true"> *</span><input type="number" name="electricity_meter_end" id="paymentElectricityEnd" min="0" max="9999999999.99" step="0.01" value="<?php echo h($savedRateSnapshot['electricity_meter_end']??''); ?>" placeholder="Please enter ending meter reading"></label><label>Electricity units (automatic)<input type="number" name="electricity_units" id="paymentElectricityUnits" min="0" max="9999999999.99" step="0.01" readonly value="<?php echo h($savedRateSnapshot['electricity_units']??'0'); ?>" placeholder="Please enter electricity units"><span class="form-note" id="paymentElectricityRate"></span></label></div></div>
                    <?php if($extraRateChoices): ?><div class="extra-charge-grid">
            <?php foreach($extraRateChoices as$key=>$extra): ?><div class="extra-charge-item"><label class="rate-refresh"><input type="checkbox" class="extra-charge-choice" name="extra_charge_keys[]" data-key="<?php echo h($key); ?>" value="<?php echo h($key); ?>" <?php echo in_array($key,$selectedExtraCharges,true)?'checked':''; ?>> <?php echo h($extra['label']); ?> <span class="extra-unit-rate" data-key="<?php echo h($key); ?>"></span></label><label>Quantity<input type="number" class="extra-charge-quantity" data-key="<?php echo h($key); ?>" name="extra_charge_qty[<?php echo h($key); ?>]" min="0.01" step="0.01" max="9999999999.99" value="<?php echo h($savedExtraQuantities[$key]??'1'); ?>" placeholder="Please enter quantity"></label></div><?php endforeach; ?>
          </div><?php endif; ?>

          <div class="field-warning err payment-inline-warning" id="paymentRateWarning" role="status"></div>
          <details class="payment-breakdown"><summary>Itemised price breakdown</summary><div class="table-wrap"><table class="rate-charge-table"><thead><tr><th>Item</th><th>Calculation</th><th>Amount</th></tr></thead><tbody id="paymentChargeBreakdown"></tbody></table></div></details>
          <details class="payment-explainer"><summary>How charges are calculated</summary><p>Cleaning is the greater of the per-guest charge and the minimum charge. The security deposit is refundable and collected in full. Electricity units equal the ending reading minus the starting reading.</p><p>The suggested advance includes the configured percentage of services plus the full refundable deposit.</p></details>
        </section>
        <section class="payment-card" aria-labelledby="paymentRecordTitle">
          <div class="payment-card-heading"><span class="payment-step">03</span><div><h3 id="paymentRecordTitle">Record a payment</h3><p>Enter only the amount received in this transaction.</p></div></div>
          <div class="payment-fields"><label>Amount received (Rs)<input name="amount" id="paymentAmountPaid" type="number" step="0.01" min="0.01" max="9999999999.99" required placeholder="Add next payment amount"><span class="field-warning err" id="paymentAmountWarning"></span></label><label>Payment type<select name="payment_mode"><?php foreach (payment_modes() as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></label><label>Transaction ID<input name="transaction_id"></label><label>Paid on<input name="paid_on" type="date" value="<?php echo date('Y-m-d'); ?>"></label><label>Verification status<select name="verification_status" id="paymentVerificationStatus"><?php foreach (payment_verification_statuses() as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></label><label class="full">Payment note (optional)<textarea name="payment_note" rows="2" placeholder="Please enter payment note"><?php echo h($booking['proposal_note']??''); ?></textarea></label></div>
          <div class="payment-record-footer"><p>Only verified payments reduce the balance.</p><button class="btn gold" type="submit">Record payment</button></div>
        </section>
      </div>
      <aside class="payment-total-card" aria-label="Live booking total">
        <div class="payment-total-hero"><span class="payment-preview-label">Live booking total</span><strong id="paymentNetPreview">Rs <?php echo h(number_format($pricing['net'],2)); ?></strong><p>Includes the refundable security deposit.</p></div>
        <div class="payment-total-body"><dl class="payment-total-lines"><div><dt>Venue fee</dt><dd id="paymentVenuePreview">Rs <?php echo h(number_format($pricing['gross'],2)); ?></dd></div><div><dt>Discount</dt><dd class="payment-saving" id="paymentDiscountPreview">Rs <?php echo h(number_format($pricing['discount'],2)); ?></dd></div><div><dt>Cleaning</dt><dd id="paymentCleaningPreview">Rs <?php echo h(number_format($pricing['cleaning'],2)); ?></dd></div><div><dt>Electricity</dt><dd id="paymentElectricityPreview">Rs <?php echo h(number_format($pricing['electricity'],2)); ?></dd></div><div id="paymentExtrasRow"><dt>Additional charges</dt><dd id="paymentExtrasPreview">Rs <?php echo h(number_format($pricing['extras'],2)); ?></dd></div><div><dt>Refundable deposit</dt><dd id="paymentDepositPreview">Rs <?php echo h(number_format($pricing['deposit'],2)); ?></dd></div><div class="payment-total-paid"><dt>Verified payments</dt><dd>Rs <?php echo h(number_format($paidTotal,2)); ?></dd></div></dl>
          <div class="payment-balance"><span>Remaining balance</span><strong id="paymentBalancePreview">Rs <?php echo h(number_format($balance,2)); ?></strong></div>
          <p class="payment-advance" id="paymentAdvancePreview"></p>
          <button class="btn payment-save-pricing" type="submit" name="save_pricing" value="1" formnovalidate>Save pricing only</button><p class="payment-summary-note">Saves this quote without adding a payment.</p>
          <span class="sr-only" id="paymentPreviewAnnouncement" role="status"></span>
        </div>
      </aside>
    </form>
    <section class="payment-history-card" aria-labelledby="paymentHistoryTitle"><div class="payment-history-heading"><div><h3 id="paymentHistoryTitle">Payment history</h3><p>Every payment and its verification status in one place.</p></div><span class="payment-history-count"><?php echo count($payments); ?> transaction<?php echo count($payments)===1?'':'s'; ?></span></div>
      <div class="table-wrap payment-history"><table class="payment-history-table"><thead><tr><th>Paid on</th><th>Amount received</th><th>Method &amp; reference</th><th>Verification</th><th>Update verification</th></tr></thead><tbody>
      <?php foreach($payments as$payment): $historyVerification=array_key_exists((string)($payment['verification_status']??''),payment_verification_statuses())?$payment['verification_status']:'pending'; ?>
        <tr><td><?php echo h(date('d M Y',strtotime($payment['paid_on']))); ?></td><td><strong class="payment-history-amount">Rs <?php echo h(number_format((float)$payment['amount'],2)); ?></strong></td><td><span class="payment-history-method"><?php echo h(payment_modes()[$payment['payment_mode']]??status_label($payment['payment_mode'])); ?></span><small class="payment-reference"><?php echo h($payment['transaction_id']?:'No transaction reference'); ?></small><?php if(!empty($payment['note'])): ?><small class="payment-reference"><?php echo h($payment['note']); ?></small><?php endif; ?></td><td><span class="badge <?php echo h($historyVerification); ?>"><?php echo h(payment_verification_statuses()[$historyVerification]); ?></span></td><td><form class="payment-status-form" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>"><input type="hidden" name="action" value="update_payment"><input type="hidden" name="back" value="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int)$bookingId; ?>#payments"><input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>"><label class="sr-only" for="verify-payment-<?php echo (int)$payment['id']; ?>">Verification status</label><select id="verify-payment-<?php echo (int)$payment['id']; ?>" name="verification_status"><?php foreach(payment_verification_statuses()as$key=>$label): ?><option value="<?php echo h($key); ?>" <?php echo $historyVerification===$key?'selected':''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select><button class="btn">Update</button></form></td></tr>
      <?php endforeach; ?><?php if(!$payments): ?><tr><td colspan="5"><div class="payment-empty"><strong>No payments recorded yet</strong><span>Use “Record a payment” to add the first instalment.</span></div></td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
          <details class="pricing-history"><summary>Saved pricing history (latest 10)</summary><p class="form-note">Each change stores the rates, quantities, line items, discount, note and calculated totals. Repeated payments with unchanged pricing reuse the saved rates.</p><div class="table-wrap"><table><thead><tr><th>Saved</th><th>Venue amount</th><th>Discount</th><th>Rate charges</th><th>Refundable deposit included</th><th>Grand total</th><th>By</th></tr></thead><tbody><?php foreach($pricingHistory as$entry):$data=json_decode($entry['snapshot'],true);$totals=$data['totals']??[]; ?><tr><td><?php echo h($entry['created_at']); ?></td><td>Rs <?php echo h(number_format((float)($totals['gross']??0),2)); ?></td><td>Rs <?php echo h(number_format((float)($totals['discount']??0),2)); ?></td><td>Rs <?php echo h(number_format((float)($totals['charges']??0),2)); ?></td><td>Rs <?php echo h(number_format((float)($totals['deposit']??0),2)); ?></td><td>Rs <?php echo h(number_format((float)($totals['net']??0),2)); ?></td><td><?php echo h($entry['actor']); ?></td></tr><?php endforeach; ?><?php if(!$pricingHistory): ?><tr><td colspan="7">No pricing changes saved yet.</td></tr><?php endif; ?></tbody></table></div></details>
    <?php endif; ?>
  </div>

  <div class="tab-panel inspection-panel" id="inspection">
    <div class="section-head"><div><h2>After Event Inspection</h2><p>Check the venue after the event, add any penalties, then send the summary to the client.</p></div></div>
    <?php if (!$isEdit): ?>
      <div class="empty-state">Save the booking first. After saving, the inspection checklist will be ready.</div>
    <?php else: ?>
      <?php if (($booking['booking_status'] ?? '') !== 'event_over'): ?>
        <div class="inspection-reminder">Use this after the event is completed. For best tracking, set booking status to Event Over in the Information tab before sending final penalties.</div>
      <?php endif; ?>
      <form class="inspection-checklist" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
        <input type="hidden" name="action" value="save_event_inspection">
        <input type="hidden" name="back" value="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $bookingId; ?>#inspection">
        <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
        <?php
          $checks = [
              'lights_ok' => 'Lights checked',
              'rooms_ok' => 'Rooms and beds checked',
              'lawn_ok' => 'Grass and lawn checked',
              'hall_ok' => 'Mini hall and glass checked',
              'washrooms_ok' => 'Washrooms checked',
              'cleanup_ok' => 'Vendor clean-up checked',
          ];
        ?>
        <?php foreach ($checks as $field => $label): ?>
          <label class="check-tile"><input type="checkbox" name="<?php echo h($field); ?>" <?php echo !empty($inspection[$field]) ? 'checked' : ''; ?>><span><?php echo h($label); ?></span></label>
        <?php endforeach; ?>
        <label class="full">Inspection note<textarea name="inspection_note" rows="3" placeholder="Any general note after checking the property"><?php echo h($inspection['inspection_note'] ?? ''); ?></textarea></label>
        <button class="btn gold">Save Checklist</button>
      </form>

      <div class="penalty-layout">
        <div>
          <h2>Add Penalty</h2>
          <form class="form-grid friendly-form" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
            <input type="hidden" name="action" value="add_penalty">
            <input type="hidden" name="back" value="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $bookingId; ?>#inspection">
            <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
            <label>Penalty item<input name="penalty_title" required placeholder="3 lights damaged"></label>
            <label>Amount<input name="amount" type="number" min="1" step="0.01" required placeholder="3000"></label>
            <label class="full">Remark<textarea name="penalty_reason" rows="3" required placeholder="Example: Three decorative lights were damaged during vendor removal."></textarea></label>
            <button class="btn gold">Add Penalty</button>
          </form>
        </div>
        <div class="penalty-summary">
          <h2>Charges to Client</h2>
          <div class="charge-total penalty-highlight"><span>Penalty total</span><strong>Rs <?php echo h(number_format($penaltyTotal,2)); ?></strong></div>
          <div class="charge-total"><span>Total due including balance</span><strong>Rs <?php echo h(number_format($clientDue,2)); ?></strong></div>
          <a class="btn gold full" href="https://wa.me/<?php echo h($clientWhatsappDigits); ?>?text=<?php echo h(rawurlencode($penaltyMessage)); ?>" target="_blank" rel="noopener">Send to Client</a>
        </div>
      </div>

      <div class="table-wrap payment-history"><table><thead><tr><th>Penalty</th><th>Remark</th><th>Amount</th><th>Remove</th></tr></thead><tbody>
        <?php foreach ($penalties as $penalty): ?>
          <tr>
            <td><strong><?php echo h($penalty['penalty_title']); ?></strong></td>
            <td><?php echo h($penalty['penalty_reason']); ?></td>
            <td><strong>Rs <?php echo h(number_format((float) $penalty['amount'])); ?></strong></td>
            <td>
              <form method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>" onsubmit="return confirm('Delete this penalty?');">
                <input type="hidden" name="action" value="delete_penalty">
                <input type="hidden" name="back" value="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $bookingId; ?>#inspection">
                <input type="hidden" name="penalty_id" value="<?php echo (int) $penalty['id']; ?>">
                <button class="btn red">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$penalties): ?><tr><td colspan="4">No penalties added. Add only if there is damage or recoverable charge.</td></tr><?php endif; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>

	  <div class="tab-panel terms-panel" id="terms">
    <div class="section-head"><div><h2>Client Accepted Terms</h2><p>Booking terms checked by this client on the public enquiry form.</p></div></div>
    <?php if (!$isEdit): ?>
      <div class="empty-state">Save the booking first. Public enquiry terms appear here for client enquiries.</div>
    <?php elseif (!$acceptedTerms): ?>
      <div class="empty-state">No accepted booking terms were saved for this booking. This usually means the booking was added manually from admin.</div>
    <?php else: ?>
      <div class="accepted-terms-list">
        <?php foreach ($acceptedTerms as $term): ?>
          <div class="accepted-term">
            <span class="term-check" aria-hidden="true">&#10003;</span>
            <div>
              <strong><?php echo h($term['title'] ?? 'Booking term'); ?></strong>
              <p><?php echo h($term['term_text'] ?? ''); ?></p>
              <?php if (!empty($term['accepted_at'])): ?><small>Accepted on <?php echo h(date('d M Y, h:i A', strtotime((string) $term['accepted_at']))); ?></small><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
	  </div>
	
	  <div class="tab-panel referrals-panel" id="referrals">
	    <div class="section-head"><div><h2>Referral Details</h2><p>Track who referred this booking and how many bookings this member has referred.</p></div></div>
	    <?php if (!$isEdit): ?>
	      <div class="empty-state">Save the booking first. Referral details will appear here.</div>
	    <?php else: ?>
	      <form class="form-grid friendly-form" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
	        <input type="hidden" name="action" value="save_referral">
            <input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
	        <input type="hidden" name="back" value="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $bookingId; ?>#referrals">
	        <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
	        <label>Referred by reference / phone<input name="referred_by_reference" id="referralTabReferredBy" value="<?php echo h($booking['referred_by_reference'] ?? ''); ?>" placeholder="Please enter current referral code or member phone"><span class="form-note">Window: <?php echo $referralEligibilityDays > 0 ? (int) $referralEligibilityDays . ' days after the referrer event' : 'No expiry'; ?>.</span><span class="field-warning" id="referralTabReferredByResult"></span></label>
		        <label>Referrer bonus amount<input type="number" min="0" max="<?php echo h((string) $referralBonusCap); ?>" step="0.01" name="referral_referrer_bonus" value="<?php echo h((string) ((float) ($booking['referral_referrer_bonus'] ?? 0) > 0 ? $booking['referral_referrer_bonus'] : $defaultReferralBonus)); ?>"><span class="form-note">Default Rs <?php echo h(number_format($defaultReferralBonus)); ?>. Max bonus Rs <?php echo h(number_format($referralBonusCap)); ?>.</span></label>
		        <label>New member bonus amount<input type="number" min="0" max="<?php echo h((string) $referralBonusCap); ?>" step="0.01" name="referral_referred_bonus" value="<?php echo h((string) ((float) ($booking['referral_referred_bonus'] ?? 0) > 0 ? $booking['referral_referred_bonus'] : $defaultNewMemberBonus)); ?>"><span class="form-note">Default Rs <?php echo h(number_format($defaultNewMemberBonus)); ?>.</span></label>
	        <label class="check-label"><input type="checkbox" name="referral_eligible" value="1" <?php echo !empty($booking['referral_eligible']) ? 'checked' : ''; ?>> Eligible for referral bonus</label>
	        <label class="full">Referral note<textarea name="referral_note" rows="3" placeholder="Eligibility reason, payout note or approval details"><?php echo h($booking['referral_note'] ?? ''); ?></textarea></label>
	        <button class="btn gold">Save Referral</button>
	      </form>
	      <div class="summary-strip" style="margin-top:20px">
	        <div><span>Current referral code</span><strong><?php echo h($memberReferralCode['code'] ?? ''); ?></strong><a href="<?php echo h(app_url('referrals.php', 'admin')); ?>?member=<?php echo (int)$bookingId; ?>">Manage code &amp; view logs</a></div>
		        <div><span>Current code uses</span><strong><?php echo (int)($memberReferralCode['usage_count'] ?? 0); ?> / <?php echo (int)($memberReferralCode['usage_limit'] ?? 0); ?></strong><span><?php echo h($memberReferralCode ? ucfirst(referral_code_state($memberReferralCode)) : ''); ?></span></div>
	        <div><span>Eligibility</span><strong><?php echo !empty($booking['referral_eligible']) ? 'Eligible' : 'Not eligible'; ?></strong></div>
	      </div>
	      <?php if ($referrerBooking): ?>
	        <div class="accepted-term">
	          <span class="term-check" aria-hidden="true">&#8592;</span>
	          <div><strong>Referred by <?php echo h($referrerBooking['client_name']); ?></strong><p><?php echo h($referrerBooking['booking_reference']); ?> - <?php echo h($referrerBooking['event_type'] ?: 'Event'); ?> on <?php echo h($referrerBooking['event_date']); ?></p><small><a href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $referrerBooking['id']; ?>">Open referrer booking</a></small></div>
	        </div>
	      <?php endif; ?>
	      <div class="table-wrap payment-history"><table><thead><tr><th>Referred member</th><th>Event</th><th>Eligibility</th><th>Bonuses</th><th>Manage</th></tr></thead><tbody>
	        <?php foreach ($referrals as $referral): ?>
	          <tr>
	            <td><strong><?php echo h($referral['client_name']); ?></strong><br><?php echo h($referral['booking_reference']); ?></td>
	            <td><?php echo h($referral['event_type'] ?: 'Event'); ?><br><?php echo h($referral['event_date']); ?></td>
	            <td><?php echo !empty($referral['referral_eligible']) ? 'Eligible' : 'Not eligible'; ?></td>
	            <td>Referrer: Rs <?php echo h(number_format((float) $referral['referral_referrer_bonus'])); ?><br>New member: Rs <?php echo h(number_format((float) $referral['referral_referred_bonus'])); ?></td>
	            <td><a class="btn" href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int) $referral['id']; ?>#referrals">Open</a></td>
	          </tr>
	        <?php endforeach; ?>
	        <?php if (!$referrals): ?><tr><td colspan="5">No bookings have used this member's reference yet.</td></tr><?php endif; ?>
	      </tbody></table></div>
	    <?php endif; ?>
	  </div>
	</section>
<script>
  if (window.location.hash === '#payments') {
    const paymentTab = document.getElementById('tab-payments');
    if (paymentTab) paymentTab.checked = true;
  }
  if (window.location.hash === '#inspection') {
    const inspectionTab = document.getElementById('tab-inspection');
    if (inspectionTab) inspectionTab.checked = true;
  }
	  if (window.location.hash === '#terms') {
	    const termsTab = document.getElementById('tab-terms');
	    if (termsTab) termsTab.checked = true;
	  }
  if (window.location.hash === '#referrals') {
    const referralsTab = document.getElementById('tab-referrals');
    if (referralsTab) referralsTab.checked = true;
  }

		  const currentBookingId = <?php echo (int) $bookingId; ?>;
		  const adminPhoneNumber = document.getElementById('adminPhoneNumber');
		  const adminWhatsappNumber = document.getElementById('adminWhatsappNumber');
		  const adminEmailAddress = document.getElementById('adminEmailAddress');
		  const adminContactWarning = document.getElementById('adminContactWarning');
		  const adminWhatsappWarning = document.getElementById('adminWhatsappWarning');
		  const adminEmailWarning = document.getElementById('adminEmailWarning');
		  const adminSameAsPhone = document.getElementById('adminSameAsPhone');
		  if (adminPhoneNumber && adminWhatsappNumber && adminSameAsPhone) {
		    const digitsOnly = (input) => {
		      input.value = input.value.replace(/\D+/g, '').slice(0, 10);
		    };
		    const syncWhatsapp = () => {
		      digitsOnly(adminPhoneNumber);
		      if (adminSameAsPhone.checked) {
		        adminWhatsappNumber.value = adminPhoneNumber.value;
		      }
		    };
		    adminSameAsPhone.checked = adminWhatsappNumber.value.trim() !== '' && adminWhatsappNumber.value.trim() === adminPhoneNumber.value.trim();
		    adminSameAsPhone.addEventListener('change', syncWhatsapp);
		    adminPhoneNumber.addEventListener('input', syncWhatsapp);
		    adminWhatsappNumber.addEventListener('input', () => {
		      digitsOnly(adminWhatsappNumber);
		      if (adminWhatsappNumber.value !== adminPhoneNumber.value) {
		        adminSameAsPhone.checked = false;
		      }
		    });
		  }
			  if (adminPhoneNumber && adminWhatsappNumber && adminEmailAddress && adminContactWarning && adminWhatsappWarning && adminEmailWarning) {
			    let contactCheckTimer = 0;
			    let contactCheckRun = 0;
			    const setFieldWarning = (element, input, message = '') => {
			      element.textContent = message;
			      element.classList.toggle('show', Boolean(message));
			      input.setCustomValidity(message);
			    };
			    const setContactWarnings = (fields = {}) => {
			      setFieldWarning(adminContactWarning, adminPhoneNumber, fields.phone || '');
			      setFieldWarning(adminWhatsappWarning, adminWhatsappNumber, fields.whatsapp_number || '');
			      setFieldWarning(adminEmailWarning, adminEmailAddress, fields.email || '');
			    };
			    const checkDuplicateContact = async () => {
		      const phone = adminPhoneNumber.value.trim();
		      const whatsapp = adminWhatsappNumber.value.trim();
			      const email = adminEmailAddress.value.trim();
			      if (phone.length < 10 && whatsapp.length < 10 && !email.includes('@')) {
			        setContactWarnings();
			        return;
			      }
		      const run = ++contactCheckRun;
		      const params = new URLSearchParams({
		        phone,
		        whatsapp_number: whatsapp,
		        email,
		        exclude_booking_id: String(currentBookingId || 0),
		        details: '1'
		      });
		      try {
		        const response = await fetch('<?php echo app_url('../api/contact-check.php', 'admin'); ?>?' + params.toString(), {
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
		    adminPhoneNumber.addEventListener('input', scheduleContactCheck);
		    adminWhatsappNumber.addEventListener('input', scheduleContactCheck);
		    adminEmailAddress.addEventListener('input', scheduleContactCheck);
		    adminEmailAddress.addEventListener('change', scheduleContactCheck);
		    scheduleContactCheck();
		  }
		  const adminGuestInput = document.getElementById('adminGuestInput');
	  const adminPackageSelect = document.getElementById('adminPackageSelect');
	  const adminPackageHint = document.getElementById('adminPackageHint');
	  if (adminGuestInput && adminPackageSelect) {
	    const chooseAdminPackageByGuests = () => {
	      const guests = Number(adminGuestInput.value || 0);
	      if (!guests) {
	        if (adminPackageHint) adminPackageHint.textContent = 'Package updates automatically from guests.';
	        return;
	      }
	      let matched = null;
	      Array.from(adminPackageSelect.options).forEach((option) => {
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
	        adminPackageSelect.value = matched.value;
	        if (adminPackageHint) adminPackageHint.textContent = 'Package selected for ' + guests + ' guests.';
	      } else if (adminPackageHint) {
	        adminPackageHint.textContent = 'No package found for this guest count.';
	      }
	    };
	    adminGuestInput.addEventListener('input', chooseAdminPackageByGuests);
	    chooseAdminPackageByGuests();
	  }
	  const bindReferralLookup = (inputId, resultId) => {
    const input = document.getElementById(inputId);
    const result = document.getElementById(resultId);
    if (!input || !result) return;
    let lookupTimer = null;
    const render = (message = '', type = '') => {
      result.textContent = message;
      result.classList.toggle('show', message !== '');
      result.classList.toggle('ok', type === 'ok');
      result.classList.toggle('err', type === 'err');
    };
    const lookup = async () => {
      const value = input.value.trim();
      const initialReferralValue = input.defaultValue.trim();
      if (!value) {
        render('');
        return;
      }
      render('Checking referral member...');
      try {
        const response = await fetch('<?php echo app_url('../api/referral-lookup.php', 'admin'); ?>?q=' + encodeURIComponent(value) + '&exclude_id=' + encodeURIComponent(currentBookingId), {
          headers: { Accept: 'application/json' },
          cache: 'no-store'
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Lookup failed');
        if (!data.found) {
          render(data.message || 'No eligible member found for this reference or phone number.', 'err');
          return;
        }
        const booking = data.booking;
        if (!initialReferralValue || initialReferralValue !== value) input.value = booking.booking_reference || value;
        render('Matched: ' + booking.client_name + ' (' + booking.booking_reference + ')', 'ok');
      } catch (error) {
        render(error.message || 'Could not check referral member.', 'err');
      }
    };
    input.addEventListener('input', () => {
      clearTimeout(lookupTimer);
      lookupTimer = setTimeout(lookup, 450);
    });
    input.addEventListener('blur', lookup);
    if (input.value.trim()) lookup();
  };
  bindReferralLookup('adminReferredBy', 'adminReferredByResult');
  bindReferralLookup('referralTabReferredBy', 'referralTabReferredByResult');

  const paymentTotalAmount = document.getElementById('paymentTotalAmount');
  const paymentAmountPaid = document.getElementById('paymentAmountPaid');
  const paymentAmountWarning = document.getElementById('paymentAmountWarning');
  const paymentVerificationStatus = document.getElementById('paymentVerificationStatus');
  const discountType = document.getElementById('paymentDiscountType');
  const discountValue = document.getElementById('paymentDiscountValue');
  const electricityUnits=document.getElementById('paymentElectricityUnits');
  const meterStart=document.getElementById('paymentElectricityStart');
  const meterEnd=document.getElementById('paymentElectricityEnd');
  const legacyElectricityUnits=<?php echo json_encode(!isset($savedRateSnapshot['electricity_meter_start'],$savedRateSnapshot['electricity_meter_end'])?(float)($savedRateSnapshot['electricity_units']??0):0); ?>;
  const refreshRates=document.getElementById('paymentRefreshRates');
  const savedRates=<?php echo json_encode($savedRateSnapshot['catalog']??null,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
  const latestRates=<?php echo json_encode($currentRateCatalog,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
  const cleaningGuests=<?php echo (int)($booking['guests']??0); ?>;
  const existingPaymentTotal = <?php echo json_encode((float)$paymentHistoryTotal); ?>;
  const verifiedPaymentTotal = <?php echo json_encode((float)$paidTotal); ?>;
  if (paymentTotalAmount && paymentAmountPaid && discountType && discountValue) {
    const rupees = value => new Intl.NumberFormat('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Math.max(0,value));
    const updatePaymentWarning = () => {
      const gross = Math.round(Number(paymentTotalAmount.value || 0)*100);
      const value = Math.round(Number(discountValue.value || 0)*100);
      const type = discountType.value;
      discountValue.disabled = type === 'none';
      discountValue.required = type !== 'none';
      discountValue.max = type === 'percent' ? '100' : String(gross/100);
      document.getElementById('paymentDiscountLabel').textContent = type === 'percent' ? 'Discount (%)' : type === 'fixed' ? 'Discount amount (Rs)' : 'Discount value';
      document.getElementById('paymentDiscountRequired').style.setProperty('display',type === 'none' ? 'none' : 'inline','important');
      discountValue.setCustomValidity(type === 'percent' && value > 10000 ? 'Discount cannot exceed 100%.' : type === 'fixed' && value > gross ? 'Discount cannot exceed the total amount.' : '');
      const discount = Math.min(gross,Math.max(0,type === 'percent' ? Math.round(gross*value/10000) : type === 'fixed' ? value : 0));
      const rates=savedRates&&!refreshRates.checked?savedRates:latestRates;
      const cents=value=>Math.round(Number(value||0)*100);
      const perGuest=cents(rates.cleaning_per_guest),minimum=cents(rates.cleaning_minimum),deposit=cents(rates.security_deposit),unitRate=cents(rates.electricity_per_unit);
      const startText=meterStart.value.trim(),endText=meterEnd.value.trim(),hasReading=startText!==''||endText!=='';
      meterStart.required=hasReading;meterEnd.required=hasReading;
      document.getElementById('electricityStartRequired').style.setProperty('display',hasReading?'inline':'none','important');
      document.getElementById('electricityEndRequired').style.setProperty('display',hasReading?'inline':'none','important');
      const start100=Math.round(Number(startText||0)*100),end100=Math.round(Number(endText||0)*100);
      const meterError=hasReading&&(startText===''||endText==='')?'Please enter both starting and ending meter readings.':hasReading&&end100<start100?'Ending reading must be greater than or equal to the starting reading.':'';
      meterStart.setCustomValidity(startText===''&&hasReading?meterError:'');meterEnd.setCustomValidity(meterError);
      const units=hasReading?(meterError?0:end100-start100):Math.round(legacyElectricityUnits*100);
      electricityUnits.value=(units/100).toFixed(2);
      const cleaning=Math.max(minimum,perGuest*cleaningGuests),electricity=Math.round(unitRate*units/100);
      const lines=[['Venue amount before discount','',gross],['Venue discount',type==='none'?'No discount':type==='percent'?Number(discountValue.value||0)+'%':'Fixed amount',-discount],['Cleaning',cleaningGuests+' guests × Rs '+rupees(perGuest/100)+'; minimum Rs '+rupees(minimum/100),cleaning],['Refundable security deposit','Collected in full',deposit],['Electricity',(units/100)+' units × Rs '+rupees(unitRate/100),electricity]];
      let extraTotal=0,unavailable=false;
      document.querySelectorAll('.extra-charge-choice').forEach(choice=>{
        const key=choice.dataset.key,rate=rates.extras[key];
        const quantity=Array.from(document.querySelectorAll('.extra-charge-quantity')).find(field=>field.dataset.key===key);
        const display=Array.from(document.querySelectorAll('.extra-unit-rate')).find(field=>field.dataset.key===key);
        quantity.disabled=!choice.checked;quantity.required=choice.checked;
        display.textContent=rate?'(Rs '+rupees(Number(rate.rate))+' each)':'(Unavailable in latest Rate Card)';
        if(!choice.checked)return;
        if(!rate){unavailable=true;return;}
        const quantity100=Math.round(Number(quantity.value||0)*100),unit=cents(rate.rate),amount=Math.round(unit*quantity100/100);
        extraTotal+=amount;lines.push([rate.label,(quantity100/100)+' × Rs '+rupees(unit/100),amount]);
      });
      refreshRates.setCustomValidity(unavailable?'Deselect extra charges unavailable in the latest Rate Card, or keep the saved rates.':'');
      const warning=document.getElementById('paymentRateWarning');
      warning.textContent=meterError|| (cleaningGuests<1?'Add the guest count to complete this quote. Choose “Edit guests” above.':unavailable?'An extra charge is unavailable in the latest Rate Card. Deselect it or keep the saved rates.':'');
      warning.classList.toggle('show',!!warning.textContent);
      const grand=gross-discount+cleaning+electricity+deposit+extraTotal;
      const net=grand/100;
      lines.push(['Grand total including deposit','',grand]);
      const tbody=document.getElementById('paymentChargeBreakdown');tbody.replaceChildren();
      lines.forEach(([label,calculation,amount])=>{const row=document.createElement('tr');[label,calculation,(amount<0?'− ':'')+'Rs '+rupees(Math.abs(amount)/100)].forEach(text=>{const cell=document.createElement('td');cell.textContent=text;row.appendChild(cell)});tbody.appendChild(row)});
      document.getElementById('paymentElectricityRate').textContent=(hasReading&&!meterError?endText+' − '+startText+' = '+(units/100)+' units. ':!hasReading&&legacyElectricityUnits>0?'Saved units retained until readings are entered. ':'')+'Rs '+rupees(unitRate/100)+' per unit';
      document.getElementById('paymentRateBasis').textContent=savedRates&&!refreshRates.checked?'Saved booking rates are selected.':'Current Rate Card is selected for this quote.';
      const advance=Math.round((grand-deposit)*cents(rates.advance_percent)/10000)+deposit;
      document.getElementById('paymentAdvancePreview').textContent='Suggested advance: Rs '+rupees(advance/100)+' ('+Number(rates.advance_percent)+'% of service total + full refundable deposit).';
      document.getElementById('paymentVenuePreview').textContent='Rs '+rupees(gross/100);
      document.getElementById('paymentCleaningPreview').textContent='Rs '+rupees(cleaning/100);
      document.getElementById('paymentElectricityPreview').textContent='Rs '+rupees(electricity/100);
      document.getElementById('paymentExtrasPreview').textContent='Rs '+rupees(extraTotal/100);
      document.getElementById('paymentExtrasRow').hidden=extraTotal===0;
      document.getElementById('paymentDepositPreview').textContent='Rs '+rupees(deposit/100);
      document.getElementById('paymentDiscountPreview').textContent = (discount>0?'− ':'')+'Rs ' + rupees(discount/100);
      document.getElementById('paymentNetPreview').textContent = 'Rs ' + rupees(net);
      document.getElementById('paymentBalancePreview').textContent = 'Rs ' + rupees(net-verifiedPaymentTotal);
      document.getElementById('paymentPreviewAnnouncement').textContent='Booking total Rs '+rupees(net)+'. Remaining balance Rs '+rupees(net-verifiedPaymentTotal)+'.';
      const nextPayment = Number(paymentAmountPaid.value || 0);
      const finalPaid = existingPaymentTotal + (paymentVerificationStatus.value !== 'rejected' ? nextPayment : 0);
      const excess = paymentTotalAmount.value !== '' && nextPayment > 0 && finalPaid > net + .001;
      paymentAmountWarning.textContent = excess ? 'Warning: paid total will exceed the grand total by Rs ' + rupees(finalPaid-net) + '.' : '';
      paymentAmountWarning.classList.toggle('show',excess);
    };
    [paymentTotalAmount,paymentAmountPaid,discountValue].forEach(field=>field.addEventListener('input',updatePaymentWarning));
    [discountType,paymentVerificationStatus,refreshRates].forEach(field=>field.addEventListener('change',updatePaymentWarning));
    [meterStart,meterEnd].forEach(field=>field.addEventListener('input',updatePaymentWarning));
    document.querySelectorAll('.extra-charge-choice,.extra-charge-quantity').forEach(field=>field.addEventListener('input',updatePaymentWarning));
    document.getElementById('editPaymentGuests').addEventListener('click',()=>{document.getElementById('tab-info').checked=true;const field=document.getElementById('adminGuestInput');field.scrollIntoView({behavior:'smooth',block:'center'});field.focus({preventScroll:true});});
    updatePaymentWarning();
  }

  const bookingEventDate = document.getElementById('bookingEventDate');
  const bookingDatePicker = document.getElementById('bookingDatePicker');
  const bookingDateGrid = document.getElementById('bookingDateGrid');
  const bookingCalendarTitle = document.getElementById('bookingCalendarTitle');
  const bookingDateHint = document.getElementById('bookingDateHint');
  const bookingEventDateError = document.getElementById('bookingEventDateError');
  const bookingPrevMonth = document.getElementById('bookingPrevMonth');
  const bookingNextMonth = document.getElementById('bookingNextMonth');

  if (bookingEventDate && bookingDatePicker && bookingDateGrid) {
    const todayValue = '<?php echo h($today->format('Y-m-d')); ?>';
    const currentBookingId = <?php echo (int) $bookingId; ?>;
    const savedBookingDate = '<?php echo h($booking['event_date'] ?? ''); ?>';
    const today = new Date(todayValue + 'T00:00:00');
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    let visibleMonth = (() => {
      const initial = bookingEventDate.value || todayValue;
      const parts = initial.split('-').map(Number);
      return new Date(parts[0], parts[1] - 1, 1);
    })();

    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };
    const monthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    const sameMonth = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth();
    const setEventDateError = (message = '') => {
      if (!bookingEventDateError) return;
      bookingEventDateError.textContent = message;
      bookingEventDateError.classList.toggle('show', Boolean(message));
      bookingEventDate.setCustomValidity(message);
    };

    const renderBookingCalendar = async () => {
      const currentMonthKey = monthKey(visibleMonth);
      bookingCalendarTitle.textContent = `${monthNames[visibleMonth.getMonth()]} ${visibleMonth.getFullYear()}`;
      bookingDateGrid.innerHTML = '<div class="booking-date-loading">Loading availability...</div>';
      bookingPrevMonth.disabled = visibleMonth.getFullYear() === today.getFullYear() && visibleMonth.getMonth() === today.getMonth();

      let booked = new Map();
      try {
        const availabilityUrl = '<?php echo app_url('../api/availability.php', 'admin'); ?>?month=' + encodeURIComponent(currentMonthKey)
          + (currentBookingId ? '&exclude_booking_id=' + encodeURIComponent(currentBookingId) : '');
        const response = await fetch(availabilityUrl, {
          headers: { 'X-Requested-With': 'fetch' }
        });
        const data = await response.json();
        (data.booked_dates || []).forEach((row) => {
          booked.set(row.event_date, row);
        });
      } catch (error) {
        bookingDateHint.textContent = 'Could not load availability. You can still enter the date manually.';
      }

      bookingDateGrid.innerHTML = '';
      const firstDay = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
      const daysInMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 0).getDate();
      const offset = (firstDay.getDay() + 6) % 7;
      for (let i = 0; i < offset; i++) {
        bookingDateGrid.appendChild(document.createElement('span'));
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), day);
        const value = formatDate(date);
        const bookedInfo = booked.get(value);
        const isPast = date < today;
        const cell = document.createElement(isPast ? 'span' : 'button');
        cell.className = 'booking-date-cell';
        cell.type = 'button';
        cell.dataset.date = value;
        cell.innerHTML = `<strong>${day}</strong>`;

        if (isPast) {
          cell.classList.add('past');
          cell.setAttribute('aria-hidden', 'true');
        } else if (bookedInfo) {
          cell.classList.add('booked');
          cell.title = `${bookedInfo.client_name || 'Booked'} - ${bookedInfo.event_type || 'Event'}`;
          cell.innerHTML += '<small>Booked</small>';
          cell.addEventListener('click', () => {
            setEventDateError('This date is already booked. Please choose a green available date.');
            bookingDateHint.textContent = 'Choose another green available date.';
          });
        } else {
          cell.classList.add('available');
          cell.innerHTML += '<small>Available</small>';
          cell.addEventListener('click', () => {
            bookingEventDate.value = value;
            setEventDateError('');
            bookingDateGrid.querySelectorAll('.selected').forEach((selected) => selected.classList.remove('selected'));
            cell.classList.add('selected');
            bookingDateHint.textContent = 'Selected date is available.';
          });
        }

        if (bookingEventDate.value === value) {
          cell.classList.add('selected');
          if (bookedInfo && !isPast) {
            setEventDateError('This date is already booked. Please choose a green available date.');
            bookingDateHint.textContent = 'Choose another green available date.';
          }
        }
        bookingDateGrid.appendChild(cell);
      }
    };

    bookingPrevMonth.addEventListener('click', () => {
      const previous = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
      if (previous < new Date(today.getFullYear(), today.getMonth(), 1)) return;
      visibleMonth = previous;
      renderBookingCalendar();
    });
    bookingNextMonth.addEventListener('click', () => {
      visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
      renderBookingCalendar();
    });
    bookingEventDate.addEventListener('change', () => {
      if (!bookingEventDate.value) return;
      const parts = bookingEventDate.value.split('-').map(Number);
      const chosen = new Date(parts[0], parts[1] - 1, parts[2]);
      if (chosen < today && bookingEventDate.value !== savedBookingDate) {
        bookingEventDate.value = '';
        setEventDateError('Previous dates cannot be selected.');
        bookingDateHint.textContent = 'Choose a green upcoming date.';
        return;
      }
      setEventDateError('');
      if (!sameMonth(chosen, visibleMonth)) {
        visibleMonth = new Date(chosen.getFullYear(), chosen.getMonth(), 1);
      }
      renderBookingCalendar();
    });
    renderBookingCalendar();
  }

  const adminPinCode = document.getElementById('adminPinCode');
  const adminPinHint = document.getElementById('adminPinHint');
  const adminAreaFrom = document.getElementById('adminAreaFrom');
  const adminAreaOptions = document.getElementById('adminAreaOptions');
  const adminCityDistrict = document.getElementById('adminCityDistrict');
  const adminClientState = document.getElementById('adminClientState');

  if (adminPinCode && adminPinHint && adminAreaFrom && adminAreaOptions && adminCityDistrict && adminClientState) {
	    let lastAdminPinLookup = '';
	    let adminPinLookupRequest = 0;
    const keepDigitsOnly = (field, limit) => {
      field.value = field.value.replace(/\D+/g, '').slice(0, limit);
    };
    const renderAdminAreaOptions = (areas) => {
      adminAreaOptions.innerHTML = '';
      areas.forEach((area) => {
        const option = document.createElement('option');
        option.value = area;
        adminAreaOptions.appendChild(option);
      });
    };
    const lookupAdminPin = async () => {
      keepDigitsOnly(adminPinCode, 6);
      const pin = adminPinCode.value;
      if (pin.length !== 6) {
        lastAdminPinLookup = '';
        renderAdminAreaOptions([]);
        adminPinHint.textContent = 'Enter 6 digits to auto-fill area, city and state.';
        return;
      }
	      if (pin === lastAdminPinLookup) return;
	      lastAdminPinLookup = pin;
	      const requestId = ++adminPinLookupRequest;
	      adminPinHint.textContent = 'Fetching area details...';
	      try {
		        const response = await fetch('<?php echo app_url('../api/pincode.php', 'admin'); ?>?pin=' + encodeURIComponent(pin), {
		          headers: { Accept: 'application/json' },
		          cache: 'no-store'
		        });
		        const data = await response.json();
		        if (requestId !== adminPinLookupRequest) return;
		        if (!response.ok || !data.ok) throw new Error(data.error || 'No PIN data');
		        const areas = Array.isArray(data.areas) ? data.areas : [];
		        renderAdminAreaOptions(areas);
		        adminAreaFrom.value = data.area || areas[0] || '';
		        adminCityDistrict.value = data.district || '';
		        adminClientState.value = data.state || '';
		        adminPinHint.textContent = areas.length > 1 ? 'Area filled. You can choose another suggestion if needed.' : 'Area details filled from PIN code.';
	      } catch (error) {
	        if (requestId !== adminPinLookupRequest) return;
	        lastAdminPinLookup = '';
	        renderAdminAreaOptions([]);
	        adminAreaFrom.value = '';
        adminCityDistrict.value = '';
        adminClientState.value = '';
        adminPinHint.textContent = 'Could not find this PIN code. Please enter address manually.';
      }
    };
    adminPinCode.addEventListener('input', lookupAdminPin);
    lookupAdminPin();
  }
</script>
<?php admin_page_end(); ?>
