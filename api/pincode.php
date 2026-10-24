<?php
require_once __DIR__ . '/_bootstrap.php';
if(isset($_GET['pin'])&&!is_scalar($_GET['pin']))json_response(['ok'=>false,'error'=>'Please enter a valid 6-digit PIN code.'],422);

$pin = preg_replace('/\D+/', '', (string) ($_GET['pin'] ?? ''));
if (!preg_match('/^\d{6}$/', $pin)) {
    json_response(['ok' => false, 'error' => 'Enter a valid 6-digit PIN code.'], 422);
}

if(!function_exists('curl_init'))json_response(['ok'=>false,'error'=>'PIN lookup is unavailable. Please enter your address manually.'],503);
$ch = curl_init('https://api.postalpincode.in/pincode/' . rawurlencode($pin));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'AnanthaEventLawns/1.0',
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);

$body = curl_exec($ch);
$error = curl_error($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($body === false || $statusCode >= 400) {
    json_response(['ok' => false, 'error' => 'PIN lookup is unavailable. Please enter your address manually.'], 502);
}

$payload = json_decode($body, true);
$offices = $payload[0]['PostOffice'] ?? [];
if (!is_array($payload) || !is_array($offices) || !$offices) {
    json_response(['ok' => false, 'error' => 'No area found for this PIN code.'], 404);
}

$areas = [];
$district = '';
$state = '';
$preferredOffice = null;
foreach ($offices as $office) {
    if (!is_array($office)) {
        continue;
    }
    $name = trim((string) ($office['Name'] ?? ''));
    if ($preferredOffice === null && stripos($name, 'kommadi') !== false) {
        $preferredOffice = $office;
    }
    if ($name !== '' && !in_array($name, $areas, true)) {
        $areas[] = $name;
    }
    if ($district === '') {
        $district = trim((string) ($office['District'] ?? ''));
    }
    if ($state === '') {
        $state = trim((string) ($office['State'] ?? ''));
    }
}

if ($preferredOffice === null) {
    $preferredOffice = is_array($offices[0] ?? null) ? $offices[0] : [];
}

json_response([
    'ok' => true,
    'pin' => $pin,
    'areas' => $areas,
    'area' => trim((string) ($preferredOffice['Name'] ?? ($areas[0] ?? ''))),
    'district' => trim((string) ($preferredOffice['District'] ?? $preferredOffice['Block'] ?? $district)),
    'state' => trim((string) ($preferredOffice['State'] ?? $state)),
]);
