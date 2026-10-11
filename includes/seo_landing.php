<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!isset($seoPage) || !is_array($seoPage)) {
    http_response_code(404);
    exit('Page not found');
}

$slug = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^a-z0-9.:-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$canonical = $scheme . '://' . $host . rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\') . '/' . $slug . '.php';
$fallbackBlogImages = default_blog_images();
$adminBlogImages = blog_page_images($slug);
$defaultGalleryImages = [
    ['assets/gallery-day-lawn-optimized.jpg', 'Anantha event lawn in daylight'],
    ['assets/gallery-celebration-evening-optimized.jpg', 'Evening celebration setup at Anantha'],
    ['assets/hero-nature-event-optimized.jpg', 'Outdoor event setting at Anantha'],
];
$pageImages = $adminBlogImages !== null ? $adminBlogImages : ($seoPage['images'] ?? array_map(static fn ($image) => [$image[1], $image[2]], $fallbackBlogImages[$slug] ?? []));
$pageImages = $pageImages ?: $defaultGalleryImages;
foreach ($defaultGalleryImages as $image) {
    if (count($pageImages) >= 3) {
        break;
    }
    if (!in_array($image[0], array_column($pageImages, 0), true)) {
        $pageImages[] = $image;
    }
}
$heroImage = $pageImages[0][0] ?? ($seoPage['hero_image'] ?? 'assets/gallery-day-lawn-optimized.jpg');
$galleryHeading = $seoPage['gallery_heading'] ?? 'Picture your celebration at Anantha';
$faqSchema = [];
foreach ($seoPage['faqs'] as $faq) {
    $faqSchema[] = ['@type' => 'Question', 'name' => $faq[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($seoPage['title']); ?></title>
<meta name="description" content="<?php echo h($seoPage['description']); ?>">
<meta name="robots" content="index,follow,max-image-preview:large">
<link rel="canonical" href="<?php echo h($canonical); ?>">
<link rel="icon" href="assets/logo.jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script type="application/ld+json"><?php echo json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqSchema], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<style>
:root{--deep:#142f28;--forest:#20483f;--leaf:#2e6b58;--gold:#d9a050;--sun:#f1cf8f;--paper:#f8f3e8;--white:#fffdf8;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.14);--cream:#e4dac3;--sky:#e8f2ee;--shadow:0 24px 60px -34px rgba(20,47,40,.58)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:var(--paper);color:var(--ink);overflow-x:hidden}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1120px,calc(100% - 36px));margin:auto}
.seo-hero{min-height:610px;padding:154px 0 78px;color:#fff;display:flex;align-items:flex-end;background-position:center;background-size:cover}.seo-hero .eyebrow{color:var(--sun);font-size:.78rem;text-transform:uppercase;font-weight:800}.seo-hero h1{max-width:900px;margin:10px 0 18px;font-size:clamp(2.5rem,5.7vw,5rem);line-height:1.02}.seo-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.88);font-size:1.08rem;line-height:1.75}.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:12px 20px;border-radius:999px;background:var(--gold);color:#261b10;font-weight:800}.btn.light{background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.3);color:#fff}
.quick-facts{position:relative;margin-top:-30px;z-index:2}.fact-grid{display:grid;grid-template-columns:repeat(4,1fr);background:var(--white);border:1px solid var(--line);border-radius:8px;box-shadow:var(--shadow)}.fact{padding:20px;border-right:1px solid var(--line)}.fact:last-child{border:0}.fact strong,.fact span{display:block}.fact strong{color:var(--deep);font-size:1.05rem}.fact span{margin-top:6px;color:var(--muted);font-size:.86rem}
	.content-section{padding:76px 0}.content-section.alt{background:var(--sky)}.section-head{display:grid;grid-template-columns:.8fr 1.2fr;gap:38px;align-items:center;margin-bottom:30px}.section-head h2{margin:0;color:var(--deep);font-size:clamp(2rem,4vw,3.25rem);line-height:1.05}.section-head p{margin:0;color:var(--muted);line-height:1.75}.topic-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.topic{background:var(--white);border:1px solid var(--line);border-radius:8px;padding:24px}.topic h2{margin:0 0 10px;color:var(--deep);font-size:1.3rem}.topic p{margin:0;color:var(--muted);line-height:1.7}.location-band{background:var(--deep);color:#fff;padding:68px 0}.location-grid{display:grid;grid-template-columns:minmax(0,1.3fr) auto;gap:40px;align-items:center}.location-grid h2{font-size:clamp(2rem,4vw,3.25rem);line-height:1.05;margin:0 0 14px}.location-grid p{color:rgba(228,218,195,.82);line-height:1.75}.link-list{display:flex;justify-content:flex-end}.link-list a{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:13px 22px;border-radius:999px;background:var(--gold);color:#261b10;font-weight:800;box-shadow:0 18px 36px -28px rgba(0,0,0,.75)}.link-list a:hover{background:var(--sun)}
.occasion-images{padding:0 0 76px}.occasion-images h2{margin:0 0 24px;color:var(--deep);font-size:clamp(1.8rem,3vw,2.6rem)}.occasion-image-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.occasion-image-grid figure{margin:0;aspect-ratio:4/3;min-height:260px;overflow:hidden;border-radius:8px;background:var(--deep)}.occasion-image-grid figure:only-child{grid-column:1/-1;aspect-ratio:16/7}.occasion-image-grid img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .45s ease}.occasion-image-grid figure:hover img{transform:scale(1.04)}
.faq{padding:76px 0}.faq h2{font-size:clamp(2rem,4vw,3.25rem);color:var(--deep);margin:0 0 24px}.faq-grid{display:grid;gap:12px}.faq details{background:var(--white);border:1px solid var(--line);border-radius:8px;padding:18px 20px}.faq summary{cursor:pointer;color:var(--deep);font-weight:800}.faq details p{margin:12px 0 0;color:var(--muted);line-height:1.7}.final-cta{background:var(--gold);padding:34px 0}.cta-row{display:flex;align-items:center;justify-content:space-between;gap:24px}.cta-row h2{margin:0;color:#261b10}.cta-row .btn{background:var(--deep);color:#fff}footer{background:#10241f;color:var(--cream);padding:38px 0}.footer-row{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}.footer-row p{margin:0;color:rgba(228,218,195,.75)}.footer-row a{color:var(--sun);font-weight:700}
@media(max-width:850px){.fact-grid{grid-template-columns:1fr 1fr}.fact:nth-child(2){border-right:0}.fact:nth-child(-n+2){border-bottom:1px solid var(--line)}.section-head,.location-grid{grid-template-columns:1fr}.link-list{justify-content:flex-start}.topic-grid{grid-template-columns:1fr}.occasion-image-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.occasion-image-grid figure{min-height:190px}.seo-hero{min-height:560px}.cta-row{align-items:flex-start;flex-direction:column}}@media(max-width:540px){.wrap,.container{width:min(100% - 26px,1120px)}.seo-hero{padding-top:124px}.fact-grid,.occasion-image-grid{grid-template-columns:1fr}.link-list a{width:100%}.occasion-image-grid figure{min-height:220px}.fact{border-right:0;border-bottom:1px solid var(--line)!important}.fact:last-child{border-bottom:0!important}.hero-actions .btn,.cta-row .btn{width:100%}}
</style>
</head>
<body class="page-<?php echo h($slug); ?>">
<?php $publicHeaderActive = 'occasions'; $publicHeaderHome = false; include __DIR__ . '/public_header.php'; ?>
<main>
  <section class="seo-hero" style="background-image:linear-gradient(90deg,rgba(20,47,40,.94),rgba(20,47,40,.52)),url('<?php echo h($heroImage); ?>')">
    <div class="wrap">
      <div class="eyebrow"><?php echo h($seoPage['keyword']); ?></div>
      <h1><?php echo h($seoPage['h1']); ?></h1>
      <p><?php echo h($seoPage['intro']); ?></p>
      <div class="hero-actions"><a class="btn" href="availability.php">Check Availability</a><a class="btn light" href="gallery.php">View Venue Photos</a></div>
    </div>
  </section>
  <section class="quick-facts"><div class="wrap fact-grid"><div class="fact"><strong>Up to 300 guests</strong><span>Comfortable event capacity</span></div><div class="fact"><strong>One event per day</strong><span>Private use for your booking</span></div><div class="fact"><strong>Lawn + mini hall</strong><span>Flexible celebration spaces</span></div><div class="fact"><strong>Two deluxe rooms</strong><span>Air-conditioned family rooms</span></div></div></section>
  <section class="content-section">
    <div class="wrap">
      <div class="section-head"><h2><?php echo h($seoPage['overview_heading']); ?></h2><p><?php echo h($seoPage['overview']); ?></p></div>
      <div class="topic-grid">
        <?php foreach ($seoPage['sections'] as $section): ?><article class="topic"><h2><?php echo h($section[0]); ?></h2><p><?php echo h($section[1]); ?></p></article><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php if ($pageImages): ?><section class="occasion-images" aria-labelledby="occasionGalleryTitle">
    <div class="wrap">
      <h2 id="occasionGalleryTitle"><?php if (!empty($seoPage['gallery_heading_url'])): ?><a href="<?php echo h($seoPage['gallery_heading_url']); ?>"><?php echo h($galleryHeading); ?></a><?php else: ?><?php echo h($galleryHeading); ?><?php endif; ?></h2>
      <div class="occasion-image-grid">
        <?php foreach ($pageImages as $image): ?><figure><img src="<?php echo h($image[0]); ?>" alt="<?php echo h($image[1]); ?>" loading="lazy" decoding="async"></figure><?php endforeach; ?>
      </div>
    </div>
  </section><?php endif; ?>
  <section class="location-band"><div class="wrap location-grid"><div><h2><?php echo h($seoPage['location_heading']); ?></h2><p><?php echo h($seoPage['location']); ?></p></div><nav class="link-list" aria-label="Plan your event"><a href="availability.php">Check Availability</a></nav></div></section>
  <section class="faq"><div class="wrap"><h2><?php echo h($seoPage['faq_heading']); ?></h2><div class="faq-grid"><?php foreach ($seoPage['faqs'] as $faq): ?><details><summary><?php echo h($faq[0]); ?></summary><p><?php echo h($faq[1]); ?></p></details><?php endforeach; ?></div></div></section>
  <section class="final-cta"><div class="wrap cta-row"><h2>Planning your celebration at Anantha?</h2><a class="btn" href="availability.php">Check Availability</a></div></section>
</main>
<?php include __DIR__ . '/public_footer.php'; ?>
<?php include __DIR__ . '/chatbot.php'; ?>
<script>
const header=document.getElementById('siteHeader');window.addEventListener('scroll',()=>header.classList.toggle('scrolled',window.scrollY>40));
const menuToggle=document.getElementById('menuToggle'),menuClose=document.getElementById('menuClose'),navLinks=document.getElementById('navLinks'),navBackdrop=document.getElementById('navBackdrop');
const setMenuOpen=open=>{navLinks.classList.toggle('open',open);navBackdrop.classList.toggle('open',open);menuToggle.classList.toggle('is-open',open);menuToggle.setAttribute('aria-expanded',open?'true':'false');document.body.classList.toggle('menu-open',open)};
menuToggle.addEventListener('click',()=>setMenuOpen(!navLinks.classList.contains('open')));menuClose.addEventListener('click',()=>setMenuOpen(false));navBackdrop.addEventListener('click',()=>setMenuOpen(false));navLinks.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>setMenuOpen(false)));
</script>
</body>
</html>
