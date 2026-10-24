<?php
function recovery_page_start(string $title,string $intro): void
{
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo h($title); ?> | Anantha Admin</title><link rel="stylesheet" href="<?php echo h(app_url('../assets/forms.css','admin')); ?>"><link rel="stylesheet" href="<?php echo h(app_url('../assets/admin-login.css','admin')); ?>"><script src="<?php echo h(app_url('../assets/forms.js','admin')); ?>" defer></script></head><body>
<main class="recovery-card"><a class="recovery-brand" href="<?php echo h(app_url('login.php','admin')); ?>"><img src="<?php echo h(app_url('../assets/logo.jpeg','admin')); ?>" alt=""><span>Anantha<small>ADMINISTRATION</small></span></a><span class="eyebrow">ACCOUNT RECOVERY</span><h1><?php echo h($title); ?></h1><p class="intro"><?php echo h($intro); ?></p>
<?php
}
function recovery_page_end(): void
{
    ?>
<a class="recovery-back" href="<?php echo h(app_url('login.php','admin')); ?>">&larr; Back to admin login</a><p class="access-note">For authorized Anantha administrators only.</p></main></body></html>
<?php
}
