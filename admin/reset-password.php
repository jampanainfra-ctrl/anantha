<?php
require_once __DIR__.'/../includes/admin_auth.php';
require_once __DIR__.'/../includes/admin_recovery_layout.php';
$token=(string)($_POST['token'] ?? $_GET['token'] ?? '');$error='';$valid=false;
try{$valid=valid_admin_reset_token($token);}catch(Throwable $e){error_log('Anantha recovery: token check could not complete.');}
if($_SERVER['REQUEST_METHOD']==='POST' && $valid){
    if(!recovery_check_csrf((string)($_POST['csrf_token'] ?? '')))$error='Your form expired. Please try again.';
    else{
        try{
            complete_admin_password_reset($token,(string)($_POST['password'] ?? ''),(string)($_POST['confirm_password'] ?? ''));
            redirect('login.php');
        }catch(Throwable $e){
            $error=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'Your password could not be updated. Please try again.';
        }
    }
}
recovery_page_start('Set a new password','Choose a password you have not used for this admin account before.');
?>
<?php if(!$valid): ?><div class="error" role="alert">This reset link is invalid, expired or already used.</div><a class="login-button" href="<?php echo h(app_url('forgot-password.php','admin')); ?>">Request a new link</a>
<?php else: ?>
<?php if($error): ?><div class="error" role="alert"><?php echo h($error); ?></div><?php endif; ?>
<form method="post" action="<?php echo h(app_url('reset-password.php','admin')); ?>"><input type="hidden" name="csrf_token" value="<?php echo h(recovery_csrf_token()); ?>"><input type="hidden" name="token" value="<?php echo h($token); ?>"><div class="field"><label for="password">New password <span class="required-mark" aria-hidden="true">*</span></label><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-guidance" placeholder="Please enter new password"><small id="password-guidance" class="recovery-help">Use 12–72 characters. A longer passphrase is easier to remember.</small></div><div class="field"><label for="confirm_password">Confirm password <span class="required-mark" aria-hidden="true">*</span></label><input id="confirm_password" name="confirm_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" placeholder="Please enter password again"></div><button type="button" class="recovery-show" aria-pressed="false" aria-controls="password confirm_password">Show passwords</button><button class="login-button" type="submit">Save new password</button></form>
<script>document.querySelector('.recovery-show').addEventListener('click',function(){const visible=this.getAttribute('aria-pressed')!=='true';document.querySelectorAll('input[autocomplete="new-password"]').forEach(input=>input.type=visible?'text':'password');this.setAttribute('aria-pressed',String(visible));this.textContent=visible?'Hide passwords':'Show passwords';});</script>
<?php endif; recovery_page_end(); ?>
