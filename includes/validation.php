<?php
function validate_admin_booking_request(array $input): void
{
    $id=max(0,(int)($input['booking_id'] ?? 0));$current=null;
    if($id){$stmt=db()->prepare('SELECT event_date,booking_status FROM event_bookings WHERE id=? FOR UPDATE');$stmt->execute([$id]);$current=$stmt->fetch();if(!$current)throw new RuntimeException('Booking was not found.');}
    $date=$input['event_date'] ?? ($current['event_date'] ?? '');
    if(!is_string($date))throw new RuntimeException('Please enter a valid event date.');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('Please enter a valid event date.');
    $sameDate=$current && $current['event_date']===$date;
    if(!$sameDate&&$parsed<new DateTimeImmutable('today'))throw new RuntimeException('Please choose today or a future event date.');
    $status=$input['booking_status'] ?? 'pending';
    if(!in_array($status,['pending','proposal_sent','confirmed','event_happening','event_over','cancelled'],true))throw new RuntimeException('Please select a valid booking status.');
    // Existing legacy records can keep their date; creating, moving or reopening must check occupancy.
    if($status!=='cancelled'&&(!$sameDate||$current['booking_status']==='cancelled')){
        $stmt=db()->prepare("SELECT id FROM event_bookings WHERE event_date=? AND booking_status<>'cancelled' AND id<>? LIMIT 1 FOR UPDATE");$stmt->execute([$date,$id]);
        if($stmt->fetchColumn())throw new RuntimeException('This date is already on hold or booked. Please choose another date.');
    }
    if(isset($input['guests'])&&$input['guests']!==''){
        $capacity=max(1,(int)rate_setting_value('maximum_capacity',300));
        if(!is_scalar($input['guests'])||!preg_match('/^[0-9]+$/D',(string)$input['guests'])||(int)$input['guests']<1||(int)$input['guests']>$capacity)throw new RuntimeException('Please enter a guest count between 1 and '.$capacity.'.');
    }
}

/** Check raw required values before any trimming, casting or phone normalization. */
function required_leading_space_error(array $input,array $fields): string
{
    foreach($fields as $name=>$label){
        if(!array_key_exists($name,$input))continue;
        $value=$input[$name];
        if(!is_string($value) && !is_numeric($value))return 'Please enter a valid '.strtolower($label).'.';
        if(preg_match('/\A[\s\p{Z}\x{FEFF}\x{200B}]/u',(string)$value)===1)return $label.' must not start with a space.';
    }
    return '';
}
function reject_required_leading_spaces(array $input,array $fields): void
{
    $error=required_leading_space_error($input,$fields);
    if($error!=='')throw new RuntimeException($error);
}
function validate_admin_required_spacing(string $action,array $input): void
{
    $fields=match($action){
        'save_booking_details','add_booking'=>['client_name'=>'Customer name','phone'=>'Phone number','event_date'=>'Event date'],
        'save_package'=>['package_name'=>'Package name','guest_min'=>'Minimum guests','guest_max'=>'Maximum guests'],
        'save_rate_setting'=>['label'=>'Rule name','setting_value'=>'Value'],
        'save_referral_settings'=>['usage_limit'=>'Uses allowed per code','eligibility_days'=>'Code validity'],
        'save_term'=>['title'=>'Title','term_text'=>'Terms text'],
        'save_review','add_review'=>['client_name'=>'Client name','review_text'=>'Review text'],
        'add_gallery','add_blog_image','save_blog_image'=>['title'=>'Image title'],
        'save_gallery_category'=>['category_name'=>'Category name'],
        'add_penalty'=>['penalty_title'=>'Penalty item','penalty_reason'=>'Remark','amount'=>'Amount'],
        'add_payment'=>['amount'=>'Amount paid'],
        default=>[]
    };
    if($action==='save_payment_tab'){
        if(($input['save_pricing'] ?? '')!=='1')$fields['amount']='Amount paid';
        if(($input['discount_type'] ?? 'none')!=='none')$fields['discount_value']='Discount value';
        if(($input['electricity_meter_start'] ?? '')!=='' || ($input['electricity_meter_end'] ?? '')!=='')$fields+=['electricity_meter_start'=>'Starting meter reading','electricity_meter_end'=>'Ending meter reading'];
        if(is_array($input['extra_charge_keys'] ?? null) && is_array($input['extra_charge_qty'] ?? null)){
            foreach($input['extra_charge_keys'] as $key){
                if(is_string($key) && array_key_exists($key,$input['extra_charge_qty']))reject_required_leading_spaces(['quantity'=>$input['extra_charge_qty'][$key]],['quantity'=>'Charge quantity']);
            }
        }
    }
    reject_required_leading_spaces($input,$fields);
}
