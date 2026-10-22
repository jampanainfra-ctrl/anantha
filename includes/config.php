<?php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'anantha');
define('DB_USER', 'root');
define('DB_PASS', '');

define('WHATSAPP_NUMBER', '917995011199');
define('CONTACT_EMAIL', 'ananthaeventlawns@gmail.com');
define('SITE_NAME', 'Anantha Event Lawns');
define('GOOGLE_REVIEW_URL', '');
define('GOOGLE_PLACES_API_KEY', '');
define('GOOGLE_PLACE_ID', '');
define('GOOGLE_PLACE_QUERY', 'Anantha Event Lawns, DRK Raju Township, Plot 61, Kommadi Rd, Gandhi Nagar, Madhurawada, Andhra Pradesh 530045, India');
define('GOOGLE_REVIEWS_CACHE_SECONDS', 21600);

// First login after importing database.sql:
// Email: admin@anantha.local
// Password: admin123

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function db_ready(): bool
{
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function google_reviews_configured(): bool
{
    return trim((string) GOOGLE_PLACES_API_KEY) !== '';
}

function google_reviews_cache_path(): string
{
    return __DIR__ . '/../storage/google_reviews_cache.json';
}

function google_maps_api_json(string $url, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'PHP cURL extension is not available.'];
    }

    $requestHeaders = array_merge(['Accept: application/json'], $headers);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_USERAGENT => SITE_NAME . '/1.0',
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $statusCode >= 400) {
        return ['ok' => false, 'error' => $error ?: 'Google reviews request failed.'];
    }

    $payload = json_decode((string) $body, true);
    if (!is_array($payload)) {
        return ['ok' => false, 'error' => 'Google reviews response was not valid JSON.'];
    }

    return ['ok' => true, 'payload' => $payload];
}

function google_review_text(array $localizedText): string
{
    return trim((string) ($localizedText['text'] ?? ''));
}

function google_places_new_reviews(string $placeId): array
{
    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId);
    $response = google_maps_api_json($url, [
        'X-Goog-Api-Key: ' . GOOGLE_PLACES_API_KEY,
        'X-Goog-FieldMask: id,displayName,rating,userRatingCount,reviews,googleMapsUri',
    ]);
    if (!$response['ok']) {
        return $response;
    }

    $payload = $response['payload'];
    if (isset($payload['error'])) {
        $message = $payload['error']['message'] ?? 'Google Places reviews unavailable.';
        return ['ok' => false, 'error' => (string) $message];
    }

    $reviews = [];
    foreach (($payload['reviews'] ?? []) as $review) {
        if (!is_array($review)) {
            continue;
        }
        $text = google_review_text((array) ($review['text'] ?? []));
        if ($text === '') {
            $text = google_review_text((array) ($review['originalText'] ?? []));
        }
        if ($text === '') {
            continue;
        }
        $reviews[] = [
            'client_name' => trim((string) ($review['authorAttribution']['displayName'] ?? 'Google user')) ?: 'Google user',
            'event_type' => 'Google Maps review',
            'rating' => max(1, min(5, (int) round((float) ($review['rating'] ?? 5)))),
            'review_text' => $text,
            'created_at' => isset($review['publishTime']) ? date('Y-m-d H:i:s', strtotime((string) $review['publishTime'])) : null,
            'source' => 'google',
            'url' => (string) ($review['googleMapsUri'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'reviews' => $reviews,
        'rating' => (float) ($payload['rating'] ?? 0),
        'total' => (int) ($payload['userRatingCount'] ?? 0),
        'url' => (string) ($payload['googleMapsUri'] ?? ''),
    ];
}

function google_places_legacy_reviews(string $placeId): array
{
    $url = 'https://maps.googleapis.com/maps/api/place/details/json?'
        . http_build_query([
            'place_id' => $placeId,
            'fields' => 'name,rating,user_ratings_total,reviews,url',
            'key' => GOOGLE_PLACES_API_KEY,
        ]);
    $response = google_maps_api_json($url);
    if (!$response['ok']) {
        return $response;
    }

    $payload = $response['payload'];
    if (($payload['status'] ?? '') !== 'OK') {
        return ['ok' => false, 'error' => (string) ($payload['error_message'] ?? $payload['status'] ?? 'Google reviews unavailable.')];
    }

    $result = $payload['result'] ?? [];
    $reviews = [];
    foreach (($result['reviews'] ?? []) as $review) {
        if (!is_array($review)) {
            continue;
        }
        $text = trim((string) ($review['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $reviews[] = [
            'client_name' => trim((string) ($review['author_name'] ?? 'Google user')) ?: 'Google user',
            'event_type' => 'Google Maps review',
            'rating' => max(1, min(5, (int) ($review['rating'] ?? 5))),
            'review_text' => $text,
            'created_at' => isset($review['time']) ? date('Y-m-d H:i:s', (int) $review['time']) : null,
            'source' => 'google',
            'url' => (string) ($review['author_url'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'reviews' => $reviews,
        'rating' => (float) ($result['rating'] ?? 0),
        'total' => (int) ($result['user_ratings_total'] ?? 0),
        'url' => (string) ($result['url'] ?? ''),
    ];
}

function google_place_id(): string
{
    $configuredPlaceId = trim((string) GOOGLE_PLACE_ID);
    if ($configuredPlaceId !== '') {
        return $configuredPlaceId;
    }

    $query = trim((string) GOOGLE_PLACE_QUERY);
    if ($query === '') {
        return '';
    }

    $url = 'https://maps.googleapis.com/maps/api/place/findplacefromtext/json?'
        . http_build_query([
            'input' => $query,
            'inputtype' => 'textquery',
            'fields' => 'place_id',
            'key' => GOOGLE_PLACES_API_KEY,
        ]);
    $response = google_maps_api_json($url);
    if (!$response['ok']) {
        return '';
    }

    $candidates = $response['payload']['candidates'] ?? [];
    return is_array($candidates) && isset($candidates[0]['place_id']) ? (string) $candidates[0]['place_id'] : '';
}

function google_reviews_data(): array
{
    $empty = [
        'configured' => google_reviews_configured(),
        'reviews' => [],
        'rating' => 0,
        'total' => 0,
        'url' => '',
        'error' => '',
    ];

    if (!$empty['configured']) {
        return $empty;
    }

    $cachePath = google_reviews_cache_path();
    if (is_file($cachePath) && time() - (int) filemtime($cachePath) < (int) GOOGLE_REVIEWS_CACHE_SECONDS) {
        $cached = json_decode((string) file_get_contents($cachePath), true);
        if (is_array($cached)) {
            return array_merge($empty, $cached, ['configured' => true]);
        }
    }

    $placeId = google_place_id();
    if ($placeId === '') {
        return array_merge($empty, ['error' => 'Google Place ID was not found.']);
    }

    $reviewResponse = google_places_new_reviews($placeId);
    if (!$reviewResponse['ok']) {
        $reviewResponse = google_places_legacy_reviews($placeId);
    }
    if (!$reviewResponse['ok']) {
        return array_merge($empty, ['error' => $reviewResponse['error'] ?? 'Google reviews request failed.']);
    }

    $data = [
        'configured' => true,
        'reviews' => $reviewResponse['reviews'] ?? [],
        'rating' => (float) ($reviewResponse['rating'] ?? 0),
        'total' => (int) ($reviewResponse['total'] ?? 0),
        'url' => (string) ($reviewResponse['url'] ?? ''),
        'error' => '',
    ];

    $dir = dirname($cachePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($cachePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $data;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function status_label(string $status): string
{
    if ($status === 'confirmed') {
        return 'Booked';
    }
    if ($status === 'proposal_sent') {
        return 'Proposal Sent';
    }
    if ($status === 'event_happening') {
        return 'Event Happening';
    }
    if ($status === 'partial_paid') {
        return 'Advance Paid';
    }
    if ($status === 'paid') {
        return 'Full Paid';
    }
    if ($status === 'cancelled') {
        return 'Canceled';
    }
    return ucwords(str_replace('_', ' ', $status));
}

function booking_statuses(): array
{
    return [
        'pending' => 'Pending',
        'confirmed' => 'Booked',
        'event_over' => 'Event Over',
        'cancelled' => 'Canceled',
    ];
}

function payment_statuses(): array
{
    return [
        'pending' => 'Payment Pending',
        'advance_paid' => 'Advance Paid',
        'paid' => 'Full Paid',
    ];
}

function payment_modes(): array
{
    return [
        'cash' => 'Cash',
        'upi' => 'UPI',
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
        'other' => 'Other',
    ];
}

function payment_verification_statuses(): array
{
    return [
        'pending' => 'Not Checked Yet',
        'verified' => 'Checked / Verified',
        'rejected' => 'Rejected',
    ];
}

function booking_reference_name_part(string $name): string
{
    $letters = strtoupper(preg_replace('/[^A-Z]/i', '', $name));
    return str_pad(substr($letters ?: 'MEM', 0, 3), 3, 'X');
}

function booking_financial_year_suffix(?DateTimeInterface $date = null): string
{
    $date = $date ?: new DateTimeImmutable('now');
    return $date->format('y');
}

function normalize_booking_reference(string $reference): string
{
    return strtoupper(trim(preg_replace('/\s+/', '', $reference)));
}

function booking_contact_digits_sql(string $column): string
{
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '')";
}

function duplicate_booking_contact_message(string $phone, string $email = '', ?int $excludeBookingId = null, string $whatsappNumber = '', bool $lock = false): string
{
    if (!db_ready()) {
        return '';
    }

    $numbers = array_values(array_unique(array_filter([
        preg_replace('/\D+/', '', $phone),
        preg_replace('/\D+/', '', $whatsappNumber),
    ])));
    foreach ($numbers as $number) {
        $phoneDigits = booking_contact_digits_sql('phone');
        $whatsappDigits = booking_contact_digits_sql('whatsapp_number');
        $sql = "SELECT booking_reference, client_name FROM event_bookings WHERE (({$phoneDigits}=? OR RIGHT({$phoneDigits}, 10)=?) OR ({$whatsappDigits}=? OR RIGHT({$whatsappDigits}, 10)=?))";
        $params = [$number, $number, $number, $number];
        if ($excludeBookingId) {
            $sql .= ' AND id<>?';
            $params[] = $excludeBookingId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $match = $stmt->fetch();
        if ($match) {
            return 'This mobile / WhatsApp number is already used by ' . ($match['client_name'] ?: 'another booking') . ' (' . ($match['booking_reference'] ?: 'booking') . ').';
        }
    }

    $email = strtolower(trim($email));
    if ($email !== '') {
        $sql = 'SELECT booking_reference, client_name FROM event_bookings WHERE LOWER(TRIM(email))=?';
        $params = [$email];
        if ($excludeBookingId) {
            $sql .= ' AND id<>?';
            $params[] = $excludeBookingId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $match = $stmt->fetch();
        if ($match) {
            return 'This email address is already used by ' . ($match['client_name'] ?: 'another booking') . ' (' . ($match['booking_reference'] ?: 'booking') . ').';
        }
    }

    return '';
}

function resolve_referral_reference(string $input, ?int $excludeBookingId = null): ?string
{
    if (trim($input) === '' || !db_ready()) return null;
    ensure_referral_schema();
    $code = find_referral_code($input, $excludeBookingId);
    return $code ? $code['code'] : null;
}

function referral_eligibility_days(): int
{
    return max(0, (int) rate_setting_value('referral_eligibility_days', 60));
}

function referral_referrer_bonus_for(string $reference): float
{
    ensure_referral_schema();
    $stmt = db()->prepare('SELECT booking_id FROM referral_codes WHERE code=?');
    $stmt->execute([normalize_booking_reference($reference)]);
    $id = $stmt->fetchColumn();
    return $id ? min(max(0.0,rate_setting_value('referral_bonus_amount',2000)),referral_bonus_available((int)$id)) : 0.0;
}

function referral_new_member_bonus_default(): float
{
    return min(
        max(0.0, rate_setting_value('referral_bonus_cap', 5000)),
        max(0.0, rate_setting_value('referral_new_member_bonus', 2000))
    );
}

function referral_reference_is_eligible(array $booking, ?DateTimeInterface $today = null): bool
{
    $days = referral_eligibility_days();
    if ($days === 0) {
        return true;
    }

    $startValue = (string) ($booking['event_date'] ?? $booking['created_at'] ?? '');
    if ($startValue === '') {
        return false;
    }

    try {
        $start = new DateTimeImmutable($startValue);
    } catch (Throwable $e) {
        return false;
    }

    $today = $today ?: new DateTimeImmutable('today');
    return $start->modify('+' . $days . ' days') >= DateTimeImmutable::createFromInterface($today)->setTime(0, 0);
}

function generate_booking_reference_code(string $name = ''): string
{
    return reserve_reference_code('ANT', $name, 'booking');
}

function generate_unique_booking_reference(string $name = ''): string
{
    return generate_booking_reference_code($name);
}

function ensure_payment_verification_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $columns = db()->query('SHOW COLUMNS FROM payments')->fetchAll();
        $names = array_column($columns, 'Field');
        if (!in_array('verification_status', $names, true)) {
            db()->exec("ALTER TABLE payments ADD verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending' AFTER note");
        }
        if (!in_array('verified_at', $names, true)) {
            db()->exec('ALTER TABLE payments ADD verified_at TIMESTAMP NULL DEFAULT NULL AFTER verification_status');
        }
    } catch (Throwable $e) {
        // Older installs still work without the badge until database access is fixed.
    }
}

function ensure_booking_enquiry_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $columns = db()->query('SHOW COLUMNS FROM event_bookings')->fetchAll();
        $names = array_column($columns, 'Field');
        $additions = [
            'rate_card_snapshot' => "ALTER TABLE event_bookings ADD rate_card_snapshot LONGTEXT DEFAULT NULL AFTER discount_value",
            'discount_type' => "ALTER TABLE event_bookings ADD discount_type ENUM('none','percent','fixed') NOT NULL DEFAULT 'none' AFTER proposal_amount",
            'discount_value' => "ALTER TABLE event_bookings ADD discount_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_type",
            'booking_reference' => "ALTER TABLE event_bookings ADD booking_reference VARCHAR(80) DEFAULT NULL AFTER id",
            'whatsapp_number' => "ALTER TABLE event_bookings ADD whatsapp_number VARCHAR(30) DEFAULT NULL AFTER phone",
            'email' => "ALTER TABLE event_bookings ADD email VARCHAR(160) DEFAULT NULL AFTER whatsapp_number",
            'date_of_birth' => "ALTER TABLE event_bookings ADD date_of_birth DATE DEFAULT NULL AFTER email",
            'occupation' => "ALTER TABLE event_bookings ADD occupation VARCHAR(160) DEFAULT NULL AFTER date_of_birth",
            'pin_code' => "ALTER TABLE event_bookings ADD pin_code VARCHAR(12) DEFAULT NULL AFTER email",
            'city_district' => "ALTER TABLE event_bookings ADD city_district VARCHAR(160) DEFAULT NULL AFTER area_from",
            'client_state' => "ALTER TABLE event_bookings ADD client_state VARCHAR(160) DEFAULT NULL AFTER city_district",
            'package_id' => "ALTER TABLE event_bookings ADD package_id INT DEFAULT NULL AFTER guests",
            'accepted_terms_snapshot' => "ALTER TABLE event_bookings ADD accepted_terms_snapshot TEXT DEFAULT NULL AFTER note",
            'referred_by_reference' => "ALTER TABLE event_bookings ADD referred_by_reference VARCHAR(80) DEFAULT NULL AFTER booking_reference",
            'referral_eligible' => "ALTER TABLE event_bookings ADD referral_eligible TINYINT(1) NOT NULL DEFAULT 0 AFTER proposal_note",
            'referral_referrer_bonus' => "ALTER TABLE event_bookings ADD referral_referrer_bonus DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER referral_eligible",
            'referral_referred_bonus' => "ALTER TABLE event_bookings ADD referral_referred_bonus DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER referral_referrer_bonus",
            'referral_note' => "ALTER TABLE event_bookings ADD referral_note TEXT DEFAULT NULL AFTER referral_referred_bonus",
        ];
        foreach ($additions as $column => $sql) {
            if (!in_array($column, $names, true)) {
                db()->exec($sql);
            }
        }
        if (!in_array('booking_reference', $names, true)) {
            $names[] = 'booking_reference';
        }
        $indexes = db()->query("SHOW INDEX FROM event_bookings WHERE Key_name = 'idx_booking_reference'")->fetchAll();
        if (!$indexes) {
            db()->exec('ALTER TABLE event_bookings ADD UNIQUE KEY idx_booking_reference (booking_reference)');
        }
        $stmt = db()->query("SELECT id FROM event_bookings WHERE booking_reference IS NULL OR booking_reference = '' ORDER BY id ASC");
        foreach ($stmt->fetchAll() as $row) {
            $nameStmt = db()->prepare('SELECT client_name FROM event_bookings WHERE id=?');
            $nameStmt->execute([(int) $row['id']]);
            $update = db()->prepare('UPDATE event_bookings SET booking_reference = ? WHERE id = ?');
            $update->execute([generate_unique_booking_reference((string) $nameStmt->fetchColumn()), (int) $row['id']]);
        }
    } catch (Throwable $e) {
        // Pages still render with older databases; saving will show the actual database error.
    }
}

function ensure_package_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec("CREATE TABLE IF NOT EXISTS event_packages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      package_name VARCHAR(160) NOT NULL,
      guest_range VARCHAR(120) NOT NULL,
      price DECIMAL(12,2) DEFAULT NULL,
      discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
      discount_reason VARCHAR(255) DEFAULT NULL,
      display_order INT NOT NULL DEFAULT 0,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = db()->query('SHOW COLUMNS FROM event_packages')->fetchAll();
    $names = array_column($columns, 'Field');
    if (!in_array('discount_percent', $names, true)) {
        db()->exec("ALTER TABLE event_packages ADD discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price");
    }
    if (!in_array('discount_reason', $names, true)) {
        db()->exec("ALTER TABLE event_packages ADD discount_reason VARCHAR(255) DEFAULT NULL AFTER discount_percent");
    }

    $count = (int) db()->query('SELECT COUNT(*) FROM event_packages')->fetchColumn();
    if ($count === 0) {
        $stmt = db()->prepare('INSERT INTO event_packages (package_name, guest_range, display_order, is_active) VALUES (?, ?, ?, 1)');
        $stmt->execute(['Friends Meetup', 'Up to 10 guests', 1]);
        $stmt->execute(['Family Celebration', '11 to 100 guests', 2]);
        $stmt->execute(['Wedding Gathering', '101 to 300 guests', 3]);
    }
}

function ensure_terms_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec("CREATE TABLE IF NOT EXISTS site_terms (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(180) NOT NULL,
      term_text TEXT NOT NULL,
      display_order INT NOT NULL DEFAULT 0,
      is_booking_required TINYINT(1) NOT NULL DEFAULT 0,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = db()->query('SHOW COLUMNS FROM site_terms')->fetchAll();
    $names = array_column($columns, 'Field');
    if (!in_array('is_booking_required', $names, true)) {
        db()->exec('ALTER TABLE site_terms ADD is_booking_required TINYINT(1) NOT NULL DEFAULT 0 AFTER display_order');
    }

    $count = (int) db()->query('SELECT COUNT(*) FROM site_terms')->fetchColumn();
    if ($count === 0) {
        $stmt = db()->prepare('INSERT INTO site_terms (title, term_text, display_order, is_active) VALUES (?, ?, ?, 1)');
        $stmt->execute(['Date hold', 'A requested date is held for 48 hours. Confirmation follows an advance of 50% of the booking amount.', 1]);
        $stmt->execute(['Capacity', 'Maximum venue capacity is 300 guests. Only one event is held per day.', 2]);
        $stmt->execute(['Check-in and check-out', 'Check-in is 11:00 AM. Check-out is 10:00 AM the following day. Late checkout is Rs. 5,000 per hour or part thereof, subject to availability.', 3]);
        $stmt->execute(['Payments', 'Any payment submitted online or by transaction ID is pending until verified by the admin team.', 4]);
        $stmt->execute(['Security deposit', 'A refundable deposit of Rs. 10,000 applies to every booking and is refunded within 48 hours after inspection, subject to damages or recoverable charges.', 5]);
        $stmt->execute(['Cancellation', 'More than 30 days before the event: full advance refund. Between 30 and 15 days: 50% advance refund. Within 15 days: advance is not refundable. One reschedule is permitted, subject to availability.', 6]);
        $stmt->execute(['Property rules', 'The host is responsible for guests and vendors. Lawn protection, sound, alcohol permit, fire safety and vendor clearance rules must be followed.', 7]);
    }

    $bookingRequiredCount = (int) db()->query('SELECT COUNT(*) FROM site_terms WHERE is_booking_required=1')->fetchColumn();
    if ($bookingRequiredCount === 0) {
        db()->exec("UPDATE site_terms SET is_booking_required=1 WHERE title IN ('Date hold','Capacity','Payments')");
        $bookingRequiredCount = (int) db()->query('SELECT COUNT(*) FROM site_terms WHERE is_booking_required=1')->fetchColumn();
        if ($bookingRequiredCount === 0) {
            $ids = db()->query('SELECT id FROM site_terms WHERE is_active=1 ORDER BY display_order ASC, id ASC LIMIT 3')->fetchAll();
            foreach ($ids as $row) {
                $stmt = db()->prepare('UPDATE site_terms SET is_booking_required=1 WHERE id=?');
                $stmt->execute([(int) $row['id']]);
            }
        }
    }
}

function booking_required_terms(int $limit = 3): array
{
    if (!db_ready()) {
        return [];
    }

    ensure_terms_schema();
    $limit = max(1, $limit);
    $stmt = db()->prepare('SELECT id, title, term_text FROM site_terms WHERE is_active=1 AND is_booking_required=1 ORDER BY display_order ASC, id ASC LIMIT ' . (int) $limit);
    $stmt->execute();
    $terms = $stmt->fetchAll();

    if (count($terms) < $limit) {
        $ids = array_map(static fn ($term) => (int) $term['id'], $terms);
        $sql = 'SELECT id, title, term_text FROM site_terms WHERE is_active=1';
        if ($ids) {
            $sql .= ' AND id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        }
        $sql .= ' ORDER BY display_order ASC, id ASC LIMIT ' . (int) ($limit - count($terms));
        $fallback = db()->prepare($sql);
        $fallback->execute($ids);
        $terms = array_merge($terms, $fallback->fetchAll());
    }

    return $terms;
}

function ensure_gallery_category_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec("CREATE TABLE IF NOT EXISTS gallery_categories (
      id INT AUTO_INCREMENT PRIMARY KEY,
      category_name VARCHAR(120) NOT NULL UNIQUE,
      display_order INT NOT NULL DEFAULT 0,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $count = (int) db()->query('SELECT COUNT(*) FROM gallery_categories')->fetchColumn();
    if ($count === 0) {
        $stmt = db()->prepare('INSERT IGNORE INTO gallery_categories (category_name, display_order, is_active) VALUES (?, ?, 1)');
        foreach (['Events', 'Venue', 'Rooms'] as $index => $category) {
            $stmt->execute([$category, $index + 1]);
        }

        $existing = db()->query("SELECT DISTINCT category FROM gallery_images WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll();
        foreach ($existing as $row) {
            $stmt->execute([$row['category'], 20]);
        }
    }
}

function seo_blog_pages(): array
{
    return [
        'reception-venue-visakhapatnam' => 'Reception Venue',
        'wedding-venue-visakhapatnam' => 'Wedding Venue',
        'engagement-venue-visakhapatnam' => 'Engagement Venue',
        'haldi-venue-visakhapatnam' => 'Haldi Venue',
        'mehendi-venue-visakhapatnam' => 'Mehendi Venue',
        'sangeet-venue-visakhapatnam' => 'Sangeet Venue',
        'birthday-party-venue-visakhapatnam' => 'Birthday Party Venue',
        'anniversary-venue-visakhapatnam' => 'Anniversary Venue',
        'family-celebration-venue-visakhapatnam' => 'Family Celebration Venue',
        'corporate-event-venue-visakhapatnam' => 'Corporate Event Venue',
        '300-guest-event-venue' => '300 Guest Event Venue',
        'event-venue-kommadi' => 'Event Venue Kommadi',
        'event-lawns-visakhapatnam' => 'Event Lawns Visakhapatnam',
        'function-hall-madhurawada' => 'Function Hall Madhurawada',
        'haldi-mehendi-venue-visakhapatnam' => 'Haldi Mehendi Venue',
        'outdoor-event-venue-visakhapatnam' => 'Outdoor Event Venue',
        'party-venue-madhurawada' => 'Party Venue Madhurawada',
        'private-event-venue-visakhapatnam' => 'Private Event Venue',
        'venues-near-madhurawada' => 'Venues Near Madhurawada',
        'venues-near-rushikonda' => 'Venues Near Rushikonda',
        'wedding-lawns-madhurawada' => 'Wedding Lawns Madhurawada',
    ];
}

function default_blog_images(): array
{
    return [
        'reception-venue-visakhapatnam' => [
            ['Reception lawn setup', 'assets/occasion-reception-optimized.jpg', 'Outdoor wedding reception setup on a private event lawn', 1],
            ['Evening reception celebration', 'assets/gallery-celebration-evening-optimized.jpg', 'Evening celebration setup suited for a wedding reception', 2],
            ['Reception lawn and rooms', 'assets/gallery-day-lawn-optimized.jpg', 'Daylight lawn and rooms for a reception venue walkthrough', 3],
        ],
        'engagement-venue-visakhapatnam' => [
            ['Engagement setup', 'assets/occasion-engagement-optimized.jpg', 'Elegant outdoor engagement ceremony setup', 1],
            ['Engagement lawn seating', 'assets/gallery-day-lawn-optimized.jpg', 'Daylight lawn space for engagement seating and rituals', 2],
            ['Engagement evening celebration', 'assets/gallery-celebration-evening-optimized.jpg', 'Evening celebration setup for an engagement function', 3],
        ],
        'haldi-venue-visakhapatnam' => [
            ['Haldi ceremony', 'assets/occasion-haldi-optimized.jpg', 'Yellow marigold haldi ceremony on a green lawn', 1],
            ['Haldi and mehendi styling', 'assets/occasion-mehendi-optimized.jpg', 'Colourful pre-wedding lawn setup for haldi and family moments', 2],
            ['Haldi family seating', 'assets/occasion-family-optimized.jpg', 'Family celebration seating arranged on the lawn', 3],
        ],
        'mehendi-venue-visakhapatnam' => [
            ['Mehendi celebration', 'assets/occasion-mehendi-optimized.jpg', 'Colourful outdoor mehendi celebration setup', 1],
            ['Mehendi pre-wedding decor', 'assets/occasion-haldi-optimized.jpg', 'Bright pre-wedding lawn setup suited to mehendi styling', 2],
            ['Mehendi photos setup', 'assets/occasion-engagement-optimized.jpg', 'Decorated family celebration setup for mehendi photographs', 3],
        ],
        'sangeet-venue-visakhapatnam' => [
            ['Sangeet evening', 'assets/occasion-sangeet-optimized.jpg', 'Evening sangeet stage and dance floor setup', 1],
            ['Sangeet lighting', 'assets/gallery-celebration-evening-optimized.jpg', 'Evening celebration lighting for a sangeet night', 2],
            ['Sangeet stage setup', 'assets/occasion-reception-optimized.jpg', 'Decorated outdoor stage setup for family performances', 3],
        ],
        'birthday-party-venue-visakhapatnam' => [
            ['Birthday party setup', 'assets/occasion-birthday-optimized.jpg', 'Outdoor birthday party with cake and activity areas', 1],
            ['Birthday celebration room', 'assets/room-birthday-celebration-optimized.jpg', 'Indoor support room arranged for a birthday celebration', 2],
            ['Birthday family seating', 'assets/occasion-family-optimized.jpg', 'Family celebration seating for birthday guests', 3],
        ],
        'anniversary-venue-visakhapatnam' => [
            ['Anniversary dinner', 'assets/occasion-anniversary-optimized.jpg', 'Romantic outdoor anniversary dinner and memory display', 1],
            ['Anniversary family gathering', 'assets/occasion-family-optimized.jpg', 'Family celebration seating for anniversary guests', 2],
            ['Anniversary evening lighting', 'assets/gallery-celebration-evening-optimized.jpg', 'Evening celebration lighting for an anniversary gathering', 3],
        ],
        'family-celebration-venue-visakhapatnam' => [
            ['Family celebration', 'assets/occasion-family-optimized.jpg', 'Family celebration gathering on a spacious lawn', 1],
            ['Family celebration setup', 'assets/gallery-day-lawn-optimized.jpg', 'Daylight lawn setup for family celebration seating', 2],
            ['Family evening celebration', 'assets/gallery-celebration-evening-optimized.jpg', 'Evening celebration setup for family gatherings', 3],
        ],
        'corporate-event-venue-visakhapatnam' => [
            ['Corporate event setup', 'assets/occasion-corporate-optimized.jpg', 'Professional outdoor corporate event with presentation screen', 1],
            ['Corporate team lawn', 'assets/gallery-day-lawn-optimized.jpg', 'Open lawn setup for team activities and seating', 2],
            ['Corporate evening event', 'assets/gallery-celebration-evening-optimized.jpg', 'Evening corporate gathering setup with venue lighting', 3],
        ],
    ];
}

function ensure_blog_images_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec("CREATE TABLE IF NOT EXISTS blog_images (
      id INT AUTO_INCREMENT PRIMARY KEY,
      page_slug VARCHAR(160) NOT NULL,
      title VARCHAR(180) NOT NULL,
      alt_text VARCHAR(255) NOT NULL,
      image_path VARCHAR(255) NOT NULL,
      display_order INT NOT NULL DEFAULT 0,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_blog_images_page (page_slug, is_active, display_order)
    )");

    $stmt = db()->prepare('INSERT INTO blog_images (page_slug, title, image_path, alt_text, display_order, is_active) SELECT ?, ?, ?, ?, ?, 1 WHERE NOT EXISTS (SELECT 1 FROM blog_images WHERE page_slug=? AND image_path=?)');
    foreach (default_blog_images() as $slug => $images) {
        foreach ($images as $image) {
            $stmt->execute([$slug, $image[0], $image[1], $image[2], $image[3], $slug, $image[1]]);
        }
    }
}

function blog_page_images(string $slug): ?array
{
    if (!db_ready()) {
        return null;
    }

    ensure_blog_images_schema();
    $countStmt = db()->prepare('SELECT COUNT(*) FROM blog_images WHERE page_slug=?');
    $countStmt->execute([$slug]);
    if ((int) $countStmt->fetchColumn() === 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT title, image_path, alt_text FROM blog_images WHERE page_slug=? AND is_active=1 ORDER BY display_order ASC, id ASC');
    $stmt->execute([$slug]);
    $images = [];
    foreach ($stmt->fetchAll() as $row) {
        $images[] = [$row['image_path'], $row['alt_text'] ?: $row['title']];
    }
    return $images;
}

function ensure_event_inspection_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec("CREATE TABLE IF NOT EXISTS event_inspections (
      id INT AUTO_INCREMENT PRIMARY KEY,
      booking_id INT NOT NULL UNIQUE,
      lights_ok TINYINT(1) NOT NULL DEFAULT 0,
      rooms_ok TINYINT(1) NOT NULL DEFAULT 0,
      lawn_ok TINYINT(1) NOT NULL DEFAULT 0,
      hall_ok TINYINT(1) NOT NULL DEFAULT 0,
      washrooms_ok TINYINT(1) NOT NULL DEFAULT 0,
      cleanup_ok TINYINT(1) NOT NULL DEFAULT 0,
      inspection_note TEXT DEFAULT NULL,
      inspected_at TIMESTAMP NULL DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_event_inspections_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE
    )");

    db()->exec("CREATE TABLE IF NOT EXISTS event_penalties (
      id INT AUTO_INCREMENT PRIMARY KEY,
      booking_id INT NOT NULL,
      penalty_title VARCHAR(160) NOT NULL,
      penalty_reason TEXT NOT NULL,
      amount DECIMAL(12,2) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_event_penalties_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE
    )");
}

function default_rate_settings(): array
{
    return [
        'cleaning_per_guest' => ['Cleaning per guest', '10', 'Rs', 1],
        'cleaning_minimum' => ['Cleaning minimum - up to 50', '500', 'Rs', 2],
        'security_deposit' => ['Security deposit', '10000', 'Rs', 3],
        'electricity_per_unit' => ['Electricity per unit', '19', 'Rs', 4],
        'maximum_capacity' => ['Maximum capacity', '300', 'guests', 5],
        'advance_percent' => ['Advance', '50', '% of booking amount', 6],
        'hold_duration_hours' => ['Hold duration', '48', 'hours', 7],
        'referral_eligibility_days' => ['Referral eligibility window', '60', 'days', 8],
        'referral_bonus_amount' => ['Referral bonus amount', '2000', 'Rs', 9],
        'referral_new_member_bonus' => ['New member referral bonus', '2000', 'Rs', 10],
        'referral_bonus_cap' => ['Referral bonus maximum', '5000', 'Rs', 11],
        'referral_max_count' => ['Maximum paid referrals', '5', 'referrals', 12],
        'referral_code_usage_limit' => ['Uses allowed per referral code', '5', 'uses', 13],
    ];
}

function ensure_rate_card_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec("CREATE TABLE IF NOT EXISTS rate_card_settings (
      setting_key VARCHAR(80) PRIMARY KEY,
      label VARCHAR(160) NOT NULL,
      setting_value DECIMAL(12,2) NOT NULL DEFAULT 0,
      suffix VARCHAR(80) DEFAULT NULL,
      display_order INT NOT NULL DEFAULT 0,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = db()->prepare('INSERT IGNORE INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order) VALUES (?, ?, ?, ?, ?)');
    foreach (default_rate_settings() as $key => $setting) {
        $stmt->execute([$key, $setting[0], $setting[1], $setting[2], $setting[3]]);
    }
}

function rate_card_settings(): array
{
    if (!db_ready()) {
        return default_rate_settings();
    }
    ensure_rate_card_schema();
    $settings = [];
    foreach (db()->query('SELECT * FROM rate_card_settings ORDER BY display_order ASC')->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row;
    }
    return $settings;
}

function rate_setting_value(string $key, float $fallback = 0): float
{
    $settings = rate_card_settings();
    if (!isset($settings[$key])) {
        return $fallback;
    }
    return (float) ($settings[$key]['setting_value'] ?? $fallback);
}

function active_packages(): array
{
    if (!db_ready()) {
        return [];
    }
    ensure_package_schema();
    return db()->query('SELECT * FROM event_packages WHERE is_active=1 ORDER BY display_order ASC, id ASC')->fetchAll();
}

function package_guest_bounds(string $range, int $fallbackMax = 300): array
{
    preg_match_all('/\d+/', $range, $matches);
    $numbers = array_map('intval', $matches[0] ?? []);
    $minGuests = count($numbers) >= 2 ? $numbers[0] : 0;
    $maxGuests = count($numbers) >= 2 ? $numbers[1] : ($numbers[0] ?? $fallbackMax);
    return [$minGuests, $maxGuests];
}

function package_discounted_price(?float $price, ?float $discountPercent): ?float
{
    if ($price === null || $price <= 0) {
        return null;
    }
    $discount = max(0.0, min(100.0, (float) $discountPercent));
    return round($price - ($price * $discount / 100), 2);
}

function upload_image(string $field, string $directory): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) {
        return null;
    }

    $ext = match ($info[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
    if (!$ext) {
        return null;
    }

    $safeDirectory = trim($directory, '/\\');
    $absoluteDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . $safeDirectory;
    if (!is_dir($absoluteDirectory)) {
        mkdir($absoluteDirectory, 0775, true);
    }

    $fileName = date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $absoluteDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmp, $target)) {
        return null;
    }

    return str_replace('\\', '/', $safeDirectory . '/' . $fileName);
}

require_once __DIR__ . '/referrals.php';

require_once __DIR__ . '/booking_pricing.php';
