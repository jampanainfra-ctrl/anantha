<?php
/** Single-use admin password recovery and session revocation. */
function ensure_admin_recovery_schema(): void
{
    static $database=null;
    $current=(string)db()->query('SELECT DATABASE()')->fetchColumn();
    if($database===$current)return;
    if(db()->inTransaction())throw new RuntimeException('Initialize admin recovery before a transaction.');
    if(!db()->query("SHOW COLUMNS FROM admin_users LIKE 'session_version'")->fetch())db()->exec('ALTER TABLE admin_users ADD COLUMN session_version INT NOT NULL DEFAULT 1');
    db()->exec("CREATE TABLE IF NOT EXISTS admin_password_resets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
        used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX reset_admin(admin_id), INDEX reset_expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS admin_reset_limits (
        bucket CHAR(64) PRIMARY KEY, window_start DATETIME NOT NULL, attempts INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $database=$current;
}
function recovery_csrf_token(): string
{
    if(empty($_SESSION['recovery_csrf']))$_SESSION['recovery_csrf']=bin2hex(random_bytes(32));
    return $_SESSION['recovery_csrf'];
}
function recovery_check_csrf(string $token): bool
{
    return $token!=='' && hash_equals(recovery_csrf_token(),$token);
}
function admin_recovery_url(string $token): string
{
    $origin=rtrim(getenv('ANANTHA_APP_ORIGIN') ?: 'http://localhost','/');
    $parts=parse_url($origin);
    if(!$parts || !in_array($parts['scheme'] ?? '',['http','https'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path']))throw new RuntimeException('Configure a valid application origin for reset emails.');
    return $origin.app_url('admin/reset-password.php').'?token='.rawurlencode($token);
}
function recovery_limit_allowed(string $email,string $ip): bool
{
    if(!db()->inTransaction())throw new RuntimeException('Recovery rate limits require a transaction.');
    $buckets=[hash('sha256','email:'.$email)=>5,hash('sha256','ip:'.$ip)=>20];
    ksort($buckets);$allowed=true;
    foreach($buckets as $bucket=>$limit){
        db()->prepare('INSERT IGNORE INTO admin_reset_limits(bucket,window_start,attempts) VALUES (?,NOW(),0)')->execute([$bucket]);
        $stmt=db()->prepare('SELECT attempts,window_start,(window_start<=DATE_SUB(NOW(),INTERVAL 1 HOUR)) AS expired FROM admin_reset_limits WHERE bucket=? FOR UPDATE');$stmt->execute([$bucket]);$row=$stmt->fetch();
        $old=(bool)$row['expired'];
        $attempts=$old?0:(int)$row['attempts'];
        if($attempts>=$limit)$allowed=false;
        db()->prepare('UPDATE admin_reset_limits SET attempts=?,window_start=IF(?,NOW(),window_start) WHERE bucket=?')->execute([min(100000,$attempts+1),$old?1:0,$bucket]);
    }
    return $allowed;
}
function request_admin_password_reset(string $email,string $ip): void
{
    ensure_admin_recovery_schema();ensure_mail_schema();
    reject_required_leading_spaces(['email'=>$email],['email'=>'Email']);
    $email=strtolower(trim($email));
    db()->beginTransaction();
    try{
        $allowed=recovery_limit_allowed($email,$ip);
        $stmt=db()->prepare('SELECT id,email FROM admin_users WHERE LOWER(email)=? FOR UPDATE');$stmt->execute([$email]);$admin=$stmt->fetch();
        if($allowed && $admin && filter_var($admin['email'],FILTER_VALIDATE_EMAIL)){
            $token=bin2hex(random_bytes(32));$url=admin_recovery_url($token);
            db()->prepare('UPDATE admin_password_resets SET used_at=NOW() WHERE admin_id=? AND used_at IS NULL')->execute([$admin['id']]);
            db()->prepare('INSERT INTO admin_password_resets(admin_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$admin['id'],hash('sha256',$token)]);
            $resetId=(int)db()->lastInsertId();
            queue_mail('admin-password-reset:'.$resetId,'admin_password_reset',$admin['email'],'admin','Reset your Anantha admin password','A password reset was requested for your admin account. Open the link below to choose a new password. The link expires in 30 minutes and works only once. If you did not request this, you can ignore this email.',['Reset link'=>$url,'Expires in'=>'30 minutes']);
        }
        db()->commit();mail_schedule_dispatch();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function valid_admin_reset_token(string $token): bool
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
    ensure_admin_recovery_schema();
    $stmt=db()->prepare('SELECT id FROM admin_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW()');$stmt->execute([hash('sha256',$token)]);
    return (bool)$stmt->fetchColumn();
}
function complete_admin_password_reset(string $token,string $password,string $confirmation): void
{
    reject_required_leading_spaces(['password'=>$password,'confirm_password'=>$confirmation],['password'=>'New password','confirm_password'=>'Confirm password']);
    if(mb_strlen($password)<12 || strlen($password)>72)throw new RuntimeException('Please use a password between 12 and 72 characters.');
    if(!hash_equals($password,$confirmation))throw new RuntimeException('The passwords do not match.');
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('This reset link is invalid or expired. Please request a new one.');
    ensure_admin_recovery_schema();ensure_mail_schema();
    db()->beginTransaction();
    try{
        // Lock account then reset rows, matching the issuance lock order.
        $stmt=db()->prepare('SELECT admin_id FROM admin_password_resets WHERE token_hash=?');$stmt->execute([hash('sha256',$token)]);$id=(int)$stmt->fetchColumn();
        $stmt=db()->prepare('SELECT id,email FROM admin_users WHERE id=? FOR UPDATE');$stmt->execute([$id]);$admin=$stmt->fetch();
        $stmt=db()->prepare('SELECT id FROM admin_password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE');$stmt->execute([hash('sha256',$token)]);$resetId=$stmt->fetchColumn();
        if(!$admin || !$resetId)throw new RuntimeException('This reset link is invalid or expired. Please request a new one.');
        db()->prepare('UPDATE admin_users SET password_hash=?,session_version=session_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        db()->prepare('UPDATE admin_password_resets SET used_at=NOW() WHERE admin_id=? AND used_at IS NULL')->execute([$id]);
        queue_mail('admin-password-changed:'.$resetId,'admin_password_changed',$admin['email'],'admin','Your admin password was changed','Your Anantha administrator password has been reset. Existing admin sessions have been signed out. If you did not make this change, contact the site administrator immediately.',['Changed on'=>date('d M Y, H:i:s')]);
        db()->commit();
        unset($_SESSION['admin_id'],$_SESSION['admin_session_version']);
        session_regenerate_id(true);$_SESSION['password_reset_success']=true;
        mail_schedule_dispatch();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
