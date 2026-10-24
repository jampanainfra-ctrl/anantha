<?php
require_once __DIR__.'/../includes/admin_auth.php';
require_once __DIR__.'/../includes/admin_recovery_layout.php';
$error='';$email='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim((string)($_POST['email'] ?? ''));
    if(!recovery_check_csrf((string)($_POST['csrf_token'] ?? '')))$error='Your form expired. Please try again.';
    elseif(($spacingError=required_leading_space_error($_POST,['email'=>'Email']))!=='')$error=$spacingError;
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Please enter a valid email address.';
    else{
        try{request_admin_password_reset($email,(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));}
        catch(Throwable $e){error_log('Anantha recovery: reset request could not be queued.');}
        // Identical response for unknown accounts, rate limits and queue failures.
        redirect('forgot-password.php?requested=1');
    }
}
recovery_page_start('Forgot password?','Enter the email address registered to your admin account.');
?>
<?php if(isset($_GET['requested'])): ?><div class="recovery-success" role="status">If this email belongs to an admin account, a reset link has been requested. Check your inbox and spam folder. Only the latest link will work.</div><?php endif; ?>
<?php if($error): ?><div class="error" role="alert"><?php echo h($error); ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo h(recovery_csrf_token()); ?>"><div class="field"><label for="email">Admin email <span class="required-mark" aria-hidden="true">*</span></label><input id="email" name="email" type="email" required maxlength="254" autocomplete="username" value="<?php echo h($email); ?>" placeholder="Please enter email address"></div><button class="login-button" type="submit">Send reset link <span aria-hidden="true">&rarr;</span></button></form>
<?php recovery_page_end(); ?>
