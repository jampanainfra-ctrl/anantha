<?php
require_once __DIR__ . '/includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Venue | <?php echo SITE_NAME; ?></title>
<meta name="description" content="Explore Anantha Event Lawns venue spaces in Kommadi, Madhurawada, Visakhapatnam.">
<link rel="icon" href="<?php echo h(app_url('assets/logo.jpeg', '')); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--forest:#20483f;--leaf:#2e6b58;--gold:#d9a050;--paper:#f8f3e8;--soft:#fffaf0;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.14);--cream:#e4dac3;--shadow:0 24px 60px -34px rgba(20,47,40,.58)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:linear-gradient(180deg,#fffaf0 0,#f8f3e8 48%,#eef5ef 100%);color:var(--ink);overflow-x:hidden}img{max-width:100%;display:block}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1180px,calc(100% - 36px));margin:auto}
header{position:fixed;top:0;left:0;right:0;z-index:100;padding:18px 0;transition:background .4s ease,padding .4s ease,box-shadow .4s ease}header.scrolled{background:rgba(23,51,43,.92);backdrop-filter:blur(10px);padding:12px 0;box-shadow:0 10px 30px -20px rgba(0,0,0,.4)}.nav{display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:10px}.brand img{height:44px;width:44px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(228,218,195,.28)}.brand span{font-family:'Inter',sans-serif;letter-spacing:.04em;font-size:1.08rem;color:var(--cream)}.nav-links{display:flex;gap:24px;list-style:none;margin:0;padding:0}.nav-links a{color:var(--cream);font-size:.88rem;font-weight:500;position:relative;padding-bottom:4px}.nav-links a:after{content:"";position:absolute;left:0;bottom:0;width:0;height:1px;background:var(--gold);transition:width .3s ease}.nav-links a:hover:after,.nav-links a.active:after{width:100%}.menu-toggle{display:none;width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(23,51,43,.28);color:var(--cream);cursor:pointer;place-items:center}.menu-toggle span,.menu-toggle span:before,.menu-toggle span:after{display:block;width:19px;height:2px;border-radius:2px;background:currentColor;content:"";transition:transform .3s ease,opacity .3s ease}.menu-toggle span:before{transform:translateY(-7px)}.menu-toggle span:after{transform:translateY(5px)}.menu-toggle.is-open span{background:transparent}.menu-toggle.is-open span:before{transform:translateY(2px) rotate(45deg)}.menu-toggle.is-open span:after{transform:translateY(0) rotate(-45deg)}.menu-close-row{display:none}.menu-close{width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(255,255,255,.06);color:var(--cream);font-size:1.7rem;line-height:1;cursor:pointer}.nav-backdrop{position:fixed;inset:0;z-index:99;background:rgba(8,28,23,.54);opacity:0;pointer-events:none;transition:opacity .3s ease}.nav-backdrop.open{opacity:1;pointer-events:auto}
.hero{min-height:560px;padding:150px 0 76px;color:#fff;background:linear-gradient(90deg,rgba(20,47,40,.88),rgba(20,47,40,.38)),url('<?php echo h(app_url('assets/hero-nature-event-optimized.jpg')); ?>') center/cover;display:flex;align-items:flex-end}.eyebrow{font-size:.78rem;letter-spacing:.13em;text-transform:uppercase;color:#f1cf8f;font-weight:800}.hero h1,.section-head h2,.feature-copy h2,.rule-section h2,.terms h2{font-family:'Inter',sans-serif}.hero h1{font-size:clamp(2.6rem,6vw,5.2rem);line-height:.98;margin:10px 0 16px}.hero p{max-width:720px;color:rgba(255,255,255,.88);font-size:1.08rem;line-height:1.7}.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}.btn{border:0;border-radius:999px;background:var(--gold);color:#261b10;padding:12px 20px;text-decoration:none;font-weight:800;display:inline-flex;align-items:center;justify-content:center}.btn.light{background:rgba(255,255,255,.13);color:#fff;border:1px solid rgba(255,255,255,.28)}
.spaces{padding:82px 0}.section-head{display:flex;justify-content:space-between;gap:24px;align-items:end;margin-bottom:28px}.section-head h2{font-size:clamp(2rem,4vw,3.4rem);line-height:1;margin:8px 0 0;color:var(--deep)}.section-head p{max-width:520px;color:var(--muted);line-height:1.65}.feature-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.feature-card{background:#fff;border:1px solid var(--line);border-radius:8px;overflow:hidden;box-shadow:var(--shadow)}.feature-card img{width:100%;height:300px;object-fit:cover}.feature-copy{padding:24px}.feature-copy .num{color:var(--gold);font-weight:800;letter-spacing:.12em}.feature-copy h2{font-size:1.7rem;color:var(--deep);margin:8px 0 10px}.feature-copy p{color:var(--muted);line-height:1.65}.tags{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}.tags span{border:1px solid var(--line);border-radius:999px;padding:7px 12px;color:var(--leaf);font-size:.82rem;font-weight:700}
.rules-hero{background:var(--deep);color:var(--cream);padding:78px 0 30px}.rules-intro{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:34px;align-items:end}.rules-intro h2{font-family:'Inter',sans-serif;font-size:clamp(2.2rem,4.4vw,4rem);line-height:1;margin:8px 0 0;color:#fff}.rules-intro p{color:rgba(228,218,195,.8);line-height:1.75}.rule-section{background:var(--deep);color:var(--cream);padding:34px 0}.rule-section h2{font-size:1.7rem;color:#fff;margin:0 0 18px}.rule-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.rule-card{background:rgba(255,255,255,.07);border:1px solid rgba(228,218,195,.18);border-radius:8px;padding:20px}.rule-card .num{color:#f1cf8f;font-size:.76rem;font-weight:800;letter-spacing:.11em}.rule-card h3{font-family:'Inter',sans-serif;color:#fff;font-size:1.18rem;margin:8px 0 8px}.rule-card p{margin:0;color:rgba(228,218,195,.78);line-height:1.65}
.terms{padding:76px 0}.terms h2{font-size:clamp(2rem,4vw,3.3rem);line-height:1;margin:8px 0 22px;color:var(--deep)}.term-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.term-card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:22px;box-shadow:var(--shadow)}.term-card h3{font-family:'Inter',sans-serif;color:var(--deep);margin:0 0 8px}.term-card p{margin:0;color:var(--muted);line-height:1.65}.remember{margin-top:18px;padding:24px;border-radius:8px;background:var(--gold);color:#261b10;font-weight:800;display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
footer{background:#10241f;color:var(--cream);padding:42px 0}.foot-row{display:grid;grid-template-columns:1.2fr repeat(3,1fr);gap:24px}.foot-brand{display:flex;align-items:center;gap:10px;font-family:'Inter',sans-serif}.foot-brand img{width:44px;height:44px;border-radius:50%;object-fit:cover}.foot-col a,.foot-col p{display:block;margin-top:8px;color:rgba(228,218,195,.74);line-height:1.6}
@media(max-width:920px){.section-head,.rules-intro{display:block}.section-head p,.rules-intro p{margin-top:14px}.feature-grid,.rule-grid,.term-grid,.foot-row{grid-template-columns:1fr}.hero{min-height:500px}.feature-card img{height:250px}}@media(max-width:860px){.nav-links{position:fixed;top:0;right:0;z-index:101;height:100vh;width:min(320px,86vw);background:#17332b;flex-direction:column;padding:100px 32px 32px;transform:translateX(100%);transition:transform .4s ease;gap:22px;box-shadow:-24px 0 50px -28px rgba(0,0,0,.65)}.nav-links.open{transform:translateX(0)}.menu-toggle{display:grid}.menu-close-row{display:flex;justify-content:flex-end;position:absolute;top:22px;right:24px}body.menu-open{overflow:hidden}}@media(max-width:540px){.container,.wrap{width:min(100% - 26px,1180px)}.hero{padding-top:120px}.hero-actions .btn{width:100%}.spaces,.terms{padding:58px 0}.rules-hero{padding-top:58px}}
.nav-links a:hover,.nav-links a.active{color:var(--gold);font-weight:800}
</style>
</head>
<body>
<?php $publicHeaderActive = 'about'; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>

<main>
  <section class="hero">
    <div class="wrap">
      <div class="eyebrow">Venue</div>
      <h1>Anantha Event Lawns.</h1>
      <p>A private celebration venue in Kommadi, Madhurawada for weddings, receptions, birthdays and family gatherings.</p>
      <div class="hero-actions">
        <a class="btn" href="<?php echo h(app_url('availability.php', '')); ?>">Check Availability</a>
        <a class="btn light" href="<?php echo h(app_url('rules.php', '')); ?>">Read Venue Rules</a>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/public_footer.php'; ?>

<?php include __DIR__ . '/includes/chatbot.php'; ?>
<script>
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 40));
  const menuToggle = document.getElementById('menuToggle');
  const menuClose = document.getElementById('menuClose');
  const navLinks = document.getElementById('navLinks');
  const navBackdrop = document.getElementById('navBackdrop');
  const setMenuOpen = (isOpen) => {
    navLinks.classList.toggle('open', isOpen);
    navBackdrop.classList.toggle('open', isOpen);
    menuToggle.classList.toggle('is-open', isOpen);
    menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    menuToggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    document.body.classList.toggle('menu-open', isOpen);
  };
  menuToggle.addEventListener('click', () => setMenuOpen(!navLinks.classList.contains('open')));
  menuClose.addEventListener('click', () => setMenuOpen(false));
  navBackdrop.addEventListener('click', () => setMenuOpen(false));
  navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenuOpen(false)));
  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenuOpen(false);
  });
</script>
</body>
</html>
