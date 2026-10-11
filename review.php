<?php
require_once __DIR__ . '/includes/config.php';

$ready = db_ready();
$name = $event = $text = '';
$rating = 5;
$message = '';
$error = '';

function seed_default_reviews(): void
{
    $count = (int) db()->query("SELECT COUNT(*) FROM reviews WHERE status = 'published'")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $defaults = [
        [
            'Sravani K.',
            'Wedding',
            5,
            'The lawn felt private, fresh and beautifully maintained. Our wedding evening looked elegant, and the team helped our families settle in comfortably.',
        ],
        [
            'Rahul Varma',
            'Reception',
            5,
            'A peaceful venue with enough space for guests, food counters and photos. The one-event-per-day approach made everything feel relaxed.',
        ],
        [
            'Mounika Reddy',
            'Birthday Celebration',
            5,
            'We hosted a family birthday here and loved the clean rooms, open lawn and warm support. It felt personal from start to finish.',
        ],
        [
            'Kiran & Deepthi',
            'Engagement',
            4,
            'The location was easy for our guests, and the outdoor setup looked lovely in the evening lights. A very pleasant experience.',
        ],
    ];

    $stmt = db()->prepare("INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by) VALUES (?, ?, ?, ?, 'published', 'admin')");
    foreach ($defaults as $review) {
        $stmt->execute($review);
    }
}

if ($ready) {
    seed_default_reviews();
}

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(strip_tags($_POST['client_name'] ?? ''));
    $event = trim(strip_tags($_POST['event_type'] ?? ''));
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $text = trim(strip_tags($_POST['review_text'] ?? ''));

    if ($name === '' || $text === '') {
        $error = 'Please add your name and review.';
    } else {
        $stmt = db()->prepare("INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by) VALUES (?, ?, ?, ?, 'pending', 'public')");
        $stmt->execute([$name, $event, $rating, $text]);
        $message = 'Thank you. Your review is sent to Anantha team for approval.';
        $name = $event = $text = '';
        $rating = 5;
    }
}

$googleReviewData = google_reviews_data();
$googleReviews = $googleReviewData['reviews'] ?? [];
$localReviews = [];
if ($ready) {
    $localReviews = db()->query("SELECT * FROM reviews WHERE status = 'published' ORDER BY created_at DESC, id DESC")->fetchAll();
}
$reviews = array_merge($googleReviews, $localReviews);
$mainReviews = array_slice($reviews, 0, 3);

$reviewCount = count($reviews);
$averageRating = 0;
if ($reviewCount > 0) {
    $totalRating = array_sum(array_map(static fn ($review) => (int) $review['rating'], $reviews));
    $averageRating = round($totalRating / $reviewCount, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reviews | <?php echo SITE_NAME; ?></title>
<link rel="icon" href="assets/logo.jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--forest:#20483f;--leaf:#2e6b58;--gold:#d9a050;--cream:#f8f3e8;--soft:#fffaf0;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.13);--shadow:0 24px 60px -34px rgba(20,47,40,.5)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:linear-gradient(180deg,#fffaf0 0,#f8f3e8 44%,#eef5ef 100%);color:var(--ink);overflow-x:hidden}body:before{content:"";position:fixed;inset:0;background:linear-gradient(110deg,rgba(20,47,40,.84),rgba(20,47,40,.22)),url('assets/gallery-celebration-evening-optimized.jpg') center/cover no-repeat;clip-path:polygon(0 0,100% 0,100% 390px,0 560px);z-index:-2}body:after{content:"";position:fixed;inset:0;background:radial-gradient(circle at 16% 22%,rgba(217,160,80,.28),transparent 24%),radial-gradient(circle at 82% 10%,rgba(255,250,240,.32),transparent 18%);z-index:-1;pointer-events:none}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1180px,calc(100% - 36px));margin:auto}
header{position:fixed;top:0;left:0;right:0;z-index:100;padding:18px 0;transition:background .4s ease,padding .4s ease,box-shadow .4s ease}header.scrolled{background:rgba(23,51,43,.92);backdrop-filter:blur(10px);padding:12px 0;box-shadow:0 10px 30px -20px rgba(0,0,0,.4)}.nav{display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:10px}.brand img{height:44px;width:44px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(228,218,195,.28)}.brand span{font-family:'Inter',sans-serif;letter-spacing:.04em;font-size:1.08rem;color:#e4dac3}.nav-links{display:flex;gap:24px;list-style:none;margin:0;padding:0}.nav-links a{color:#e4dac3;font-size:.88rem;font-weight:500;position:relative;padding-bottom:4px}.nav-links a:after{content:"";position:absolute;left:0;bottom:0;width:0;height:1px;background:var(--gold);transition:width .3s ease}.nav-links a:hover:after,.nav-links a.active:after{width:100%}.menu-toggle{display:none;width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(23,51,43,.28);color:#e4dac3;cursor:pointer;place-items:center}.menu-toggle span,.menu-toggle span:before,.menu-toggle span:after{display:block;width:19px;height:2px;border-radius:2px;background:currentColor;content:"";transition:transform .3s ease}.menu-toggle span:before{transform:translateY(-7px)}.menu-toggle span:after{transform:translateY(5px)}.menu-toggle.is-open span{background:transparent}.menu-toggle.is-open span:before{transform:translateY(2px) rotate(45deg)}.menu-toggle.is-open span:after{transform:translateY(0) rotate(-45deg)}.menu-close-row{display:none}.menu-close{width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(255,255,255,.06);color:#e4dac3;font-size:1.7rem;line-height:1;cursor:pointer}.nav-backdrop{position:fixed;inset:0;z-index:99;background:rgba(8,28,23,.54);opacity:0;pointer-events:none;transition:opacity .3s ease}.nav-backdrop.open{opacity:1;pointer-events:auto}
.hero{padding:132px 0 54px;color:#fff}.hero-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:34px;align-items:end}.eyebrow{font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:#f1cf8f;font-weight:700}.hero h1{font-family:'Inter',sans-serif;font-size:clamp(2.4rem,6vw,5rem);line-height:1;margin:10px 0 16px}.hero p{max-width:660px;color:rgba(255,255,255,.86);font-size:1.06rem}.score-card{background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.24);border-radius:8px;padding:24px;backdrop-filter:blur(14px);box-shadow:0 24px 50px -30px rgba(0,0,0,.55)}.score-card strong{display:block;font-family:'Inter',sans-serif;font-size:2.7rem;color:#fff;line-height:1}.score-card span{display:block;color:#f4dcaa;margin-top:8px}.score-stars{color:#f4c16b;letter-spacing:.08em;margin-top:14px}
.content{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px;align-items:start;padding:26px 0 76px}.review-section-title{margin:0 0 16px;color:#fff;font-size:clamp(1.8rem,3vw,2.65rem);line-height:1}.reviews{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.reviews .review-card:first-of-type{grid-column:1/-1}.review-card,.review-form,.empty{background:rgba(255,255,255,.94);border:1px solid rgba(255,255,255,.86);border-radius:8px;box-shadow:var(--shadow)}.review-card{padding:24px;position:relative;overflow:hidden;transition:transform .25s ease,box-shadow .25s ease}.review-card:before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--gold),var(--leaf))}.review-card:hover{transform:translateY(-5px);box-shadow:0 30px 66px -38px rgba(20,47,40,.7)}.stars{color:var(--gold);font-size:1rem;letter-spacing:.08em;font-weight:800}.quote{font-family:'Inter',sans-serif;font-size:1.28rem;line-height:1.5;color:var(--deep);margin:18px 0 22px}.person{display:flex;align-items:center;gap:12px}.avatar{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,var(--deep),var(--leaf));color:#fff;font-weight:800}.person strong{display:block;color:var(--deep)}.person span{display:block;color:var(--muted);font-size:.9rem;margin-top:2px}.review-form{padding:26px;position:sticky;top:96px;background:var(--deep);color:#e4dac3}.review-form h2{font-family:'Inter',sans-serif;font-size:1.75rem;margin:0 0 8px}.review-form p{color:rgba(228,218,195,.78);margin:0 0 18px}.review-form label{display:block;font-size:.8rem;font-weight:700;color:var(--gold);margin-top:13px}.review-form input,.review-form select,.review-form textarea{width:100%;margin-top:6px;border:1px solid rgba(228,218,195,.22);border-radius:8px;padding:12px 13px;background:rgba(255,255,255,.07);color:#fff;font:inherit}.review-form select option{color:var(--ink)}.review-form textarea{resize:vertical;min-height:118px}.review-form input::placeholder,.review-form textarea::placeholder{color:rgba(228,218,195,.48)}.btn{border:0;border-radius:999px;background:var(--gold);color:#261b10;padding:12px 20px;text-decoration:none;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}.review-form .btn{width:100%;margin-top:18px}.btn:disabled{opacity:.55;cursor:not-allowed}.msg{padding:12px 14px;border-radius:8px;margin-bottom:12px;font-size:.9rem}.ok{background:rgba(46,107,88,.34);color:#e6fff3}.err{background:rgba(189,69,61,.28);color:#ffe0dc}.empty{padding:24px;color:var(--muted)}
@media(max-width:960px){.hero-grid,.content{grid-template-columns:1fr}.review-form{position:static}.reviews{grid-template-columns:1fr}}@media(max-width:860px){.nav-links{position:fixed;top:0;right:0;z-index:101;height:100vh;width:min(320px,86vw);background:#17332b;flex-direction:column;padding:100px 32px 32px;transform:translateX(100%);transition:transform .4s ease;gap:22px;box-shadow:-24px 0 50px -28px rgba(0,0,0,.65)}.nav-links.open{transform:translateX(0)}.menu-toggle{display:grid}.menu-close-row{display:flex;justify-content:flex-end;position:absolute;top:22px;right:24px}body.menu-open{overflow:hidden}}@media(max-width:540px){.container,.wrap{width:min(100% - 26px,1180px)}.hero{padding-top:116px}.quote{font-size:1.16rem}.score-card,.review-card,.review-form{padding:20px}}
.rating-stars{display:flex;gap:6px;margin-top:8px}.rating-stars input{position:absolute;opacity:0;pointer-events:none}.rating-stars label{width:34px;height:34px;display:grid;place-items:center;margin:0;border-radius:50%;cursor:pointer;color:rgba(228,218,195,.42);font-size:1.5rem;line-height:1;transition:color .18s ease,transform .18s ease,background .18s ease}.rating-stars label.active,.rating-stars label:hover{color:#f4c16b;background:rgba(244,193,107,.12);transform:translateY(-2px)}.source-badge{display:inline-flex;margin-top:7px;border-radius:999px;background:#fff4de;color:#815501;padding:5px 8px;font-style:normal;font-size:.72rem;font-weight:900}
.google-card{margin-bottom:16px;padding:18px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(228,218,195,.18)}.google-card strong{display:block;color:#fff}.google-card p{margin:8px 0 14px;color:rgba(228,218,195,.78)}
.nav-links a:hover,.nav-links a.active{color:var(--gold);font-weight:800}
</style>
</head>
<body>
<?php $publicHeaderActive = 'reviews'; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>

<main>
  <section class="hero">
    <div class="wrap hero-grid">
      <div>
        <div class="eyebrow">Guest Stories</div>
        <h1>Celebrations remembered with warmth.</h1>
        <p>Read what families loved about hosting their weddings, receptions and intimate gatherings at Anantha Event Lawns.</p>
      </div>
      <aside class="score-card">
        <strong><?php echo $averageRating > 0 ? h((string) $averageRating) : '5.0'; ?></strong>
        <span>Average rating from <?php echo $reviewCount; ?> published reviews</span>
        <div class="score-stars" aria-label="5 star rating"><?php echo str_repeat('&#9733;', 5); ?></div>
      </aside>
    </div>
  </section>

	  <section class="wrap content">
	    <div class="review-column">
	      <h2 class="review-section-title">Main reviews</h2>
	      <div class="reviews">
	        <?php if (!$ready): ?>
	          <div class="empty">Import <strong>database.sql</strong> to show live reviews.</div>
	        <?php elseif (!$reviews): ?>
	          <div class="empty">Published reviews will appear here after approval.</div>
	        <?php endif; ?>

	      <?php foreach ($mainReviews as $review): ?>
	        <?php $isGoogleReview = ($review['source'] ?? '') === 'google'; ?>
	        <article class="review-card">
          <div class="stars" aria-label="<?php echo (int) $review['rating']; ?> star rating">
            <?php echo str_repeat('&#9733;', (int) $review['rating']); ?>
          </div>
          <p class="quote"><?php echo h($review['review_text']); ?></p>
          <div class="person">
            <div class="avatar" aria-hidden="true"><?php echo h(strtoupper(substr((string) $review['client_name'], 0, 1))); ?></div>
	            <div>
	              <strong><?php echo h($review['client_name']); ?></strong>
	              <span><?php echo h($review['event_type'] ?: 'Celebration'); ?></span>
	              <?php if ($isGoogleReview): ?><em class="source-badge">Google Maps</em><?php endif; ?>
	            </div>
          </div>
	        </article>
	      <?php endforeach; ?>
	      </div>
	    </div>

    <form class="review-form" method="POST">
      <h2>Share your review</h2>
      <p>Your note goes to the team first, then appears here after approval.</p>
	      <div class="google-card">
	        <strong>Google reviews</strong>
	        <?php if ($googleReviewData['configured'] && !$googleReviewData['error']): ?>
	          <p>Google Maps reviews sync automatically here.</p>
	        <?php elseif ($googleReviewData['configured'] && $googleReviewData['error']): ?>
	          <p>Google review sync is connected but needs attention.</p>
	        <?php else: ?>
	          <p>Google Maps reviews can appear here after the profile is connected.</p>
	        <?php endif; ?>
	        <?php $googleReviewLink = GOOGLE_REVIEW_URL !== '' ? GOOGLE_REVIEW_URL : ($googleReviewData['url'] ?? ''); ?>
	        <?php if ($googleReviewLink !== ''): ?><a class="btn" href="<?php echo h($googleReviewLink); ?>" target="_blank" rel="noopener">Open Google Maps</a><?php endif; ?>
	      </div>
      <?php if ($message): ?><div class="msg ok"><?php echo h($message); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="msg err"><?php echo h($error); ?></div><?php endif; ?>
      <label>Name<input name="client_name" required value="<?php echo h($name); ?>" placeholder="Your name"></label>
      <label>Event type<input name="event_type" value="<?php echo h($event); ?>" placeholder="Wedding, birthday, reception"></label>
      <label>Rating
        <div class="rating-stars" id="ratingStars" aria-label="Choose rating">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <input id="rating<?php echo $i; ?>" type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $rating === $i ? 'checked' : ''; ?>>
            <label for="rating<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</label>
          <?php endfor; ?>
        </div>
      </label>
      <label>Review<textarea name="review_text" rows="5" required placeholder="Tell us what made your celebration special"><?php echo h($text); ?></textarea></label>
      <button class="btn" type="submit" <?php echo !$ready ? 'disabled' : ''; ?>>Submit Review</button>
    </form>
  </section>
</main>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
<?php include __DIR__ . '/includes/chatbot.php'; ?>

<script>
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => {
    header.classList.toggle('scrolled', window.scrollY > 40);
  });

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

  const ratingStars = document.getElementById('ratingStars');
  const paintRating = () => {
    const checked = ratingStars.querySelector('input:checked');
    const value = checked ? Number(checked.value) : 5;
    ratingStars.querySelectorAll('label').forEach((label, index) => {
      label.classList.toggle('active', index < value);
    });
  };
  ratingStars.querySelectorAll('input').forEach(input => input.addEventListener('change', paintRating));
  paintRating();
</script>
</body>
</html>
