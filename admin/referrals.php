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
    return app_url('admin/referrals.php') . '?' . http_build_query(array_merge($_GET,$changes));
}
admin_page_start('Referrals & Logs','referrals');
?>
<div class="referral-heading"><div><span class="referral-eyebrow">MEMBER REWARDS</span><h1>Referrals &amp; logs</h1><p>Manage member codes, set usage rules and follow every referral.</p></div><a class="referral-link-button" href="#logs">View audit logs <span aria-hidden="true">&darr;</span></a></div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready.</div><?php else: ?>
<div class="referral-policy"><span class="policy-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6Z"/><path d="m8 12 3 3 5-6"/></svg></span><div><strong>Code replacement stays in your control</strong><p>Codes stop at their limit or expiry. Issue a replacement when you choose; previous codes and history are retained.</p></div><a href="#referral-settings">Manage rules &rarr;</a></div>
<div class="referral-metrics">
  <div><span>Matching member codes</span><strong><?php echo $totalCodes; ?></strong><small><?php echo ($search !== '' || $ownerId) ? 'Current filter results' : 'Current member codes'; ?></small></div>
  <div><span>Matching audit entries</span><strong><?php echo $totalLogs; ?></strong><small>Recorded referral activity</small></div>
  <div class="<?php echo $duplicateCodes ? 'metric-warning' : 'metric-clear'; ?>"><span>Duplicate codes</span><strong><?php echo $duplicateCodes; ?></strong><small>Booking &amp; referral code checks</small></div>
  <div class="<?php echo $duplicateContacts ? 'metric-warning' : ''; ?>"><span>Shared contact groups</span><strong><?php echo $duplicateContacts; ?></strong><small>Existing bookings sharing contact details</small></div>
</div>
<section class="card referral-settings" id="referral-settings">
  <div class="referral-section-heading"><div><h2>Referral settings</h2><p>Set the defaults for newly issued codes.</p></div><a class="text-link" href="<?php echo h(app_url('rate-card.php', 'admin')); ?>?q=referral">Bonus settings in Rate Card &nearr;</a></div>
  <div class="settings-layout"><form class="referral-settings-form" method="post" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
    <input type="hidden" name="action" value="save_referral_settings"><input type="hidden" name="back" value="<?php echo h(app_url('referrals.php', 'admin')); ?>"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
    <label>Uses allowed per code <span class="required-mark" aria-hidden="true">*</span><input type="number" name="usage_limit" min="1" max="100000" step="1" required value="<?php echo referral_code_usage_limit(); ?>" placeholder="Please enter usage limit"><span class="field-help">Each referred booking counts as one use.</span></label>
    <label>Code validity in days <span class="required-mark" aria-hidden="true">*</span><input type="number" name="eligibility_days" min="0" max="36500" step="1" required value="<?php echo referral_eligibility_days(); ?>" placeholder="Please enter validity in days"><span class="field-help">Enter 0 for no expiry.</span></label>
    <label class="apply-current"><input type="checkbox" name="apply_current" value="1"><span>Update usage limits on current codes too<small>Existing expiry dates stay the same.</small></span></label>
    <div class="settings-save"><button class="btn gold">Save settings</button><span>Applies to newly issued codes by default.</span></div>
  </form><aside class="settings-guide"><span class="referral-eyebrow">HOW IT WORKS</span><ul><li>Cancelled or removed referrals still count toward the usage limit.</li><li>A booking can claim a referral only once.</li><li>Replacement codes start a new validity period.</li></ul><p>Bonus amounts, lifetime paid-referral limits and bonus caps are managed in Rate Card.</p></aside></div>
</section>
<section class="card referral-members" id="member-codes">
  <div class="referral-section-heading"><div><h2>Member referral codes <span class="section-count"><?php echo $totalCodes; ?></span></h2><p>View usage, copy a code or issue a replacement.</p></div></div>
  <form class="referral-search" method="get" action="<?php echo h(app_url('referrals.php', 'admin')); ?>"><label for="member-search">Search member or code<input id="member-search" name="q" value="<?php echo h($search); ?>" placeholder="Please enter member name or referral code"></label><?php if ($ownerId): ?><input type="hidden" name="member" value="<?php echo $ownerId; ?>"><?php endif; ?><button class="btn">Search</button><?php if ($search !== '' || $ownerId): ?><a class="referral-link-button" href="<?php echo h(app_url('referrals.php', 'admin')); ?>">Clear filters</a><?php endif; ?></form>
  <?php if ($ownerId): ?><div class="filter-notice">Showing one member’s codes and audit history. <a href="<?php echo h(app_url('referrals.php', 'admin')); ?>">View all members &rarr;</a></div><?php endif; ?>
  <p class="referral-table-hint">Scroll the table sideways to see usage, status and actions &rarr;</p><div class="table-wrap"><table class="referral-codes-table"><thead><tr><th>Member</th><th>Current referral code</th><th>Usage</th><th>Expires</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($codes as $code): $state=referral_code_state($code); $cancelled=$code['booking_status']==='cancelled'; $usagePercent=min(100,(int)round(100*(int)$code['usage_count']/max(1,(int)$code['usage_limit']))); ?>
  <tr><td><a class="member-name" href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int)$code['booking_id']; ?>#referrals"><?php echo h($code['client_name']); ?></a><small class="booking-reference">Booking: <?php echo h($code['booking_reference']); ?></small></td>
  <td><div class="code-value"><code><?php echo h($code['code']); ?></code><button class="copy-code" type="button" data-copy-code="<?php echo h($code['code']); ?>" aria-label="Copy referral code for <?php echo h($code['client_name']); ?>">Copy</button></div><a class="member-logs" href="<?php echo h(app_url('referrals.php', 'admin')); ?>?member=<?php echo (int)$code['booking_id']; ?>#logs">View member history &rarr;</a></td>
  <td><div class="usage-value"><strong><?php echo (int)$code['usage_count']; ?></strong><span>/ <?php echo (int)$code['usage_limit']; ?></span></div><div class="usage-track" aria-hidden="true"><span style="width:<?php echo $usagePercent; ?>%"></span></div></td>
  <td class="expiry-cell"><?php echo $code['expires_on'] ? h(date('d M Y',strtotime($code['expires_on']))) : 'No expiry'; ?></td>
  <td><span class="referral-status status-<?php echo $cancelled ? 'cancelled' : h($state); ?>"><?php echo $cancelled ? 'Member cancelled' : h(ucfirst($state)); ?></span></td>
  <td><form method="post" action="<?php echo h(app_url('actions.php', 'admin')); ?>" onsubmit="return confirm('Retire this referral code permanently and issue a new code? Booking references and history stay unchanged.');"><input type="hidden" name="action" value="replace_referral_code"><input type="hidden" name="back" value="<?php echo h(referral_page_url([])); ?>"><input type="hidden" name="booking_id" value="<?php echo (int)$code['booking_id']; ?>"><input type="hidden" name="expected_code_id" value="<?php echo (int)$code['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>"><button class="replace-button" <?php echo $cancelled ? 'disabled' : ''; ?>>Replace code</button></form></td></tr>
  <?php endforeach; ?><?php if (!$codes): ?><tr><td colspan="6"><div class="referral-empty"><strong>No members found</strong><p>Try a different member name or referral code.</p><a href="<?php echo h(app_url('referrals.php', 'admin')); ?>">Clear filters</a></div></td></tr><?php endif; ?></tbody></table></div>
  <nav class="referral-pagination" aria-label="Member codes pages"><span>Showing <?php echo $totalCodes ? ($codePage-1)*25+1 : 0; ?>&ndash;<?php echo min($codePage*25,$totalCodes); ?> of <?php echo $totalCodes; ?> members</span><div><span>Page <?php echo $codePage; ?> of <?php echo max(1,(int)ceil($totalCodes/25)); ?></span><?php if ($codePage>1): ?><a href="<?php echo h(referral_page_url(['codes_page'=>$codePage-1])); ?>#member-codes">Previous</a><?php endif; ?><?php if ($codePage*25<$totalCodes): ?><a href="<?php echo h(referral_page_url(['codes_page'=>$codePage+1])); ?>#member-codes">Next</a><?php endif; ?></div></nav>
</section>
<section class="card referral-audit" id="logs">
  <div class="referral-section-heading"><div><h2>Referral audit logs <span class="section-count"><?php echo $totalLogs; ?></span></h2><p>A record of code issuance, replacements, claims, bonus edits and settings changes.</p></div><span class="audit-label">Activity history</span></div>
  <p class="referral-table-hint">Scroll the table sideways to see all activity details &rarr;</p><div class="table-wrap"><table class="referral-logs-table"><thead><tr><th>When</th><th>Activity</th><th>Member / code</th><th>Referred booking</th><th>By</th><th>Details</th></tr></thead><tbody>
  <?php foreach ($logs as $log): ?><tr><td><span class="log-date"><?php echo h(date('d M Y',strtotime($log['created_at']))); ?></span><small><?php echo h(date('H:i:s',strtotime($log['created_at']))); ?></small></td><td><span class="audit-action"><?php echo h(ucfirst(str_replace('_',' ',$log['event_type']))); ?></span></td><td><strong><?php echo h($log['client_name'] ?? 'System'); ?></strong><?php if (!empty($log['code'])): ?><code class="log-code"><?php echo h($log['code']); ?></code><?php endif; ?></td><td><?php if ($log['referred_booking_id']): ?><a class="text-link" href="<?php echo h(app_url('booking-form.php', 'admin')); ?>?id=<?php echo (int)$log['referred_booking_id']; ?>#referrals">Booking #<?php echo (int)$log['referred_booking_id']; ?> &nearr;</a><?php else: ?><span class="muted">&mdash;</span><?php endif; ?></td><td><span class="log-actor"><?php echo h($log['actor']); ?></span></td><td class="log-details"><?php echo h($log['details']); ?></td></tr><?php endforeach; ?>
  <?php if (!$logs): ?><tr><td colspan="6"><div class="referral-empty"><strong>No activity found</strong><p>Referral activity will appear here as codes are used or updated.</p></div></td></tr><?php endif; ?></tbody></table></div>
  <nav class="referral-pagination" aria-label="Audit log pages"><span>Showing <?php echo $totalLogs ? ($page-1)*50+1 : 0; ?>&ndash;<?php echo min($page*50,$totalLogs); ?> of <?php echo $totalLogs; ?> entries</span><div><span>Page <?php echo $page; ?> of <?php echo max(1,(int)ceil($totalLogs/50)); ?></span><?php if ($page>1): ?><a href="<?php echo h(referral_page_url(['page'=>$page-1])); ?>#logs">Previous</a><?php endif; ?><?php if ($page*50<$totalLogs): ?><a href="<?php echo h(referral_page_url(['page'=>$page+1])); ?>#logs">Next</a><?php endif; ?></div></nav>
  <p class="audit-footnote">Legacy referrals were imported with their original booking references and usage history.</p>
</section>
<p class="copy-feedback" role="status" aria-live="polite"></p>
<script src="<?php echo h(app_url('../assets/admin-referrals.js', 'admin')); ?>" defer></script>
<?php endif; admin_page_end(); ?>
