<?php
require_once __DIR__.'/../includes/admin_layout.php';
require_admin();
if(!db_ready()){admin_page_start('Email & Logs','email');echo '<div class="msg err">Database is not ready.</div>';admin_page_end();exit;}
ensure_mail_schema();
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        require_referral_csrf();
        $action=$_POST['mail_action'] ?? '';
        if($action==='settings'){
            save_mail_settings($_POST);
            set_flash('ok','Email settings saved. '.(mail_settings()['enabled']?'Delivery is enabled.':'Delivery is paused.'));
        }elseif($action==='test'){
            $settings=mail_settings();
            if(!filter_var($settings['admin_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Save a valid admin alert email first.');
            queue_mail('test:'.bin2hex(random_bytes(16)),'test',$settings['admin_email'],'admin','Anantha email connection test','This test confirms that the Anantha email connection can deliver to your inbox.',['Created by'=>admin_user()['name'],'Time'=>date('d M Y, H:i:s')]);
            set_flash('ok','Test email queued. Use Send queued emails when delivery is enabled.');
        }elseif($action==='process'){
            $result=process_mail_queue(10);
            set_flash('ok',$result['paused']?'Delivery is paused. Enable sending in Connection settings first.':$result['sent'].' emails sent; '.$result['failed'].' failed. See delivery history below.');
        }elseif($action==='retry'){
            db()->prepare("UPDATE email_outbox SET status='pending',attempts=0,last_error=NULL,locked_until=NULL,next_attempt_at=NOW() WHERE id=? AND status='failed'")->execute([(int)($_POST['email_id'] ?? 0)]);
            set_flash('ok','Failed email queued for retry.');
        }else throw new RuntimeException('Unknown email action.');
    }catch(Throwable $e){set_flash('error',$e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'Email action could not be completed.');}
    redirect('email.php');
}
$settings=mail_settings();
$errors=mail_configuration_errors($settings);
$status=$_GET['status'] ?? 'all';
if(!in_array($status,['all','pending','sent','failed','sending'],true))$status='all';
$page=max(1,(int)($_GET['page'] ?? 1));
$counts=array_fill_keys(['pending','sent','failed','sending'],0);
foreach(db()->query('SELECT status,COUNT(*) AS total FROM email_outbox GROUP BY status')->fetchAll() as $row)$counts[$row['status']]=(int)$row['total'];
$where=$status==='all'?'':' WHERE status=?';$params=$status==='all'?[]:[$status];
$stmt=db()->prepare('SELECT COUNT(*) FROM email_outbox'.$where);$stmt->execute($params);$total=(int)$stmt->fetchColumn();
$page=min($page,max(1,(int)ceil($total/25)));$offset=($page-1)*25;
$stmt=db()->prepare('SELECT id,event_type,booking_id,recipient,audience,subject,status,attempts,last_error,created_at,sent_at FROM email_outbox'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset);$stmt->execute($params);$messages=$stmt->fetchAll();
admin_page_start('Email & Logs','email');
?>
<div class="top"><div><h1>Email &amp; logs</h1><p>One mail connection for customer updates and admin alerts.</p></div><span class="mail-state <?php echo $settings['enabled'] && !$errors ? 'enabled' : ''; ?>"><?php echo $settings['enabled'] && !$errors ? 'Sending enabled' : 'Sending paused'; ?></span></div>
<?php show_flash(); ?>
<?php if($errors): ?><div class="msg mail-notice">Set the From and To addresses and sending method below to enable delivery. Messages remain queued until delivery is enabled.</div><?php endif; ?>
<div class="mail-metrics"><?php foreach(['pending'=>'Queued','sent'=>'Sent','failed'=>'Failed','sending'=>'Sending'] as $key=>$label): ?><a href="?status=<?php echo $key; ?>#delivery-history"><span><?php echo $label; ?></span><strong><?php echo (int)$counts[$key]; ?></strong></a><?php endforeach; ?></div>
<section class="card"><div class="section-head"><div><h2>Connection settings</h2><p>Set one From address for the application and a To address for admin alerts. Customer updates use each customer’s email.</p></div></div>
<form class="form-grid mail-settings-form" method="post" autocomplete="off">
<input type="hidden" name="mail_action" value="settings"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
<label class="full">Sending method<select name="transport" id="mail-transport"><option value="php_mail" <?php echo $settings['transport']==='php_mail'?'selected':''; ?>>PHP mail() — server mail service</option><option value="smtp" <?php echo $settings['transport']==='smtp'?'selected':''; ?>>SMTP — email provider connection</option></select></label>
<p class="full mail-help" id="php-mail-help">PHP mail() uses the web server’s mail service. From and To addresses alone cannot deliver mail unless that service is configured.</p>
<label data-smtp-field>SMTP host<input name="host" <?php echo $settings['transport']==='smtp'?'required':''; ?> value="<?php echo h($settings['host']); ?>" placeholder="Please enter SMTP host"></label>
<label data-smtp-field>SMTP port<input name="port" type="number" min="1" max="65535" <?php echo $settings['transport']==='smtp'?'required':''; ?> value="<?php echo (int)$settings['port']; ?>"></label>
<label data-smtp-field>Encryption<select name="security"><?php foreach(['tls'=>'STARTTLS (usually 587)','ssl'=>'SSL / TLS (usually 465)','none'=>'None (local testing only)'] as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo $settings['security']===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
<label data-smtp-field>SMTP username<input name="username" value="<?php echo h($settings['username']); ?>" autocomplete="off"></label>
<label class="wide" data-smtp-field>SMTP app password<input type="password" name="password" autocomplete="new-password" aria-describedby="password-help"><small id="password-help"><?php echo $settings['password']!==''?'A credential is saved. Leave blank to keep it.':'Enter the app password here, not in chat.'; ?></small></label>
<label class="mail-check" data-smtp-field><input type="checkbox" name="auth" <?php echo $settings['auth']?'checked':''; ?>>Use SMTP authentication</label>
<label>Sender name<input name="from_name" required value="<?php echo h($settings['from_name']); ?>"></label>
<label>From email<input name="from_email" type="email" required value="<?php echo h($settings['from_email']); ?>"></label>
<label>To email (admin alerts)<input name="admin_email" type="email" required value="<?php echo h($settings['admin_email']); ?>"></label>
<div class="mail-options full"><label class="mail-check"><input type="checkbox" name="customer_emails" <?php echo $settings['customer_emails']?'checked':''; ?>>Customer updates</label><label class="mail-check"><input type="checkbox" name="admin_alerts" <?php echo $settings['admin_alerts']?'checked':''; ?>>Admin activity alerts</label><label class="mail-check"><input type="checkbox" name="enabled" <?php echo $settings['enabled']?'checked':''; ?>>Enable email delivery</label></div>
<p class="full mail-help">Customer emails cover booking updates, payment acknowledgements and verification, and referral activity. Admin alerts also cover reviews and management updates. Customers without an email address are skipped. Enabling delivery allows queued messages to be sent.</p>
<button class="btn gold" type="submit">Save connection</button>
</form></section>
<section class="card"><div class="section-head"><div><h2>Delivery controls</h2><p>New events are queued automatically. Enabled delivery sends small batches after a request. Schedule the CLI worker for unattended retries.</p></div></div><div class="mail-controls">
<?php foreach(['test'=>'Queue test email','process'=>'Send queued emails'] as $key=>$label): ?><form method="post"><input type="hidden" name="mail_action" value="<?php echo $key; ?>"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>"><button class="btn <?php echo $key==='process'?'gold':''; ?>"><?php echo $label; ?></button></form><?php endforeach; ?>
</div></section>
<section class="card" id="delivery-history"><div class="section-head"><div><h2>Delivery history</h2><p>Sent messages are retained and are not retried. Failed delivery retries up to five times. Sent means accepted by the mail service; check the recipient inbox to confirm arrival.</p></div></div>
<div class="filters"><?php foreach(['all'=>'All','pending'=>'Queued','sent'=>'Sent','failed'=>'Failed','sending'=>'Sending'] as $key=>$label): ?><a class="<?php echo $status===$key?'on':''; ?>" href="?status=<?php echo $key; ?>#delivery-history"><?php echo $label; ?></a><?php endforeach; ?></div>
<div class="table-wrap"><table class="mail-history"><thead><tr><th>Created</th><th>Recipient</th><th>Subject / activity</th><th>Status</th><th>Attempts</th><th>Details</th></tr></thead><tbody>
<?php foreach($messages as $message): ?><tr><td><?php echo h(date('d M Y, H:i',strtotime($message['created_at']))); ?></td><td><?php echo h($message['recipient']); ?><small><?php echo h(ucfirst($message['audience'])); ?></small></td><td><strong><?php echo h($message['subject']); ?></strong><small><?php echo h(str_replace('_',' ',$message['event_type'])); ?><?php if($message['booking_id']): ?> &middot; <a href="<?php echo h(app_url('booking-form.php','admin')); ?>?id=<?php echo (int)$message['booking_id']; ?>">Booking #<?php echo (int)$message['booking_id']; ?></a><?php endif; ?></small></td><td><span class="badge <?php echo $message['status']==='sent'?'verified':($message['status']==='failed'?'rejected':'pending'); ?>"><?php echo h(ucfirst($message['status'])); ?></span></td><td><?php echo (int)$message['attempts']; ?> / 5</td><td><?php if($message['last_error']): ?><p><?php echo h($message['last_error']); ?></p><form method="post"><input type="hidden" name="mail_action" value="retry"><input type="hidden" name="email_id" value="<?php echo (int)$message['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>"><button class="btn">Retry</button></form><?php elseif($message['sent_at']): ?>Sent <?php echo h(date('d M, H:i',strtotime($message['sent_at']))); ?><?php else: ?><?php echo $message['status']==='sending'?'Delivery in progress':'Waiting for enabled delivery'; ?><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$messages): ?><tr><td colspan="6">No email activity yet. New events will appear here.</td></tr><?php endif; ?></tbody></table></div>
<div class="mail-pagination"><span>Page <?php echo $page; ?> of <?php echo max(1,(int)ceil($total/25)); ?> &middot; <?php echo $total; ?> messages</span><div><?php if($page>1): ?><a href="?status=<?php echo h($status); ?>&amp;page=<?php echo $page-1; ?>#delivery-history">Previous</a><?php endif; ?><?php if($page*25<$total): ?><a href="?status=<?php echo h($status); ?>&amp;page=<?php echo $page+1; ?>#delivery-history">Next</a><?php endif; ?></div></div>
</section>
<script src="<?php echo h(app_url('../assets/admin-email.js', 'admin')); ?>" defer></script>
<?php admin_page_end(); ?>