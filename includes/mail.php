<?php
/** Shared PHP mail / SMTP delivery and a durable email outbox. */
function mail_settings_path(): string
{
    return getenv('ANANTHA_MAIL_SETTINGS_PATH') ?: __DIR__ . '/../storage/mail-settings.php';
}
function mail_settings(): array
{
    $defaults = ['transport'=>'php_mail','enabled'=>false,'host'=>'','port'=>587,'security'=>'tls','auth'=>true,'username'=>'','password'=>'','from_email'=>'','from_name'=>SITE_NAME,'admin_email'=>CONTACT_EMAIL,'customer_emails'=>true,'admin_alerts'=>true];
    $path=mail_settings_path();
    $saved=is_file($path) ? require $path : [];
    if(is_array($saved) && $saved && !isset($saved['transport']))$saved['transport']='smtp';
    return array_replace($defaults,is_array($saved)?$saved:[]);
}
function mail_configuration_errors(array $settings): array
{
    $errors=[];
    foreach(['from_email'=>'Sender email','admin_email'=>'Admin alert email'] as $key=>$label) {
        if(!filter_var($settings[$key] ?? '',FILTER_VALIDATE_EMAIL))$errors[]=$label.' is invalid.';
    }
    $transport=$settings['transport'] ?? 'smtp';
    if(!in_array($transport,['smtp','php_mail'],true))$errors[]='Please select PHP mail or SMTP.';
    if($transport==='smtp'){
    if(!preg_match('/^[a-z0-9.-]+$/i',(string)($settings['host'] ?? '')))$errors[]='Please enter an SMTP hostname without a URL prefix.';
    if((int)($settings['port'] ?? 0)<1 || (int)$settings['port']>65535)$errors[]='SMTP port must be between 1 and 65535.';
    if(!in_array($settings['security'] ?? '',['tls','ssl','none'],true))$errors[]='Please select an SMTP encryption method.';
    if(($settings['security'] ?? '')==='none' && !in_array(strtolower($settings['host'] ?? ''),['localhost','127.0.0.1','::1'],true))$errors[]='Use TLS or SSL for a remote SMTP server.';
    if(!empty($settings['auth']) && (trim($settings['username'] ?? '')==='' || ($settings['password'] ?? '')===''))$errors[]='SMTP username and app password are required.';
    }
    if(preg_match('/[\r\n\x00]/',(string)($settings['from_name'] ?? '')))$errors[]='Sender name is invalid.';
    return $errors;
}
function save_mail_settings(array $input): void
{
    $fields=['from_email'=>'From email','from_name'=>'Sender name','admin_email'=>'Admin alert email'];
    if(($input['transport'] ?? mail_settings()['transport'])==='smtp')$fields+=['host'=>'SMTP host','port'=>'SMTP port'];
    reject_required_leading_spaces($input,$fields);
    $previous=mail_settings();
    $transport=$input['transport'] ?? $previous['transport'];
    $settings=[
        'transport'=>$transport,
        'enabled'=>isset($input['enabled']), 'host'=>trim($input['host'] ?? $previous['host']),
        'port'=>(int)($input['port'] ?? $previous['port']), 'security'=>$input['security'] ?? $previous['security'],
        'auth'=>$transport==='smtp' ? isset($input['auth']) : $previous['auth'], 'username'=>trim($input['username'] ?? $previous['username']),
        'password'=>($input['password'] ?? '')!=='' ? $input['password'] : $previous['password'],
        'from_email'=>trim($input['from_email'] ?? ''), 'from_name'=>trim($input['from_name'] ?? '') ?: SITE_NAME,
        'admin_email'=>trim($input['admin_email'] ?? ''),
        'customer_emails'=>isset($input['customer_emails']), 'admin_alerts'=>isset($input['admin_alerts'])
    ];
    $errors=mail_configuration_errors($settings);
    if($errors)throw new RuntimeException(implode(' ',$errors));
    $path=mail_settings_path();
    $contents="<?php\n// Private mail settings. Do not commit this file.\nreturn ".var_export($settings,true).";\n";
    $temporary=tempnam(dirname($path),'mail-settings-');
    if($temporary===false)throw new RuntimeException('Mail settings could not be saved.');
    try{
        if(file_put_contents($temporary,$contents,LOCK_EX)===false)throw new RuntimeException('Mail settings could not be saved.');
        @chmod($temporary,0600);
        if(!rename($temporary,$path))throw new RuntimeException('Mail settings could not be saved.');
    }finally{if(is_file($temporary))unlink($temporary);}
    if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
}
function ensure_mail_schema(): void
{
    static $database=null;
    $current=(string)db()->query('SELECT DATABASE()')->fetchColumn();
    if($database===$current)return;
    if(db()->inTransaction())throw new RuntimeException('Initialize the mail outbox before starting a transaction.');
    db()->exec("CREATE TABLE IF NOT EXISTS email_outbox (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_key CHAR(64) NOT NULL UNIQUE,
        event_type VARCHAR(100) NOT NULL,
        booking_id INT NULL,
        recipient VARCHAR(254) NOT NULL,
        audience VARCHAR(20) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body_html MEDIUMTEXT NOT NULL,
        body_text MEDIUMTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL,
        next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        locked_until DATETIME NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX email_delivery_queue(status,next_attempt_at), INDEX email_booking(booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $database=$current;
}
function mail_template(string $heading,string $intro,array $rows): array
{
    $html='<html lang="en"><body style="margin:0;padding:24px;background:#f3f5ef;font-family:Arial,sans-serif;color:#24432f"><main style="max-width:600px;margin:auto;background:#fff;border:1px solid #e2e8dc;border-radius:14px;overflow:hidden"><div style="padding:24px;background:#173d2d;color:#fff"><strong style="font-size:24px">Anantha</strong><div style="font-size:12px;letter-spacing:2px;color:#e5bd77;margin-top:5px">EVENT LAWNS</div></div><div style="padding:26px"><h1 style="font-size:24px;line-height:1.3;margin:0 0 16px">'.h($heading).'</h1><p style="font-size:15px;line-height:1.7">'.h($intro).'</p><table style="width:100%;border-collapse:collapse;font-size:14px">';
    $text=SITE_NAME."\n\n".$heading."\n".$intro."\n\n";
    foreach($rows as $label=>$value){
        $display=nl2br(h((string)$value));
        if($label==='Reset link' && filter_var($value,FILTER_VALIDATE_URL) && in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true))$display='<a style="display:inline-block;padding:11px 15px;background:#244c34;color:#fff;text-decoration:none;border-radius:7px" href="'.h($value).'">Reset password</a><br><small style="display:block;margin-top:10px;word-break:break-all">'.h($value).'</small>';
        $html.='<tr><th style="padding:12px 0;border-bottom:1px solid #e8ede2;text-align:left;vertical-align:top;width:42%;font-weight:normal;color:#687b5c">'.h((string)$label).'</th><td style="padding:12px 0 12px 15px;border-bottom:1px solid #e8ede2;overflow-wrap:anywhere">'.$display.'</td></tr>';
        $text.=$label.': '.$value."\n";
    }
    $html.='</table><p style="font-size:13px;color:#687b5c;margin-top:24px">Questions? Contact '.h(CONTACT_EMAIL).' or +91 79950 11199.</p></div></main></body></html>';
    return [$html,$text."\nQuestions? ".CONTACT_EMAIL.' / +91 79950 11199'];
}
function queue_mail(string $key,string $event,string $recipient,string $audience,string $subject,string $intro,array $rows,?int $bookingId=null): bool
{
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))return false;
    ensure_mail_schema();
    [$html,$text]=mail_template($subject,$intro,$rows);
    $stmt=db()->prepare('INSERT IGNORE INTO email_outbox(event_key,event_type,booking_id,recipient,audience,subject,body_html,body_text) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([hash('sha256',$key.'|'.$recipient.'|'.$audience),$event,$bookingId,$recipient,$audience,mb_substr($subject,0,255),$html,$text]);
    return $stmt->rowCount()>0;
}
function mail_schedule_dispatch(): void
{
    static $registered=false;
    if($registered || PHP_SAPI==='cli' || empty(mail_settings()['enabled']))return;
    $registered=true;
    register_shutdown_function(function(){
        if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();
        try{process_mail_queue(2);}catch(Throwable $e){error_log('Anantha mail: delivery worker could not run.');}
    });
}
function create_application_mailer(array $message): \PHPMailer\PHPMailer\PHPMailer
{
    $settings=mail_settings();
    $errors=mail_configuration_errors($settings);
    if($errors)throw new RuntimeException('Email configuration is incomplete.');
    $autoload=__DIR__.'/../vendor/autoload.php';
    if(!is_file($autoload))throw new RuntimeException('Run composer install to install mail dependencies.');
    require_once $autoload;
    $mailer=new \PHPMailer\PHPMailer\PHPMailer(true);
    if($settings['transport']==='smtp'){
    $mailer->isSMTP(); $mailer->Host=$settings['host']; $mailer->Port=(int)$settings['port'];
    $mailer->SMTPAuth=(bool)$settings['auth']; $mailer->Username=$settings['username']; $mailer->Password=$settings['password'];
    $mailer->SMTPSecure=$settings['security']==='none' ? '' : $settings['security'];
    $mailer->SMTPAutoTLS=$settings['security']!=='none'; $mailer->Timeout=8; $mailer->Timelimit=12;
    }else{
        $mailer->isMail();
        $mailer->UseSendmailOptions=false;
    }
    $mailer->CharSet='UTF-8'; $mailer->setFrom($settings['from_email'],$settings['from_name']);
    $mailer->addReplyTo($settings['from_email'],$settings['from_name']);
    $mailer->addAddress($message['recipient']); $mailer->isHTML(true);
    $mailer->Subject=$message['subject']; $mailer->Body=$message['body_html']; $mailer->AltBody=$message['body_text'];
    $mailer->MessageID='<anantha-'.$message['event_key'].'@'.substr(strrchr($settings['from_email'],'@'),1).'>';
    return $mailer;
}
/** Build native mail headers without accepting recipient or header injection. */
function application_php_mail_payload(array $message,array $settings): array
{
    if(mail_configuration_errors($settings))throw new RuntimeException('Email configuration is incomplete.');
    if(!filter_var($message['recipient'] ?? '',FILTER_VALIDATE_EMAIL))throw new RuntimeException('Email recipient is invalid.');
    if(preg_match('/[\r\n\x00]/',(string)($message['subject'] ?? '')))throw new RuntimeException('Email subject is invalid.');
    $encode=static fn(string $value):string => mb_encode_mimeheader($value,'UTF-8','B',"\r\n");
    $senderName=$encode($settings['from_name']);
    if(!str_starts_with($senderName,'=?'))$senderName='"'.addcslashes($senderName,"\\\"").'"';
    $sender=$senderName.' <'.$settings['from_email'].'>';
    $boundary='anantha_'.bin2hex(random_bytes(24));
    $headers=[
        'From'=>$sender,
        'Reply-To'=>$sender,
        'MIME-Version'=>'1.0',
        'Content-Type'=>'multipart/alternative; boundary="'.$boundary.'"',
        'Message-ID'=>'<anantha-'.hash('sha256',(string)$message['event_key']).'@'.substr(strrchr($settings['from_email'],'@'),1).'>'
    ];
    $body='';
    foreach(['text/plain'=>$message['body_text'],'text/html'=>$message['body_html']] as $type=>$content){
        $body.='--'.$boundary."\r\nContent-Type: ".$type."; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($content),76,"\r\n");
    }
    $body.='--'.$boundary."--\r\n";
    return ['to'=>$message['recipient'],'subject'=>$encode($message['subject']),'body'=>$body,'headers'=>$headers];
}
function send_application_mail(array $message): void
{
    $settings=mail_settings();
    if($settings['transport']!=='php_mail'){
        create_application_mailer($message)->send();
        return;
    }
    if(!function_exists('mail'))throw new RuntimeException('PHP mail is unavailable on this server.');
    $payload=application_php_mail_payload($message,$settings);
    // Use the same native PHP function as a simple mail($to, $subject, $body, $headers) script.
    // A false result must remain a failed outbox entry, rather than a successful delivery.
    if(!@mail($payload['to'],$payload['subject'],$payload['body'],$payload['headers'])){
        throw new RuntimeException('PHP mail did not accept this message.');
    }
}
function process_mail_queue(int $limit=20,?callable $testSender=null): array
{
    if($testSender!==null && PHP_SAPI!=='cli')throw new RuntimeException('Test transport is CLI only.');
    ensure_mail_schema();
    $result=['sent'=>0,'failed'=>0,'paused'=>empty(mail_settings()['enabled'])];
    if($result['paused'])return $result;
    db()->exec("UPDATE email_outbox SET status='failed',locked_until=NULL,last_error='Delivery was interrupted after the retry limit. Review before retrying.' WHERE status='sending' AND locked_until<NOW() AND attempts>=5");
    for($i=0;$i<max(1,min(100,$limit));$i++){
        db()->beginTransaction();
        $message=db()->query("SELECT * FROM email_outbox WHERE ((status IN ('pending','failed') AND next_attempt_at<=NOW() AND attempts<5) OR (status='sending' AND locked_until<NOW() AND attempts<5)) ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
        if(!$message){db()->commit();break;}
        db()->prepare("UPDATE email_outbox SET status='sending',attempts=attempts+1,locked_until=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?")->execute([$message['id']]);
        db()->commit();
        try{
            if($testSender)$testSender($message);else send_application_mail($message);
            db()->prepare("UPDATE email_outbox SET status='sent',sent_at=NOW(),last_error=NULL,locked_until=NULL WHERE id=?")->execute([$message['id']]);
            $result['sent']++;
        }catch(Throwable $e){
            // Log a useful category, never SMTP replies, credentials or customer content.
            $error=mail_settings()['transport']==='php_mail'
                ? 'PHP mail delivery failed. The web server needs a working mail relay or sendmail configuration.'
                : 'SMTP delivery failed. Check the sender account, SMTP settings and provider access.';
            db()->prepare("UPDATE email_outbox SET status='failed',last_error=?,locked_until=NULL,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id=?")->execute([$error,min(60,5*(2**(int)$message['attempts'])),$message['id']]);
            $result['failed']++;
        }
    }
    return $result;
}
