<?php
require_once __DIR__ . '/includes/config.php';

$posts = [
    ['wedding-venue-visakhapatnam.php', 'Wedding Venue in Visakhapatnam'],
    ['event-lawns-visakhapatnam.php', 'Event Lawns in Visakhapatnam'],
    ['function-hall-madhurawada.php', 'Function Hall in Madhurawada'],
    ['birthday-party-venue-visakhapatnam.php', 'Birthday Party Venue in Visakhapatnam'],
    ['engagement-venue-visakhapatnam.php', 'Engagement Venue in Visakhapatnam'],
    ['corporate-event-venue-visakhapatnam.php', 'Corporate Event Venue in Visakhapatnam'],
    ['reception-venue-visakhapatnam.php', 'Reception Venue in Visakhapatnam'],
    ['wedding-lawns-madhurawada.php', 'Wedding Lawns in Madhurawada'],
    ['event-venue-kommadi.php', 'Event Venue in Kommadi'],
    ['venues-near-madhurawada.php', 'Event Venues Near Madhurawada'],
    ['venues-near-rushikonda.php', 'Event Venue Near Rushikonda'],
    ['outdoor-event-venue-visakhapatnam.php', 'Outdoor Event Venue in Visakhapatnam'],
    ['private-event-venue-visakhapatnam.php', 'Private Event Venue in Visakhapatnam'],
    ['party-venue-madhurawada.php', 'Party Venue in Madhurawada'],
    ['haldi-venue-visakhapatnam.php', 'Haldi Venue in Visakhapatnam'],
    ['mehendi-venue-visakhapatnam.php', 'Mehendi Venue in Visakhapatnam'],
    ['haldi-mehendi-venue-visakhapatnam.php', 'Haldi & Mehendi Venue in Visakhapatnam'],
    ['sangeet-venue-visakhapatnam.php', 'Sangeet Venue in Visakhapatnam'],
    ['anniversary-venue-visakhapatnam.php', 'Anniversary Venue in Visakhapatnam'],
    ['family-celebration-venue-visakhapatnam.php', 'Family Celebration Venue in Visakhapatnam'],
    ['300-guest-event-venue.php', 'Event Venue for Up to 300 Guests'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blogs | <?php echo h(SITE_NAME); ?></title>
<meta name="description" content="Venue planning blogs and guides from Anantha Event Lawns in Kommadi, Madhurawada, Visakhapatnam.">
<link rel="icon" href="assets/logo.jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--forest:#20483f;--gold:#d9a050;--paper:#f8f3e8;--cream:#e4dac3;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.14);--shadow:0 24px 60px -34px rgba(20,47,40,.55)}*{box-sizing:border-box}body{margin:0;font-family:'Inter',sans-serif;background:var(--paper);color:var(--ink)}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1180px,calc(100% - 36px));margin:auto}.hero{position:relative;min-height:420px;padding:148px 0 72px;background:linear-gradient(90deg,rgba(20,47,40,.92),rgba(20,47,40,.5)),url('assets/gallery-day-lawn-optimized.jpg') center/cover;color:#fff;display:flex;align-items:flex-end;overflow:hidden}.hero:after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:90px;background:linear-gradient(180deg,transparent,var(--paper));pointer-events:none}.hero .wrap{position:relative;z-index:1}.hero-kicker{display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(241,207,143,.34);border-radius:999px;padding:8px 13px;background:rgba(255,255,255,.09);color:#f1cf8f;font-size:.78rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.hero-kicker:before{content:"";width:8px;height:8px;border-radius:50%;background:#f1cf8f;box-shadow:0 0 0 6px rgba(241,207,143,.14)}.hero h1{font-size:clamp(3rem,8vw,6.8rem);line-height:.9;margin:18px 0 0;letter-spacing:0}.hero-tags{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}.hero-tags span{border:1px solid rgba(255,255,255,.22);border-radius:999px;padding:9px 12px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.9);font-weight:800;font-size:.86rem;backdrop-filter:blur(10px)}.blog-list{padding:64px 0 78px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.post{display:flex;align-items:center;min-height:120px;background:#fff;border:1px solid var(--line);border-radius:8px;padding:22px;box-shadow:var(--shadow);transition:transform .2s ease,box-shadow .2s ease}.post:hover{transform:translateY(-3px);box-shadow:0 28px 66px -36px rgba(20,47,40,.72)}.post h2{font-size:1.28rem;line-height:1.25;color:var(--deep);margin:0}footer{background:#10241f;color:var(--cream);padding:42px 0}.foot-row{display:grid;grid-template-columns:1.2fr repeat(3,1fr);gap:24px}.foot-brand{display:flex;align-items:center;gap:10px;font-weight:800}.foot-brand img{width:44px;height:44px;border-radius:50%;object-fit:cover}.foot-col a,.foot-col p{display:block;margin-top:8px;color:rgba(228,218,195,.74);line-height:1.6}@media(max-width:920px){.grid,.foot-row{grid-template-columns:1fr}.hero{min-height:360px}}@media(max-width:540px){.container,.wrap{width:min(100% - 26px,1180px)}.hero{padding-top:120px}.hero h1{font-size:clamp(3rem,18vw,5rem)}.blog-list{padding:46px 0 58px}}
</style>
</head>
<body>
<?php $publicHeaderActive = ''; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>
<main>
  <section class="hero">
    <div class="wrap">
      <div class="hero-kicker">Venue journal</div>
      <h1>Blogs</h1>
      <div class="hero-tags" aria-label="Blog categories">
        <span>Weddings</span>
        <span>Lawns</span>
        <span>Family Events</span>
        <span>Corporate</span>
      </div>
    </div>
  </section>
  <section class="blog-list">
    <div class="wrap grid">
      <?php foreach ($posts as $post): ?>
        <a class="post" href="<?php echo h($post[0]); ?>">
          <h2><?php echo h($post[1]); ?></h2>
        </a>
      <?php endforeach; ?>
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
