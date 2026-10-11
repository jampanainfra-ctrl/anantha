<?php
require_once __DIR__ . '/../includes/admin_auth.php';

if (admin_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (db_ready()) {
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id'] = (int) $user['id'];
            redirect('dashboard.php');
        }
    }
    $error = 'Invalid admin email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Anantha</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}html,body{max-width:100%;overflow-x:hidden}body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,rgba(20,47,40,.86),rgba(20,47,40,.62)),url('../assets/hero-nature-event-optimized.jpg') center/cover;font-family:'Inter',sans-serif;padding:18px}.card{width:min(420px,100%);background:#f8f3e8;border-radius:8px;padding:34px;box-shadow:0 30px 80px -35px #000}.brand{display:flex;gap:12px;align-items:center;margin-bottom:28px}.brand img{width:52px;height:52px;border-radius:50%;object-fit:cover}.brand strong{font-family:'Inter',sans-serif;font-size:1.45rem;color:#142f28}h1{font-family:'Inter',sans-serif;margin:0 0 8px;color:#142f28}p{margin:0 0 22px;color:#66736d}label{display:block;font-size:.82rem;font-weight:700;color:#526058;margin-top:14px}input{width:100%;margin-top:7px;border:1px solid rgba(20,47,40,.16);border-radius:8px;padding:13px;font:inherit;background:#fff;font-size:16px}.btn{width:100%;min-height:46px;margin-top:20px;border:0;border-radius:999px;background:#d9a050;color:#21170c;padding:13px;font-weight:800;cursor:pointer}.error{background:#fae7e4;color:#9e3029;border-radius:8px;padding:12px;margin-bottom:14px}.hint{font-size:.86rem;margin-top:18px;color:#6e6453}@media(max-width:420px){body{padding:12px}.card{padding:24px}.brand{margin-bottom:22px}.brand strong{font-size:1.25rem}h1{font-size:1.75rem}}
</style>
</head>
<body>
<form class="card" method="POST">
  <div class="brand"><img src="../assets/logo.jpeg" alt=""><strong>Anantha</strong></div>
  <h1>Admin login</h1>
  <p>Manage bookings, gallery, proposals and reviews.</p>
  <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
  <label>Email<input type="email" name="email" required value="admin@anantha.local"></label>
  <label>Password<input type="password" name="password" required placeholder="admin123"></label>
  <button class="btn">Login</button>
  <p class="hint">Change this default login after setup.</p>
</form>
</body>
</html>
