<?php
require_once __DIR__ . '/includes/config.php';

$terms = [];
if (db_ready()) {
    ensure_terms_schema();
    $terms = db()->query('SELECT title, term_text FROM site_terms WHERE is_active=1 ORDER BY display_order ASC, id ASC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms & Conditions | <?php echo h(SITE_NAME); ?></title>
<link rel="icon" href="<?php echo h(app_url('assets/logo.jpeg', '')); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--forest:#20483f;--gold:#d9a050;--sun:#f1cf8f;--paper:#f8f3e8;--white:#fffdf8;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.14);--cream:#e4dac3;--shadow:0 24px 60px -34px rgba(20,47,40,.55)}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:linear-gradient(180deg,#fffaf0 0,#f8f3e8 54%,#eef5ef 100%);color:var(--ink);overflow-x:hidden}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1120px,calc(100% - 36px));margin:auto}.terms-hero{min-height:470px;padding:150px 0 72px;background:linear-gradient(90deg,rgba(20,47,40,.92),rgba(20,47,40,.54)),url('<?php echo h(app_url('assets/gallery-celebration-evening-optimized.jpg')); ?>') center/cover;color:#fff;display:flex;align-items:flex-end}.eyebrow{font-size:.78rem;letter-spacing:.13em;text-transform:uppercase;color:var(--sun);font-weight:800}.terms-hero h1{font-size:clamp(2.6rem,6vw,5rem);line-height:1;margin:10px 0 14px}.terms-hero p{max-width:700px;color:rgba(255,255,255,.86);line-height:1.7;font-size:1.05rem}.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;background:var(--gold);color:#261b10;padding:12px 18px;font-weight:800}.btn.light{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);color:#fff}.terms-shell{padding:64px 0 78px}.terms-head{display:grid;grid-template-columns:.76fr 1.24fr;gap:34px;align-items:start;margin-bottom:24px}.terms-head h2{font-size:clamp(1.9rem,4vw,3.1rem);line-height:1.08;color:var(--deep);margin:0}.terms-head p{margin:0;color:var(--muted);line-height:1.75}.terms{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.term{position:relative;background:var(--white);border:1px solid var(--line);border-radius:8px;padding:24px;box-shadow:var(--shadow);overflow:hidden}.term:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--gold)}.term h2{font-size:1.2rem;color:var(--deep);margin:0 0 9px}.term p{margin:0;color:var(--muted);line-height:1.68}.notice{margin-top:18px;display:flex;justify-content:space-between;gap:18px;align-items:center;flex-wrap:wrap;background:var(--deep);color:var(--cream);border-radius:8px;padding:22px 24px}.notice strong{display:block;color:#fff}.notice span{display:block;margin-top:4px;color:rgba(228,218,195,.76);line-height:1.5}.notice .btn{background:var(--sun);color:#261b10}@media(max-width:860px){.terms-head,.terms{grid-template-columns:1fr}.terms-hero{min-height:420px}}@media(max-width:540px){.container,.wrap{width:min(100% - 26px,1120px)}.terms-hero{padding-top:122px}.hero-actions .btn,.notice .btn{width:100%}.terms-shell{padding:46px 0 58px}.term,.notice{padding:20px}}
</style>
</head>
<body>
<?php $publicHeaderActive = 'rules'; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>
<main>
  <section class="terms-hero">
    <div class="wrap">
      <div class="eyebrow">Booking terms</div>
      <h1>Terms & Conditions</h1>
      <p>Clear booking points for enquiries, payments, security deposit, cancellations and house rules at Anantha Event Lawns.</p>
      <div class="hero-actions">
        <a class="btn" href="<?php echo h(app_url('availability.php', '')); ?>">Check Availability</a>
        <a class="btn light" href="<?php echo h(app_url('rules.php', '')); ?>">Venue Rules</a>
      </div>
    </div>
  </section>
  <section class="terms-shell">
    <div class="wrap">
      <div class="terms-head">
        <h2>Before you confirm your date</h2>
        <p>These terms help both sides keep dates, payments and event-day responsibilities clear. Our team confirms final details through phone or WhatsApp.</p>
      </div>
      <div class="terms">
        <?php foreach ($terms as $term): ?>
          <article class="term"><h2><?php echo h($term['title']); ?></h2><p><?php echo h($term['term_text']); ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
<?php include __DIR__ . '/includes/chatbot.php'; ?>
<script>
const header=document.getElementById('siteHeader');window.addEventListener('scroll',()=>header.classList.toggle('scrolled',window.scrollY>40));
const menuToggle=document.getElementById('menuToggle');const menuClose=document.getElementById('menuClose');const navLinks=document.getElementById('navLinks');const navBackdrop=document.getElementById('navBackdrop');
const setMenuOpen=(isOpen)=>{navLinks.classList.toggle('open',isOpen);navBackdrop.classList.toggle('open',isOpen);menuToggle.classList.toggle('is-open',isOpen);menuToggle.setAttribute('aria-expanded',isOpen?'true':'false');menuToggle.setAttribute('aria-label',isOpen?'Close menu':'Open menu');document.body.classList.toggle('menu-open',isOpen);};
menuToggle.addEventListener('click',()=>setMenuOpen(!navLinks.classList.contains('open')));menuClose.addEventListener('click',()=>setMenuOpen(false));navBackdrop.addEventListener('click',()=>setMenuOpen(false));navLinks.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setMenuOpen(false)));window.addEventListener('keydown',(event)=>{if(event.key==='Escape')setMenuOpen(false);});
</script>
</body>
</html>
