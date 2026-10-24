<?php
require_once __DIR__ . '/_bootstrap.php';
api_require_db();
require_once __DIR__ . '/../includes/admin_auth.php';

$phone = (string) ($_GET['phone'] ?? '');
$whatsapp = (string) ($_GET['whatsapp_number'] ?? '');
$email = (string) ($_GET['email'] ?? '');
$adminUser = admin_user();
$showDetails = $adminUser && (string) ($_GET['details'] ?? '') === '1';
$excludeId = $adminUser ? max(0, (int) ($_GET['exclude_booking_id'] ?? 0)) : 0;

$excludeBookingId = $excludeId > 0 ? $excludeId : null;
$returningCustomer=$phone!=='' ? returning_customer_context($phone) : null;
$sameCustomerPhone=$returningCustomer['phone'] ?? '';
if($adminUser && $excludeBookingId){
    $original=db()->prepare('SELECT phone FROM event_bookings WHERE id=?');$original->execute([$excludeBookingId]);
    $originalPhone=$original->fetchColumn();
    if($originalPhone && customer_phone($originalPhone)===customer_phone($phone))$sameCustomerPhone=customer_phone($phone);
}
header('Cache-Control: no-store, private');
$genericMessage = 'This contact is already used.';
$phoneMessage = preg_replace('/\D+/', '', $phone) !== ''
    ? duplicate_booking_contact_message($phone, '', $excludeBookingId, '', false, $sameCustomerPhone)
    : '';
$whatsappMessage = preg_replace('/\D+/', '', $whatsapp) !== ''
    ? duplicate_booking_contact_message('', '', $excludeBookingId, $whatsapp, false, $sameCustomerPhone)
    : '';
$emailMessage = trim($email) !== ''
    ? duplicate_booking_contact_message('', $email, $excludeBookingId, '', false, $sameCustomerPhone)
    : '';
$message = $phoneMessage ?: ($whatsappMessage ?: $emailMessage);
$formatMessage = static function (string $fieldMessage) use ($showDetails, $genericMessage): string {
    if ($fieldMessage === '') {
        return '';
    }
    return $showDetails ? $fieldMessage : $genericMessage;
};

json_response([
    'ok' => true,
    'duplicate' => $message !== '',
    'returning_verified' => $returningCustomer!==null,
    'message' => $formatMessage($message),
    'fields' => [
        'phone' => $formatMessage($phoneMessage),
        'whatsapp_number' => $formatMessage($whatsappMessage),
        'email' => $formatMessage($emailMessage),
    ],
]);
