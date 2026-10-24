<?php
require_once __DIR__.'/mail.php';

function mail_booking_snapshot(int $id): array
{
    if($id<=0)return [];
    $stmt=db()->prepare('SELECT id,booking_reference,client_name,email,event_date,event_type,guests,booking_status,proposal_amount,discount_type,discount_value,rate_card_snapshot,payment_status,referred_by_reference FROM event_bookings WHERE id=?');
    $stmt->execute([$id]);$booking=$stmt->fetch();
    if(!$booking)return [];
    $stmt=db()->prepare('SELECT id,amount,payment_mode,paid_on,verification_status FROM payments WHERE booking_id=? ORDER BY id');
    $stmt->execute([$id]);$payments=$stmt->fetchAll();
    return ['booking'=>$booking,'payments'=>array_column($payments,null,'id')];
}
function mail_capture_action(string $action,array $input): array
{
    try{
        ensure_mail_schema();
        $id=(int)($input['booking_id'] ?? 0);
        if($action==='update_payment'){
            $stmt=db()->prepare('SELECT booking_id FROM payments WHERE id=?');$stmt->execute([(int)($input['payment_id'] ?? 0)]);$id=(int)$stmt->fetchColumn();
        }
        return ['id'=>$id,'before'=>mail_booking_snapshot($id),'referral_log_id'=>(int)db()->query('SELECT COALESCE(MAX(id),0) FROM referral_logs')->fetchColumn()];
    }catch(Throwable $e){error_log('Anantha mail: notification state could not be prepared.');return [];}
}
function mail_booking_rows(array $booking): array
{
    $rows=['Customer'=>$booking['client_name'],'Booking reference'=>$booking['booking_reference'],'Event date'=>date('d M Y',strtotime($booking['event_date'])),'Occasion'=>$booking['event_type'] ?: 'Event','Guests'=>(string)($booking['guests'] ?? ''),'Booking status'=>status_label($booking['booking_status'])];
    $price=booking_price_summary($booking);
    if($price['has_total'])$rows+=['Venue amount'=>'Rs '.number_format($price['gross'],2),'Discount'=>'Rs '.number_format($price['discount'],2),'Rate Card charges (including deposit)'=>'Rs '.number_format($price['charges'],2),'Grand total including deposit'=>'Rs '.number_format($price['net'],2)];
    return $rows;
}
function notify_booking_change(int $id,array $before=[],string $source='admin'): void
{
    $after=mail_booking_snapshot($id);
    if(!$after)return;
    $settings=mail_settings();$booking=$after['booking'];$rows=mail_booking_rows($booking);
    if(!$before){
        $stmt=db()->prepare('SELECT code FROM referral_codes WHERE active_booking_id=? LIMIT 1');$stmt->execute([$id]);
        if($code=$stmt->fetchColumn())$rows['Your referral code']=$code;
    }
    $event='booking_updated';$heading='Your booking details were updated';
    $intro='Your updated event details are below. Please contact our team if anything needs changing.';
    if(!$before){$event='booking_created';$heading='We received your booking enquiry';$intro='Thank you for choosing Anantha. Your enquiry is saved. Our team will contact you about availability and the quotation. Your date is confirmed only after the team confirms your booking.';}
    elseif(($before['booking'] ?? [])===$booking)return;
    elseif(($before['booking']['booking_status'] ?? '')!==$booking['booking_status']){
        $heading='Booking status: '.status_label($booking['booking_status']);
        $intro='The status of your booking has changed. Please review the details below.';
    }
    $key=$event.':'.$id.':'.($before ? bin2hex(random_bytes(10)) : 'created');
    if(!empty($settings['customer_emails']))queue_mail($key,$event,(string)$booking['email'],'customer',$heading,$intro,$rows,$id);
    if(!empty($settings['admin_alerts']))queue_mail($key,$event,$settings['admin_email'],'admin',!$before?'New booking enquiry':'Booking updated','A booking was saved through the '.($source==='public'?'customer enquiry form':'admin portal').'.',$rows,$id);
}
function notify_payment_changes(int $id,array $before): void
{
    $after=mail_booking_snapshot($id);if(!$after)return;
    $settings=mail_settings();$booking=$after['booking'];
    foreach($after['payments'] as $paymentId=>$payment){
        if(($before['payments'][$paymentId] ?? null)===$payment)continue;
        $state=$payment['verification_status'];
        $title=$state==='verified'?'Payment receipt — verified':($state==='rejected'?'Payment verification update':'Payment recorded — awaiting verification');
        $intro=$state==='verified'?'Your payment has been checked and verified by Anantha.':($state==='rejected'?'This payment was not verified. Please contact our team for details.':'Your payment has been recorded and is awaiting verification. This is an acknowledgement, not a verified payment receipt.');
        $rows=mail_booking_rows($booking)+['Payment reference'=>'#'.$paymentId,'Payment amount'=>'Rs '.number_format((float)$payment['amount'],2),'Payment type'=>payment_modes()[$payment['payment_mode']] ?? $payment['payment_mode'],'Paid on'=>date('d M Y',strtotime($payment['paid_on'])),'Checked status'=>payment_verification_statuses()[$state] ?? ucfirst($state)];
        $key='payment:'.$paymentId.':'.bin2hex(random_bytes(10));
        if(!empty($settings['customer_emails']))queue_mail($key,'payment_updated',(string)$booking['email'],'customer',$title,$intro,$rows,$id);
        if(!empty($settings['admin_alerts']))queue_mail($key,'payment_updated',$settings['admin_email'],'admin',$title,'A payment was recorded or its verification status changed.',$rows,$id);
    }
}
function notify_referral_activity(int $bookingId,int $afterLogId): void
{
    $stmt=db()->prepare("SELECT l.*,c.code,c.usage_count,c.usage_limit,c.expires_on,b.email,b.client_name,b.booking_reference FROM referral_logs l LEFT JOIN referral_codes c ON c.id=l.code_id LEFT JOIN event_bookings b ON b.id=l.referrer_booking_id WHERE l.id>? AND (l.referrer_booking_id=? OR l.referred_booking_id=?) AND l.event_type NOT IN ('history_imported','legacy_referral_imported') ORDER BY l.id");
    $stmt->execute([$afterLogId,$bookingId,$bookingId]);$settings=mail_settings();
    foreach($stmt->fetchAll() as $log){
        $label=ucfirst(str_replace('_',' ',$log['event_type']));
        $rows=['Member'=>$log['client_name'] ?: 'Member','Booking reference'=>$log['booking_reference'] ?: '','Referral code'=>$log['code'] ?: '','Activity'=>$label,'Code uses'=>$log['usage_count'].' / '.$log['usage_limit'],'Expiry'=>$log['expires_on'] ?: 'No expiry'];
        $intro='Your referral details have changed. Codes stop at their usage limit or expiry. Replacements are issued only by the admin.';
        if(!empty($settings['customer_emails']) && $log['email'])queue_mail('referral-log:'.$log['id'],'referral_'.$log['event_type'],$log['email'],'customer','Referral update: '.$label,$intro,$rows,(int)$log['referrer_booking_id']);
        if(!empty($settings['admin_alerts']))queue_mail('referral-log:'.$log['id'],'referral_'.$log['event_type'],$settings['admin_email'],'admin','Referral activity: '.$label,$intro,$rows,(int)$log['referrer_booking_id'] ?: null);
    }
}
function notify_admin_action(string $action,array $input,array $captured,int $bookingId=0): void
{
    try{
        $bookingActions=['save_booking_details','add_booking','update_booking','quick_update_booking_status'];
        $paymentActions=['save_payment_tab','add_payment','update_payment'];
        $id=$bookingId ?: (int)($captured['id'] ?? $input['booking_id'] ?? 0);
        if(in_array($action,$bookingActions,true))notify_booking_change($id,$captured['before'] ?? []);
        if(in_array($action,$paymentActions,true)){
            notify_payment_changes($id,$captured['before'] ?? []);
            if(($input['save_pricing'] ?? '')==='1')notify_booking_change($id,$captured['before'] ?? []);
        }
        if($id>0 && isset($captured['referral_log_id']))notify_referral_activity($id,(int)$captured['referral_log_id']);
        // Admin edits across all other management screens use the same mail connection.
        $otherActions=['save_referral','replace_referral_code','save_referral_settings','save_event_inspection','add_penalty','delete_penalty','save_review','delete_review','review_status','add_review','add_gallery','save_gallery_image','save_gallery_category','delete_gallery','add_blog_image','save_blog_image','delete_blog_image','save_package','save_packages_bulk','delete_package','save_rate_setting','delete_rate_setting','save_term','delete_term'];
        $settings=mail_settings();
        if(in_array($action,$otherActions,true) && !empty($settings['admin_alerts'])){
            $rows=['Activity'=>ucfirst(str_replace('_',' ',$action)),'Administrator'=>admin_user()['name'] ?? 'Admin','Time'=>date('d M Y, H:i:s')];
            if($id>0){$snapshot=mail_booking_snapshot($id);if($snapshot)$rows+=mail_booking_rows($snapshot['booking']);}
            // No posted credentials, identity documents, customer notes or form payloads are emailed.
            queue_mail('admin-action:'.bin2hex(random_bytes(16)),$action,$settings['admin_email'],'admin','Admin activity: '.ucfirst(str_replace('_',' ',$action)),'An update was completed in the Anantha administration portal.',$rows,$id ?: null);
        }
        mail_schedule_dispatch();
    }catch(Throwable $e){error_log('Anantha mail: notification could not be queued; the saved data was retained.');}
}
function notify_public_booking(int $id): void
{
    try{
        notify_booking_change($id,[],'public');
        // Only the newly created member and its referrer activity; no historic backfill.
        $stmt=db()->prepare('SELECT COALESCE(MIN(id),0) FROM referral_logs WHERE referred_booking_id=?');$stmt->execute([$id]);$first=(int)$stmt->fetchColumn();
        if($first)notify_referral_activity($id,$first-1);
        mail_schedule_dispatch();
    }catch(Throwable $e){error_log('Anantha mail: enquiry notification could not be queued.');}
}
function notify_public_review(int $reviewId,string $name,int $rating): void
{
    try{
        $settings=mail_settings();
        if(!empty($settings['admin_alerts']))queue_mail('review:'.$reviewId,'review_submitted',$settings['admin_email'],'admin','New review awaiting approval','A customer review is waiting for moderation.',['Review ID'=>(string)$reviewId,'Customer'=>$name,'Rating'=>$rating.' / 5']);
        mail_schedule_dispatch();
    }catch(Throwable $e){error_log('Anantha mail: review notification could not be queued.');}
}