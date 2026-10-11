<?php
require_once __DIR__ . '/includes/config.php';
$ready = db_ready();
$category = $_GET['category'] ?? 'all';
$images = [];
$categories = [];
if ($ready) {
    ensure_gallery_category_schema();
    $categories = db()->query("SELECT DISTINCT category FROM gallery_images WHERE is_active=1 ORDER BY category")->fetchAll();
    if ($category !== 'all') {
        $stmt = db()->prepare("SELECT * FROM gallery_images WHERE is_active=1 AND category=? ORDER BY display_order ASC, id DESC");
        $stmt->execute([$category]);
        $images = $stmt->fetchAll();
    } else {
        $images = db()->query("SELECT * FROM gallery_images WHERE is_active=1 ORDER BY display_order ASC, id DESC")->fetchAll();
    }
}
$occasions = ['Weddings', 'Receptions', 'Engagements', 'Haldi', 'Mehendi', 'Sangeet', 'Birthdays', 'Anniversaries', 'Family celebrations', 'Corporate gatherings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gallery | <?php echo SITE_NAME; ?></title>
<link rel="icon" href="assets/logo.jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--forest:#20483f;--leaf:#2e6b58;--gold:#d9a050;--paper:#f8f3e8;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.13);--shadow:0 24px 60px -34px rgba(20,47,40,.55)}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:linear-gradient(180deg,#fffaf0 0,#f8f3e8 48%,#eef5ef 100%);color:var(--ink);overflow-x:hidden}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1180px,calc(100% - 36px));margin:auto}header{position:fixed;top:0;left:0;right:0;z-index:100;padding:18px 0;transition:background .4s ease,padding .4s ease,box-shadow .4s ease}header.scrolled{background:rgba(23,51,43,.92);backdrop-filter:blur(10px);padding:12px 0;box-shadow:0 10px 30px -20px rgba(0,0,0,.4)}.nav{display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:10px}.brand img{height:44px;width:44px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(228,218,195,.28)}.brand span{font-family:'Inter',sans-serif;letter-spacing:.04em;font-size:1.08rem;color:#e4dac3}.nav-links{display:flex;gap:24px;list-style:none;margin:0;padding:0}.nav-links a{color:#e4dac3;font-size:.88rem;font-weight:500;position:relative;padding-bottom:4px}.nav-links a:after{content:"";position:absolute;left:0;bottom:0;width:0;height:1px;background:var(--gold);transition:width .3s ease}.nav-links a:hover:after,.nav-links a.active:after{width:100%}.menu-toggle{display:none;width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(23,51,43,.28);color:#e4dac3;cursor:pointer;place-items:center}.menu-toggle span,.menu-toggle span:before,.menu-toggle span:after{display:block;width:19px;height:2px;border-radius:2px;background:currentColor;content:"";transition:transform .3s ease}.menu-toggle span:before{transform:translateY(-7px)}.menu-toggle span:after{transform:translateY(5px)}.menu-toggle.is-open span{background:transparent}.menu-toggle.is-open span:before{transform:translateY(2px) rotate(45deg)}.menu-toggle.is-open span:after{transform:translateY(0) rotate(-45deg)}.menu-close-row{display:none}.menu-close{width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(255,255,255,.06);color:#e4dac3;font-size:1.7rem;line-height:1;cursor:pointer}.nav-backdrop{position:fixed;inset:0;z-index:99;background:rgba(8,28,23,.54);opacity:0;pointer-events:none;transition:opacity .3s ease}.nav-backdrop.open{opacity:1;pointer-events:auto}.hero{min-height:520px;padding:150px 0 78px;color:#fff;background:linear-gradient(90deg,rgba(20,47,40,.86),rgba(20,47,40,.34)),url('assets/gallery-celebration-evening-optimized.jpg') center/cover;display:flex;align-items:flex-end}.eyebrow{font-size:.78rem;letter-spacing:.13em;text-transform:uppercase;color:#f1cf8f;font-weight:800}.hero h1{font-family:'Inter',sans-serif;font-size:clamp(2.6rem,6vw,5.2rem);line-height:.98;margin:10px 0 16px}.hero p{max-width:680px;color:rgba(255,255,255,.88);font-size:1.08rem;line-height:1.7}.btn{border:0;border-radius:999px;background:var(--gold);color:#261b10;padding:12px 18px;text-decoration:none;font-weight:800;display:inline-flex;align-items:center;justify-content:center}.tabs{display:flex;gap:10px;flex-wrap:wrap;margin:32px 0 24px}.tabs a{border-radius:999px;background:#fff;color:var(--deep);border:1px solid var(--line);padding:11px 16px;font-weight:800}.tabs .on{background:var(--gold);color:#25190b}.gallery{columns:3 260px;column-gap:16px;padding-bottom:64px}.tile{break-inside:avoid;background:#fff;border-radius:8px;overflow:hidden;margin:0 0 16px;box-shadow:var(--shadow);border:1px solid var(--line);animation:rise .45s ease both}.tile img{width:100%;display:block}.tile div{padding:14px}.tile strong{display:block;color:var(--deep)}.tile span{color:var(--muted);font-size:.86rem}.empty{background:#fff;padding:24px;border-radius:8px;box-shadow:var(--shadow)}@keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}.packages{background:var(--deep);color:#e4dac3;padding:72px 0}.section-head{display:flex;justify-content:space-between;gap:24px;align-items:end;margin-bottom:26px}.section-head h2{font-family:'Inter',sans-serif;font-size:clamp(2rem,4vw,3.4rem);line-height:1;margin:8px 0 0}.section-head p{max-width:470px;color:rgba(228,218,195,.78);line-height:1.65}.package-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.package-card{min-height:260px;border-radius:8px;border:1px solid rgba(228,218,195,.18);background:linear-gradient(150deg,rgba(255,255,255,.09),rgba(255,255,255,.035));padding:24px;display:flex;flex-direction:column;justify-content:space-between}.package-card .size{color:#f1cf8f;font-size:.78rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.package-card h3{font-family:'Inter',sans-serif;font-size:1.6rem;margin:12px 0 0;color:#fff}.package-card p{color:rgba(228,218,195,.82);line-height:1.6}.occasion-strip{padding:72px 0}.occasion-board{position:relative;min-height:440px;border-radius:8px;overflow:hidden;background:linear-gradient(180deg,rgba(20,47,40,.08),rgba(20,47,40,.86)),url('assets/gallery-day-lawn-optimized.jpg') center/cover;display:flex;align-items:flex-end;padding:34px}.occasion-board h2{font-family:'Inter',sans-serif;font-size:clamp(2rem,4vw,3.4rem);color:#fff;margin:8px 0 10px}.occasion-board p{color:rgba(255,255,255,.86);margin:0 0 22px}.occasion-tags{display:flex;flex-wrap:wrap;gap:10px}.occasion-tags span{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);color:#fff;border-radius:999px;padding:9px 12px;font-weight:700;font-size:.9rem;backdrop-filter:blur(8px)}@media(max-width:900px){.section-head{display:block}.package-grid{grid-template-columns:1fr}.hero{min-height:470px}.occasion-board{padding:24px}}@media(max-width:860px){.nav-links{position:fixed;top:0;right:0;z-index:101;height:100vh;width:min(320px,86vw);background:#17332b;flex-direction:column;padding:100px 32px 32px;transform:translateX(100%);transition:transform .4s ease;gap:22px;box-shadow:-24px 0 50px -28px rgba(0,0,0,.65)}.nav-links.open{transform:translateX(0)}.menu-toggle{display:grid}.menu-close-row{display:flex;justify-content:flex-end;position:absolute;top:22px;right:24px}body.menu-open{overflow:hidden}}@media(max-width:540px){.container,.wrap{width:min(100% - 26px,1180px)}.hero{padding-top:120px}.occasion-tags span{font-size:.82rem}}
.nav-links a:hover,.nav-links a.active{color:var(--gold);font-weight:800}
</style>
</head>
<body>
<?php $publicHeaderActive = 'gallery'; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>
<main>
  <section class="hero">
    <div class="wrap">
      <div class="eyebrow">Moments at Anantha</div>
      <h1>See the lawn in celebration.</h1>
      <p>Browse real spaces across the open lawn, garden, rooms and evening setups, then choose the guest experience that fits your day.</p>
      <a class="btn" href="availability.php">Check Availability</a>
    </div>
  </section>
  <section class="wrap">
    <nav class="tabs" aria-label="Gallery categories">
      <a class="<?php echo $category === 'all' ? 'on' : ''; ?>" href="gallery.php">All</a>
      <?php foreach ($categories as $row): ?><a class="<?php echo $category === $row['category'] ? 'on' : ''; ?>" href="?category=<?php echo urlencode($row['category']); ?>"><?php echo h($row['category']); ?></a><?php endforeach; ?>
    </nav>
    <section class="gallery">
      <?php if (!$ready): ?><div class="empty">Import database.sql to show live gallery.</div><?php endif; ?>
      <?php foreach ($images as $image): ?><article class="tile"><img src="<?php echo h($image['image_path']); ?>" alt="<?php echo h($image['title']); ?>" loading="lazy" decoding="async"><div><strong><?php echo h($image['title']); ?></strong><span><?php echo h($image['category']); ?></span></div></article><?php endforeach; ?>
    </section>
  </section>
  <section class="occasion-strip">
    <div class="wrap occasion-board">
      <div>
        <div class="eyebrow">Occasions</div>
        <h2>Bring your celebration to Anantha</h2>
        <p>Choose the occasion and make the space your own.</p>
        <div class="occasion-tags"><?php foreach ($occasions as $occasion): ?><span><?php echo h($occasion); ?></span><?php endforeach; ?></div>
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
