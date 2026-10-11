<?php
require_once __DIR__ . '/../includes/admin_layout.php';
admin_page_start('Booking Details', 'bookings');
$ready = db_ready();
if ($ready) {
    ensure_booking_enquiry_schema();
    ensure_payment_verification_schema();
    ensure_package_schema();
    ensure_event_inspection_schema();
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
        $stmt = db()->prepare('SELECT id, booking_reference, client_name, event_date, event_type, referral_eligible, referral_referrer_bonus, referral_referred_bonus FROM event_bookings WHERE referred_by_reference=? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$booking['booking_reference']]);
        $referrals = $stmt->fetchAll();
    }
    if (!empty($booking['referred_by_reference'])) {
        $stmt = db()->prepare('SELECT id, booking_reference, client_name, event_date, event_type FROM event_bookings WHERE booking_reference=? LIMIT 1');
        $stmt->execute([$booking['referred_by_reference']]);
        $referrerBooking = $stmt->fetch() ?: null;
    }
}

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
$quoteTotal = (float) ($booking['proposal_amount'] ?? 0);
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
        . "Pending booking balance: Rs " . number_format($balance) . "\n"
        . "Total amount due: Rs " . number_format($clientDue) . "\n"
        . "Security deposit is handled separately as per inspection and venue terms.";
}
?>
<div class="top">
  <div><h1><?php echo h($pageTitle); ?></h1><p><?php echo $isEdit ? 'Update booking information and payments from one full screen.' : 'First save the booking information, then add payment details.'; ?></p></div>
  <a class="btn" href="bookings.php">Back to Grid</a>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql in phpMyAdmin first.</div><?php endif; ?>

<?php if ($isEdit): ?>
<section class="summary-strip">
  <div><span>Customer ID</span><strong><?php echo h($booking['phone'] ?? ''); ?></strong></div>
  <div><span>Reference code</span><strong><?php echo h($booking['booking_reference'] ?? ('#' . (int) $bookingId)); ?></strong></div>
  <div><span>Total amount</span><strong>Rs <?php echo h(number_format($quoteTotal)); ?></strong></div>
  <div><span>Paid amount</span><strong>Rs <?php echo h(number_format($paidTotal)); ?></strong></div>
  <div><span>Penalties</span><strong>Rs <?php echo h(number_format($penaltyTotal)); ?></strong></div>
  <div><span>Total due</span><strong>Rs <?php echo h(number_format($clientDue)); ?></strong></div>
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
    <form class="form-grid friendly-form" method="POST" enctype="multipart/form-data" action="actions.php">
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
			      <label>Referred by<input name="referred_by_reference" id="adminReferredBy" value="<?php echo h($booking['referred_by_reference'] ?? ''); ?>" placeholder="ANT-RAM-1234 or 10-digit phone"><span class="form-note">Optional. Eligible for <?php echo $referralEligibilityDays > 0 ? (int) $referralEligibilityDays . ' days after the referrer event' : 'unlimited days'; ?>.</span><span class="field-warning" id="adminReferredByResult"></span></label>
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
      <?php if (!empty($booking['aadhaar_image'])): ?><p class="full form-note">Current Aadhaar image: <a href="../<?php echo h($booking['aadhaar_image']); ?>" target="_blank">View image</a></p><?php endif; ?>
      <button class="btn gold" <?php echo !$ready ? 'disabled' : ''; ?>>Save Information</button>
    </form>
  </div>

	  <div class="tab-panel payment-panel" id="payments">
    <div class="section-head"><div><h2>Payments</h2><p>Enter an amount and save for each payment received. Repeat this for multiple payments from the same client.</p></div></div>
    <?php if (!$isEdit): ?>
      <div class="empty-state">Save the booking information first. After saving, the Payments tab will be ready.</div>
    <?php else: ?>
      <form class="form-grid friendly-form payment-settings payment-entry-form" method="POST" action="actions.php">
        <input type="hidden" name="action" value="save_payment_tab">
        <input type="hidden" name="back" value="booking-form.php?id=<?php echo (int) $bookingId; ?>#payments">
        <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
        <label>Payment status<select name="payment_status"><?php foreach (payment_statuses() as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $selectedPaymentStatus === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select></label>
        <label>Total amount<input type="number" step="0.01" name="proposal_amount" id="paymentTotalAmount" value="<?php echo h($booking['proposal_amount'] ?? ''); ?>"></label>
        <label>Payment note<input name="payment_note" value="<?php echo h($booking['proposal_note'] ?? ''); ?>"></label>
        <label>Amount paid<input name="amount" id="paymentAmountPaid" type="number" step="0.01" min="1" required placeholder="Add next payment amount"><span class="field-warning err" id="paymentAmountWarning"></span></label>
        <label>Payment type<select name="payment_mode"><?php foreach (payment_modes() as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></label>
        <label>Transaction ID<input name="transaction_id"></label>
        <label>Paid on<input name="paid_on" type="date" value="<?php echo date('Y-m-d'); ?>"></label>
        <label>Checked status<select name="verification_status" id="paymentVerificationStatus"><?php foreach (payment_verification_statuses() as $key => $label): ?><option value="<?php echo h($key); ?>"><?php echo h($label); ?></option><?php endforeach; ?></select></label>
        <button class="btn gold compact-save">Save Payment</button>
      </form>

      <div class="table-wrap payment-history"><table class="payment-history-table"><thead><tr><th>Paid on</th><th>Amount</th><th>Type</th><th>Status</th><th>Change status</th></tr></thead><tbody>
        <?php foreach ($payments as $payment): ?>
          <?php $paymentStatusText = payment_verification_statuses()[$payment['verification_status']] ?? status_label($payment['verification_status']); ?>
          <tr>
            <td><?php echo h($payment['paid_on']); ?></td>
            <td><strong>Rs <?php echo h(number_format((float) $payment['amount'])); ?></strong></td>
            <td><?php echo h(status_label($payment['payment_mode'])); ?><br><?php echo h($payment['transaction_id']); ?><br><?php echo h($payment['note']); ?></td>
            <td><span class="badge <?php echo h($payment['verification_status']); ?>"><?php echo h($paymentStatusText); ?></span></td>
            <td>
              <form class="payment-status-form" method="POST" action="actions.php">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="back" value="booking-form.php?id=<?php echo (int) $bookingId; ?>#payments">
                <input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>">
                <select name="verification_status"><?php foreach (payment_verification_statuses() as $key => $label): ?><option value="<?php echo h($key); ?>" <?php echo $payment['verification_status'] === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select>
                <button class="btn gold">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="5">No payments added yet.</td></tr><?php endif; ?>
      </tbody></table></div>
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
      <form class="inspection-checklist" method="POST" action="actions.php">
        <input type="hidden" name="action" value="save_event_inspection">
        <input type="hidden" name="back" value="booking-form.php?id=<?php echo (int) $bookingId; ?>#inspection">
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
          <form class="form-grid friendly-form" method="POST" action="actions.php">
            <input type="hidden" name="action" value="add_penalty">
            <input type="hidden" name="back" value="booking-form.php?id=<?php echo (int) $bookingId; ?>#inspection">
            <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
            <label>Penalty item<input name="penalty_title" required placeholder="3 lights damaged"></label>
            <label>Amount<input name="amount" type="number" min="1" step="0.01" required placeholder="3000"></label>
            <label class="full">Remark<textarea name="penalty_reason" rows="3" required placeholder="Example: Three decorative lights were damaged during vendor removal."></textarea></label>
            <button class="btn gold">Add Penalty</button>
          </form>
        </div>
        <div class="penalty-summary">
          <h2>Charges to Client</h2>
          <div class="charge-total penalty-highlight"><span>Penalty total</span><strong>Rs <?php echo h(number_format($penaltyTotal)); ?></strong></div>
          <div class="charge-total"><span>Total due including balance</span><strong>Rs <?php echo h(number_format($clientDue)); ?></strong></div>
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
              <form method="POST" action="actions.php" onsubmit="return confirm('Delete this penalty?');">
                <input type="hidden" name="action" value="delete_penalty">
                <input type="hidden" name="back" value="booking-form.php?id=<?php echo (int) $bookingId; ?>#inspection">
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
	      <form class="form-grid friendly-form" method="POST" action="actions.php">
	        <input type="hidden" name="action" value="save_referral">
	        <input type="hidden" name="back" value="booking-form.php?id=<?php echo (int) $bookingId; ?>#referrals">
	        <input type="hidden" name="booking_id" value="<?php echo (int) $bookingId; ?>">
	        <label>Referred by reference / phone<input name="referred_by_reference" id="referralTabReferredBy" value="<?php echo h($booking['referred_by_reference'] ?? ''); ?>" placeholder="ANT-RAM-1234 or 10-digit phone"><span class="form-note">Window: <?php echo $referralEligibilityDays > 0 ? (int) $referralEligibilityDays . ' days after the referrer event' : 'No expiry'; ?>.</span><span class="field-warning" id="referralTabReferredByResult"></span></label>
		        <label>Referrer bonus amount<input type="number" min="0" max="<?php echo h((string) $referralBonusCap); ?>" step="0.01" name="referral_referrer_bonus" value="<?php echo h((string) ((float) ($booking['referral_referrer_bonus'] ?? 0) > 0 ? $booking['referral_referrer_bonus'] : $defaultReferralBonus)); ?>"><span class="form-note">Default Rs <?php echo h(number_format($defaultReferralBonus)); ?>. Max bonus Rs <?php echo h(number_format($referralBonusCap)); ?>.</span></label>
		        <label>New member bonus amount<input type="number" min="0" max="<?php echo h((string) $referralBonusCap); ?>" step="0.01" name="referral_referred_bonus" value="<?php echo h((string) ((float) ($booking['referral_referred_bonus'] ?? 0) > 0 ? $booking['referral_referred_bonus'] : $defaultNewMemberBonus)); ?>"><span class="form-note">Default Rs <?php echo h(number_format($defaultNewMemberBonus)); ?>.</span></label>
	        <label class="check-label"><input type="checkbox" name="referral_eligible" value="1" <?php echo !empty($booking['referral_eligible']) ? 'checked' : ''; ?>> Eligible for referral bonus</label>
	        <label class="full">Referral note<textarea name="referral_note" rows="3" placeholder="Eligibility reason, payout note or approval details"><?php echo h($booking['referral_note'] ?? ''); ?></textarea></label>
	        <button class="btn gold">Save Referral</button>
	      </form>
	      <div class="summary-strip" style="margin-top:20px">
	        <div><span>This member code</span><strong><?php echo h($booking['booking_reference'] ?? ''); ?></strong></div>
		        <div><span>Referral count</span><strong><?php echo h((string) count($referrals)); ?> / <?php echo h((string) $referralMaxCount); ?></strong></div>
	        <div><span>Eligibility</span><strong><?php echo !empty($booking['referral_eligible']) ? 'Eligible' : 'Not eligible'; ?></strong></div>
	      </div>
	      <?php if ($referrerBooking): ?>
	        <div class="accepted-term">
	          <span class="term-check" aria-hidden="true">&#8592;</span>
	          <div><strong>Referred by <?php echo h($referrerBooking['client_name']); ?></strong><p><?php echo h($referrerBooking['booking_reference']); ?> - <?php echo h($referrerBooking['event_type'] ?: 'Event'); ?> on <?php echo h($referrerBooking['event_date']); ?></p><small><a href="booking-form.php?id=<?php echo (int) $referrerBooking['id']; ?>">Open referrer booking</a></small></div>
	        </div>
	      <?php endif; ?>
	      <div class="table-wrap payment-history"><table><thead><tr><th>Referred member</th><th>Event</th><th>Eligibility</th><th>Bonuses</th><th>Manage</th></tr></thead><tbody>
	        <?php foreach ($referrals as $referral): ?>
	          <tr>
	            <td><strong><?php echo h($referral['client_name']); ?></strong><br><?php echo h($referral['booking_reference']); ?></td>
	            <td><?php echo h($referral['event_type'] ?: 'Event'); ?><br><?php echo h($referral['event_date']); ?></td>
	            <td><?php echo !empty($referral['referral_eligible']) ? 'Eligible' : 'Not eligible'; ?></td>
	            <td>Referrer: Rs <?php echo h(number_format((float) $referral['referral_referrer_bonus'])); ?><br>New member: Rs <?php echo h(number_format((float) $referral['referral_referred_bonus'])); ?></td>
	            <td><a class="btn" href="booking-form.php?id=<?php echo (int) $referral['id']; ?>#referrals">Open</a></td>
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
		        const response = await fetch('../api/contact-check.php?' + params.toString(), {
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
      if (!value) {
        render('');
        return;
      }
      render('Checking referral member...');
      try {
        const response = await fetch('../api/referral-lookup.php?q=' + encodeURIComponent(value) + '&exclude_id=' + encodeURIComponent(currentBookingId), {
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
        input.value = booking.booking_reference || value;
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
  const existingPaymentTotal = <?php echo json_encode((float) $paymentHistoryTotal); ?>;
  if (paymentTotalAmount && paymentAmountPaid && paymentAmountWarning) {
    const formatRupees = (value) => new Intl.NumberFormat('en-IN', {
      maximumFractionDigits: 0
    }).format(Math.max(0, value));
    const updatePaymentWarning = () => {
      const total = Number(paymentTotalAmount.value || 0);
      const nextPayment = Number(paymentAmountPaid.value || 0);
      const countsAsPaid = !paymentVerificationStatus || paymentVerificationStatus.value !== 'rejected';
      const finalPaid = existingPaymentTotal + (countsAsPaid ? nextPayment : 0);
      if (total > 0 && nextPayment > 0 && finalPaid > total) {
        paymentAmountWarning.textContent = `Warning: paid total will exceed total amount by Rs ${formatRupees(finalPaid - total)}.`;
        paymentAmountWarning.classList.add('show');
      } else {
        paymentAmountWarning.textContent = '';
        paymentAmountWarning.classList.remove('show');
      }
    };
    paymentTotalAmount.addEventListener('input', updatePaymentWarning);
    paymentAmountPaid.addEventListener('input', updatePaymentWarning);
    if (paymentVerificationStatus) paymentVerificationStatus.addEventListener('change', updatePaymentWarning);
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
        const availabilityUrl = '../api/availability.php?month=' + encodeURIComponent(currentMonthKey)
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
		        const response = await fetch('../api/pincode.php?pin=' + encodeURIComponent(pin), {
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
