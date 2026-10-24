<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){
    set_flash('error','Please use an admin form to make changes.');redirect('dashboard.php');
}

if (!db_ready()) {
    set_flash('error', 'Database is not ready. Import database.sql first.');
    redirect('dashboard.php');
}
ensure_payment_verification_schema();
ensure_booking_enquiry_schema();
ensure_package_schema();
ensure_gallery_category_schema();
ensure_blog_images_schema();
ensure_event_inspection_schema();
ensure_rate_card_schema();
ensure_referral_schema();
ensure_pricing_history_schema();

$action = $_POST['action'] ?? '';
$back=is_string($_POST['back'] ?? null)?$_POST['back']:'dashboard.php';
$back=preg_match('/[\x00-\x1f\x7f]/',$back)?app_url('dashboard.php','admin'):app_url($back,'admin');
if(parse_url($back,PHP_URL_HOST)!==null||!str_starts_with($back,app_url('admin/','')))$back=app_url('dashboard.php','admin');
$mailContext = mail_capture_action((string)$action, $_POST);
$bulkJson=$action==='save_packages_bulk' && str_contains($_SERVER['HTTP_ACCEPT'] ?? '','application/json');
$actionError='';

try {
    require_referral_csrf();
    validate_admin_required_spacing((string)$action, $_POST);
    $referralTransaction = in_array($action, ['save_booking_details','save_referral','add_booking','quick_update_booking_status','update_booking','replace_referral_code','save_referral_settings','save_rate_setting','delete_rate_setting'], true);
    if ($referralTransaction) {
        db()->beginTransaction();
        db()->query("SELECT setting_key FROM rate_card_settings WHERE setting_key='referral_code_usage_limit' FOR UPDATE")->fetch();
    }
    if(in_array($action,['save_booking_details','add_booking','quick_update_booking_status','update_booking'],true))validate_admin_booking_request($_POST);
    if (in_array($action,['save_payment_tab','add_payment','update_payment'],true)) {
        db()->beginTransaction();
        $paymentBookingId=(int)($_POST['booking_id'] ?? 0);
        if ($action==='update_payment') {
            $lookup=db()->prepare('SELECT booking_id FROM payments WHERE id=?');$lookup->execute([(int)($_POST['payment_id'] ?? 0)]);
            $paymentBookingId=(int)$lookup->fetchColumn();
        }
        $lock=db()->prepare('SELECT id FROM event_bookings WHERE id=? FOR UPDATE');$lock->execute([$paymentBookingId]);
        if (!$lock->fetchColumn()) throw new RuntimeException('Booking was not found.');
    }
    if ($action === 'save_booking_details') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $createdBooking = false;
        $image = upload_image('aadhaar_image', 'uploads/clients');
        $bookingStatus = $_POST['booking_status'] ?? 'pending';
        $clientName = trim($_POST['client_name'] ?? '');
        if ($clientName === '') throw new RuntimeException('Please enter full name.');
        $phone = preg_replace('/\D+/', '', trim($_POST['phone'] ?? ''));
        $whatsappNumber = preg_replace('/\D+/', '', trim($_POST['whatsapp_number'] ?? '')) ?: null;
        $email = strtolower(trim($_POST['email'] ?? ''));
        $referralInput = trim($_POST['referred_by_reference'] ?? '');
        $referredBy = '';
        if ($phone === '') {
            throw new RuntimeException('Phone number is required.');
        }
        if (!preg_match('/^\d{10}$/', $phone)) {
            throw new RuntimeException('Please enter a valid 10-digit phone number.');
        }
        if ($whatsappNumber !== null && !preg_match('/^\d{10}$/', $whatsappNumber)) {
            throw new RuntimeException('Please enter a valid 10-digit WhatsApp number.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        $sameCustomerPhone='';
        if($bookingId>0){
            $original=db()->prepare('SELECT phone FROM event_bookings WHERE id=?');$original->execute([$bookingId]);
            $originalPhone=$original->fetchColumn();
            if($originalPhone && customer_phone($originalPhone)===$phone)$sameCustomerPhone=$phone;
        }
        $duplicateContactMessage = duplicate_booking_contact_message($phone, $email, $bookingId > 0 ? $bookingId : null, $whatsappNumber ?? '', false, $sameCustomerPhone);
        if ($duplicateContactMessage !== '') {
            throw new RuntimeException($duplicateContactMessage);
        }

        if ($bookingId > 0) {
            $stmt = db()->prepare("SELECT * FROM event_bookings WHERE id=? FOR UPDATE");
            $stmt->execute([$bookingId]);
            $currentBooking = $stmt->fetch() ?: [];
            if ($referralInput !== '') {
                $referredBy = resolve_referral_reference($referralInput, $bookingId) ?? '';
                if ($referredBy === normalize_booking_reference((string) ($currentBooking['booking_reference'] ?? ''))) {
                    throw new RuntimeException('A booking cannot refer itself.');
                }
                if ($referredBy === '') {
                    throw new RuntimeException('Referred by reference or phone number was not found or is no longer eligible.');
                }
            }
            $image = $image ?: (($currentBooking['aadhaar_image'] ?? '') ?: null);
            $paymentStatus = $_POST['payment_status'] ?? ($currentBooking['payment_status'] ?? 'pending');
            $proposalAmount = array_key_exists('proposal_amount', $_POST) ? ($_POST['proposal_amount'] ?: null) : ($currentBooking['proposal_amount'] ?? null);
            $proposalNote = array_key_exists('proposal_note', $_POST) ? trim($_POST['proposal_note'] ?? '') : ($currentBooking['proposal_note'] ?? '');

            $referrerBonus = $referredBy !== '' ? referral_referrer_bonus_for($referredBy) : 0;
            $newMemberBonus = $referredBy !== '' ? referral_new_member_bonus_default() : 0;
            $stmt = db()->prepare("UPDATE event_bookings SET client_name=?, phone=?, whatsapp_number=?, email=?, date_of_birth=?, occupation=?, pin_code=?, area_from=?, city_district=?, client_state=?, aadhaar_number=?, aadhaar_image=?, event_date=?, event_type=?, guests=?, package_id=?, note=?, booking_status=?, payment_status=?, proposal_amount=?, proposal_note=? WHERE id=?");
            $stmt->execute([
                $clientName,
                $phone,
                $whatsappNumber,
                $email ?: null,
                $_POST['date_of_birth'] ?: null,
                trim($_POST['occupation'] ?? '') ?: null,
                trim($_POST['pin_code'] ?? '') ?: null,
                trim($_POST['area_from'] ?? ''),
                trim($_POST['city_district'] ?? '') ?: null,
                trim($_POST['client_state'] ?? '') ?: null,
                trim($_POST['aadhaar_number'] ?? ''),
                $image,
                $_POST['event_date'] ?? '',
                trim($_POST['event_type'] ?? ''),
                $_POST['guests'] ?: null,
                $_POST['package_id'] ?: null,
                trim($_POST['note'] ?? ''),
                $bookingStatus,
                $paymentStatus,
                $proposalAmount,
                $proposalNote,
                $bookingId,
            ]);
            set_flash('ok', 'Booking information saved.');
		        } else {
			            if ($referralInput !== '') {
			                $referredBy = resolve_referral_reference($referralInput) ?? '';
			                if ($referredBy === '') {
				                    throw new RuntimeException('Referred by reference or phone number was not found or is no longer eligible.');
			                }
			            }
			            $bookingReference = generate_unique_booking_reference($clientName);
				            $referrerBonus = $referredBy !== '' ? referral_referrer_bonus_for($referredBy) : 0;
				            $newMemberBonus = $referredBy !== '' ? referral_new_member_bonus_default() : 0;
			            $stmt = db()->prepare("INSERT INTO event_bookings
			                (booking_reference, referred_by_reference, client_name, phone, whatsapp_number, email, date_of_birth, occupation, pin_code, area_from, city_district, client_state, aadhaar_number, aadhaar_image, event_date, event_type, guests, package_id, note, booking_status, payment_status, proposal_amount, proposal_note, referral_eligible, referral_referrer_bonus, referral_referred_bonus, created_by)
			                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin')");
			            $stmt->execute([
			                $bookingReference,
				                $referredBy ?: null,
			                $clientName,
			                $phone,
		                $whatsappNumber,
	                $email ?: null,
                $_POST['date_of_birth'] ?: null,
                trim($_POST['occupation'] ?? '') ?: null,
                trim($_POST['pin_code'] ?? '') ?: null,
                trim($_POST['area_from'] ?? ''),
                trim($_POST['city_district'] ?? '') ?: null,
                trim($_POST['client_state'] ?? '') ?: null,
                trim($_POST['aadhaar_number'] ?? ''),
                $image,
                $_POST['event_date'] ?? '',
                trim($_POST['event_type'] ?? ''),
                $_POST['guests'] ?: null,
                $_POST['package_id'] ?: null,
                trim($_POST['note'] ?? ''),
                $bookingStatus,
	                $_POST['payment_status'] ?? 'pending',
	                $_POST['proposal_amount'] ?: null,
	                trim($_POST['proposal_note'] ?? ''),
	                $referredBy !== '' ? 1 : 0,
	                $referrerBonus,
	                $newMemberBonus,
	            ]);
            $bookingId = (int) db()->lastInsertId();
            $createdBooking = true;
            set_flash('ok', 'Booking added. You can add payment now.');
        }

        if ($action === 'save_booking_details') {
            member_referral_code($bookingId);
            $sameReferral = !empty($currentBooking['referred_by_reference']) && $currentBooking['referred_by_reference'] === $referredBy;
            save_booking_referral($bookingId, $referralInput,
                $sameReferral ? !empty($currentBooking['referral_eligible']) : true,
                $sameReferral ? (float)$currentBooking['referral_referrer_bonus'] : null,
                $sameReferral ? (float)$currentBooking['referral_referred_bonus'] : null,
                $currentBooking['referral_note'] ?? null);
        }

        sync_cancelled_referrals();
        db()->commit();
        notify_admin_action((string)$action, $_POST, $mailContext, (int)$bookingId);
        redirect('booking-form.php?id=' . $bookingId . ($createdBooking ? '#payments' : ''));
    } elseif ($action === 'quick_update_booking_status') {
        $bookingId = (int) $_POST['booking_id'];
        $bookingStatus = $_POST['booking_status'] ?? 'pending';
        $paymentStatus = $_POST['payment_status'] ?? 'pending';

        if (!array_key_exists($bookingStatus, booking_statuses())) {
            $bookingStatus = 'pending';
        }
        if (!array_key_exists($paymentStatus, payment_statuses())) {
            $paymentStatus = 'pending';
        }

        db()->prepare("UPDATE event_bookings SET booking_status=?, payment_status=? WHERE id=?")->execute([
            $bookingStatus,
            $paymentStatus,
            $bookingId,
        ]);

        set_flash('ok', 'Status updated.');
    } elseif ($action === 'save_referral') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        if ($bookingId <= 0) {
            throw new RuntimeException('Booking is required.');
        }
        require_referral_csrf();
        save_booking_referral($bookingId, trim($_POST['referred_by_reference'] ?? ''), isset($_POST['referral_eligible']),
            isset($_POST['referral_referrer_bonus']) && $_POST['referral_referrer_bonus'] !== '' ? (float)$_POST['referral_referrer_bonus'] : null,
            isset($_POST['referral_referred_bonus']) && $_POST['referral_referred_bonus'] !== '' ? (float)$_POST['referral_referred_bonus'] : null,
            trim($_POST['referral_note'] ?? '') ?: null);
        set_flash('ok', 'Referral details saved.');
    } elseif ($action === 'add_booking') {
		        $image = upload_image('aadhaar_image', 'uploads/clients');
		        $clientName = trim($_POST['client_name'] ?? '');
        if ($clientName === '') throw new RuntimeException('Please enter full name.');
		        $phone = preg_replace('/\D+/', '', trim($_POST['phone'] ?? ''));
		        $whatsappNumber = preg_replace('/\D+/', '', trim($_POST['whatsapp_number'] ?? '')) ?: null;
		        $email = strtolower(trim($_POST['email'] ?? ''));
			        if ($phone === '') {
			            throw new RuntimeException('Phone number is required.');
			        }
			        if (!preg_match('/^\d{10}$/', $phone)) {
			            throw new RuntimeException('Please enter a valid 10-digit phone number.');
			        }
			        if ($whatsappNumber !== null && !preg_match('/^\d{10}$/', $whatsappNumber)) {
			            throw new RuntimeException('Please enter a valid 10-digit WhatsApp number.');
			        }
			        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			            throw new RuntimeException('Please enter a valid email address.');
			        }
		        $duplicateContactMessage = duplicate_booking_contact_message($phone, $email, null, $whatsappNumber ?? '');
		        if ($duplicateContactMessage !== '') {
		            throw new RuntimeException($duplicateContactMessage);
		        }
			        $bookingReference = generate_unique_booking_reference($clientName);
			        $referrerBonus = 0;
			        $newMemberBonus = 0;
		        $stmt = db()->prepare("INSERT INTO event_bookings
		            (booking_reference, client_name, phone, whatsapp_number, email, date_of_birth, occupation, pin_code, area_from, city_district, client_state, aadhaar_number, aadhaar_image, event_date, event_type, guests, package_id, note, booking_status, payment_status, proposal_amount, proposal_note, referral_eligible, referral_referrer_bonus, referral_referred_bonus, created_by)
		            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'admin')");
		        $stmt->execute([
		            $bookingReference,
		            trim($_POST['client_name'] ?? ''),
		            $phone,
	            $whatsappNumber,
	            $email ?: null,
            $_POST['date_of_birth'] ?: null,
            trim($_POST['occupation'] ?? '') ?: null,
            trim($_POST['pin_code'] ?? '') ?: null,
            trim($_POST['area_from'] ?? ''),
            trim($_POST['city_district'] ?? '') ?: null,
            trim($_POST['client_state'] ?? '') ?: null,
            trim($_POST['aadhaar_number'] ?? ''),
            $image,
            $_POST['event_date'] ?? '',
            trim($_POST['event_type'] ?? ''),
            $_POST['guests'] ?: null,
            $_POST['package_id'] ?: null,
            trim($_POST['note'] ?? ''),
            $_POST['booking_status'] ?? 'pending',
	            $_POST['payment_status'] ?? 'pending',
	            $_POST['proposal_amount'] ?: null,
	            trim($_POST['proposal_note'] ?? ''),
	            $referrerBonus,
	            $newMemberBonus,
	        ]);
        $bookingId = (int) db()->lastInsertId();
        member_referral_code($bookingId);
        save_booking_referral($bookingId,'');
        set_flash('ok', 'Client booking added.');
    } elseif ($action === 'update_booking') {
        $bookingId = (int) $_POST['booking_id'];
        $bookingStatus = $_POST['booking_status'] ?? 'pending';
        $paymentStatus = $_POST['payment_status'] ?? 'pending';
        $proposalAmount = $_POST['proposal_amount'] ?: null;
        $proposalNote = trim($_POST['proposal_note'] ?? '');

        $stmt = db()->prepare("UPDATE event_bookings SET booking_status=?, payment_status=?, proposal_amount=?, proposal_note=? WHERE id=?");
        $stmt->execute([
            $bookingStatus,
            $paymentStatus,
            $proposalAmount,
            $proposalNote,
            $bookingId,
        ]);

        set_flash('ok', 'Booking updated.');
    } elseif ($action === 'add_payment') {
        $bookingId = (int) $_POST['booking_id'];
        $verificationStatus = $_POST['verification_status'] ?? 'pending';
        if (!array_key_exists($verificationStatus, payment_verification_statuses())) {
            $verificationStatus = 'pending';
        }
        $stmt = db()->prepare("INSERT INTO payments (booking_id, amount, payment_mode, transaction_id, paid_on, note, verification_status, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $bookingId,
            $_POST['amount'],
            $_POST['payment_mode'],
            trim($_POST['transaction_id'] ?? ''),
            $_POST['paid_on'] ?: date('Y-m-d'),
            trim($_POST['payment_note'] ?? ''),
            $verificationStatus,
            $verificationStatus === 'verified' ? date('Y-m-d H:i:s') : null,
        ]);
        sync_booking_payment($bookingId);
        set_flash('ok', 'Payment recorded. Check it, then inform the customer.');
    } elseif ($action === 'save_payment_tab') {
        require_referral_csrf();
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $pricingOnly = ($_POST['save_pricing'] ?? '') === '1';
        if (!$pricingOnly) {
            $amount = pricing_money_cents($_POST['amount'] ?? '', 'payment amount');
            if ($amount <= 0) throw new RuntimeException('Please enter a payment amount greater than zero.');
            $mode = $_POST['payment_mode'] ?? 'cash';
            if (!array_key_exists($mode,payment_modes())) throw new RuntimeException('Please select a valid payment type.');
            $paidOn = trim((string)($_POST['paid_on'] ?? '')) ?: date('Y-m-d');
            $paidDate = DateTimeImmutable::createFromFormat('!Y-m-d',$paidOn);
            if (!$paidDate || $paidDate->format('Y-m-d') !== $paidOn) throw new RuntimeException('Please select a valid payment date.');
        }
        save_booking_pricing($bookingId,$_POST);
        if (!$pricingOnly) {
            $verificationStatus = $_POST['verification_status'] ?? 'pending';
            if (!array_key_exists($verificationStatus,payment_verification_statuses())) throw new RuntimeException('Please select a valid checked status.');
            db()->prepare('INSERT INTO payments(booking_id,amount,payment_mode,transaction_id,paid_on,note,verification_status,verified_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$bookingId,number_format($amount/100,2,'.',''),$mode,trim($_POST['transaction_id'] ?? ''),$paidOn,trim($_POST['payment_note'] ?? ''),$verificationStatus,$verificationStatus==='verified' ? date('Y-m-d H:i:s') : null]);
        }
        sync_booking_payment($bookingId);
        set_flash('ok',$pricingOnly ? 'Total, discount and itemised charges saved. No payment was added.' : 'Payment and itemised booking total saved.');
    } elseif ($action === 'update_payment') {
        $paymentId = (int) $_POST['payment_id'];
        $verificationStatus = $_POST['verification_status'] ?? 'pending';
        if (!array_key_exists($verificationStatus, payment_verification_statuses())) {
            $verificationStatus = 'pending';
        }
        $stmt = db()->prepare("SELECT booking_id FROM payments WHERE id=?");
        $stmt->execute([$paymentId]);
        $bookingId = (int) $stmt->fetchColumn();
        db()->prepare("UPDATE payments SET verification_status=?, verified_at=? WHERE id=?")->execute([
            $verificationStatus,
            $verificationStatus === 'verified' ? date('Y-m-d H:i:s') : null,
            $paymentId,
        ]);
        if ($bookingId > 0) {
            sync_booking_payment($bookingId);
        }
        set_flash('ok', 'Payment status updated.');
    } elseif ($action === 'save_event_inspection') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        if ($bookingId <= 0) {
            throw new RuntimeException('Booking is required.');
        }

        $payload = [
            $bookingId,
            isset($_POST['lights_ok']) ? 1 : 0,
            isset($_POST['rooms_ok']) ? 1 : 0,
            isset($_POST['lawn_ok']) ? 1 : 0,
            isset($_POST['hall_ok']) ? 1 : 0,
            isset($_POST['washrooms_ok']) ? 1 : 0,
            isset($_POST['cleanup_ok']) ? 1 : 0,
            trim($_POST['inspection_note'] ?? '') ?: null,
            date('Y-m-d H:i:s'),
        ];

        $stmt = db()->prepare("INSERT INTO event_inspections
            (booking_id, lights_ok, rooms_ok, lawn_ok, hall_ok, washrooms_ok, cleanup_ok, inspection_note, inspected_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              lights_ok=VALUES(lights_ok),
              rooms_ok=VALUES(rooms_ok),
              lawn_ok=VALUES(lawn_ok),
              hall_ok=VALUES(hall_ok),
              washrooms_ok=VALUES(washrooms_ok),
              cleanup_ok=VALUES(cleanup_ok),
              inspection_note=VALUES(inspection_note),
              inspected_at=VALUES(inspected_at)");
        $stmt->execute($payload);
        set_flash('ok', 'Inspection checklist saved.');
    } elseif ($action === 'add_penalty') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $title = trim($_POST['penalty_title'] ?? '');
        $reason = trim($_POST['penalty_reason'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($bookingId <= 0 || $title === '' || $reason === '' || $amount <= 0) {
            throw new RuntimeException('Penalty item, remark and valid amount are required.');
        }

        $stmt = db()->prepare('INSERT INTO event_penalties (booking_id, penalty_title, penalty_reason, amount) VALUES (?, ?, ?, ?)');
        $stmt->execute([$bookingId, $title, $reason, $amount]);
        set_flash('ok', 'Penalty added.');
    } elseif ($action === 'delete_penalty') {
        $penaltyId = (int) ($_POST['penalty_id'] ?? 0);
        if ($penaltyId <= 0) {
            throw new RuntimeException('Penalty is required.');
        }
        $stmt = db()->prepare('DELETE FROM event_penalties WHERE id=?');
        $stmt->execute([$penaltyId]);
        set_flash('ok', 'Penalty deleted.');
    } elseif ($action === 'save_review') {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $name = trim($_POST['client_name'] ?? '');
        $text = trim($_POST['review_text'] ?? '');
        $status = $_POST['status'] ?? 'published';
        if ($name === '' || $text === '') {
            throw new RuntimeException('Client name and review text are required.');
        }
        if (!in_array($status, ['pending', 'published', 'hidden'], true)) {
            $status = 'published';
        }
        $payload = [
            $name,
            trim($_POST['event_type'] ?? '') ?: null,
            max(1, min(5, (int) ($_POST['rating'] ?? 5))),
            $text,
            $status,
        ];
        if ($reviewId > 0) {
            $stmt = db()->prepare('UPDATE reviews SET client_name=?, event_type=?, rating=?, review_text=?, status=? WHERE id=?');
            $stmt->execute([...$payload, $reviewId]);
            set_flash('ok', 'Review updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by) VALUES (?, ?, ?, ?, ?, "admin")');
            $stmt->execute($payload);
            set_flash('ok', 'Review added.');
        }
    } elseif ($action === 'delete_review') {
        $stmt = db()->prepare('DELETE FROM reviews WHERE id=?');
        $stmt->execute([(int) $_POST['review_id']]);
        set_flash('ok', 'Review deleted.');
    } elseif ($action === 'review_status') {
        $stmt = db()->prepare("UPDATE reviews SET status=? WHERE id=?");
        $stmt->execute([$_POST['status'], (int) $_POST['review_id']]);
        set_flash('ok', 'Review updated.');
    } elseif ($action === 'add_review') {
        $stmt = db()->prepare("INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by) VALUES (?, ?, ?, ?, 'published', 'admin')");
        $stmt->execute([trim($_POST['client_name']), trim($_POST['event_type']), (int) $_POST['rating'], trim($_POST['review_text'])]);
        set_flash('ok', 'Review added.');
    } elseif ($action === 'add_gallery') {
        $category = trim($_POST['new_category'] ?? '');
        if ($category === '') {
            $category = trim($_POST['category'] ?? '');
        }
        if ($category === '') {
            throw new RuntimeException('Please select or add a category.');
        }
        $title=trim((string)($_POST['title'] ?? ''));
        if($title===''||mb_strlen($title)>180||mb_strlen($category)>120)throw new RuntimeException('Please enter a valid image title and category.');
        $image=upload_image('gallery_image','uploads/gallery');
        if(!$image)throw new RuntimeException('Please upload a JPG, PNG or WEBP image.');
        if(trim($_POST['new_category'] ?? '')!==''){
            $stmt=db()->prepare('INSERT INTO gallery_categories(category_name,display_order,is_active) VALUES (?,10,1) ON DUPLICATE KEY UPDATE is_active=1');$stmt->execute([$category]);
        }
        $stmt = db()->prepare("INSERT INTO gallery_images (title, category, image_path, display_order, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([trim($_POST['title']), $category, $image, (int) $_POST['display_order']]);
        set_flash('ok', 'Gallery image added.');
    } elseif ($action === 'save_gallery_image') {
        reject_required_leading_spaces($_POST,['title'=>'Image title','category'=>'Category']);
        $title=trim((string)($_POST['title'] ?? ''));$category=trim((string)($_POST['category'] ?? ''));
        if($title===''||mb_strlen($title)>180||$category===''||mb_strlen($category)>120)throw new RuntimeException('Please enter an image title and category within their length limits.');
        $order=filter_var($_POST['display_order'] ?? '',FILTER_VALIDATE_INT);
        if($order===false)throw new RuntimeException('Please enter a whole number for image sequence.');
        $stmt=db()->prepare('UPDATE gallery_images SET title=?,category=?,display_order=?,is_active=? WHERE id=?');
        $stmt->execute([$title,$category,$order,isset($_POST['is_active'])?1:0,(int)($_POST['image_id'] ?? 0)]);
        set_flash('ok','Gallery image updated.');
    } elseif ($action === 'save_gallery_category') {
        $categoryName = trim($_POST['category_name'] ?? '');
        if ($categoryName === '') {
            throw new RuntimeException('Category name is required.');
        }
        $stmt = db()->prepare('INSERT INTO gallery_categories (category_name, display_order, is_active) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_order=VALUES(display_order), is_active=VALUES(is_active)');
        $stmt->execute([
            $categoryName,
            (int) ($_POST['display_order'] ?? 0),
            (int) ($_POST['is_active'] ?? 1),
        ]);
        set_flash('ok', 'Gallery category saved. You can select it now.');
    } elseif ($action === 'delete_gallery') {
        $stmt = db()->prepare("DELETE FROM gallery_images WHERE id=?");
        $stmt->execute([(int) $_POST['image_id']]);
        set_flash('ok', 'Gallery image deleted.');
    } elseif ($action === 'add_blog_image') {
        $pageSlug = trim($_POST['page_slug'] ?? '');
        if (!array_key_exists($pageSlug, seo_blog_pages())) {
            throw new RuntimeException('Please select a valid blog page.');
        }
        $image = upload_image('blog_image', 'uploads/blogs');
        if (!$image) {
            throw new RuntimeException('Please upload JPG, PNG or WEBP image.');
        }
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            throw new RuntimeException('Image title is required.');
        }
        $altText = trim($_POST['alt_text'] ?? '') ?: $title;
	        $stmt = db()->prepare("INSERT INTO blog_images (page_slug, title, alt_text, image_path, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
	        $stmt->execute([$pageSlug, $title, $altText, $image, (int) ($_POST['display_order'] ?? 10)]);
	        set_flash('ok', 'Blog image added.');
	    } elseif ($action === 'save_blog_image') {
	        $imageId = (int) ($_POST['image_id'] ?? 0);
	        $title = trim($_POST['title'] ?? '');
	        if ($imageId <= 0 || $title === '') {
	            throw new RuntimeException('Blog image and title are required.');
	        }
	        $altText = trim($_POST['alt_text'] ?? '') ?: $title;
	        $stmt = db()->prepare('UPDATE blog_images SET title=?, alt_text=?, display_order=? WHERE id=?');
	        $stmt->execute([$title, $altText, (int) ($_POST['display_order'] ?? 10), $imageId]);
	        set_flash('ok', 'Blog image updated.');
	    } elseif ($action === 'delete_blog_image') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        if ($imageId <= 0) {
            throw new RuntimeException('Blog image is required.');
        }
        $stmt = db()->prepare('UPDATE blog_images SET is_active=0 WHERE id=?');
        $stmt->execute([$imageId]);
        set_flash('ok', 'Blog image removed from the public page.');
    } elseif ($action === 'save_packages_bulk') {
        require_referral_csrf();
        if(($_POST['submission_complete'] ?? '')!=='1')throw new RuntimeException('The package changes were incomplete. Please save fewer rows at once.');
        if(!is_array($_POST['packages'] ?? [])||!is_array($_POST['deleted_packages'] ?? []))throw new RuntimeException('Please enter valid package changes.');
        $bulkResult=save_packages_bulk($_POST['packages'] ?? [],$_POST['deleted_packages'] ?? []);
        set_flash('ok','Package changes saved. '.$bulkResult['added'].' added, '.$bulkResult['updated'].' updated, '.$bulkResult['removed'].' removed.');
    } elseif ($action === 'save_package') {
        $packageId = (int) ($_POST['package_id'] ?? 0);
        $name = trim($_POST['package_name'] ?? '');
        $guestMin = (int) ($_POST['guest_min'] ?? 0);
        $guestMax = (int) ($_POST['guest_max'] ?? 0);
        if ($name === '') {
            throw new RuntimeException('Package name is required.');
        }
        if ($guestMin < 0 || $guestMax < 0 || $guestMax < $guestMin) {
            throw new RuntimeException('Please enter a valid guest range.');
        }
        $range = $guestMin . ' to ' . $guestMax . ' guests';
        $payload = [
            $name,
            $range,
            $_POST['price'] !== '' ? $_POST['price'] : null,
            min(100, max(0, (float) ($_POST['discount_percent'] ?? 0))),
            trim($_POST['discount_reason'] ?? '') ?: null,
            (int) ($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($packageId > 0) {
            $stmt = db()->prepare('UPDATE event_packages SET package_name=?, guest_range=?, price=?, discount_percent=?, discount_reason=?, display_order=?, is_active=? WHERE id=?');
            $stmt->execute([...$payload, $packageId]);
            set_flash('ok', 'Package updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO event_packages (package_name, guest_range, price, discount_percent, discount_reason, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute($payload);
            set_flash('ok', 'Package added.');
        }
    } elseif ($action === 'delete_package') {
        $stmt = db()->prepare('DELETE FROM event_packages WHERE id=?');
        $stmt->execute([(int) $_POST['package_id']]);
        set_flash('ok', 'Package deleted.');
    } elseif ($action === 'replace_referral_code') {
        require_referral_csrf();
        $code = member_referral_code((int)($_POST['booking_id'] ?? 0), true, (int)($_POST['expected_code_id'] ?? 0));
        set_flash('ok', 'New referral code: ' . $code['code'] . '. The previous code is retired.');
    } elseif ($action === 'save_referral_settings') {
        require_referral_csrf();
        $limit = filter_var($_POST['usage_limit'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100000]]);
        if ($limit === false) throw new RuntimeException('Please enter a whole-number usage limit from 1 to 100000.');
        $days = filter_var($_POST['eligibility_days'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>36500]]);
        if ($days === false) throw new RuntimeException('Please enter a valid expiry period (0 means no expiry).');
        db()->prepare("SELECT setting_key FROM rate_card_settings WHERE setting_key='referral_code_usage_limit' FOR UPDATE")->execute();
        $previous = ['usage_limit'=>referral_code_usage_limit(),'eligibility_days'=>referral_eligibility_days()];
        foreach (['referral_code_usage_limit'=>$limit,'referral_eligibility_days'=>$days] as $key=>$value) {
            db()->prepare('UPDATE rate_card_settings SET setting_value=? WHERE setting_key=?')->execute([$value,$key]);
        }
        if (isset($_POST['apply_current'])) {
            db()->prepare("UPDATE referral_codes SET usage_limit=?,status=IF(usage_count>=?,'exhausted','active') WHERE active_booking_id IS NOT NULL")->execute([$limit,$limit]);
        }
        referral_log('settings_updated',null,null,null,json_encode(['previous'=>$previous,'usage_limit'=>$limit,'eligibility_days'=>$days,'apply_current'=>isset($_POST['apply_current'])]));
        set_flash('ok','Referral settings saved. Codes are replaced only by admin. Existing expiry dates are retained; replacement codes use the new expiry period.');
    } elseif ($action === 'save_rate_setting') {
        $key = trim($_POST['setting_key'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            throw new RuntimeException('Rule name is required.');
        }
        if ($key === '') {
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
            $key = trim($key, '_') ?: 'custom_rate';
            $key .= '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
        if (str_starts_with($key,'referral_')) require_referral_csrf();
        $value = max(0, (float) ($_POST['setting_value'] ?? 0));
        if ($key === 'referral_code_usage_limit' && ($value < 1 || $value > 100000 || floor($value) !== $value)) throw new RuntimeException('Referral code usage limit must be a whole number from 1 to 100000.');
        $suffix = trim($_POST['suffix'] ?? '') ?: null;
        $displayOrder = (int) ($_POST['display_order'] ?? 0);
        $stmt = db()->prepare('INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE label=VALUES(label), setting_value=VALUES(setting_value), suffix=VALUES(suffix), display_order=VALUES(display_order)');
        $stmt->execute([$key, $label, $value, $suffix, $displayOrder]);
        if (str_starts_with($key,'referral_')) referral_log('settings_updated',null,null,null,json_encode(['key'=>$key,'value'=>$value,'source'=>'rate_card']));
        set_flash('ok', 'Rate setting updated.');
    } elseif ($action === 'delete_rate_setting') {
        $key = trim($_POST['setting_key'] ?? '');
        if ($key === '') {
            throw new RuntimeException('Rate setting is required.');
        }
        if (str_starts_with($key,'referral_')) { require_referral_csrf(); referral_log('settings_deleted',null,null,null,json_encode(['key'=>$key,'source'=>'rate_card','note'=>'Built-in defaults are restored on the next request.'])); }
        $stmt = db()->prepare('DELETE FROM rate_card_settings WHERE setting_key=?');
        $stmt->execute([$key]);
        set_flash('ok', 'Rate setting deleted.');
    } elseif ($action === 'save_term') {
        ensure_terms_schema();
	        $termId = (int) ($_POST['term_id'] ?? 0);
	        $title = trim($_POST['title'] ?? '');
	        $text = trim($_POST['term_text'] ?? '');
	        $isActive = isset($_POST['is_active']) ? 1 : 0;
	        $isBookingRequired = $isActive && isset($_POST['is_booking_required']) ? 1 : 0;
	        if ($title === '' || $text === '') {
	            throw new RuntimeException('Title and terms text are required.');
	        }
	        if ($isBookingRequired) {
	            $stmt = db()->prepare('SELECT COUNT(*) FROM site_terms WHERE is_booking_required=1 AND id<>?');
	            $stmt->execute([$termId]);
	            if ((int) $stmt->fetchColumn() >= 3) {
	                throw new RuntimeException('Only three terms can be marked as Booking check.');
	            }
	        }
	        $payload = [
	            $title,
	            $text,
	            (int) ($_POST['display_order'] ?? 0),
	            $isBookingRequired,
	            $isActive,
	        ];
	        if ($termId > 0) {
	            $stmt = db()->prepare('UPDATE site_terms SET title=?, term_text=?, display_order=?, is_booking_required=?, is_active=? WHERE id=?');
	            $stmt->execute([...$payload, $termId]);
	            set_flash('ok', 'Terms updated.');
	        } else {
	            $stmt = db()->prepare('INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active) VALUES (?, ?, ?, ?, ?)');
	            $stmt->execute($payload);
            set_flash('ok', 'Terms added.');
        }
    } elseif ($action === 'delete_term') {
        ensure_terms_schema();
        $stmt = db()->prepare('DELETE FROM site_terms WHERE id=?');
        $stmt->execute([(int) $_POST['term_id']]);
        set_flash('ok', 'Terms deleted.');
    } else throw new RuntimeException('This admin action is not supported. Please refresh the page and try again.');
    if (db()->inTransaction()) {
        if ($referralTransaction) sync_cancelled_referrals();
        db()->commit();
    }
    notify_admin_action((string)$action, $_POST, $mailContext, (int)($bookingId ?? 0));
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $actionError=$e->getMessage();
    set_flash('error', $e->getMessage());
}

if($bulkJson){
    header('Content-Type: application/json');header('Cache-Control: no-store');
    if($actionError!==''){http_response_code(422);unset($_SESSION['flash']);}
    echo json_encode(['ok'=>$actionError==='','error'=>$actionError,'counts'=>$bulkResult ?? null]);exit;
}
redirect($back);
