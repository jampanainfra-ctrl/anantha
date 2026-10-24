<?php
function package_edit_version(array $row): string
{
    $values=[];
    foreach(['package_name','guest_range','price','discount_percent','discount_reason','display_order','is_active'] as $key)$values[$key]=(string)($row[$key] ?? '');
    return hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
function package_edit_payload(array $row): array
{
    foreach(['package_name','guest_min','guest_max','price','discount_percent','discount_reason','display_order','is_active','version'] as $field){
        if(isset($row[$field])&&!is_scalar($row[$field]))throw new RuntimeException('Please enter valid package details.');
    }
    reject_required_leading_spaces($row,['package_name'=>'Package name','guest_min'=>'Minimum guests','guest_max'=>'Maximum guests']);
    $name=trim((string)($row['package_name'] ?? ''));
    if($name===''||mb_strlen($name)>160)throw new RuntimeException('Please enter a package name of up to 160 characters.');
    $min=filter_var($row['guest_min'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]]);
    $max=filter_var($row['guest_max'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]]);
    if($min===false||$max===false||$max<$min)throw new RuntimeException($name.': maximum guests must be a whole number greater than or equal to minimum guests.');
    $price=trim((string)($row['price'] ?? ''));
    if($price!==''&&!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$price))throw new RuntimeException($name.': please enter a valid non-negative price with up to two decimal places.');
    $discount=trim((string)($row['discount_percent'] ?? '')) ?: '0';
    if(!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D',$discount)||(float)$discount>100)throw new RuntimeException($name.': discount must be between 0 and 100 percent.');
    $reason=trim((string)($row['discount_reason'] ?? ''));
    if(mb_strlen($reason)>255)throw new RuntimeException($name.': discount reason must contain no more than 255 characters.');
    $order=filter_var($row['display_order'] ?? '0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]]);
    if($order===false)throw new RuntimeException($name.': display order must be a non-negative whole number.');
    return [$name,$min.' to '.$max.' guests',$price===''?null:number_format((float)$price,2,'.',''),number_format((float)$discount,2,'.',''),$reason?:null,$order,isset($row['is_active'])?1:0];
}
function save_packages_bulk(array $edits,array $deletes): array
{
    if(count($edits)+count($deletes)>100)throw new RuntimeException('Please save no more than 100 package changes at once.');
    $payloads=[];$versions=[];
    foreach($edits as $key=>$row){
        if(!is_array($row))throw new RuntimeException('Please enter valid package details.');
        $existing=ctype_digit((string)$key)&&(int)$key>0&&(string)(int)$key===(string)$key;
        if(!$existing&&!preg_match('/^new_[A-Za-z0-9_]+$/D',(string)$key))throw new RuntimeException('A package identifier is invalid. Refresh and try again.');
        $payloads[$key]=package_edit_payload($row);
        if($existing)$versions[(int)$key]=(string)($row['version'] ?? '');
    }
    foreach($deletes as $id=>$version){
        if(!ctype_digit((string)$id)||(int)$id<1||!is_string($version)||isset($edits[$id]))throw new RuntimeException('A package removal is invalid. Refresh and try again.');
        $versions[(int)$id]=$version;
    }
    $counts=['added'=>0,'updated'=>0,'removed'=>0];
    db()->beginTransaction();
    try{
        ksort($versions,SORT_NUMERIC);
        foreach($versions as $id=>$version){
            $stmt=db()->prepare('SELECT * FROM event_packages WHERE id=? FOR UPDATE');$stmt->execute([$id]);$current=$stmt->fetch();
            if(!$current||!hash_equals(package_edit_version($current),$version))throw new RuntimeException('A package was changed in another session. Reload this page before saving to avoid overwriting those changes.');
        }
        foreach($deletes as $id=>$version){
            $used=db()->prepare('SELECT id FROM event_bookings WHERE package_id=? LIMIT 1');$used->execute([(int)$id]);
            if($used->fetchColumn())throw new RuntimeException('A package marked for removal is used by bookings. Undo its removal and turn off Active instead.');
            db()->prepare('DELETE FROM event_packages WHERE id=?')->execute([(int)$id]);$counts['removed']++;
        }
        foreach($payloads as $key=>$payload){
            if(ctype_digit((string)$key)){
                db()->prepare('UPDATE event_packages SET package_name=?,guest_range=?,price=?,discount_percent=?,discount_reason=?,display_order=?,is_active=? WHERE id=?')->execute([...$payload,(int)$key]);
                $counts['updated']++;
            }else{
                db()->prepare('INSERT INTO event_packages(package_name,guest_range,price,discount_percent,discount_reason,display_order,is_active) VALUES (?,?,?,?,?,?,?)')->execute($payload);
                $counts['added']++;
            }
        }
        db()->commit();return $counts;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
