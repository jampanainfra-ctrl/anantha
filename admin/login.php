<?php
require_once __DIR__ . '/../includes/admin_auth.php';

if (admin_user()) {
    redirect('dashboard.php');
}

$resetSuccess=!empty($_SESSION['password_reset_success']);
unset($_SESSION['password_reset_success']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $spacingError=required_leading_space_error($_POST,['email'=>'Email','password'=>'Password']);
    $csrfValid=recovery_check_csrf((string)($_POST['csrf_token'] ?? ''));
    if ($csrfValid && $spacingError === '' && db_ready()) {
        ensure_admin_recovery_schema();
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $user['id'];
            $_SESSION['admin_session_version'] = (int)$user['session_version'];
            redirect('dashboard.php');
        }
    }
    $error = !$csrfValid ? 'Your sign-in form expired. Please try again.' : ($spacingError ?: 'Invalid admin email or password.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login | Anantha</title>
<link rel="stylesheet" href="<?php echo h(app_url('../assets/forms.css', 'admin')); ?>">
<link rel="stylesheet" href="<?php echo h(app_url('../assets/admin-login.css', 'admin')); ?>">
<script src="<?php echo h(app_url('../assets/forms.js', 'admin')); ?>" defer></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="login-shell">
  <section class="venue-panel" aria-label="Anantha Event Lawns">
    <img class="venue-photo" src="<?php echo h(app_url('../assets/gallery-celebration-evening-optimized.jpg', 'admin')); ?>" alt="Anantha lawn dressed for an evening celebration">
    <a class="brand" href="<?php echo h(app_url('')); ?>"><img src="<?php echo h(app_url('../assets/logo.jpeg', 'admin')); ?>" alt=""><span>Anantha<small>EVENT LAWNS</small></span></a>
    <div class="venue-copy"><span class="eyebrow">THE ANANTHA EXPERIENCE</span><h2>Beautiful celebrations.<br>Thoughtfully managed.</h2><p>A dedicated space to bring every booking, every detail and every celebration together.</p><div class="venue-tags"><span>Bookings</span><span>Payments</span><span>Guest experiences</span></div></div>
    <span class="venue-location">Madhurawada, Visakhapatnam</span>
  </section>
  <section class="login-panel" aria-labelledby="login-title">
    <div class="panel-top"><span class="access-badge"><span aria-hidden="true">&#9670;</span> Admin access</span><a href="<?php echo h(app_url('')); ?>">View website <span aria-hidden="true">&#8599;</span></a></div>
    <div class="login-content">
      <div class="login-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg></div>
      <span class="eyebrow">WELCOME BACK</span>
      <h1 id="login-title">Admin login</h1>
      <p class="intro">Sign in to manage your venue.</p>
      <?php if($resetSuccess): ?><div class="recovery-success" role="status">Password updated. Sign in with your new password.</div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h(recovery_csrf_token()); ?>">
        <?php if ($error): ?><div class="error" role="alert"><?php echo h($error); ?></div><?php endif; ?>
        <div class="field"><label for="email">Email address <span class="required-mark" aria-hidden="true">*</span></label><input id="email" type="email" name="email" required autocomplete="username" placeholder="Please enter email address" value="<?php echo h($email ?? ''); ?>"></div>
        <div class="field"><label for="password">Password <span class="required-mark" aria-hidden="true">*</span></label><div class="password-field"><input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Please enter password"><button class="password-toggle" type="button" aria-controls="password" aria-pressed="false">Show</button></div></div>
        <div class="forgot-password-row"><a href="<?php echo h(app_url('forgot-password.php', 'admin')); ?>">Forgot password?</a></div>
        <button class="login-button" type="submit">Sign in <span aria-hidden="true">&#8594;</span></button>
      </form>
      <p class="access-note">For authorized Anantha administrators only.</p>
    </div>
    <footer>&copy; <?php echo date('Y'); ?> Anantha Event Lawns<span>Administration portal</span></footer>
  </section>
</main>
<script>
const toggle = document.querySelector('.password-toggle');
toggle.addEventListener('click', () => {
  const password = document.getElementById('password');
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password';
  toggle.textContent = visible ? 'Hide' : 'Show';
  toggle.setAttribute('aria-pressed', String(visible));
});
</script>
</body>
</html>
