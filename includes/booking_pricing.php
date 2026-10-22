<?php
function pricing_money_cents($value, string $label = 'amount'): int
{
    $value = str_replace(',', '', trim((string)$value));
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) throw new RuntimeException('Please enter a valid ' . $label . ' with up to two decimal places.');
    [$whole,$fraction] = array_pad(explode('.',$value,2),2,'');
    return (int)$whole * 100 + (int)str_pad($fraction,2,'0');
}

function booking_price_summary(?array $booking): array
{
    $booking = $booking ?: [];
    $hasTotal = isset($booking['proposal_amount']) && $booking['proposal_amount'] !== '';
    $gross = $hasTotal ? pricing_money_cents($booking['proposal_amount'],'total amount') : 0;
    $type = $booking['discount_type'] ?? 'none';
    $value = pricing_money_cents($booking['discount_value'] ?? '0','discount');
    $discount = $type === 'percent' ? intdiv($gross * min(10000,$value) + 5000,10000) : ($type === 'fixed' ? $value : 0);
    $discount = min($gross,$discount);
    $snapshot=decode_booking_rate_snapshot($booking);
    $charges=$deposit=$cleaning=$electricity=$extras=0;
    foreach($snapshot['items']??[] as $item){
        $amount=(int)$item['amount_cents'];$charges+=$amount;
        if($item['key']==='security_deposit')$deposit+=$amount;
        elseif($item['key']==='cleaning')$cleaning+=$amount;
        elseif($item['key']==='electricity')$electricity+=$amount;
        else $extras+=$amount;
    }
    $net=$gross-$discount+$charges;
    $advance=$snapshot ? intdiv(($net-$deposit)*(int)$snapshot['advance_basis_points']+5000,10000)+$deposit : 0;
    return ['has_total'=>$hasTotal,'gross_cents'=>$gross,'discount_cents'=>$discount,'net_cents'=>$net,
        'gross'=>$gross/100,'discount'=>$discount/100,'net'=>$net/100,'charges_cents'=>$charges,'charges'=>$charges/100,
        'deposit_cents'=>$deposit,'deposit'=>$deposit/100,'cleaning'=>$cleaning/100,'electricity'=>$electricity/100,'extras'=>$extras/100,
        'venue_net'=>($gross-$discount)/100,'suggested_advance'=>$advance/100,'rate_snapshot'=>$snapshot];
}

function sync_booking_payment(int $bookingId): void
{
    $stmt=db()->prepare('SELECT proposal_amount,discount_type,discount_value,rate_card_snapshot FROM event_bookings WHERE id=?');
    $stmt->execute([$bookingId]); $booking=$stmt->fetch();
    if (!$booking) throw new RuntimeException('Booking was not found.');
    $price=booking_price_summary($booking);
    $stmt=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=? AND verification_status='verified'");
    $stmt->execute([$bookingId]);$paid=pricing_money_cents($stmt->fetchColumn(),'paid total');
    $status=$price['has_total'] && $paid >= $price['net_cents'] ? 'paid' : ($paid>0 ? 'advance_paid' : 'pending');
    db()->prepare('UPDATE event_bookings SET payment_status=? WHERE id=?')->execute([$status,$bookingId]);
}

function save_booking_pricing(int $bookingId, array $input): array
{
    $stmt=db()->prepare('SELECT * FROM event_bookings WHERE id=? FOR UPDATE');$stmt->execute([$bookingId]);$booking=$stmt->fetch();
    if(!$booking)throw new RuntimeException('Booking was not found.');
    $snapshot=decode_booking_rate_snapshot($booking);
    $before=['proposal_amount'=>$booking['proposal_amount'],'discount_type'=>$booking['discount_type']??'none','discount_value'=>$booking['discount_value']??'0','rate_card_snapshot'=>$booking['rate_card_snapshot']??null,'payment_note'=>$booking['proposal_note']??''];
    $type=(string)($input['discount_type'] ?? 'none');
    if (!in_array($type,['none','percent','fixed'],true)) throw new RuntimeException('Please select a valid discount type.');
    $rawTotal=trim((string)($input['proposal_amount'] ?? ''));
    $gross=$rawTotal === '' ? null : pricing_money_cents($rawTotal,'total amount');
    $value=$type==='none' ? 0 : pricing_money_cents($input['discount_value'] ?? '', 'discount');
    if ($type!=='none' && $gross===null) throw new RuntimeException('Please enter the total amount before applying a discount.');
    if ($type==='percent' && $value>10000) throw new RuntimeException('Percentage discount must be between 0 and 100.');
    if ($type==='fixed' && $value>$gross) throw new RuntimeException('Discount amount cannot exceed the total amount.');
    if(($input['apply_rate_card']??'')==='1'){
        if($gross===null)throw new RuntimeException('Please enter the venue amount before saving Rate Card charges.');
        $catalog=$snapshot && empty($input['refresh_rate_card']) ? $snapshot['catalog'] : booking_rate_catalog();
        $selected=$input['extra_charge_keys']??[];$quantities=$input['extra_charge_qty']??[];
        if(!is_array($selected)||!is_array($quantities))throw new RuntimeException('Please select valid extra charges.');
        $meter=booking_electricity_readings($input,$snapshot);
        $snapshot=build_booking_rate_snapshot($catalog,(int)($booking['guests']??0),$meter['units'],$selected,$quantities);
        $snapshot['electricity_meter_start']=$meter['start'];$snapshot['electricity_meter_end']=$meter['end'];
        foreach($snapshot['items']as&$item){if($item['key']==='electricity'){$item['meter_start']=$meter['start'];$item['meter_end']=$meter['end'];}}unset($item);
    }
    $total=$gross===null ? null : number_format($gross/100,2,'.','');
    $encoded=$snapshot?json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null;
    $next=['proposal_amount'=>$total,'discount_type'=>$type,'discount_value'=>number_format($value/100,2,'.',''),'rate_card_snapshot'=>$encoded,'payment_note'=>trim((string)($input['payment_note']??''))];
    $summary=booking_price_summary($next);
    if($summary['net_cents']>999999999999)throw new RuntimeException('Grand total is too large.');
    db()->prepare('UPDATE event_bookings SET proposal_amount=?,discount_type=?,discount_value=?,proposal_note=?,rate_card_snapshot=? WHERE id=?')
        ->execute([$total,$type,$next['discount_value'],$next['payment_note'],$encoded,$bookingId]);
    // Ignore the snapshot timestamp when deciding whether pricing actually changed.
    $oldSnapshot=json_decode((string)$before['rate_card_snapshot'],true);$newSnapshot=$snapshot;
    if(is_array($oldSnapshot))unset($oldSnapshot['saved_at']);if(is_array($newSnapshot))unset($newSnapshot['saved_at']);
    $changed=$oldSnapshot!==$newSnapshot || (string)$before['proposal_amount']!==(string)$total || $before['discount_type']!==$type || (string)$before['discount_value']!==$next['discount_value'] || $before['payment_note']!==$next['payment_note'];
    if($changed)db()->prepare('INSERT INTO booking_pricing_history(booking_id,snapshot,actor) VALUES (?,?,?)')->execute([$bookingId,json_encode(['pricing'=>$next,'totals'=>$summary],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),referral_actor()]);
    return $summary;
}

function ensure_pricing_history_schema(): void
{
    static $ready=false;
    if($ready)return;
    db()->exec("CREATE TABLE IF NOT EXISTS booking_pricing_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL,
        snapshot LONGTEXT NOT NULL, actor VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pricing_booking(booking_id,id)
    ) ENGINE=InnoDB");
    $ready=true;
}

function booking_rate_catalog(): array
{
    $settings=rate_card_settings();
    $get=static function(string $key,string $fallback)use($settings):string{return (string)($settings[$key]['setting_value']??$fallback);};
    $catalog=['cleaning_per_guest'=>$get('cleaning_per_guest','10'),'cleaning_minimum'=>$get('cleaning_minimum','500'),
        'security_deposit'=>$get('security_deposit','10000'),'electricity_per_unit'=>$get('electricity_per_unit','19'),
        'advance_percent'=>$get('advance_percent','50'),'extras'=>[]];
    $excluded=['cleaning_per_guest','cleaning_minimum','security_deposit','electricity_per_unit'];
    foreach($settings as $key=>$setting){
        if(in_array($key,$excluded,true)||str_starts_with($key,'referral_'))continue;
        if(!preg_match('/^(Rs\.?|INR|₹)(?:\s|$)/iu',trim((string)($setting['suffix']??''))))continue;
        $catalog['extras'][$key]=['label'=>(string)$setting['label'],'rate'=>(string)$setting['setting_value'],'suffix'=>(string)$setting['suffix']];
    }
    return $catalog;
}

function decode_booking_rate_snapshot(?array $booking): ?array
{
    $snapshot=json_decode((string)($booking['rate_card_snapshot']??''),true);
    return is_array($snapshot)&&isset($snapshot['catalog'],$snapshot['items'])?$snapshot:null;
}

function rate_line_amount(int $rate,int $quantity): int
{
    if($rate<0||$quantity<0||($rate>0&&$quantity>intdiv(PHP_INT_MAX-50,$rate)))throw new RuntimeException('Charge quantity is too large.');
    return intdiv($rate*$quantity+50,100);
}

function build_booking_rate_snapshot(array $catalog,int $guests,string $units,array $selected,array $quantities): array
{
    if($guests<1||$guests>100000)throw new RuntimeException('Please enter the expected guest count in Booking Information before saving Rate Card charges.');
    $perGuest=pricing_money_cents($catalog['cleaning_per_guest'],'cleaning rate');
    $minimum=pricing_money_cents($catalog['cleaning_minimum'],'cleaning minimum');
    $deposit=pricing_money_cents($catalog['security_deposit'],'security deposit');
    $electricRate=pricing_money_cents($catalog['electricity_per_unit'],'electricity rate');
    $unitQuantity=pricing_money_cents($units,'electricity units');
    $advance=pricing_money_cents($catalog['advance_percent'],'advance percentage');
    if($advance>10000)throw new RuntimeException('Advance percentage in Rate Card must be between 0 and 100.');
    $cleaning=max(rate_line_amount($perGuest,$guests*100),$minimum);
    $items=[
        ['key'=>'cleaning','label'=>'Cleaning','quantity'=>(string)$guests,'unit_cents'=>$perGuest,'minimum_cents'=>$minimum,'amount_cents'=>$cleaning,'refundable'=>false],
        ['key'=>'security_deposit','label'=>'Refundable security deposit','quantity'=>'1','unit_cents'=>$deposit,'amount_cents'=>$deposit,'refundable'=>true],
        ['key'=>'electricity','label'=>'Electricity','quantity'=>number_format($unitQuantity/100,2,'.',''),'unit_cents'=>$electricRate,'amount_cents'=>rate_line_amount($electricRate,$unitQuantity),'refundable'=>false],
    ];
    foreach(array_unique($selected)as$key){
        if(!is_string($key)||!isset($catalog['extras'][$key]))throw new RuntimeException('An extra charge is no longer in the selected Rate Card. Deselect it or keep the saved rates.');
        $extra=$catalog['extras'][$key];$quantity=pricing_money_cents($quantities[$key]??'1','quantity for '.$extra['label']);
        if($quantity===0)throw new RuntimeException('Please enter a quantity greater than zero for '.$extra['label'].'.');
        $rate=pricing_money_cents($extra['rate'],'rate for '.$extra['label']);
        $items[]=['key'=>$key,'label'=>$extra['label'],'quantity'=>number_format($quantity/100,2,'.',''),'unit_cents'=>$rate,'amount_cents'=>rate_line_amount($rate,$quantity),'refundable'=>false];
    }
    $total=array_sum(array_column($items,'amount_cents'));
    if($total>999999999999)throw new RuntimeException('Combined charges are too large.');
    return ['version'=>1,'saved_at'=>date('Y-m-d H:i:s'),'catalog'=>$catalog,'guest_count'=>$guests,'electricity_units'=>number_format($unitQuantity/100,2,'.',''),'items'=>$items,'advance_basis_points'=>$advance];
}
function booking_electricity_readings(array $input,?array $saved): array
{
    $hasFields=array_key_exists('electricity_meter_start',$input)||array_key_exists('electricity_meter_end',$input);
    if(!$hasFields && !isset($saved['electricity_meter_start'],$saved['electricity_meter_end'])){
        return ['units'=>(string)($input['electricity_units']??$saved['electricity_units']??'0'),'start'=>null,'end'=>null];
    }
    $start=trim((string)($hasFields?($input['electricity_meter_start']??''):($saved['electricity_meter_start']??'')));
    $end=trim((string)($hasFields?($input['electricity_meter_end']??''):($saved['electricity_meter_end']??'')));
    if($start===''&&$end===''){
        // Preserve historic manually entered units until readings replace them.
        $legacy=!isset($saved['electricity_meter_start'],$saved['electricity_meter_end']);
        return ['units'=>$legacy?(string)($saved['electricity_units']??'0'):'0','start'=>null,'end'=>null];
    }
    if($start===''||$end==='')throw new RuntimeException('Please enter both starting and ending electricity meter readings.');
    $startValue=pricing_money_cents($start,'starting meter reading');
    $endValue=pricing_money_cents($end,'ending meter reading');
    if($endValue<$startValue)throw new RuntimeException('Ending meter reading must be greater than or equal to the starting reading.');
    return ['units'=>number_format(($endValue-$startValue)/100,2,'.',''),'start'=>number_format($startValue/100,2,'.',''),'end'=>number_format($endValue/100,2,'.','')];
}