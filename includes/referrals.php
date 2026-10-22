<?php
/** Referral codes are permanent records; retired codes are never reissued. */
function ensure_reference_registry_schema(): void
{
    static $ready = false;
    if ($ready) return;
    db()->exec("CREATE TABLE IF NOT EXISTS issued_reference_codes (code VARCHAR(80) NOT NULL PRIMARY KEY, kind VARCHAR(20) NOT NULL, issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    db()->exec("INSERT IGNORE INTO issued_reference_codes (code,kind) SELECT booking_reference,'booking' FROM event_bookings WHERE booking_reference IS NOT NULL AND booking_reference<>''");
    $ready = true;
}

function reserve_reference_code(string $prefix, string $name, string $kind): string
{
    ensure_reference_registry_schema();
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = $prefix . '-' . booking_reference_name_part($name) . '-' . strtoupper(bin2hex(random_bytes(8)));
        try {
            db()->prepare('INSERT INTO issued_reference_codes (code,kind) VALUES (?,?)')->execute([$code, $kind]);
            return $code;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
        }
    }
    throw new RuntimeException('Could not reserve a unique reference. Please try again.');
}

function ensure_referral_schema(): void
{
    static $ready = false;
    if ($ready) return;
    ensure_booking_enquiry_schema();
    ensure_rate_card_schema();
    ensure_reference_registry_schema();
    db()->exec("CREATE TABLE IF NOT EXISTS referral_codes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        code VARCHAR(80) NOT NULL,
        active_booking_id INT DEFAULT NULL,
        status ENUM('active','exhausted','retired') NOT NULL DEFAULT 'active',
        usage_limit INT NOT NULL,
        usage_count INT NOT NULL DEFAULT 0,
        expires_on DATE DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        retired_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uq_referral_code(code), UNIQUE KEY uq_active_referral(active_booking_id), KEY idx_referral_owner(booking_id)
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS referral_uses (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_id BIGINT UNSIGNED NOT NULL,
        referrer_booking_id INT NOT NULL,
        referred_booking_id INT NOT NULL,
        status ENUM('applied','removed') NOT NULL DEFAULT 'applied',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_referred_booking(referred_booking_id), KEY idx_referrer(referrer_booking_id), KEY idx_used_code(code_id)
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS referral_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(40) NOT NULL,
        code_id BIGINT UNSIGNED DEFAULT NULL,
        referrer_booking_id INT DEFAULT NULL,
        referred_booking_id INT DEFAULT NULL,
        actor VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_referral_log_owner(referrer_booking_id), KEY idx_referral_log_created(created_at)
    ) ENGINE=InnoDB");
    // Import existing referrals once; keep original booking references and timestamps.
    $lockName = 'referral-migration:' . db()->query('SELECT DATABASE()')->fetchColumn();
    $lock = db()->prepare('SELECT GET_LOCK(?,15)'); $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Referral setup is busy. Please try again.');
    try {
    $marker = db()->query("SELECT code FROM issued_reference_codes WHERE code='__REFERRAL_MIGRATION_V1__'")->fetchColumn();
    if (!$marker) {
        $limit = referral_code_usage_limit();
        $days = referral_eligibility_days();
        db()->prepare("INSERT IGNORE INTO referral_codes (booking_id,code,active_booking_id,usage_limit,expires_on,created_at)
            SELECT id,booking_reference,id,?,IF(?=0,NULL,DATE_ADD(COALESCE(event_date,DATE(created_at)),INTERVAL ? DAY)),created_at FROM event_bookings WHERE booking_reference IS NOT NULL AND booking_reference<>''")
            ->execute([$limit,$days,$days]);
        db()->exec("INSERT IGNORE INTO referral_uses (code_id,referrer_booking_id,referred_booking_id,created_at)
            SELECT c.id,c.booking_id,b.id,b.created_at FROM event_bookings b JOIN referral_codes c ON c.code=b.referred_by_reference WHERE b.id<>c.booking_id");
        db()->exec("UPDATE referral_codes c SET usage_count=(SELECT COUNT(*) FROM referral_uses u WHERE u.code_id=c.id)");
        db()->exec("UPDATE referral_codes SET status='exhausted' WHERE status='active' AND usage_count>=usage_limit");
        db()->exec("INSERT INTO referral_logs(event_type,code_id,referrer_booking_id,referred_booking_id,actor,details,created_at) SELECT 'legacy_referral_imported',u.code_id,u.referrer_booking_id,u.referred_booking_id,'migration','Imported existing referral; original booking and bonus history retained.',u.created_at FROM referral_uses u");
        db()->exec("INSERT IGNORE INTO issued_reference_codes(code,kind) VALUES ('__REFERRAL_MIGRATION_V1__','migration')");
        referral_log('history_imported', null, null, null, 'Existing referrals imported; historic codes and usage retained.');
    }
    } finally { db()->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]); }
    $ready = true;
}

function referral_code_usage_limit(): int
{
    return max(1, (int) rate_setting_value('referral_code_usage_limit', 5));
}

function referral_actor(): string
{
    return !empty($_SESSION['admin_id']) ? 'admin:' . (int) $_SESSION['admin_id'] : 'public';
}

function referral_log(string $event, ?int $codeId, ?int $ownerId, ?int $memberId, string $details): void
{
    db()->prepare('INSERT INTO referral_logs(event_type,code_id,referrer_booking_id,referred_booking_id,actor,details) VALUES (?,?,?,?,?,?)')
        ->execute([$event,$codeId,$ownerId,$memberId,referral_actor(),$details]);
}

function referral_atomic(callable $operation)
{
    $ownTransaction = !db()->inTransaction();
    if ($ownTransaction) db()->beginTransaction();
    try {
        $result = $operation();
        if ($ownTransaction) db()->commit();
        return $result;
    } catch (Throwable $e) {
        if ($ownTransaction && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function member_referral_code(int $bookingId, bool $replace = false, ?int $expectedCodeId = null): array
{
    ensure_referral_schema();
    return referral_atomic(function () use ($bookingId, $replace, $expectedCodeId) {
        db()->query("SELECT setting_key FROM rate_card_settings WHERE setting_key='referral_code_usage_limit' FOR UPDATE")->fetch();
        $stmt = db()->prepare('SELECT * FROM event_bookings WHERE id=? FOR UPDATE');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) throw new RuntimeException('Member booking was not found.');
        $stmt = db()->prepare('SELECT * FROM referral_codes WHERE active_booking_id=? FOR UPDATE');
        $stmt->execute([$bookingId]);
        $current = $stmt->fetch();
        if ($current && !$replace) return $current;
        if ($replace && $expectedCodeId !== null && (!$current || (int)$current['id'] !== $expectedCodeId)) throw new RuntimeException('The referral code has already changed. Refresh the page to see the current code.');
        if ($replace && ($booking['booking_status'] ?? '') === 'cancelled') throw new RuntimeException('A cancelled booking cannot receive a new referral code.');
        if ($current) {
            db()->prepare("UPDATE referral_codes SET status='retired',active_booking_id=NULL,retired_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$current['id']]);
            referral_log('code_retired',(int)$current['id'],$bookingId,null,'Admin replaced this code; it will never be accepted again.');
        }
        $code = reserve_reference_code('ANR',(string)$booking['client_name'],'referral');
        $days = referral_eligibility_days();
        $base = $replace ? new DateTimeImmutable('today') : new DateTimeImmutable($booking['event_date'] ?: 'today');
        $expiry = $days > 0 ? $base->modify('+' . $days . ' days')->format('Y-m-d') : null;
        db()->prepare('INSERT INTO referral_codes(booking_id,code,active_booking_id,usage_limit,expires_on) VALUES (?,?,?,?,?)')
            ->execute([$bookingId,$code,$bookingId,referral_code_usage_limit(),$expiry]);
        $id = (int) db()->lastInsertId();
        referral_log($replace ? 'code_replaced' : 'code_issued',$id,$bookingId,null,'Issued ' . $code . '; limit ' . referral_code_usage_limit() . '; expires ' . ($expiry ?: 'never') . '.');
        $stmt = db()->prepare('SELECT * FROM referral_codes WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    });
}

function referral_code_state(array $code): string
{
    if ($code['status'] === 'retired') return 'retired';
    if (!empty($code['expires_on']) && $code['expires_on'] < date('Y-m-d')) return 'expired';
    if ((int)$code['usage_count'] >= (int)$code['usage_limit']) return 'exhausted';
    return 'active';
}

function find_referral_code(string $input, ?int $existingBookingId = null): ?array
{
    $input = normalize_booking_reference($input);
    if ($input === '') return null;
    // Phone lookups always return the member's current code, never an old code.
    if (preg_match('/^[+0-9().-]+$/', $input)) {
        $digits = preg_replace('/\D/','',$input);
        if (strlen($digits) === 12 && str_starts_with($digits,'91')) $digits = substr($digits,-10);
        if (strlen($digits) !== 10) return null;
        $phone = booking_contact_digits_sql('b.phone');
        $wa = booking_contact_digits_sql('b.whatsapp_number');
        $stmt = db()->prepare("SELECT c.*,b.booking_reference,b.client_name,b.booking_status FROM referral_codes c JOIN event_bookings b ON b.id=c.booking_id WHERE c.active_booking_id=b.id AND (RIGHT($phone,10)=? OR RIGHT($wa,10)=?) ORDER BY b.id DESC LIMIT 1");
        $stmt->execute([$digits,$digits]);
    } else {
        $stmt = db()->prepare('SELECT c.*,b.booking_reference,b.client_name,b.booking_status FROM referral_codes c JOIN event_bookings b ON b.id=c.booking_id WHERE c.code=? LIMIT 1');
        $stmt->execute([$input]);
    }
    $code = $stmt->fetch();
    if (!$code || (int)$code['booking_id'] === $existingBookingId) return null;
    if ($existingBookingId) {
        $stmt = db()->prepare("SELECT id FROM referral_uses WHERE referred_booking_id=? AND code_id=? AND status='applied'");
        $stmt->execute([$existingBookingId,$code['id']]);
        if ($stmt->fetchColumn()) return $code; // An unchanged historic referral remains editable.
    }
    return referral_code_state($code) === 'active' && $code['booking_status'] !== 'cancelled' ? $code : null;
}

function referral_bonus_available(int $ownerId, ?int $excludeBookingId = null): float
{
    $stmt = db()->prepare("SELECT COUNT(*),COALESCE(SUM(b.referral_referrer_bonus),0) FROM referral_uses u JOIN event_bookings b ON b.id=u.referred_booking_id WHERE u.referrer_booking_id=? AND u.status='applied' AND b.referral_eligible=1 AND b.booking_status<>'cancelled' AND b.id<>?");
    $stmt->execute([$ownerId,$excludeBookingId ?: 0]);
    [$count,$amount] = $stmt->fetch(PDO::FETCH_NUM);
    if ((int)$count >= max(0,(int)rate_setting_value('referral_max_count',5))) return 0.0;
    return max(0.0,(float)rate_setting_value('referral_bonus_cap',5000)-(float)$amount);
}

function save_booking_referral(int $bookingId, string $input, bool $eligible = true, ?float $referrerBonus = null, ?float $memberBonus = null, ?string $note = null): void
{
    ensure_referral_schema();
    referral_atomic(function () use ($bookingId,$input,$eligible,$referrerBonus,$memberBonus,$note) {
        // Serialise claims/changes, then lock the code before checking its usage counter.
        db()->query("SELECT setting_key FROM rate_card_settings WHERE setting_key='referral_code_usage_limit' FOR UPDATE")->fetch();
        $stmt = db()->prepare('SELECT * FROM event_bookings WHERE id=? FOR UPDATE');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) throw new RuntimeException('Booking was not found.');
        $duplicate = duplicate_booking_contact_message((string)$booking['phone'], (string)($booking['email'] ?? ''), $bookingId, (string)($booking['whatsapp_number'] ?? ''), true);
        if ($duplicate !== '') throw new RuntimeException('This mobile, WhatsApp number or email is already used by another booking.');
        $stmt = db()->prepare('SELECT * FROM referral_uses WHERE referred_booking_id=? FOR UPDATE');
        $stmt->execute([$bookingId]);
        $existing = $stmt->fetch();
        if (trim($input) === '') {
            if ($existing && $existing['status'] === 'applied') {
                db()->prepare("UPDATE referral_uses SET status='removed' WHERE id=?")->execute([$existing['id']]);
                referral_log('referral_removed',(int)$existing['code_id'],(int)$existing['referrer_booking_id'],$bookingId,'Referral removed. Historical code usage is not reset.');
            }
            db()->prepare('UPDATE event_bookings SET referred_by_reference=NULL,referral_eligible=0,referral_referrer_bonus=0,referral_referred_bonus=0,referral_note=? WHERE id=?')->execute([$note,$bookingId]);
            return;
        }
        $code = find_referral_code($input,$bookingId);
        if (!$code) throw new RuntimeException('Referral code is invalid, retired, expired, exhausted or belongs to this booking. Ask admin for the current code.');
        $stmt = db()->prepare('SELECT * FROM referral_codes WHERE id=? FOR UPDATE');
        $stmt->execute([$code['id']]);
        $locked = $stmt->fetch();
        $unchanged = $existing && $existing['status']==='applied' && (int)$existing['code_id']===(int)$code['id'];
        if (!$unchanged && referral_code_state($locked) !== 'active') throw new RuntimeException('This referral code is no longer available.');
        if ($existing && !$unchanged) throw new RuntimeException('This booking has already used a referral. A second referral cannot be applied, even after removal.');
        $stmt = db()->prepare('SELECT * FROM event_bookings WHERE id=? FOR UPDATE');
        $stmt->execute([$code['booking_id']]);
        $owner = $stmt->fetch();
        if (!$owner || (!$unchanged && $owner['booking_status']==='cancelled')) throw new RuntimeException('This referrer booking is cancelled.');
        foreach (['phone','whatsapp_number'] as $field) {
            $number = substr(preg_replace('/\D/','',(string)($booking[$field] ?? '')),-10);
            foreach (['phone','whatsapp_number'] as $ownerField) {
                if ($number !== '' && $number === substr(preg_replace('/\D/','',(string)($owner[$ownerField] ?? '')),-10)) throw new RuntimeException('Self-referrals using the same contact number are not allowed.');
            }
        }
        if (!empty($booking['email']) && !empty($owner['email']) && strcasecmp(trim($booking['email']),trim($owner['email']))===0) throw new RuntimeException('Self-referrals using the same email are not allowed.');
        if ($booking['booking_status']==='cancelled') $eligible = false;
        $available = referral_bonus_available((int)$owner['id'],$bookingId);
        $referrerBonus = $eligible ? min($available,max(0,$referrerBonus ?? (float)rate_setting_value('referral_bonus_amount',2000))) : 0;
        $memberBonus = $eligible ? min(max(0,(float)rate_setting_value('referral_bonus_cap',5000)),max(0,$memberBonus ?? referral_new_member_bonus_default())) : 0;
        if (!$unchanged) {
            db()->prepare('INSERT INTO referral_uses(code_id,referrer_booking_id,referred_booking_id) VALUES (?,?,?)')->execute([$code['id'],$owner['id'],$bookingId]);
            db()->prepare("UPDATE referral_codes SET usage_count=usage_count+1,status=IF(usage_count>=usage_limit,'exhausted','active') WHERE id=?")->execute([$code['id']]);
            referral_log('referral_applied',(int)$code['id'],(int)$owner['id'],$bookingId,'Code ' . $code['code'] . ' used once; duplicate saves do not consume another use.');
            if ((int)$locked['usage_count']+1 >= (int)$locked['usage_limit']) referral_log('code_exhausted',(int)$code['id'],(int)$owner['id'],$bookingId,'Usage limit reached. Only admin can issue a replacement.');
        }
        $changed = !$unchanged || (int)$booking['referral_eligible'] !== (int)$eligible || abs((float)$booking['referral_referrer_bonus']-$referrerBonus)>.001 || abs((float)$booking['referral_referred_bonus']-$memberBonus)>.001 || (string)($booking['referral_note'] ?? '') !== (string)$note;
        db()->prepare('UPDATE event_bookings SET referred_by_reference=?,referral_eligible=?,referral_referrer_bonus=?,referral_referred_bonus=?,referral_note=? WHERE id=?')
            ->execute([$code['code'],$eligible?1:0,$referrerBonus,$memberBonus,$note,$bookingId]);
        if ($changed) referral_log('bonus_updated',(int)$code['id'],(int)$owner['id'],$bookingId,json_encode(['eligible'=>$eligible,'referrer_bonus'=>$referrerBonus,'member_bonus'=>$memberBonus,'note'=>$note],JSON_UNESCAPED_UNICODE));
    });
}

function referral_csrf_token(): string
{
    if (empty($_SESSION['referral_csrf'])) $_SESSION['referral_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['referral_csrf'];
}

function require_referral_csrf(): void
{
    if (!hash_equals(referral_csrf_token(),(string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Your session changed. Refresh the page and try again.');
}

function sync_cancelled_referrals(): void
{
    $rows = db()->query("SELECT b.id,b.referral_referrer_bonus,b.referral_referred_bonus,u.code_id,u.referrer_booking_id FROM event_bookings b JOIN referral_uses u ON u.referred_booking_id=b.id WHERE b.booking_status='cancelled' AND (b.referral_eligible=1 OR b.referral_referrer_bonus<>0 OR b.referral_referred_bonus<>0)")->fetchAll();
    foreach ($rows as $row) {
        db()->prepare('UPDATE event_bookings SET referral_eligible=0,referral_referrer_bonus=0,referral_referred_bonus=0 WHERE id=?')->execute([$row['id']]);
        referral_log('booking_cancelled',(int)$row['code_id'],(int)$row['referrer_booking_id'],(int)$row['id'],'Cancelled booking: bonuses cleared; historical usage retained.');
    }
}