<?php
function customer_phone(string $phone): string
{
    return substr(preg_replace('/\D/','',$phone),-10);
}
function returning_customer_context(string $phone): ?array
{
    $context=$_SESSION['returning_customer'] ?? null;
    if(!$context || (int)$context['expires']<time() || $context['phone']!==customer_phone($phone))return null;
    $stmt=db()->prepare('SELECT id,phone FROM event_bookings WHERE id=?');
    $stmt->execute([(int)$context['booking_id']]);$row=$stmt->fetch();
    return $row && customer_phone($row['phone'])===$context['phone'] ? $context : null;
}
function returning_customer_lookup(string $phone,string $reference,bool $admin=false): array
{
    if(!preg_match('/^[0-9]{10}$/D',$phone))throw new RuntimeException('Please enter a valid 10-digit mobile number.');
    $alreadyVerified=returning_customer_context($phone)!==null;
    if(!$admin && trim($reference)==='' && !$alreadyVerified)return ['ok'=>true,'verification_required'=>true];
    unset($_SESSION['returning_customer']);
    if(!$admin && (!$alreadyVerified || trim($reference)!=='')){
        db()->exec('CREATE TABLE IF NOT EXISTS customer_lookup_limits (bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, expires_at DATETIME NOT NULL) ENGINE=InnoDB');
        $buckets=[hash('sha256','phone:'.$phone),hash('sha256','ip:'.($_SERVER['REMOTE_ADDR'] ?? 'cli'))];
        foreach($buckets as $index=>$bucket){
            db()->prepare('INSERT INTO customer_lookup_limits(bucket,attempts,expires_at) VALUES (?,1,DATE_ADD(NOW(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<NOW(),1,attempts+1), expires_at=IF(expires_at<NOW(),DATE_ADD(NOW(),INTERVAL 15 MINUTE),expires_at)')->execute([$bucket]);
            $limit=db()->prepare('SELECT attempts FROM customer_lookup_limits WHERE bucket=?');$limit->execute([$bucket]);
            if((int)$limit->fetchColumn()>($index===0?5:20))throw new RuntimeException('Too many lookup attempts. Please try again in 15 minutes.');
        }
        $stmt=db()->prepare('SELECT id,phone FROM event_bookings WHERE booking_reference=? LIMIT 1');
        $stmt->execute([normalize_booking_reference($reference)]);$match=$stmt->fetch();
        if(!$match || customer_phone($match['phone'])!==$phone)throw new RuntimeException('The mobile number and previous booking reference do not match. Please check both details.');
    }
    $digits=booking_contact_digits_sql('phone');
    $stmt=db()->prepare("SELECT id,client_name,phone,whatsapp_number,email,pin_code,area_from,city_district,client_state FROM event_bookings WHERE RIGHT({$digits},10)=? ORDER BY created_at DESC,id DESC LIMIT 1");
    $stmt->execute([$phone]);$customer=$stmt->fetch();
    if(!$customer)throw new RuntimeException('No previous booking was found for this mobile number.');
    $_SESSION['returning_customer']=['phone'=>$phone,'booking_id'=>(int)$customer['id'],'expires'=>time()+1800];
    unset($customer['id']);
    $customer['phone']=$phone;
    $customer['whatsapp_number']=$customer['whatsapp_number'] ? customer_phone($customer['whatsapp_number']) : '';
    return ['ok'=>true,'verification_required'=>false,'customer'=>$customer];
}
