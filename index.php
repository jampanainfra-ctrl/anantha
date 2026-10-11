<?php
require_once __DIR__ . '/includes/config.php';

$waLink = '';
$formError = '';
$formSubmitted = false;
$reviewError = '';
$reviewSubmitted = false;
$homeReviews = [];
$googleReviewData = google_reviews_data();
$googleReviews = array_slice($googleReviewData['reviews'] ?? [], 0, 8);
$googleReviewLink = GOOGLE_REVIEW_URL !== '' ? GOOGLE_REVIEW_URL : ($googleReviewData['url'] ?? '');
$heroWhatsappLink = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode("Hi Anantha Event Lawns, I'd like to check availability for an event.");

if ($googleReviews) {
    $homeReviews = $googleReviews;
} elseif (db_ready()) {
    try {
        $homeReviews = db()->query("SELECT client_name, event_type, rating, review_text, '' AS source FROM reviews WHERE status = 'published' ORDER BY created_at DESC, id DESC LIMIT 8")->fetchAll();
    } catch (Throwable $e) {
        $homeReviews = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enquire'])) {
    $name   = trim(strip_tags($_POST['name'] ?? ''));
    $phone  = trim(strip_tags($_POST['phone'] ?? ''));
    $date   = trim(strip_tags($_POST['event_date'] ?? ''));
    $type   = trim(strip_tags($_POST['event_type'] ?? ''));
    $guests = trim(strip_tags($_POST['guests'] ?? ''));
    $note   = trim(strip_tags($_POST['note'] ?? ''));

    if ($name === '' || $phone === '') {
        $formError = 'Please share your name and phone number so we can reach you.';
    } else {
        $message = "Hi Anantha Event Lawns, I'd like to enquire about hosting an event.\n"
                 . "Name: {$name}\n"
                 . "Phone: {$phone}\n"
                 . "Event date: " . ($date ?: 'Not decided yet') . "\n"
                 . "Event type: " . ($type ?: 'Not decided yet') . "\n"
                 . "Expected guests: " . ($guests ?: 'Not decided yet') . "\n"
                 . ($note !== '' ? "Note: {$note}\n" : '');
        $waLink = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
        $formSubmitted = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])) {
    $reviewName = trim(strip_tags($_POST['review_name'] ?? ''));
    $reviewEvent = trim(strip_tags($_POST['review_event'] ?? ''));
    $reviewText = trim(strip_tags($_POST['review_text'] ?? ''));

    if ($reviewName === '' || $reviewText === '') {
        $reviewError = 'Please add your name and review.';
    } else {
        $reviewSubmitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anantha Event Lawns | Kommadi, Madhurawada, Visakhapatnam</title>
<meta name="description" content="Anantha Event Lawns is a private event lawn in Kommadi, Madhurawada, Visakhapatnam for weddings, receptions, ceremonies, birthdays, family celebrations, and corporate gatherings up to 300 guests.">
<link rel="icon" href="assets/logo.jpeg">
<link rel="preload" as="image" href="assets/hero-nature-event-optimized.jpg" fetchpriority="high">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --forest-deep:#17332B;
    --forest:#20483F;
    --forest-soft:#2E5C4F;
    --gold:#C7B896;
    --gold-soft:#E4DAC3;
    --ivory:#F6F2E9;
    --ink:#1B2A24;
    --sunset:#D9A050;
    --sunset-deep:#B97F3A;
    --line:rgba(199,184,150,0.25);
    --shadow:0 20px 40px -20px rgba(23,51,43,0.35);
  }

  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0;
    background:var(--ivory);
    color:var(--ink);
    font-family:'Inter', sans-serif;
    font-size:16px;
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
  }
  @media (prefers-reduced-motion: reduce){
    html{scroll-behavior:auto;}
    *{animation-duration:0.01ms !important; animation-iteration-count:1 !important; transition-duration:0.01ms !important;}
  }
  img{max-width:100%; display:block;}
  a{color:inherit;}
  h1,h2,h3,h4{
    font-family:'Inter', sans-serif;
    font-weight:600;
    margin:0;
    line-height:1.15;
  }
  p{margin:0;}
  .container{
    width:min(1180px, calc(100% - 48px));
    margin-inline:auto;
  }
  .container--narrow{ width:min(820px, calc(100% - 48px)); }
  header,
  section,
  footer{
    width:100%;
    max-width:100%;
  }
  section{ padding:clamp(64px, 9vw, 120px) 0; }
  .section-title{
    width:100%;
    margin-bottom:42px;
  }
  .section-title.center{
    text-align:center;
    max-width:760px;
    margin-inline:auto;
  }
  .section-title h2{
    margin-top:10px;
    font-size:clamp(1.9rem, 3.4vw, 2.8rem);
  }
  .section-title p{
    margin-top:14px;
    color:#44564F;
    max-width:68ch;
  }
  .section-dark .section-title p{ color:rgba(228,218,195,0.78); }
  .section-dark .section-title h2{ color:var(--gold-soft); }
  .topic-grid{
    display:grid;
    width:100%;
    gap:18px;
  }
  .topic-grid > *{ width:100%; }
  .container > *,
  .venue-gallery,
  .venue-frame,
  .visit-grid,
  .rules-grid,
  .capacity-grid{
    max-width:100%;
  }
  .topic-card{
    border-radius:8px;
    padding:24px;
    background:#fff;
    box-shadow:var(--shadow);
  }
  .topic-card.dark{
    background:rgba(255,255,255,0.06);
    border:1px solid var(--line);
    color:var(--gold-soft);
    box-shadow:none;
  }
  .topic-card h3,
  .topic-card h4{
    color:var(--forest-deep);
    font-size:1.2rem;
  }
  .topic-card.dark h3,
  .topic-card.dark h4{ color:var(--gold-soft); }
  .topic-card p,
  .topic-card li{
    margin-top:10px;
    color:#4a5850;
  }
  .topic-card.dark p,
  .topic-card.dark li{ color:rgba(228,218,195,0.82); }
  .topic-card ul{
    list-style:none;
    margin:16px 0 0;
    padding:0;
  }
  .topic-card li{
    padding-left:18px;
    position:relative;
  }
  .topic-card li::before{
    content:'';
    position:absolute;
    left:0;
    top:0.75em;
    width:6px;
    height:6px;
    border-radius:50%;
    background:var(--sunset);
  }
  .eyebrow{
    font-family:'Inter', sans-serif;
    font-weight:500;
    font-size:0.8rem;
    letter-spacing:0.06em;
    color:var(--forest-soft);
  }
  .section-light .eyebrow{ color:var(--sunset-deep); }
  .btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:14px 28px;
    border-radius:999px;
    font-family:'Inter', sans-serif;
    font-weight:500;
    font-size:0.95rem;
    text-decoration:none;
    border:1px solid transparent;
    cursor:pointer;
    position:relative;
    overflow:hidden;
    transition:transform 0.35s cubic-bezier(.2,.8,.2,1), box-shadow 0.35s ease, background 0.35s ease;
  }
  .btn::after{
    content:'';
    position:absolute;
    inset:-40% auto -40% -70%;
    width:52%;
    background:linear-gradient(90deg, transparent, rgba(255,255,255,0.38), transparent);
    transform:skewX(-18deg);
    transition:left 0.65s ease;
  }
  .btn:hover{ transform:translateY(-3px) scale(1.02); }
  .btn:hover::after{ left:120%; }
  .btn:active{ transform:translateY(0) scale(0.98); }
  .btn--gold{ background:var(--sunset); color:var(--forest-deep); }
  .btn--gold:hover{ box-shadow:0 12px 28px -10px rgba(217,160,80,0.55); }
  .btn--outline{ background:transparent; color:var(--gold-soft); border-color:var(--line); }
  .btn--outline:hover{ background:rgba(199,184,150,0.08); }
  .btn--outline-dark{ background:transparent; color:var(--forest); border-color:rgba(32,72,63,0.3); }
  .btn--outline-dark:hover{ background:rgba(32,72,63,0.06); }

  /* ---------- NAV ---------- */
  header{
    position:fixed; top:0; left:0; right:0; z-index:100;
    padding:18px 0;
    transition:background 0.4s ease, padding 0.4s ease, box-shadow 0.4s ease;
  }
  header.scrolled{
    background:rgba(23,51,43,0.92);
    backdrop-filter:blur(10px);
    padding:12px 0;
    box-shadow:0 10px 30px -20px rgba(0,0,0,0.4);
  }
  .nav{ display:flex; align-items:center; justify-content:space-between; gap:24px; }
  .brand{ display:flex; align-items:center; gap:10px; text-decoration:none; }
  .brand img{ height:44px; width:44px; border-radius:50%; box-shadow:0 0 0 2px rgba(228,218,195,0.28); }
  .brand span{ font-family:'Inter', sans-serif; letter-spacing:0.04em; font-size:1.08rem; color:var(--gold-soft); }
  .nav-links{ display:flex; gap:28px; list-style:none; margin:0; padding:0; align-items:center; }
  .nav-links a{
    text-decoration:none; color:var(--gold-soft); font-size:0.88rem; font-weight:500;
    position:relative; padding-bottom:4px;
  }
  .nav-links a::after{
    content:''; position:absolute; left:0; bottom:0; width:0; height:1px; background:var(--sunset);
    transition:width 0.3s ease;
  }
  .nav-links a:hover::after{ width:100%; }
  .nav-cta{
    border:1px solid rgba(244,193,107,0.58);
    border-radius:999px;
    padding:10px 15px !important;
    background:var(--sunset);
    color:#24190c !important;
    font-weight:800 !important;
    box-shadow:0 12px 24px -20px rgba(0,0,0,0.6);
  }
  .nav-cta::after{ display:none; }
  .menu-toggle{
    display:none;
    width:44px;
    height:44px;
    border-radius:50%;
    border:1px solid rgba(228,218,195,0.32);
    background:rgba(23,51,43,0.28);
    color:var(--gold-soft);
    cursor:pointer;
    place-items:center;
  }
  .menu-toggle span,
  .menu-toggle span::before,
  .menu-toggle span::after{
    display:block;
    width:19px;
    height:2px;
    border-radius:2px;
    background:currentColor;
    content:'';
    transition:transform 0.3s ease, opacity 0.3s ease;
  }
  .menu-toggle span::before{ transform:translateY(-7px); }
  .menu-toggle span::after{ transform:translateY(5px); }
  .menu-toggle.is-open span{ background:transparent; }
  .menu-toggle.is-open span::before{ transform:translateY(2px) rotate(45deg); }
  .menu-toggle.is-open span::after{ transform:translateY(0) rotate(-45deg); }
  .menu-close-row{ display:none; }
  .menu-close{
    width:44px;
    height:44px;
    border-radius:50%;
    border:1px solid rgba(228,218,195,0.32);
    background:rgba(255,255,255,0.06);
    color:var(--gold-soft);
    font-size:1.7rem;
    line-height:1;
    cursor:pointer;
    transition:transform 0.3s ease, background 0.3s ease;
  }
  .menu-close:hover{ transform:rotate(90deg) scale(1.04); background:rgba(255,255,255,0.12); }
  .nav-backdrop{
    position:fixed;
    inset:0;
    z-index:99;
    background:rgba(8,28,23,0.54);
    opacity:0;
    pointer-events:none;
    transition:opacity 0.3s ease;
  }
  .nav-backdrop.open{ opacity:1; pointer-events:auto; }

  @media (max-width: 860px){
    .nav-links{
      position:fixed; top:0; right:0; z-index:101; height:100vh; width:min(320px,86vw);
      background:var(--forest-deep); flex-direction:column; padding:100px 32px 32px;
      transform:translateX(100%); transition:transform 0.4s ease; gap:22px;
      box-shadow:-24px 0 50px -28px rgba(0,0,0,0.65);
    }
    .nav-links.open{ transform:translateX(0); }
    .menu-toggle{ display:grid; }
    .menu-close-row{
      display:flex;
      justify-content:flex-end;
      position:absolute;
      top:22px;
      right:24px;
    }
    body.menu-open{ overflow:hidden; }
  }

  /* ---------- HERO ---------- */
  .hero{
    position:relative;
    min-height:100vh;
    background:
      linear-gradient(180deg, rgba(23,51,43,0.10), rgba(23,51,43,0.86)),
      linear-gradient(90deg, rgba(23,51,43,0.72), rgba(23,51,43,0.24) 45%, rgba(23,51,43,0.62)),
      url('assets/hero-nature-event-optimized.jpg') center/cover no-repeat,
      radial-gradient(circle at 22% 24%, rgba(217,160,80,0.24) 0 10%, transparent 28%),
      radial-gradient(circle at 78% 18%, rgba(228,218,195,0.16) 0 8%, transparent 27%),
      linear-gradient(135deg, var(--forest-soft), var(--forest) 48%, var(--forest-deep));
    display:flex; align-items:center; justify-content:center; text-align:left;
    overflow:hidden;
    color:var(--gold-soft);
    padding-top:90px;
  }
  .hero::before,
  .hero::after{
    content:'';
    position:absolute;
    inset:-20%;
    pointer-events:none;
  }
  .hero::before{
    background:
      conic-gradient(from 185deg at 50% 18%, transparent 0 9deg, rgba(228,218,195,0.12) 12deg 20deg, transparent 24deg 45deg),
      conic-gradient(from 207deg at 42% 12%, transparent 0 11deg, rgba(217,160,80,0.12) 14deg 22deg, transparent 25deg 44deg);
    mix-blend-mode:screen;
    animation:sunRays 16s ease-in-out infinite alternate;
  }
  .hero::after{
    background:
      radial-gradient(ellipse at bottom, rgba(199,184,150,0.24), transparent 42%),
      linear-gradient(0deg, rgba(8,28,23,0.95) 0 12%, transparent 42%);
  }
  @keyframes sunRays{
    from{ transform:translateX(-1.5%) rotate(-2deg); opacity:0.65; }
    to{ transform:translateX(1.5%) rotate(2deg); opacity:0.95; }
  }
  .petal{
    position:absolute; z-index:1; width:14px; height:14px; opacity:0.35;
    background:var(--gold);
    clip-path:polygon(50% 0%, 100% 40%, 80% 100%, 20% 100%, 0% 40%);
    animation:drift linear infinite;
  }
  .firefly{
    position:absolute;
    z-index:1;
    width:5px;
    height:5px;
    border-radius:50%;
    background:#F7E8A4;
    box-shadow:0 0 12px 4px rgba(247,232,164,0.38);
    opacity:0;
    animation:glowFloat ease-in-out infinite;
  }
  @keyframes glowFloat{
    0%,100%{ transform:translate3d(0, 0, 0) scale(0.7); opacity:0; }
    20%{ opacity:0.9; }
    55%{ transform:translate3d(24px, -42px, 0) scale(1); opacity:0.55; }
    80%{ opacity:0.85; }
  }
  @keyframes drift{
    0%{ transform:translateY(-10vh) translateX(0) rotate(0deg); opacity:0; }
    10%{ opacity:0.4; }
    90%{ opacity:0.25; }
    100%{ transform:translateY(110vh) translateX(40px) rotate(160deg); opacity:0; }
  }
  .hero-inner{
    position:relative;
    z-index:2;
    width:min(960px, calc(100% - 48px));
  }
  .hero-location{
    font-size:0.78rem;
	    font-weight:700;
	    letter-spacing:0.24em;
    text-transform:uppercase;
    color:var(--gold);
  }
  .hero-mark{
    width:118px; height:118px; margin:0 auto 28px;
    border:3px solid rgba(228,218,195,0.62);
    box-shadow:0 18px 40px -18px rgba(0,0,0,0.7), 0 0 0 10px rgba(23,51,43,0.18);
    opacity:0; animation:fadeScale 1s ease forwards 0.1s;
  }
	  .hero h1{
	    max-width:900px;
	    margin-top:34px;
		    font-family:'Cormorant Garamond', serif;
		    font-size:clamp(3.2rem, 5.8vw, 5rem);
		    font-weight:700;
		    line-height:1.02;
	    letter-spacing:0;
	    color:var(--gold-soft);
	    text-wrap:balance;
		    text-shadow:0 18px 42px rgba(0,0,0,0.38);
	    opacity:0; animation:riseIn 1s ease forwards 0.4s;
  }
  .hero .tagline{
	    margin-top:24px;
	    font-family:'Cormorant Garamond', serif;
	    font-size:clamp(1.85rem, 3.1vw, 2.7rem);
	    font-weight:600;
	    line-height:1;
	    letter-spacing:0;
    color:var(--gold);
    opacity:0; animation:riseIn 1s ease forwards 0.7s;
  }
  .hero .lede{
	    margin:24px 0 0;
	    max-width:680px;
	    font-size:1.16rem;
	    line-height:1.75;
	    font-weight:500;
    color:rgba(246,242,233,0.92);
    opacity:0; animation:riseIn 1s ease forwards 0.95s;
  }
  .hero-actions{
    margin-top:34px; display:flex; gap:18px; justify-content:flex-start; flex-wrap:wrap;
    opacity:0; animation:riseIn 1s ease forwards 1.2s;
  }
	  .hero-actions .btn{
	    min-width:250px;
	    justify-content:center;
	    border-radius:4px;
	    letter-spacing:0.24em;
	    text-transform:uppercase;
	    font-size:0.88rem;
	  }
	  .hero-highlights{
	    margin-top:22px;
	    max-width:760px;
		    font-size:0.82rem;
	    font-weight:700;
		    letter-spacing:0.08em;
	    line-height:1.8;
	    text-transform:uppercase;
	    color:rgba(246,242,233,0.9);
	    opacity:0; animation:riseIn 1s ease forwards 1.35s;
	  }
	  @media (max-width: 900px){
	    .hero h1{
		      font-size:clamp(2.8rem, 7.8vw, 3.8rem);
	    }
	  }
	  @media (max-width: 720px){
    .hero{
      min-height:92vh;
      padding-top:86px;
      padding-bottom:42px;
    }
    .hero-location{
      font-size:0.68rem;
	      letter-spacing:0.16em;
      line-height:1.8;
    }
	    .hero h1{
	      margin-top:24px;
		      font-size:clamp(2.25rem, 10vw, 3rem);
	      letter-spacing:0;
	    }
    .hero .tagline{
	      margin-top:20px;
		      font-size:clamp(1.65rem, 7.5vw, 2.25rem);
    }
    .hero .lede{
      margin-top:20px;
	      font-size:1.02rem;
	      line-height:1.7;
    }
    .hero-actions{
      margin-top:28px;
      gap:12px;
    }
	    .hero-actions .btn{
	      width:100%;
	      min-width:0;
	      padding-inline:18px;
	      letter-spacing:0.16em;
	    }
	    .hero-highlights{
	      margin-top:18px;
	      font-size:0.76rem;
		      letter-spacing:0.04em;
	      line-height:1.7;
	    }
	  }
	  @media (max-width: 420px){
	    .hero h1{
	      font-size:2.18rem;
	    }
	  }
  @keyframes riseIn{ from{ opacity:0; transform:translateY(18px);} to{ opacity:1; transform:translateY(0);} }
  @keyframes fadeScale{ from{ opacity:0; transform:scale(0.85);} to{ opacity:1; transform:scale(1);} }

  .scroll-cue{
    position:absolute; bottom:28px; left:50%; transform:translateX(-50%);
    z-index:2;
    width:22px; height:36px; border:1px solid var(--line); border-radius:12px;
    display:flex; justify-content:center; padding-top:6px;
  }
  .scroll-cue::before{
    content:''; width:4px; height:8px; border-radius:2px; background:var(--gold);
    animation:cue 1.8s ease-in-out infinite;
  }
  @keyframes cue{ 0%{ transform:translateY(0); opacity:1;} 70%{ transform:translateY(10px); opacity:0;} 100%{opacity:0;} }

  /* ---------- reveal-on-scroll (subtle, section-level) ---------- */
  .reveal{ opacity:0; transform:translateY(24px); transition:opacity 0.8s ease, transform 0.8s ease; }
  .reveal.is-visible{ opacity:1; transform:translateY(0); }
  .reveal .photo-tile,
  .reveal .occasion-card,
  .reveal .space-visual,
  .reveal .pillar{
    opacity:0;
    transform:translateY(18px);
    transition:opacity 0.75s ease, transform 0.75s ease, box-shadow 0.35s ease;
  }
  .reveal.is-visible .photo-tile,
  .reveal.is-visible .occasion-card,
  .reveal.is-visible .space-visual,
  .reveal.is-visible .pillar{
    opacity:1;
    transform:translateY(0);
  }
  .reveal.is-visible .photo-tile:nth-child(2),
  .reveal.is-visible .occasion-card:nth-child(2),
  .reveal.is-visible .pillar:nth-child(2){ transition-delay:0.1s; }
  .reveal.is-visible .photo-tile:nth-child(3),
  .reveal.is-visible .occasion-card:nth-child(3),
  .reveal.is-visible .pillar:nth-child(3){ transition-delay:0.2s; }
  .reveal.is-visible .occasion-card:nth-child(4){ transition-delay:0.3s; }
  .reveal.is-visible .occasion-card:nth-child(5){ transition-delay:0.4s; }
  .reveal.is-visible .occasion-card:nth-child(6){ transition-delay:0.05s; }
  .reveal.is-visible .occasion-card:nth-child(7){ transition-delay:0.15s; }
  .reveal.is-visible .occasion-card:nth-child(8){ transition-delay:0.25s; }
  .reveal.is-visible .occasion-card:nth-child(9){ transition-delay:0.35s; }
  .reveal.is-visible .occasion-card:nth-child(10){ transition-delay:0.45s; }
  .reveal.is-visible .photo-tile:hover,
  .reveal.is-visible .occasion-card:hover,
  .reveal.is-visible .space-visual:hover,
  .reveal.is-visible .pillar:hover{
    transform:translateY(-7px);
  }

  /* ---------- ABOUT ---------- */
  .section-light{ background:var(--ivory); }
  .about{
    position:relative;
    overflow:hidden;
    background:
      linear-gradient(180deg, rgba(246,242,233,0.94), rgba(246,242,233,0.98)),
      radial-gradient(circle at 18% 52%, rgba(199,184,150,0.22), transparent 30%),
      radial-gradient(circle at 86% 14%, rgba(46,92,79,0.10), transparent 28%);
  }
  .about::before{
    content:'';
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    height:7px;
    background:linear-gradient(90deg, var(--forest), var(--sunset), var(--forest-soft));
    opacity:0.75;
  }
  .about-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:clamp(28px, 4vw, 48px);
    align-items:center;
    max-width:920px;
  }
  .about-copy{ text-align:center; }
  .about-visual{
    position:relative;
    min-height:420px;
    isolation:isolate;
  }
  .about-photo{
    position:absolute;
    overflow:hidden;
    border-radius:8px;
    box-shadow:0 24px 50px -24px rgba(23,51,43,0.48);
  }
  .about-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    transition:transform 0.9s cubic-bezier(.2,.8,.2,1), filter 0.9s ease;
  }
  .about-photo:hover img{
    transform:scale(1.09);
    filter:saturate(1.12) contrast(1.04);
  }
  .about-photo.main{
    inset:0 9% 12% 0;
    animation:floatPhoto 7s ease-in-out infinite;
  }
  .about-photo.accent{
    right:0;
    bottom:0;
    width:46%;
    height:44%;
    border:8px solid var(--ivory);
    animation:floatPhoto 7s ease-in-out infinite reverse;
    z-index:2;
  }
  .about-logo-badge{
    position:absolute;
    left:8%;
    bottom:6%;
    z-index:3;
    width:92px;
    height:92px;
    border-radius:50%;
    display:grid;
    place-items:center;
    background:rgba(246,242,233,0.9);
    border:1px solid rgba(199,184,150,0.7);
    box-shadow:0 16px 38px -20px rgba(23,51,43,0.55);
    animation:breathe 5s ease-in-out infinite;
  }
  .about-logo-badge img{
    width:68px;
    height:68px;
    border-radius:50%;
    object-fit:cover;
  }
  @keyframes floatPhoto{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-10px); } }
  @keyframes breathe{ 0%,100%{ transform:scale(1);} 50%{ transform:scale(1.045);} }
	  .about h2{ font-size:clamp(1.8rem, 3vw, 2.4rem); color:var(--forest-deep); margin:10px auto 0; max-width:760px; line-height:1.08; text-wrap:balance; }
	  .about-copy > p{ margin:18px auto 0; color:#3a4a44; max-width:62ch; }
			  .about-pillars{
			    display:grid;
			    grid-template-columns:repeat(6,minmax(0,1fr));
			    gap:16px;
			    margin:32px auto 0;
			    max-width:860px;
			  }
			  .pillar{
				    grid-column:span 2;
				    min-width:0;
				    min-height:138px;
			    display:grid;
			    grid-template-rows:auto auto;
			    align-content:center;
			    justify-items:center;
			    gap:10px;
			    padding:24px 18px;
			    text-align:center;
		    background:linear-gradient(145deg, rgba(255,255,255,0.88), rgba(246,242,233,0.72));
		    border:1px solid rgba(179,128,42,0.26);
		    border-top:4px solid var(--gold);
		    border-radius:8px;
		    box-shadow:0 18px 38px -32px rgba(23,51,43,0.58);
		    transition:transform 0.35s ease, background 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
		  }
	  .pillar:hover{
	    transform:translateY(-5px);
	    background:linear-gradient(145deg, rgba(255,255,255,0.96), rgba(234,197,104,0.28));
	    border-color:rgba(179,128,42,0.48);
	    box-shadow:0 24px 42px -30px rgba(23,51,43,0.62);
	  }
	  .pillar.is-highlight{
	    background:linear-gradient(135deg, rgba(246,242,233,0.94), rgba(234,197,104,0.34));
	    border-color:rgba(179,128,42,0.42);
	    box-shadow:0 20px 42px -30px rgba(92,67,30,0.55);
	  }
		  .pillar:nth-child(2){ border-top-color:var(--forest-soft); }
		  .pillar:nth-child(3){ border-top-color:var(--sunset); }
		  .pillar:nth-child(4){ border-top-color:#3f7f93; }
		  .pillar:nth-child(5){ border-top-color:#7b8f38; }
		  .pillar:nth-child(4){ grid-column:2 / span 2; }
		  .pillar:nth-child(5){ grid-column:4 / span 2; }
					  .pillar strong{ display:block; font-family:'Inter', sans-serif; font-size:clamp(1.58rem,2.1vw,2rem); line-height:1.08; color:var(--forest); white-space:normal; }
					  .pillar span{ display:block; margin:0; font-size:clamp(0.94rem,1.1vw,1.04rem); line-height:1.35; font-weight:800; color:#3d5048; overflow-wrap:normal; word-break:normal; hyphens:none; }

	  @media (max-width: 980px){
	    .about-grid{ grid-template-columns:1fr; gap:36px; }
		    .about-copy{ text-align:center; }
		    .about-visual{ min-height:330px; }
		    .about-copy > p{ margin-inline:auto; }
		    .about-pillars{ grid-template-columns:repeat(2,minmax(220px,1fr)); max-width:620px; }
		    .pillar{ grid-column:auto; }
		    .pillar:nth-child(4),
		    .pillar:nth-child(5){ grid-column:auto; }
		  }

	  @media (max-width: 560px){
	    .about h2{ font-size:clamp(1.75rem,9vw,2.45rem); }
	    .about-pillars{ grid-template-columns:1fr; }
	    .pillar{ min-height:118px; padding:22px 16px; }
	  }

  /* ---------- OCCASIONS ---------- */
  .occasion-band{
    background:
      linear-gradient(180deg, rgba(246,242,233,0.94), rgba(246,242,233,0.98)),
      radial-gradient(circle at 10% 20%, rgba(217,160,80,0.18), transparent 28%),
      radial-gradient(circle at 90% 12%, rgba(46,92,79,0.16), transparent 30%);
    position:relative;
    overflow:hidden;
  }
  .occasion-band::before{
    content:'';
    position:absolute;
    inset:36px;
    border:1px solid rgba(32,72,63,0.12);
    border-radius:36px;
    pointer-events:none;
  }
	  .occasion-head{
	    display:grid;
	    grid-template-columns:0.9fr 1.1fr;
	    gap:42px;
	    align-items:end;
	    margin-bottom:42px;
	    position:relative;
	    z-index:1;
	  }
  .occasion-head h2{
    color:var(--forest-deep);
    font-size:clamp(1.8rem,3vw,2.5rem);
    margin-top:10px;
  }
  .occasion-head p{ color:#44564F; }
		  .occasion-grid{
		    display:grid;
		    grid-template-columns:repeat(3, minmax(0, 1fr));
		    gap:18px;
		    width:100%;
		    position:relative;
		    z-index:1;
		  }
		  .occasion-card{
		    position:relative;
		    min-height:270px;
		    aspect-ratio:4/3;
		    padding:24px;
	    border-radius:8px;
	    overflow:hidden;
	    color:var(--gold-soft);
	    background:var(--forest);
    box-shadow:var(--shadow);
    isolation:isolate;
    display:flex;
    align-items:flex-end;
    transition:transform 0.35s ease, box-shadow 0.35s ease;
  }
  .occasion-card::before{
    content:'';
    position:absolute;
    inset:0;
	    background:
	      linear-gradient(180deg, rgba(23,51,43,0.04), rgba(23,51,43,0.78)),
	      var(--occasion-art);
	    background-size:cover;
	    background-position:var(--occasion-position, center);
	    transform:scale(1.04);
	    transition:transform 0.7s cubic-bezier(.2,.8,.2,1);
	    z-index:-2;
	  }
  .occasion-card::after{
    content:'';
    position:absolute;
    left:18px;
    right:18px;
    bottom:18px;
    height:1px;
    background:linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity:0.65;
    z-index:-1;
  }
  .occasion-card h3::before{
    content:'';
    position:absolute;
    left:0;
    bottom:-8px;
    width:34px;
    height:2px;
    background:var(--gold);
    transform-origin:left;
    transform:scaleX(0.65);
    transition:transform 0.28s ease;
  }
  .occasion-card:hover::before{ transform:scale(1.12); }
  .occasion-card:hover{
    transform:translateY(-8px);
    box-shadow:0 28px 56px -28px rgba(23,51,43,0.62);
  }
  .occasion-card:hover h3::before{ transform:scaleX(1.55); }
  .occasion-icon{ display:none; }
	  .occasion-card h3{
	    position:relative;
	    font-size:1.25rem;
	    color:var(--gold-soft);
	    text-shadow:0 2px 12px rgba(0,0,0,0.34);
	    margin:0;
	  }
	  .occasion-card p{ margin:14px 0 0; font-size:0.92rem; line-height:1.55; color:rgba(246,242,233,0.88); max-width:31ch; text-shadow:0 2px 10px rgba(0,0,0,0.38); }
	  .occasion-copy{ width:100%; }

  .nature-note{
    margin-top:36px;
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:18px;
  }
  .nature-note div{ border-top:1px solid rgba(32,72,63,0.18); padding-top:16px; }
  .nature-note strong{ display:block; font-family:'Inter', sans-serif; color:var(--forest); font-size:1.05rem; }
  .nature-note span{ display:block; margin-top:6px; color:#52645C; font-size:0.92rem; }

	  @media (max-width: 960px){
	    .occasion-head{ grid-template-columns:1fr; gap:14px; margin-bottom:28px; }
	  }
		  @media (max-width: 980px){
		    .occasion-grid{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
		  }
	  @media (max-width: 620px){
	    .occasion-band::before{ display:none; }
	    .occasion-band{ padding-top:56px; padding-bottom:56px; }
	    .occasion-head{ gap:12px; margin-bottom:24px; }
	    .occasion-head h2{ font-size:clamp(1.9rem,8vw,2.5rem); line-height:1.08; max-width:11ch; }
	    .occasion-head p{ margin:0; line-height:1.65; }
	    .occasion-grid, .nature-note{ grid-template-columns:1fr; }
	    .occasion-grid{ gap:14px; }
	    .occasion-card{ min-height:160px; aspect-ratio:16/8.5; padding:18px; }
	    .occasion-card h3{ font-size:1.12rem; }
	  }

  /* ---------- GALLERY ---------- */
  .gallery-band{
    background:#fff;
    padding-top:clamp(56px, 8vw, 96px);
  }
  .gallery-head{
    display:flex;
    align-items:end;
    justify-content:space-between;
    gap:32px;
    margin-bottom:30px;
  }
  .gallery-head h2{
    color:var(--forest-deep);
    font-size:clamp(1.8rem,3vw,2.45rem);
    margin-top:10px;
  }
  .gallery-head p{
    max-width:48ch;
    color:#4a5850;
  }
  .venue-gallery{
    display:grid;
    grid-template-columns:1.35fr 0.85fr;
    gap:18px;
  }
  .photo-tile{
    position:relative;
    min-height:420px;
    border-radius:8px;
    overflow:hidden;
    box-shadow:var(--shadow);
    background:#17332B;
  }
  .photo-tile.small{ min-height:201px; }
  .photo-stack{
    display:grid;
    gap:18px;
  }
  .photo-tile img{
    width:100%;
    height:100%;
    object-fit:cover;
    position:absolute;
    inset:0;
    transform:scale(1.02);
    transition:transform 0.8s cubic-bezier(.2,.8,.2,1), filter 0.8s ease;
  }
  .photo-tile:hover img{
    transform:scale(1.08);
    filter:saturate(1.08);
  }
  .photo-tile:hover{
    transform:translateY(-6px);
    box-shadow:0 28px 58px -28px rgba(23,51,43,0.58);
  }
  .photo-tile::after{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(180deg, transparent 34%, rgba(23,51,43,0.72));
  }
  .photo-caption{
    position:absolute;
    left:22px;
    right:22px;
    bottom:20px;
    z-index:1;
    color:var(--gold-soft);
  }
  .photo-caption strong{
    display:block;
    font-family:'Inter', sans-serif;
    font-size:1.2rem;
    line-height:1.2;
  }
  .photo-caption span{
    display:block;
    margin-top:5px;
    font-size:0.9rem;
    color:rgba(246,242,233,0.78);
  }
  @media (max-width: 820px){
    .gallery-head{ display:block; }
    .gallery-head p{ margin-top:14px; }
    .venue-gallery{ grid-template-columns:1fr; }
    .photo-tile, .photo-tile.small{ min-height:300px; }
  }

  /* ---------- SPACES (zigzag) ---------- */
  .section-dark{ background:var(--forest-deep); color:var(--gold-soft); }
  .spaces-head{ text-align:center; max-width:620px; margin:0 auto 64px; }
  .spaces-head h2{ font-size:clamp(1.8rem,3vw,2.4rem); color:var(--gold-soft); margin-top:10px; }
  .spaces-head p{ margin-top:14px; color:rgba(228,218,195,0.75); }

  .space-row{
    display:grid; grid-template-columns:1fr 1fr; gap:56px; align-items:center;
    padding-block:36px;
  }
  .space-row:not(:last-child){ border-bottom:1px solid var(--line); }
  .space-row.reverse .space-visual{ order:2; }
  .space-visual{
    aspect-ratio:4/3; border-radius:140px 20px 140px 20px;
    background:linear-gradient(155deg, var(--forest-soft), var(--forest) 60%, var(--forest-deep));
    position:relative; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
  }
  .space-visual img{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    object-fit:cover;
    transform:scale(1.02);
    transition:transform 0.8s cubic-bezier(.2,.8,.2,1), filter 0.8s ease;
  }
  .space-visual::after{
    content:'';
    position:absolute;
    inset:0;
    background:
      linear-gradient(180deg, rgba(23,51,43,0.06), rgba(23,51,43,0.36)),
      radial-gradient(circle at 70% 24%, rgba(217,160,80,0.24), transparent 28%);
    pointer-events:none;
  }
  .space-visual:hover img{
    transform:scale(1.08);
    filter:saturate(1.08) contrast(1.03);
  }
  .space-visual:hover{
    box-shadow:0 28px 58px -28px rgba(0,0,0,0.62);
  }
  .space-visual svg{ width:58%; opacity:0.9; }
  .space-copy .num{ font-family:'Inter', sans-serif; font-size:0.95rem; color:var(--sunset); letter-spacing:0.08em; }
  .space-copy h3{ font-size:1.7rem; color:var(--gold-soft); margin-top:8px; }
  .space-copy p{ margin-top:14px; color:rgba(228,218,195,0.78); max-width:46ch; }
  .space-tags{ display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; }
  .space-tags span{
    max-width:100%;
    overflow-wrap:anywhere;
    font-size:0.78rem; padding:6px 14px; border:1px solid var(--line); border-radius:999px; color:var(--gold);
  }
  .venue-frame{
    display:grid;
    grid-template-columns:minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap:28px;
    align-items:stretch;
    width:100%;
    padding:28px;
    border:1px solid var(--line);
    border-radius:8px;
    background:rgba(255,255,255,0.06);
  }
  .venue-frame > *{
    min-width:0;
  }
  .venue-frame .space-visual{
    width:100%;
    min-height:360px;
    border-radius:90px 16px 90px 16px;
  }
  .venue-frame-copy{
    display:grid;
    gap:18px;
  }
  .venue-feature{
    width:100%;
    min-width:0;
    overflow:hidden;
    padding:22px;
    border-radius:8px;
    background:rgba(255,255,255,0.06);
  }
  .venue-feature h3{
    color:var(--gold-soft);
    font-size:1.45rem;
  }
  .venue-feature p{
    margin-top:10px;
    color:rgba(228,218,195,0.78);
  }
  .room-photo-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:12px;
    margin-top:18px;
    width:100%;
  }
  .room-photo-grid img{
    width:100%;
    height:150px;
    object-fit:cover;
    border-radius:8px;
  }
  .site-list{
    grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
    margin-top:28px;
  }
  .site-list .topic-card{
    min-height:100%;
  }
  .capacity-grid{
    grid-template-columns:0.75fr 1.25fr;
    align-items:stretch;
    margin-bottom:24px;
  }
  .capacity-card{
    display:flex;
    flex-direction:column;
    justify-content:center;
    text-align:center;
    background:var(--sunset);
    color:var(--forest-deep);
  }
  .capacity-card strong{
    display:block;
    font-family:'Inter', sans-serif;
    font-size:clamp(3.4rem, 8vw, 5.5rem);
    line-height:1;
  }
  .capacity-card span{
    display:block;
    margin-top:8px;
    font-weight:600;
    letter-spacing:0.1em;
    text-transform:uppercase;
  }
  .rules-grid{
    grid-template-columns:repeat(2, minmax(0, 1fr));
    margin-top:24px;
  }
  .package-grid{
    grid-template-columns:repeat(3, minmax(0, 1fr));
  }
  .package-card{
    display:flex;
    min-height:250px;
    flex-direction:column;
    justify-content:space-between;
  }
  .package-card .package-size{
    color:var(--sunset);
    font-size:0.78rem;
    font-weight:600;
    letter-spacing:0.12em;
    text-transform:uppercase;
  }
  .package-card h3{
    margin-top:12px;
    font-size:1.6rem;
  }
  .contact-links{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:10px;
  }

  @media (max-width: 780px){
    .space-row, .space-row.reverse{ grid-template-columns:1fr; }
    .space-row.reverse .space-visual{ order:0; }
    .venue-frame{ grid-template-columns:1fr; }
    .capacity-grid,
    .rules-grid,
    .package-grid{ grid-template-columns:1fr; }
    .room-photo-grid{ grid-template-columns:1fr; }
  }

  /* ---------- EXPERIENCE TIMELINE ---------- */
  .timeline{ position:relative; margin-top:60px; }
  .timeline::before{
    content:''; position:absolute; left:23px; top:6px; bottom:6px; width:1px;
    background:linear-gradient(var(--forest-soft), transparent 95%);
  }
  .tl-item{ position:relative; padding-left:64px; margin-bottom:44px; }
  .tl-item:last-child{ margin-bottom:0; }
  .tl-dot{
    position:absolute; left:0; top:0; width:48px; height:48px; border-radius:50%;
    background:var(--ivory); border:1px solid rgba(32,72,63,0.15);
    display:flex; align-items:center; justify-content:center;
    font-family:'Inter', sans-serif; color:var(--forest); font-size:1rem;
  }
  .tl-item h4{ font-size:1.15rem; color:var(--forest-deep); }
  .tl-item p{ margin-top:6px; color:#4a5850; max-width:52ch; }
  .tl-item .time{ font-size:0.8rem; color:var(--sunset-deep); letter-spacing:0.04em; }

  /* ---------- REVIEWS ---------- */
  .reviews-section{
    position:relative;
    overflow:hidden;
    background:
      linear-gradient(180deg, rgba(23,51,43,0.96), rgba(23,51,43,0.98)),
      radial-gradient(circle at 12% 18%, rgba(217,160,80,0.18), transparent 30%),
      radial-gradient(circle at 86% 74%, rgba(199,184,150,0.12), transparent 32%);
  }
  .reviews-section::before{
    content:'';
    position:absolute;
    inset:0;
    background:url('assets/hero-nature-event-optimized.jpg') center/cover no-repeat;
    opacity:0.08;
    transform:scale(1.05);
    animation:slowBackdrop 18s ease-in-out infinite alternate;
  }
  @keyframes slowBackdrop{
    from{ transform:scale(1.05) translateX(-1%); }
    to{ transform:scale(1.1) translateX(1%); }
  }
  .reviews-section .container{ position:relative; z-index:1; }
  .review-head{
    display:grid;
    grid-template-columns:0.9fr 1.1fr;
    gap:40px;
    align-items:end;
    margin-bottom:34px;
  }
  .review-head h2{
    color:var(--gold-soft);
    font-size:clamp(1.9rem, 3vw, 2.7rem);
    margin-top:10px;
  }
  .review-head p{ color:rgba(228,218,195,0.78); max-width:62ch; }
  .review-hero-strip{
    display:grid;
    grid-template-columns:1.2fr 0.8fr 0.8fr;
    gap:14px;
    margin-bottom:34px;
  }
  .review-hero-strip img{
    width:100%;
    height:220px;
    object-fit:cover;
    border-radius:8px;
    box-shadow:0 24px 50px -28px rgba(0,0,0,0.62);
    transition:transform 0.7s cubic-bezier(.2,.8,.2,1), filter 0.7s ease;
  }
  .review-hero-strip img:first-child{ height:300px; grid-row:span 2; }
  .review-hero-strip img:hover{
    transform:translateY(-7px) scale(1.025);
    filter:saturate(1.12) contrast(1.04);
  }
  .review-carousel{width:100%;margin-inline:auto}
  .review-wrap{overflow:hidden;padding:8px 0;touch-action:pan-y;mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)}
  .review-track{display:flex;width:max-content;gap:14px;will-change:transform}
  .review-track.is-moving{animation:reviewMarquee 26s linear infinite}
  .review-track.is-moving:hover{animation-play-state:paused}
  .review-group{display:flex;gap:14px}
  .review-card{position:relative;flex:0 0 280px;min-height:210px;background:var(--ivory);color:var(--ink);border:1px solid rgba(217,160,80,.3);border-radius:8px;padding:24px;box-shadow:0 18px 42px -30px rgba(0,0,0,.6);display:flex;flex-direction:column;justify-content:space-between}
	  .review-card::before{content:'\201C';position:absolute;top:8px;right:16px;color:rgba(217,160,80,.18);font-size:4.5rem;line-height:1;font-family:Georgia,serif}
	  .review-card p.quote{position:relative;margin:0;padding-top:18px;font-family:'Inter',sans-serif;font-size:1rem;line-height:1.6;color:var(--forest-deep)}
	  .review-card .who{margin:18px 0 0;padding-top:14px;border-top:1px solid rgba(23,51,43,.1);font-size:.82rem;font-weight:800;color:#5c6b64}
	  .review-card .source-badge{display:inline-flex;margin-left:8px;color:var(--sunset-deep);font-style:normal;font-size:.72rem}
	  @keyframes reviewMarquee{from{transform:translateX(0)}to{transform:translateX(calc(-50% - 7px))}}
  @media(prefers-reduced-motion:reduce){.review-wrap{overflow-x:auto;mask-image:none}.review-track.is-moving{animation:none}}  .review-empty{
    background:rgba(255,255,255,0.08);
    border:1px solid var(--line);
    border-radius:8px;
    color:rgba(228,218,195,0.82);
    padding:28px;
    text-align:center;
  }
  .review-panel{
    border:1px solid var(--line);
    border-radius:8px;
    padding:28px;
    background:rgba(255,255,255,0.06);
  }
  .love-list{ display:flex; flex-direction:column; gap:16px; }
  .love-item{
    display:flex; gap:14px; align-items:flex-start; padding:16px 0; border-bottom:1px solid var(--line);
  }
  .love-item:last-child{ border-bottom:none; }
  .love-item .dot{ width:8px; height:8px; border-radius:50%; background:var(--sunset); margin-top:8px; flex-shrink:0; }
  .love-item p{ color:rgba(228,218,195,0.85); font-size:0.96rem; }
  .review-form{
    padding:28px;
    border-radius:8px;
    background:rgba(255,255,255,0.07);
    border:1px solid var(--line);
  }
  .review-form h4{ color:var(--gold-soft); font-size:1.15rem; margin-bottom:18px; }
  .review-form label{ display:block; font-size:0.8rem; color:var(--gold); margin-bottom:6px; }
  .review-form input,
  .review-form textarea{
    width:100%;
    border:1px solid var(--line);
    background:rgba(255,255,255,0.05);
    color:var(--gold-soft);
    border-radius:8px;
    padding:12px 14px;
    margin-bottom:14px;
    font:inherit;
  }
  .review-form textarea{ resize:vertical; min-height:96px; }
  .review-form input::placeholder,
  .review-form textarea::placeholder{ color:rgba(228,218,195,0.45); }
  .review-form input:focus,
  .review-form textarea:focus{ outline:2px solid var(--sunset); outline-offset:1px; }

  @media (max-width: 960px){
    .review-head{ grid-template-columns:1fr; }
    .review-wrap{ min-height:0; }
    .review-hero-strip{ grid-template-columns:1fr 1fr; }
    .review-hero-strip img:first-child{ grid-column:1 / -1; height:260px; }
  }
  @media (max-width: 620px){
    .review-carousel{width:100%}
    .review-card{flex-basis:245px;min-height:195px;padding:20px}
    .review-card::before{font-size:4.5rem;right:18px}
    .review-controls{margin-top:16px}
    .review-hero-strip{ grid-template-columns:1fr; }
    .review-hero-strip img,
    .review-hero-strip img:first-child{ height:220px; grid-column:auto; }
  }

	  /* ---------- VISIT / CONTACT ---------- */
	  .visit-map-section{
	    padding:0;
	    background:var(--forest-deep);
	  }
		  .visit-map-grid{
		    display:grid;
		    grid-template-columns:minmax(0, 0.95fr) minmax(0, 1.05fr);
		    min-height:440px;
		  }
	  .visit-copy{
	    display:flex;
	    flex-direction:column;
	    justify-content:center;
		    padding:clamp(34px, 5vw, 58px) clamp(24px, 5vw, 64px);
		    color:var(--gold-soft);
		  }
	  .visit-copy .eyebrow{
	    color:var(--gold);
	    display:flex;
	    align-items:center;
	    gap:16px;
	  }
	  .visit-copy .eyebrow::before{
	    content:'';
	    width:36px;
	    height:1px;
	    background:var(--gold);
	  }
		  .visit-copy h2{
		    margin-top:18px;
		    color:#fff;
		    font-size:clamp(2rem, 3.4vw, 3.35rem);
		    max-width:620px;
		  }
		  .visit-copy p{
		    margin-top:16px;
		    color:rgba(228,218,195,0.86);
		    font-size:clamp(1rem, 1.7vw, 1.22rem);
		    line-height:1.6;
		    max-width:560px;
		  }
	  .visit-actions{
	    display:flex;
	    gap:18px;
	    flex-wrap:wrap;
		    margin-top:26px;
		  }
	  .visit-actions .btn{
	    min-width:190px;
	    justify-content:center;
	    letter-spacing:0.14em;
	    text-transform:uppercase;
	  }
		  .visit-map{
		    min-height:360px;
		    background:#e9e4d8;
		  }
	  .visit-map iframe{
	    width:100%;
	    height:100%;
		    min-height:440px;
	    border:0;
	    display:block;
	  }

		  @media (max-width: 860px){
		    .visit-map-grid{ grid-template-columns:1fr; min-height:0; }
		    .visit-map iframe{ min-height:320px; }
		  }

  @media (max-width: 480px){
    .container,
    .hero-inner{
      width:min(100% - 32px, 1100px);
    }
    section{
      padding:56px 0;
    }
    .about-visual{
      min-height:280px;
    }
    .about-logo-badge{
      width:76px;
      height:76px;
    }
    .about-logo-badge img{
      width:56px;
      height:56px;
    }
    .about-pillars{
      gap:12px;
    }
    .gallery-head,
    .occasion-head,
    .review-head,
    .spaces-head{
      margin-bottom:28px;
    }
    .photo-tile,
    .photo-tile.small{
      min-height:240px;
    }
    .occasion-card{
      padding:20px;
    }
    .occasion-icon{
      margin-bottom:58px;
    }
    .space-row{
      gap:24px;
      padding-block:28px;
    }
    .space-visual{
      border-radius:70px 14px 70px 14px;
    }
    .timeline{
      margin-top:38px;
    }
    .tl-item{
      padding-left:52px;
      margin-bottom:34px;
    }
    .tl-dot{
      width:40px;
      height:40px;
	    }
	    .review-card,
	    .review-panel,
	    .review-form{
	      padding:22px;
	    }
    .review-card p.quote{
      font-size:1.18rem;
    }
  }

	  /* ---------- FOOTER ---------- */
	  .faq-section{
	    background:#fffaf0;
	    padding:72px 0;
	  }
	  .faq-head{
	    display:grid;
	    grid-template-columns:minmax(0,0.8fr) minmax(0,1fr);
	    gap:28px;
	    align-items:end;
	    margin-bottom:24px;
	  }
	  .faq-head h2{
	    color:var(--forest-deep);
	    font-size:clamp(1.8rem,3vw,2.5rem);
	  }
	  .faq-head p{
	    color:#52645c;
	    line-height:1.7;
	  }
	  .faq-grid{
	    display:grid;
	    grid-template-columns:repeat(2,minmax(0,1fr));
	    gap:14px;
	  }
	  .faq-item{
	    border:1px solid rgba(20,47,40,.12);
	    border-radius:8px;
	    background:#fff;
	    padding:20px;
	    box-shadow:0 18px 44px -38px rgba(20,47,40,.6);
	  }
	  .faq-item h3{
	    color:var(--forest);
	    font-size:1rem;
	    margin-bottom:8px;
	  }
	  .faq-item p{
	    color:#52645c;
	    line-height:1.65;
	  }
	  @media (max-width: 820px){
	    .faq-head,
	    .faq-grid{
	      grid-template-columns:1fr;
	    }
	  }

	  footer{ background:var(--forest-deep); color:rgba(228,218,195,0.72); padding:44px 0; }
  .foot-row{ display:grid; grid-template-columns:1fr 0.8fr 1fr 1fr; align-items:start; gap:24px; font-size:0.88rem; }
  .foot-brand{ display:flex; align-items:center; gap:10px; color:var(--gold-soft); }
  .foot-brand img{ height:28px; width:28px; border-radius:50%; }
  .foot-col strong{ display:block; color:var(--gold-soft); margin-bottom:8px; }
  .foot-col a{ display:block; text-decoration:none; margin-top:6px; }

  @media (max-width: 820px){
    .foot-row{ grid-template-columns:1fr 1fr; }
  }
  @media (max-width: 480px){
    .foot-row{
      grid-template-columns:1fr;
    }
  }

	  :focus-visible{ outline:2px solid var(--sunset); outline-offset:2px; }
.nav-links a:hover,.nav-links a.active{color:var(--sunset);font-weight:800}
.nav-links a.active::after{width:100%}
</style>
</head>
<body>

<?php $publicHeaderActive = 'home'; $publicHeaderHome = true; include __DIR__ . '/includes/public_header.php'; ?>

<section class="hero" id="top">
	  <div class="hero-inner">
	    <p class="hero-location">Kommadi &middot; Madhurawada &middot; Visakhapatnam</p>
	    <h1>Private Event Lawn in Madhurawada, Visakhapatnam</h1>
	    <p class="tagline">Celebrate beautifully at Anantha.</p>
	    <p class="lede">An exclusive outdoor venue in Kommadi for birthdays, engagements, Haldi, Sangeet, receptions, weddings and family celebrations &mdash; up to 300 guests.</p>
	    <div class="hero-actions">
		      <a href="availability.php" class="btn btn--gold">Check Your Date</a>
		      <a href="<?php echo h($heroWhatsappLink); ?>" class="btn btn--outline" target="_blank" rel="noopener">WhatsApp Us</a>
	    </div>
	    <p class="hero-highlights">Private Venue &bull; One Event Per Day &bull; 2 AC Rooms &bull; Mini Hall &bull; Up to 300 Guests</p>
	  </div>
  <div class="scroll-cue" aria-hidden="true"></div>
  <div id="petals"></div>
  <div id="fireflies"></div>
</section>

<section class="section-light about" id="about">
  <div class="container about-grid reveal">
    <div class="about-copy">
		      <h2>A beautiful space for meaningful celebrations</h2>
		      <div class="about-pillars">
		        <div class="pillar is-highlight"><strong>1</strong><span>Private event per day</span></div>
		        <div class="pillar"><strong>2</strong><span>AC rooms</span></div>
		        <div class="pillar"><strong>1</strong><span>Mini hall</span></div>
		        <div class="pillar"><strong>Up to 300</strong><span>Guests</span></div>
	        <div class="pillar"><strong>3,000 sq. ft.</strong><span>Spacious event lawn</span></div>
	      </div>
	    </div>
	  </div>
	</section>

<section class="gallery-band" id="gallery">
  <div class="container reveal">
	    <div class="gallery-head">
	      <div>
	        <p class="eyebrow">The grounds</p>
		        <h2>Venue gallery</h2>
		      </div>
		      <p>Open lawns, celebration setups, and rooms close by for the people who matter most.</p>
	    </div>

    <div class="venue-gallery">
      <figure class="photo-tile">
	        <img src="assets/hero-nature-event-optimized.jpg" alt="Anantha lawn venue glowing with evening event lights and tables" loading="lazy" decoding="async">
        <figcaption class="photo-caption">
	          <strong>Outdoor celebration space</strong>
        </figcaption>
      </figure>
      <div class="photo-stack">
        <figure class="photo-tile small">
	          <img src="assets/gallery-day-lawn-optimized.jpg" alt="Fresh daytime lawn and guest rooms at Anantha" loading="lazy" decoding="async">
          <figcaption class="photo-caption">
	            <strong>Day lawn view</strong>
          </figcaption>
        </figure>
        <figure class="photo-tile small">
	          <img src="assets/gallery-celebration-evening-optimized.jpg" alt="Decorated outdoor wedding and birthday celebration setup" loading="lazy" decoding="async">
          <figcaption class="photo-caption">
	            <strong>Celebration setup</strong>
          </figcaption>
        </figure>
      </div>
    </div>
  </div>
</section>

<section class="occasion-band" id="ideas">
  <div class="container reveal">
    <div class="occasion-head">
      <div>
	        <p class="eyebrow">Ideas</p>
        <h2>Bring your celebration to Anantha</h2>
      </div>
	      <p>Choose your idea and make the space your own.</p>
    </div>

	    <div class="occasion-grid">
		      <a class="occasion-card" href="reception-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-reception-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Receptions</h3></div>
	      </a>
		      <a class="occasion-card" href="engagement-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-engagement-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Engagements</h3></div>
	      </a>
		      <a class="occasion-card" href="haldi-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-haldi-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Haldi</h3></div>
	      </a>
		      <a class="occasion-card" href="mehendi-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-mehendi-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Mehendi</h3></div>
	      </a>
		      <a class="occasion-card" href="sangeet-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-sangeet-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Sangeet</h3></div>
	      </a>
		      <a class="occasion-card" href="birthday-party-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-birthday-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Birthdays</h3></div>
	      </a>
		      <a class="occasion-card" href="anniversary-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-anniversary-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Anniversaries</h3></div>
	      </a>
		      <a class="occasion-card" href="family-celebration-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-family-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Family celebrations</h3></div>
	      </a>
		      <a class="occasion-card" href="corporate-event-venue-visakhapatnam.php" style="--occasion-art:url('assets/occasion-corporate-optimized.jpg'); --occasion-position:center;">
	        <div class="occasion-copy"><h3>Corporate gatherings</h3></div>
	      </a>
	    </div>
  </div>
</section>

<section class="section-dark reviews-section" id="reviews">
  <div class="container reveal">
    <div class="review-head">
      <div>
        <h2>Stories that stayed after the lights went down</h2>
      </div>
      <p>Small moments, warm gatherings and celebrations remembered long after the day.</p>
	      <?php if ($googleReviewLink !== ''): ?><a class="btn btn--gold" href="<?php echo h($googleReviewLink); ?>" target="_blank" rel="noopener">View Google Reviews</a><?php endif; ?>
    </div>

    <div class="review-carousel" id="reviewCarousel">
      <div class="review-wrap">
        <?php if ($homeReviews): ?>
          <div class="review-track<?php echo count($homeReviews) > 1 ? ' is-moving' : ''; ?>">
            <div class="review-group">
	              <?php foreach ($homeReviews as $review): ?>
	                <?php $isGoogleReview = ($review['source'] ?? '') === 'google'; ?>
	                <article class="review-card">
	                  <p class="quote">"<?php echo h($review['review_text']); ?>"</p>
	                  <p class="who"><?php echo h($review['client_name']); ?><?php echo !empty($review['event_type']) ? ' - ' . h($review['event_type']) : ''; ?><?php if ($isGoogleReview): ?><em class="source-badge">Google Maps</em><?php endif; ?></p>
	                </article>
	              <?php endforeach; ?>
            </div>
            <?php if (count($homeReviews) > 1): ?>
              <div class="review-group" aria-hidden="true">
	                <?php foreach ($homeReviews as $review): ?>
	                  <?php $isGoogleReview = ($review['source'] ?? '') === 'google'; ?>
	                  <article class="review-card">
	                    <p class="quote">"<?php echo h($review['review_text']); ?>"</p>
	                    <p class="who"><?php echo h($review['client_name']); ?><?php echo !empty($review['event_type']) ? ' - ' . h($review['event_type']) : ''; ?><?php if ($isGoogleReview): ?><em class="source-badge">Google Maps</em><?php endif; ?></p>
	                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="review-empty">Guest stories will appear here after approval.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

	<section class="visit-map-section" id="visit">
	  <div class="visit-map-grid reveal">
    <div class="visit-copy">
      <p class="eyebrow">Finding us</p>
      <h2>Kommadi, Madhurawada<br>Visakhapatnam</h2>
      <p>Off the Kommadi junction, a short drive from Rushikonda beach and the Madhurawada IT hills. Parking on site.</p>
      <div class="visit-actions">
        <a class="btn btn--gold" href="https://maps.app.goo.gl/dug6jos7PEtAs1Qq6?g_st=ic" target="_blank" rel="noopener">Get Directions</a>
        <a class="btn btn--outline" href="tel:+917995011199">Contact</a>
      </div>
    </div>
    <div class="visit-map">
      <iframe
        src="https://maps.google.com/maps?q=Anantha%20Event%20Lawns%20%26%20Rooms%2C%20Kommadi%2C%20Madhurawada%2C%20Visakhapatnam&output=embed"
        loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Anantha Event Lawns and Rooms on Google Maps">
      </iframe>
    </div>
	  </div>
	</section>

	<section class="faq-section" id="faq">
	  <div class="container reveal">
	    <div class="faq-head">
	      <div>
	        <p class="eyebrow">Frequently asked questions</p>
	        <h2>Venue overview, timing and rules</h2>
	      </div>
	      <p>Anantha Event Lawns is a private outdoor event lawn in Kommadi, Madhurawada with one private event per day, two AC rooms, one mini hall and guest capacity up to 300.</p>
	    </div>
	    <div class="faq-grid">
	      <article class="faq-item">
	        <h3>What is included in the lawn?</h3>
	        <p>The booking includes the event lawn, mini hall support, two AC rooms, washrooms, hand wash points, flood lights and space for stage, food counters and guest seating.</p>
	      </article>
	      <article class="faq-item">
	        <h3>What are the event timings?</h3>
	        <p>Timings are confirmed during enquiry based on the event type. Mini hall use closes by 10:00 PM, and loud sound or DJ timings must follow local permission rules.</p>
	      </article>
	      <article class="faq-item">
	        <h3>Can we check date availability?</h3>
	        <p>Yes. Use Check Availability, choose your event date, and the form will show whether the date is available because only one private event is accepted per day.</p>
	      </article>
	      <article class="faq-item">
	        <h3>Are liquor and DJ allowed?</h3>
	        <p>Liquor, DJ and amplified sound are allowed only with required permissions and within legal timing limits. The client and vendors are responsible for permits and safe conduct.</p>
	      </article>
	      <article class="faq-item">
	        <h3>How many guests can attend?</h3>
	        <p>The venue supports up to 300 guests. The enquiry form asks for expected guest count so the team can suggest the right package and layout.</p>
	      </article>
	      <article class="faq-item">
	        <h3>What should we share for enquiry?</h3>
	        <p>Please share your event date, occasion, guest count, timing needs and decor or food-counter requirements. The team will confirm date availability and next steps.</p>
	      </article>
	    </div>
	  </div>
	</section>
	
	<?php include __DIR__ . '/includes/public_footer.php'; ?>

<?php include __DIR__ . '/includes/chatbot.php'; ?>

<script>
  // header shadow on scroll
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => {
    header.classList.toggle('scrolled', window.scrollY > 40);
  });

	  // mobile menu
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
	  const sectionNavLinks = [...navLinks.querySelectorAll('a[href^=\"#\"]')];
	  const setActiveNav = (activeLink) => {
	    sectionNavLinks.forEach(link => link.classList.toggle('active', link === activeLink));
	  };
	  sectionNavLinks.forEach(link => link.addEventListener('click', () => setActiveNav(link)));
	  navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenuOpen(false)));
	  const navSections = sectionNavLinks.map(link => ({ link, section: document.querySelector(link.getAttribute('href')) })).filter(item => item.section);
	  const updateActiveNav = () => {
	    const current = [...navSections].reverse().find(item => item.section.getBoundingClientRect().top <= 150) || navSections[0];
	    if (current) setActiveNav(current.link);
	  };
	  window.addEventListener('scroll', updateActiveNav, { passive: true });
	  updateActiveNav();
	  window.addEventListener('keydown', (event) => {
	    if (event.key === 'Escape') setMenuOpen(false);
	  });

	  // scroll reveal for section blocks
	  const revealEls = document.querySelectorAll('.reveal');
	  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        io.unobserve(entry.target);
      }
    });
	  }, { threshold: 0.15 });
	  revealEls.forEach(el => io.observe(el));

	  // drifting petals in hero
  const petalHost = document.getElementById('petals');
  const petalCount = window.innerWidth < 700 ? 6 : 12;
  for (let i = 0; i < petalCount; i++) {
    const p = document.createElement('div');
    p.className = 'petal';
    p.style.left = Math.random() * 100 + '%';
    p.style.animationDuration = (14 + Math.random() * 10) + 's';
    p.style.animationDelay = (Math.random() * 12) + 's';
    p.style.width = p.style.height = (8 + Math.random() * 10) + 'px';
    petalHost.appendChild(p);
  }

  // fireflies for a soft nature-at-night feel
  const fireflyHost = document.getElementById('fireflies');
  const fireflyCount = window.innerWidth < 700 ? 10 : 22;
  for (let i = 0; i < fireflyCount; i++) {
    const f = document.createElement('span');
    f.className = 'firefly';
    f.style.left = (8 + Math.random() * 84) + '%';
    f.style.top = (32 + Math.random() * 48) + '%';
    f.style.animationDuration = (4 + Math.random() * 4) + 's';
    f.style.animationDelay = (Math.random() * 5) + 's';
    fireflyHost.appendChild(f);
  }

</script>

</body>
</html>






