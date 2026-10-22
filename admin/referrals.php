<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();
$ready = db_ready();
$codes = $logs = [];
$totalLogs = $totalCodes = $duplicateCodes = $duplicateContacts = 0;
$ownerId = max(0,(int)($_GET['member'] ?? 0));
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$codePage = max(1,(int)($_GET['codes_page'] ?? 1));
if ($ready) {
    ensure_referral_schema();
    // New bookings created by older integrations receive a separate referral code here.
    foreach (db()->query('SELECT b.id FROM event_bookings b LEFT JOIN referral_codes c ON c.booking_id=b.id WHERE c.id IS NULL')->fetchAll() as $row) member_referral_code((int)$row['id']);
    $where = ['c.active_booking_id IS NOT NULL'];
    $params = [];
    if ($ownerId) { $where[]='c.booking_id=?'; $params[]=$ownerId; }
    if ($search !== '') { $where[]='(c.code LIKE ? OR b.client_name LIKE ? OR b.booking_reference LIKE ?)'; $params=array_merge($params,array_fill(0,3,'%'.$search.'%')); }
    $sqlWhere = implode(' AND ',$where);
    $stmt=db()->prepare("SELECT COUNT(*) FROM referral_codes c JOIN event_bookings b ON b.id=c.booking_id WHERE $sqlWhere");
    $stmt->execute($params); $totalCodes=(int)$stmt->fetchColumn();
    $codePage=min($codePage,max(1,(int)ceil($totalCodes/25)));
    $offset=($codePage-1)*25;
    $stmt=db()->prepare("SELECT c.*,b.client_name,b.booking_reference,b.booking_status FROM referral_codes c JOIN event_bookings b ON b.id=c.booking_id WHERE $sqlWhere ORDER BY c.id DESC LIMIT 25 OFFSET $offset");
    $stmt->execute($params); $codes=$stmt->fetchAll();
    $logWhere=[]; $logParams=[];
    if ($ownerId) { $logWhere[]='l.referrer_booking_id=?'; $logParams[]=$ownerId; }
    if ($search !== '') { $logWhere[]='(c.code LIKE ? OR b.client_name LIKE ? OR l.details LIKE ?)'; $logParams=array_merge($logParams,array_fill(0,3,'%'.$search.'%')); }
    $logSql=$logWhere?' WHERE '.implode(' AND ',$logWhere):'';
    $from=' FROM referral_logs l LEFT JOIN referral_codes c ON c.id=l.code_id LEFT JOIN event_bookings b ON b.id=l.referrer_booking_id';
    $stmt=db()->prepare('SELECT COUNT(*)'.$from.$logSql); $stmt->execute($logParams); $totalLogs=(int)$stmt->fetchColumn();
    $page=min($page,max(1,(int)ceil($totalLogs/50))); $offset=($page-1)*50;
    $stmt=db()->prepare('SELECT l.*,c.code,b.client_name'.$from.$logSql." ORDER BY l.id DESC LIMIT 50 OFFSET $offset");
    $stmt->execute($logParams); $logs=$stmt->fetchAll();
    $duplicateCodes=(int)db()->query('SELECT COUNT(*) FROM (SELECT code FROM referral_codes GROUP BY code HAVING COUNT(*)>1) d')->fetchColumn();
    $duplicateCodes+=(int)db()->query("SELECT COUNT(*) FROM (SELECT booking_reference FROM event_bookings WHERE booking_reference IS NOT NULL AND booking_reference<>'' GROUP BY booking_reference HAVING COUNT(*)>1) d")->fetchColumn();
    $phone=booking_contact_digits_sql('phone'); $wa=booking_contact_digits_sql('whatsapp_number');
    $duplicateContacts=(int)db()->query("SELECT COUNT(*) FROM (SELECT contact FROM (SELECT id,RIGHT($phone,10) AS contact FROM event_bookings UNION SELECT id,RIGHT($wa,10) AS contact FROM event_bookings UNION SELECT id,LOWER(TRIM(email)) AS contact FROM event_bookings) n WHERE contact IS NOT NULL AND contact<>'' GROUP BY contact HAVING COUNT(DISTINCT id)>1) d")->fetchColumn();
}
function referral_page_url(array $changes): string {
    return 'referrals.php?' . http_build_query(array_merge($_GET,$changes));
}
admin_page_start('Referrals & Logs','referrals');
?>
<div class="top"><div><h1>Referrals &amp; Logs</h1><p>Codes stop at their usage limit or expiry. Only an admin can issue a replacement.</p></div></div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready.</div><?php else: ?>
<div class="summary-strip"><div><span>Duplicate booking / referral codes</span><strong><?php echo $duplicateCodes; ?></strong></div><div><span>Existing duplicate contact groups</span><strong><?php echo $duplicateContacts; ?></strong></div><div><span>Matching member codes</span><strong><?php echo $totalCodes; ?></strong></div><div><span>Matching log entries</span><strong><?php echo $totalLogs; ?></strong></div></div>
<section class="card"><h2>Referral settings</h2>
<form class="form-grid" method="post" action="actions.php">
<input type="hidden" name="action" value="save_referral_settings"><input type="hidden" name="back" value="referrals.php"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
<label>Uses allowed per code <span class="required-mark" aria-hidden="true">*</span><input type="number" name="usage_limit" min="1" max="100000" step="1" required value="<?php echo referral_code_usage_limit(); ?>" placeholder="Please enter usage limit"></label>
<label>Code validity in days <span class="required-mark" aria-hidden="true">*</span><input type="number" name="eligibility_days" min="0" max="36500" step="1" required value="<?php echo referral_eligibility_days(); ?>" placeholder="Please enter validity in days"><span class="form-note">0 means no expiry. Replacements start a new validity period.</span></label>
<label class="check-label"><input type="checkbox" name="apply_current" value="1"> Apply usage limit to current codes too</label><div><button class="btn gold">Save settings</button></div>
</form><p class="form-note" style="margin-top:16px">The limit counts each referred booking once, including cancelled or removed referrals. A booking cannot claim a second referral. New limits apply to newly issued codes; tick the option above to update current limits too. Existing expiry dates remain unchanged. <a href="rate-card.php?q=referral">Bonus amounts, lifetime paid-referral limit and bonus cap are managed in Rate Card.</a></p></section>
<section class="card"><h2>Member referral codes</h2>
<form class="search-bar" method="get"><label>Search member or code<input name="q" value="<?php echo h($search); ?>" placeholder="Please enter member name or referral code"></label><?php if ($ownerId): ?><input type="hidden" name="member" value="<?php echo $ownerId; ?>"><?php endif; ?><button class="btn">Search</button><a class="btn" href="referrals.php">Reset</a></form>
<div class="table-wrap"><table><thead><tr><th>Member</th><th>Current referral code</th><th>Uses</th><th>Expiry</th><th>Status</th><th>Replace</th></tr></thead><tbody>
<?php foreach ($codes as $code): $state=referral_code_state($code); ?>
<tr><td><a href="booking-form.php?id=<?php echo (int)$code['booking_id']; ?>#referrals"><?php echo h($code['client_name']); ?></a><br><small>Booking: <?php echo h($code['booking_reference']); ?></small></td><td><strong><?php echo h($code['code']); ?></strong><br><a href="referrals.php?member=<?php echo (int)$code['booking_id']; ?>#logs">View member logs</a></td><td><?php echo (int)$code['usage_count']; ?> / <?php echo (int)$code['usage_limit']; ?></td><td><?php echo h($code['expires_on'] ?: 'No expiry'); ?></td><td><?php echo $code['booking_status']==='cancelled'?'Member cancelled':h(ucfirst($state)); ?></td><td>
<form method="post" action="actions.php" onsubmit="return confirm('Retire this referral code permanently and issue a new code? Booking references and history stay unchanged.');"><input type="hidden" name="action" value="replace_referral_code"><input type="hidden" name="back" value="<?php echo h(referral_page_url([])); ?>"><input type="hidden" name="booking_id" value="<?php echo (int)$code['booking_id']; ?>"><input type="hidden" name="expected_code_id" value="<?php echo (int)$code['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>"><button class="btn gold" <?php echo $code['booking_status']==='cancelled'?'disabled':''; ?>>Replace code</button></form></td></tr>
<?php endforeach; ?><?php if (!$codes): ?><tr><td colspan="6">No matching members.</td></tr><?php endif; ?></tbody></table></div>
<p>Code page <?php echo $codePage; ?> of <?php echo max(1,(int)ceil($totalCodes/25)); ?> <?php if ($codePage>1): ?><a class="btn" href="<?php echo h(referral_page_url(['codes_page'=>$codePage-1])); ?>">Previous</a><?php endif; ?> <?php if ($codePage*25<$totalCodes): ?><a class="btn" href="<?php echo h(referral_page_url(['codes_page'=>$codePage+1])); ?>">Next</a><?php endif; ?></p>
</section>
<section class="card" id="logs"><h2>Referral audit logs</h2><p class="form-note">Issuance, replacement, exhaustion, claims, removals, bonus edits, cancellations and settings changes. Legacy records were imported without changing their booking references.</p>
<div class="table-wrap"><table><thead><tr><th>Time</th><th>Action</th><th>Member / code</th><th>Referred booking</th><th>By</th><th>Details</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?php echo h($log['created_at']); ?></td><td><?php echo h(str_replace('_',' ',$log['event_type'])); ?></td><td><?php echo h($log['client_name'] ?? 'System'); ?><br><?php echo h($log['code'] ?? ''); ?></td><td><?php if ($log['referred_booking_id']): ?><a href="booking-form.php?id=<?php echo (int)$log['referred_booking_id']; ?>#referrals">#<?php echo (int)$log['referred_booking_id']; ?></a><?php else: ?>—<?php endif; ?></td><td><?php echo h($log['actor']); ?></td><td style="max-width:380px;overflow-wrap:anywhere"><?php echo h($log['details']); ?></td></tr><?php endforeach; ?><?php if (!$logs): ?><tr><td colspan="6">No matching log entries.</td></tr><?php endif; ?></tbody></table></div>
<p>Log page <?php echo $page; ?> of <?php echo max(1,(int)ceil($totalLogs/50)); ?> <?php if ($page>1): ?><a class="btn" href="<?php echo h(referral_page_url(['page'=>$page-1])); ?>#logs">Previous</a><?php endif; ?> <?php if ($page*50<$totalLogs): ?><a class="btn" href="<?php echo h(referral_page_url(['page'=>$page+1])); ?>#logs">Next</a><?php endif; ?></p>
</section>
<?php endif; admin_page_end(); ?>
