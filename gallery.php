<?php
require_once __DIR__ . '/includes/config.php';
$ready = db_ready();
$images = $reviews = [];
$capacity = $ready ? max(1, (int) rate_setting_value('maximum_capacity', 300)) : 300;
if ($ready) {
    ensure_gallery_category_schema();
    $images = db()->query("SELECT * FROM gallery_images WHERE is_active=1 ORDER BY display_order ASC, id DESC")->fetchAll();
    $reviews = db()->query("SELECT client_name, event_type, rating, review_text FROM reviews WHERE status='published' ORDER BY created_at DESC, id DESC LIMIT 3")->fetchAll();
}
$categories = array_values(array_unique(array_column($images, 'category')));
$categoryParam = $_GET['category'] ?? 'all';
$selectedCategory = is_string($categoryParam) && in_array($categoryParam, $categories, true) ? $categoryParam : 'all';
$occasions = [
    ['💍', 'Weddings', 'Full ceremony and reception in one private venue.'],
    ['🥂', 'Receptions', 'Elegant evening hosting for up to ' . $capacity . ' guests.'],
    ['🌸', 'Engagements', 'An intimate, beautiful setting for the big yes.'],
    ['🌼', 'Haldi & Mehendi', 'Open lawn space for colour, music and rituals.'],
    ['🎶', 'Sangeet', 'Music, dance and lively nights with your favourite people.'],
    ['🎂', 'Birthdays', 'Milestone parties with a theme that feels like you.'],
    ['🥳', 'Anniversaries', 'Romantic evenings and family gatherings.'],
    ['🏢', 'Corporate', 'Offsites, dinners and team celebrations.'],
];
$descriptions = [
    'Events' => 'Explore celebration setups, evening lights and moments on the lawn.',
    'Rooms' => 'A comfortable space for getting ready and spending time with family.',
    'Venue' => 'Discover the open lawn, garden and spaces for your celebration.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Gallery · <?php echo h(SITE_NAME); ?></title>
<meta name="description" content="Explore Anantha Event Lawns in Madhurawada, Visakhapatnam. Browse event setups, rooms and venue photos, then check your celebration date.">
<link rel="icon" href="assets/logo.jpeg">
<link rel="stylesheet" href="assets/cursors.css">
<link rel="stylesheet" href="assets/forms.css">
<script src="assets/forms.js" defer></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#faf6ec;--card:#fff;--ink:#14261c;--mut:#5d6b61;--g:#0f2a1f;--g2:#1d4a35;--gold:#d9a24b;--line:#e6dfcc;--sh:0 10px 30px rgba(15,42,31,.10)}
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#0c1a13;--card:#142a1f;--ink:#f3ecd9;--mut:#a9b8ac;--line:#25402f;--sh:0 10px 30px rgba(0,0,0,.4)}}
*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:82px}body{margin:0;background:var(--bg);color:var(--ink);font:400 16px/1.65 'Plus Jakarta Sans',system-ui,sans-serif}h1,h2,h3{font-family:'Cormorant Garamond',Georgia,serif;line-height:1.1;margin:0}a{color:inherit}button{font:inherit}a,button,summary{-webkit-tap-highlight-color:transparent}a:focus-visible,button:focus-visible,summary:focus-visible{outline:3px solid var(--gold);outline-offset:5px}.w{max-width:1120px;margin:0 auto;padding:0 20px}.skip-link{position:fixed;left:16px;top:-80px;z-index:50;background:var(--gold);padding:10px 20px;color:#14261c}.skip-link:focus{top:10px}
.site-nav{position:sticky;top:0;z-index:20;background:rgba(15,42,31,.96);backdrop-filter:blur(8px);color:#fff}.nav-inner{display:flex;align-items:center;justify-content:space-between;min-height:70px;gap:20px}.logo{font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:700;letter-spacing:.14em;text-decoration:none;line-height:1.1}.logo small{display:block;font:600 8px 'Plus Jakarta Sans',sans-serif;letter-spacing:.3em;color:var(--gold);margin-top:5px}.nav-links{display:flex;align-items:center;gap:22px}.nav-links a{color:#e9e3d0;text-decoration:none;font-size:13px;font-weight:600}.nav-links a.on{color:var(--gold)}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;background:var(--gold);color:#2b1c04;font-weight:700;font-size:14px;padding:12px 24px;border-radius:999px;text-decoration:none;box-shadow:0 6px 18px rgba(217,162,75,.25);transition:transform .2s}.btn:hover{transform:translateY(-2px)}.nav-links .btn{padding:9px 18px;font-size:12px;color:#2b1c04;min-height:42px}.menu-toggle{display:none;border:1px solid #809686;border-radius:8px;background:transparent;color:#fff;min-width:44px;min-height:44px;cursor:pointer}
.hero{background:linear-gradient(180deg,rgba(8,24,17,.65),rgba(8,24,17,.91)),url('assets/gallery-celebration-evening-optimized.jpg') center/cover;color:#fff;padding:90px 0 110px;text-align:center}.eyebrow{font-size:11px;font-weight:700;letter-spacing:.28em;text-transform:uppercase;color:var(--gold)}.hero h1{font-size:clamp(40px,7vw,72px);font-weight:700;margin:14px 0}.hero p{max-width:620px;margin:0 auto 28px;color:#e4ddc8;font-size:17px}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:-60px auto 0;position:relative}.stat{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;text-align:center;box-shadow:var(--sh)}.stat b{display:block;font:700 34px 'Cormorant Garamond',serif;color:var(--g2)}.stat span{font-size:12.5px;color:var(--mut)}
section{padding:72px 0}.head{text-align:center;max-width:640px;margin:0 auto 34px}.head h2{font-size:clamp(32px,5vw,46px);margin:8px 0 10px}.head p{color:var(--mut);margin:0}.chips{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:28px}.chip{border:1px solid var(--line);background:var(--card);color:var(--ink);font:600 13px 'Plus Jakarta Sans',sans-serif;padding:10px 20px;min-height:44px;border-radius:999px;cursor:pointer}.chip.on{background:var(--gold);border-color:var(--gold);color:#2b1c04}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.g{background:var(--card);border-radius:18px;overflow:hidden;box-shadow:var(--sh);border:1px solid var(--line);transition:transform .25s}.g:hover{transform:translateY(-5px)}.g .ph{height:210px;position:relative;display:flex;align-items:flex-end;padding:14px;background:var(--g)}.ph img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.g .tag{position:relative;background:rgba(255,255,255,.94);color:#14261c;font-size:11px;font-weight:700;padding:4px 11px;border-radius:99px}.g .tx{padding:16px 18px 20px}.g h3{font-size:23px;overflow-wrap:anywhere}.g p{margin:6px 0 0;font-size:13.5px;color:var(--mut)}[hidden]{display:none!important}.note{text-align:center;color:var(--mut);font-size:13px;margin-top:22px}.empty{grid-column:1/-1;text-align:center;padding:36px;border:1px solid var(--line);border-radius:16px;background:var(--card)}.occ{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.o{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px}.o i{font-style:normal;font-size:26px}.o h3{font-size:22px;margin:8px 0 4px}.o p{margin:0;font-size:13.5px;color:var(--mut)}.compact-top{padding-top:0}
.dark{background:var(--g);color:#f3ecd9}.dark .head p{color:#b8c7bb}.inc{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.inc div{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px 20px}.inc b{color:var(--gold);display:block;margin-bottom:4px}.inc span{font-size:14px;color:#cfd9d1}.steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;counter-reset:s}.st{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px;counter-increment:s}.st:before{content:counter(s);display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:var(--gold);color:#2b1c04;font-weight:700;margin-bottom:12px}.st h3{font-size:24px}.st p{color:var(--mut);margin:6px 0 0;font-size:14.5px}.rev{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.r{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px}.r q{display:block;font:500 20px/1.35 'Cormorant Garamond',serif;quotes:none;overflow-wrap:anywhere}.r em{display:block;margin-top:14px;font-style:normal;font-size:13px;color:var(--mut)}.faq{max-width:760px;margin:0 auto}details{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 20px;margin-bottom:10px}summary{cursor:pointer;font-weight:600}details p{margin:10px 0 0;color:var(--mut);font-size:14.5px}.cta{background:linear-gradient(135deg,#1d4a35,#0f2a1f);color:#fff;border-radius:24px;padding:54px 24px;text-align:center}.cta h2{font-size:clamp(32px,5vw,48px);margin:8px 0 10px}.cta p{color:#d3dccf;margin:0 0 24px}.cta-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}.btn-outline{background:transparent;color:#f3ecd9;box-shadow:none;border:1px solid #829b88}footer{background:#0a1a12;color:#b9c6bb;padding:48px 0 24px;font-size:13.5px}footer .f{display:grid;grid-template-columns:1.6fr 1fr 1.3fr 1fr;gap:28px}footer h4{color:var(--gold);margin:0 0 10px;font-size:12px;letter-spacing:.12em;text-transform:uppercase}footer a{display:block;color:#b9c6bb;text-decoration:none;margin-bottom:6px;overflow-wrap:anywhere}footer address{font-style:normal}.cp{border-top:1px solid #1f3629;margin-top:30px;padding-top:18px;text-align:center;font-size:12px}.footer-logo{color:#fff;margin-bottom:14px;font-size:21px}
@media(prefers-color-scheme:dark){.stat b{color:var(--gold)}}
@media(max-width:860px){.grid,.occ,.inc,.steps,.rev{grid-template-columns:repeat(2,minmax(0,1fr))}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}footer .f{grid-template-columns:repeat(2,minmax(0,1fr))}.menu-toggle{display:block}.nav-inner{flex-wrap:wrap;padding-top:12px;padding-bottom:12px;gap:12px}.nav-links{display:none;width:100%;flex-wrap:wrap;padding:12px 0;gap:18px}.nav-links.open{display:flex}.nav-links a{min-height:44px;display:inline-flex;align-items:center}.hero{padding-top:65px}.hero h1{font-size:clamp(40px,8vw,64px)}}
@media(max-width:560px){.grid,.occ,.inc,.steps,.rev{grid-template-columns:1fr}.w{padding:0 18px}section{padding:52px 0}.compact-top{padding-top:0}.hero{padding:54px 0 96px}.hero p{font-size:15px}.stat{padding:16px 10px}.stat b{font-size:30px}.stats{gap:10px}.stat span{font-size:11px}.head{margin-bottom:26px}footer .f{grid-template-columns:1fr}.cta{padding:38px 20px}.cta-actions .btn{width:100%}.contact-stack{display:none!important}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{transition:none!important;animation:none!important}}
</style>
</head>
<body>
<a class="skip-link" href="#gallery">Skip to gallery</a>
<nav class="site-nav" aria-label="Main navigation"><div class="w nav-inner">
<a class="logo" href="index.php">ANANTHA<small>EVENT LAWNS</small></a>
<button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false" aria-controls="navLinks" aria-label="Open navigation">☰</button>
<div class="nav-links" id="navLinks"><a href="index.php">Home</a><a class="on" href="#gallery" aria-current="page">Gallery</a><a href="#occasions">Occasions</a><a href="#faq">FAQ</a><a href="#contact">Contact</a><a class="btn" href="availability.php">Check Availability</a></div>
</div></nav>
<main>
<header class="hero"><div class="w"><div class="eyebrow">Moments at Anantha</div><h1>See the lawn in celebration.</h1><p>Step inside a private, open-air venue in Madhurawada, Visakhapatnam. Browse real setups across the lawn, garden, rooms and evening décor, then choose the experience that fits your day.</p><a class="btn" href="availability.php">Check Availability</a></div></header>
<div class="w"><div class="stats" aria-label="Venue at a glance"><div class="stat"><b><?php echo $capacity; ?></b><span>Guests, comfortably hosted</span></div><div class="stat"><b>1</b><span>Event per day, fully private</span></div><div class="stat"><b>10+</b><span>Celebration types</span></div><div class="stat"><b>Your date</b><span>Check availability online</span></div></div></div>
<section id="gallery"><div class="w"><div class="head"><div class="eyebrow">Gallery</div><h2>Real spaces, real setups</h2><p>Tap a category to explore the lawn by day, the glow of an evening, and the rooms your family will use.</p></div>
<div class="chips" id="chips" role="group" aria-label="Filter gallery">
<button type="button" class="chip<?php echo $selectedCategory === 'all' ? ' on' : ''; ?>" data-filter="all" aria-pressed="<?php echo $selectedCategory === 'all' ? 'true' : 'false'; ?>">All</button>
<?php foreach ($categories as $category): ?><button type="button" class="chip<?php echo $selectedCategory === $category ? ' on' : ''; ?>" data-filter="<?php echo h($category); ?>" aria-pressed="<?php echo $selectedCategory === $category ? 'true' : 'false'; ?>"><?php echo h($category); ?></button><?php endforeach; ?>
</div>
<div class="grid" id="galleryGrid">
<?php foreach ($images as $image): ?>
<article class="g" data-category="<?php echo h($image['category']); ?>"<?php echo $selectedCategory !== 'all' && $selectedCategory !== $image['category'] ? ' hidden' : ''; ?>>
<div class="ph"><img src="<?php echo h($image['image_path']); ?>" alt="<?php echo h($image['title']); ?>" loading="lazy" decoding="async" width="720" height="480"><span class="tag"><?php echo h($image['category']); ?></span></div>
<div class="tx"><h3><?php echo h($image['title']); ?></h3><p><?php echo h($descriptions[$image['category']] ?? 'Find inspiration for your own celebration at Anantha.'); ?></p></div></article>
<?php endforeach; ?>
<?php if (!$images): ?><div class="empty">New venue photos are coming soon. <a href="availability.php">Plan a visit to explore the lawn.</a></div><?php endif; ?>
</div><p class="note" id="galleryCount" role="status"><?php echo count(array_filter($images, static fn ($image) => $selectedCategory === 'all' || $image['category'] === $selectedCategory)); ?> photos to explore</p>
</div></section>
<section class="compact-top" id="occasions"><div class="w"><div class="head"><div class="eyebrow">Occasions</div><h2>Bring your celebration to Anantha</h2><p>Choose the occasion and make the space your own.</p></div><div class="occ">
<?php foreach ($occasions as [$icon, $title, $description]): ?><article class="o"><i aria-hidden="true"><?php echo $icon; ?></i><h3><?php echo h($title); ?></h3><p><?php echo h($description); ?></p></article><?php endforeach; ?>
</div></div></section>
<section class="dark"><div class="w"><div class="head"><div class="eyebrow">What you get</div><h2>Everything for a stress-free day</h2><p>A private venue with the essentials already taken care of.</p></div><div class="inc">
<div><b>Private lawn</b><span>Only one event per day, so the venue is entirely yours.</span></div><div><b>Up to <?php echo $capacity; ?> guests</b><span>Space to plan your seating, dining and stage zones.</span></div><div><b>Guest rooms</b><span>Comfortable rooms for family, bride and groom.</span></div><div><b>Evening lighting</b><span>Warm ambient lighting for a magical night look.</span></div><div><b>Guest access</b><span>Discuss parking and vendor arrangements with our team.</span></div><div><b>Custom quotation</b><span>Clear pricing shaped around your date and guest count.</span></div>
</div></div></section>
<section><div class="w"><div class="head"><div class="eyebrow">How it works</div><h2>Book in three simple steps</h2></div><div class="steps"><article class="st"><h3>Check your date</h3><p>Send your preferred date and occasion. We will confirm availability.</p></article><article class="st"><h3>Visit &amp; plan</h3><p>Walk the lawn, pick your setup and discuss your guest count.</p></article><article class="st"><h3>Get your quotation</h3><p>Receive a private quotation and speak to our team to secure your date.</p></article></div></div></section>
<?php if ($reviews): ?><section class="compact-top"><div class="w"><div class="head"><div class="eyebrow">Kind words</div><h2>Loved by our hosts</h2></div><div class="rev"><?php foreach ($reviews as $review): ?><article class="r"><q><?php echo h($review['review_text']); ?></q><em><span aria-label="<?php echo (int) $review['rating']; ?> out of 5 stars"><?php echo str_repeat('★', max(0, min(5, (int) $review['rating']))); ?></span> · <?php echo h($review['event_type'] ?: $review['client_name']); ?></em></article><?php endforeach; ?></div><p class="note"><a href="review.php">Read more guest reviews</a></p></div></section><?php endif; ?>
<section class="compact-top" id="faq"><div class="w"><div class="head"><div class="eyebrow">FAQ</div><h2>Good to know</h2></div><div class="faq"><details><summary>How many guests can the lawn hold?</summary><p>Up to <?php echo $capacity; ?> guests, with flexible seating, dining and stage layouts.</p></details><details><summary>Is the venue shared with other events?</summary><p>No. We host one event per day, so your celebration is fully private.</p></details><details><summary>Can I get a quotation before visiting?</summary><p>Yes. Share your date, guest count and occasion. Our team will verify your mobile number and share a private quotation.</p></details><details><summary>Are rooms available for the family?</summary><p>Yes. Celebration rooms are available for getting ready and resting. Ask our team about arrangements for your event.</p></details><details><summary>Can I bring my own decorator or caterer?</summary><p>Please check our <a href="rules.php">venue rules</a> or contact us to confirm vendor policies.</p></details></div></div></section>
<section class="compact-top" id="contact"><div class="w"><div class="cta"><div class="eyebrow">Plan your day</div><h2>Is your date still open?</h2><p>Tell us the occasion and we will reply with availability and a private quotation.</p><div class="cta-actions"><a class="btn" href="availability.php">Check Availability</a><a class="btn btn-outline" href="tel:+917995011199">Call +91 79950 11199</a></div></div></div></section>
</main>
<footer><div class="w"><div class="f"><div><div class="logo footer-logo">ANANTHA<small>EVENT LAWNS</small></div><address>DRK Raju Township, Plot 61, Kommadi Rd, Gandhi Nagar, Madhurawada, Andhra Pradesh 530045, India</address></div><div><h4>Visit</h4><a href="index.php">Home</a><a href="#gallery">Gallery</a><a href="blogs.php">Blogs</a><a href="rules.php">Venue rules</a><a href="#contact">Contact</a></div><div><h4>Reach us</h4><a href="tel:+917995011199">+91 79950 11199</a><a href="mailto:<?php echo h(CONTACT_EMAIL); ?>"><?php echo h(CONTACT_EMAIL); ?></a><a href="https://www.instagram.com/ananthaeventlawns/" target="_blank" rel="noopener">@ananthaeventlawns</a></div><div><h4>Ceremony</h4>One event per day<br>Up to <?php echo $capacity; ?> guests<br>Private quotation</div></div><div class="cp">© <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?></div></div></footer>
<script>
const chips = document.getElementById('chips');
const cards = Array.from(document.querySelectorAll('#galleryGrid .g'));
function filterGallery(filter) {
  chips.querySelectorAll('.chip').forEach(chip => {
    const active = chip.dataset.filter === filter;
    chip.classList.toggle('on', active);
    chip.setAttribute('aria-pressed', String(active));
  });
  let count = 0;
  cards.forEach(card => {
    card.hidden = filter !== 'all' && card.dataset.category !== filter;
    if (!card.hidden) count++;
  });
  document.getElementById('galleryCount').textContent = count + (count === 1 ? ' photo to explore' : ' photos to explore');
}
chips.addEventListener('click', event => {
  const button = event.target.closest('button[data-filter]');
  if (!button) return;
  filterGallery(button.dataset.filter);
  const url = new URL(window.location.href);
  if (button.dataset.filter === 'all') url.searchParams.delete('category');
  else url.searchParams.set('category', button.dataset.filter);
  history.pushState(null, '', url);
});
window.addEventListener('popstate', () => {
  const filter = new URL(window.location.href).searchParams.get('category') || 'all';
  const valid = Array.from(chips.querySelectorAll('.chip')).some(chip => chip.dataset.filter === filter);
  filterGallery(valid ? filter : 'all');
});
const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');
function setMenu(open) {
  navLinks.classList.toggle('open', open);
  menuToggle.setAttribute('aria-expanded', String(open));
  menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
}
menuToggle.addEventListener('click', () => setMenu(menuToggle.getAttribute('aria-expanded') !== 'true'));
navLinks.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', event => { if (event.key === 'Escape') setMenu(false); });
</script>
</body>
</html>
