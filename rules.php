<?php
require_once __DIR__ . '/includes/config.php';

$ruleGroups = [
    'Venue & property' => [
        ['Check-in & check-out', 'Check-in: 11:00 AM | Check-out: 10:00 AM the following day. The venue and all vendor materials must be cleared by checkout. Late checkout: Rs. 5,000 per hour or part thereof, subject to availability.'],
        ['Rooms', 'Package includes fully equipped air-conditioned rooms. Two persons per room are permitted for overnight stay. Additional persons may be accommodated with prior intimation to management.'],
        ['Mini hall', 'The Mini Hall is strictly an event-support space. It is not meant for guest freshening/changing during the afternoon or at any other time, and is not for sleeping or overnight stay. Mini Hall closes at 10:00 PM.'],
        ['Guest washrooms', 'Private room washrooms are reserved for the host and authorised room occupants. All other guests must use the public washrooms provided by the venue.'],
        ['Protect the lawn', 'Cars, bikes, vehicles, generators, burners, tandoors, BBQs, hot vessels, hot catering equipment, heavy machinery and heat-producing equipment are strictly prohibited on the lawn. All such equipment must remain in designated service areas.'],
        ['Electricity & generator', 'Included: fully equipped AC rooms, basic property floodlights and existing landscape lights. A separate generator arranged by the client is required for decorative/event lighting, DJ or sound systems, tower fans, tower ACs, mist fans and other temporary/high-load event equipment.'],
        ['Security deposit & damages', 'The host is responsible for damage caused by the host, guests, decorators, caterers or any appointed vendor. Repair/restoration costs will be deducted from the security deposit. Any excess shall be payable by the host.'],
    ],
    'Event, vendor & safety' => [
        ['Decorators & vendors', 'Outside vendors are welcome. Setup can normally begin from check-in; earlier setup may be allowed with prior coordination and availability. All vendor materials must be cleared before checkout. No drilling, nailing, cutting or permanent fixing without approval.'],
        ['Catering & fire safety', 'Cooking, LPG cylinders, burners, tandoors and hot equipment are permitted only in designated catering/service areas. Fireworks, crackers, cold pyros or flame effects require prior venue approval and applicable permissions.'],
        ['DJ, music & sound', 'Outdoor DJ / amplified music must stop by 10:00 PM. Required loudspeaker/DJ permissions are the host\'s responsibility. Management may reduce or stop sound whenever required by competent authorities or applicable law.'],
        ['Alcohol', 'Alcohol may be served only with the applicable permit/licence from the competent Andhra Pradesh authorities. Without the required permit, alcohol service will not be permitted.'],
        ['Pets', 'Pets are welcome. Owners must supervise them and clean and properly dispose of pet waste immediately.'],
        ['Parking, clean-up & safety', 'Vehicles must use designated parking/loading areas and are not permitted on the lawn. Decorators and caterers must remove event material and waste before checkout. Confetti, thermocol, glitter or difficult-to-clean materials require prior approval.'],
    ],
];

$bookingTerms = [
    ['Capacity', 'A maximum of 300 guests. One event is held per day, so the grounds are never shared.'],
    ['Cleaning', 'A cleaning charge applies, calculated on the guest count recorded in your booking, with a minimum charge of Rs. 500 for gatherings of up to 50 guests.'],
    ['Security deposit', 'A refundable deposit of Rs. 10,000 applies to every booking. It is refunded within 48 hours of the event, subject to inspection and confirmation that there are no damages or recoverable charges.'],
    ['Advance and hold', 'A requested date is held for 48 hours. Confirmation follows an advance of 50% of the booking amount.'],
    ['Cancellation and rescheduling', 'One reschedule is permitted, subject to availability. Refunds on the advance depend on when you cancel:'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Venue Rules | <?php echo h(SITE_NAME); ?></title>
<meta name="description" content="House terms, venue rules and booking terms for Anantha Event Lawns in Kommadi, Madhurawada, Visakhapatnam.">
<link rel="icon" href="<?php echo h(app_url('assets/logo.jpeg', '')); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--deep:#142f28;--forest:#20483f;--leaf:#2e6b58;--gold:#d9a050;--sun:#f1cf8f;--paper:#f8f3e8;--white:#fffdf8;--ink:#1b2a24;--muted:#63736c;--line:rgba(20,47,40,.14);--cream:#e4dac3;--sky:#e8f2ee;--rose:#f4ded7;--shadow:0 24px 60px -34px rgba(20,47,40,.58)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:'Inter',sans-serif;background:var(--paper);color:var(--ink);overflow-x:hidden;-webkit-font-smoothing:antialiased}img{max-width:100%;display:block}a{text-decoration:none;color:inherit}.container,.wrap{width:min(1180px,calc(100% - 36px));margin:auto}
header{position:fixed;top:0;left:0;right:0;z-index:100;padding:18px 0;transition:background .4s ease,padding .4s ease,box-shadow .4s ease}header.scrolled{background:rgba(23,51,43,.92);backdrop-filter:blur(10px);padding:12px 0;box-shadow:0 10px 30px -20px rgba(0,0,0,.4)}.nav{display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:10px}.brand img{height:44px;width:44px;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px rgba(228,218,195,.28)}.brand span{font-family:'Inter',sans-serif;letter-spacing:.04em;font-size:1.08rem;color:var(--cream)}.nav-links{display:flex;gap:24px;list-style:none;margin:0;padding:0}.nav-links a{color:var(--cream);font-size:.88rem;font-weight:500;position:relative;padding-bottom:4px}.nav-links a:after{content:"";position:absolute;left:0;bottom:0;width:0;height:1px;background:var(--gold);transition:width .3s ease}.nav-links a:hover:after,.nav-links a.active:after{width:100%}.menu-toggle{display:none;width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(23,51,43,.28);color:var(--cream);cursor:pointer;place-items:center}.menu-toggle span,.menu-toggle span:before,.menu-toggle span:after{display:block;width:19px;height:2px;border-radius:2px;background:currentColor;content:"";transition:transform .3s ease,opacity .3s ease}.menu-toggle span:before{transform:translateY(-7px)}.menu-toggle span:after{transform:translateY(5px)}.menu-toggle.is-open span{background:transparent}.menu-toggle.is-open span:before{transform:translateY(2px) rotate(45deg)}.menu-toggle.is-open span:after{transform:translateY(0) rotate(-45deg)}.menu-close-row{display:none}.menu-close{width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(255,255,255,.06);color:var(--cream);font-size:1.7rem;line-height:1;cursor:pointer}.nav-backdrop{position:fixed;inset:0;z-index:99;background:rgba(8,28,23,.54);opacity:0;pointer-events:none;transition:opacity .3s ease}.nav-backdrop.open{opacity:1;pointer-events:auto}
.btn{border:0;border-radius:999px;background:var(--gold);color:#261b10;padding:12px 20px;text-decoration:none;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:46px}.btn.light{background:rgba(255,255,255,.13);color:#fff;border:1px solid rgba(255,255,255,.28)}.btn.dark{background:var(--deep);color:#fff}.eyebrow{font-size:.78rem;letter-spacing:.13em;text-transform:uppercase;color:#9a6728;font-weight:800}
.hero{position:relative;isolation:isolate;padding:142px 0 42px;background:var(--deep);color:#fff;overflow:hidden}.hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(20,47,40,.96),rgba(20,47,40,.68) 56%,rgba(20,47,40,.48)),url('<?php echo h(app_url('assets/gallery-celebration-evening-optimized.jpg')); ?>') center/cover no-repeat;z-index:-2}.hero:after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:96px;background:linear-gradient(180deg,transparent,var(--paper));z-index:-1}.hero-layout{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.72fr);gap:42px;align-items:end}.hero h1,.rule-section h2,.terms h2,.snapshot h2{font-family:'Inter',sans-serif}.hero h1{font-size:clamp(2.65rem,6vw,5.45rem);line-height:.98;margin:10px 0 16px;letter-spacing:0}.hero p{max-width:760px;color:rgba(255,255,255,.9);font-size:1.08rem;line-height:1.7}.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}.hero-panel{background:rgba(255,253,248,.94);color:var(--ink);border:1px solid rgba(255,255,255,.44);border-radius:8px;padding:22px;box-shadow:0 28px 70px -38px rgba(0,0,0,.72)}.hero-panel h2{font-family:'Inter',sans-serif;margin:0 0 16px;color:var(--deep);font-size:1.45rem}.priority-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}.priority-list li{display:grid;grid-template-columns:38px 1fr;gap:12px;align-items:start;padding:12px;border-radius:8px;background:var(--sky);border:1px solid rgba(32,72,63,.1)}.priority-icon{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:var(--deep);color:var(--sun);font-weight:800}.priority-list strong{display:block;color:var(--deep);font-size:.96rem}.priority-list span{display:block;margin-top:2px;color:#52645c;font-size:.9rem;line-height:1.45}
.snapshot{padding:0 0 46px;margin-top:-16px;position:relative;z-index:2}.snapshot-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.snapshot-card{background:var(--white);border:1px solid var(--line);border-radius:8px;padding:18px;box-shadow:var(--shadow)}.snapshot-card strong{display:block;font-family:'Inter',sans-serif;color:var(--deep);font-size:1.55rem;line-height:1}.snapshot-card span{display:block;margin-top:8px;color:var(--muted);font-size:.92rem;line-height:1.45}
.page-nav{position:sticky;top:78px;z-index:20;background:rgba(248,243,232,.92);backdrop-filter:blur(12px);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.page-nav .wrap{display:flex;gap:10px;overflow-x:auto;padding:10px 0}.page-nav a{white-space:nowrap;border:1px solid var(--line);background:var(--white);color:var(--deep);border-radius:999px;padding:9px 14px;font-weight:800;font-size:.88rem}.page-nav a:hover{background:var(--deep);color:#fff}
.rule-section{padding:56px 0}.rule-section:nth-of-type(odd){background:var(--sky)}.section-head{display:flex;justify-content:space-between;gap:24px;align-items:end;margin-bottom:22px}.section-head h2{font-size:clamp(1.9rem,3vw,2.7rem);color:var(--deep);margin:0}.section-head p{max-width:560px;margin:0;color:var(--muted);line-height:1.65}.rule-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:start}
.rule-card{min-width:0;background:var(--white);border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 32px -28px rgba(20,47,40,.45);transition:border-color .2s ease,box-shadow .2s ease}
.rule-card summary{display:grid;grid-template-columns:34px minmax(0,1fr) 12px;gap:13px;align-items:center;list-style:none;padding:17px 18px;cursor:pointer;border-radius:12px}
.rule-card summary::-webkit-details-marker{display:none}
.rule-card summary::after{content:'';width:8px;height:8px;border-right:2px solid #94703b;border-bottom:2px solid #94703b;transform:rotate(45deg);margin-top:-4px;transition:transform .2s ease}
.rule-card summary .num{width:34px;height:34px;border-radius:10px;background:#f6f0e3;color:#85612e;display:grid;place-items:center;font-size:12px;font-weight:700;letter-spacing:.04em}
.rule-card h3{font-family:'Inter',sans-serif;color:var(--deep);font-size:1rem;font-weight:600;line-height:1.5;margin:0}
.rule-card p{margin:0 18px 18px 65px;padding-top:14px;border-top:1px solid #ece8dd;color:#52645c;font-size:.94rem;line-height:1.75}
.rule-card summary:hover{background:#fbf8f1}
.rule-card summary:focus-visible{outline:3px solid #d9a24b;outline-offset:3px}
.rule-card[open]{border-color:#c9b68f;box-shadow:0 14px 30px -24px rgba(20,47,40,.35)}
.rule-card[open] summary::after{transform:rotate(225deg);margin-top:4px}
@media(max-width:540px){.rule-card summary{padding:15px 14px;gap:10px}.rule-card p{margin-left:14px;margin-right:14px}}
@media(prefers-reduced-motion:reduce){.rule-card,.rule-card summary::after{transition:none}}
.terms{background:var(--deep);color:#fff;padding:76px 0}.terms .eyebrow{color:var(--sun)}.terms h2{font-size:clamp(2rem,4vw,3.3rem);line-height:1;margin:8px 0 14px;color:#fff}.terms-intro{max-width:720px;color:rgba(228,218,195,.82);line-height:1.7;margin:0 0 24px}.term-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.term-card{background:rgba(255,255,255,.08);border:1px solid rgba(228,218,195,.2);border-radius:8px;padding:22px}.term-card h3{font-family:'Inter',sans-serif;color:var(--sun);margin:0 0 8px}.term-card p{margin:0;color:rgba(228,218,195,.8);line-height:1.65}.remember{margin-top:18px;padding:24px;border-radius:8px;background:var(--gold);color:#261b10;font-weight:800;display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}.remember .btn.light{background:#261b10;color:#fff;border-color:#261b10}
footer{background:#10241f;color:var(--cream);padding:42px 0}.foot-row{display:grid;grid-template-columns:1.2fr repeat(3,1fr);gap:24px}.foot-brand{display:flex;align-items:center;gap:10px;font-family:'Inter',sans-serif}.foot-brand img{width:44px;height:44px;border-radius:50%;object-fit:cover}.foot-col a,.foot-col p{display:block;margin-top:8px;color:rgba(228,218,195,.74);line-height:1.6}
@media(max-width:980px){.hero-layout{grid-template-columns:1fr}.snapshot-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rule-grid,.term-grid,.foot-row{grid-template-columns:1fr}.section-head{display:block}.section-head p{margin-top:10px}}@media(max-width:860px){.nav-links{position:fixed;top:0;right:0;z-index:101;height:100vh;width:min(320px,86vw);background:#17332b;flex-direction:column;padding:100px 32px 32px;transform:translateX(100%);transition:transform .4s ease;gap:22px;box-shadow:-24px 0 50px -28px rgba(0,0,0,.65)}.nav-links.open{transform:translateX(0)}.menu-toggle{display:grid}.menu-close-row{display:flex;justify-content:flex-end;position:absolute;top:22px;right:24px}body.menu-open{overflow:hidden}.page-nav{top:68px}}@media(max-width:540px){.container,.wrap{width:min(100% - 26px,1180px)}.hero{padding-top:120px}.hero-actions .btn{width:100%}.hero-panel{padding:16px}.priority-list li{grid-template-columns:34px 1fr}.priority-icon{width:34px;height:34px}.snapshot-grid{grid-template-columns:1fr}.rule-section{padding:42px 0}.terms{padding:58px 0}}
.priority-list > li > .priority-icon{display:flex;align-items:center;justify-content:center;margin:0;color:var(--sun);font-size:0;line-height:1}.priority-icon svg{display:block;width:21px;height:21px;overflow:visible;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;fill:none}.priority-list > li > .priority-copy{display:block;margin-top:2px;color:#52645c;font-size:.9rem;line-height:1.45}
.nav-links a:hover,.nav-links a.active{color:var(--gold);font-weight:800}
/* Booking overview and terms */
.rule-summary{background:#edf4ee;padding:24px 0 48px}.rule-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.rule-summary-card{border-radius:18px;padding:22px 24px;font-family:'Plus Jakarta Sans',sans-serif}.rule-summary-card.welcome{background:#e1f2e5;color:#163e2b}.rule-summary-card.not-allowed{background:#fce7e2;color:#70201c}.rule-summary-card h2{display:flex;align-items:center;gap:12px;font:600 27px/1.2 'Cormorant Garamond',Georgia,serif;margin:0 0 10px}.rule-summary-card ul{margin:0;padding-left:20px;font-size:14px;line-height:2}.rule-summary-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 24px;width:24px;height:24px;font:700 19px/1 sans-serif;color:#fff}.welcome .rule-summary-icon{background:#4dcc85;border:1px solid #37b770;border-radius:3px}.not-allowed .rule-summary-icon{background:#ff4e79;border-radius:50%}
.terms{background:#102e22;padding:62px 0 34px;font-family:'Plus Jakarta Sans',sans-serif}.terms .terms-heading{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(220px,1fr);gap:34px;align-items:end;margin-bottom:28px}.terms .eyebrow{color:#dda246;font-size:11px;letter-spacing:.25em}.terms h2{font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(2rem,3.6vw,3rem);font-weight:600;line-height:1.15;color:#f5e9c9;margin:8px 0 0}.terms .terms-intro{font-size:14px;line-height:1.65;color:#d0ddce;margin:0;max-width:360px}.terms .term-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.terms .term-card{background:rgba(255,255,255,.055);border:1px solid rgba(228,218,195,.22);border-radius:16px;padding:22px;box-shadow:none}.terms .term-card:last-child{grid-column:span 2}.terms .term-card h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;color:#e1a442;margin:0 0 9px}.terms .term-card p{color:#e0e7db;font-size:14px;line-height:1.85;margin:0}.refund-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:16px}.refund-card{background:rgba(255,255,255,.07);border-radius:14px;text-align:center;padding:20px 14px}.refund-card strong{display:block;font:700 30px/1.2 'Cormorant Garamond',Georgia,serif;color:#e1a442;margin-bottom:8px}.refund-card span{font-size:12px;line-height:1.6;color:#e0e7db}.terms .remember{display:block;margin-top:22px;padding:24px;border-radius:16px;background:#dda246;color:#201807;font-size:14px;font-weight:600;line-height:1.7}.terms .remember .btn.light{display:inline-flex;width:auto;margin-top:16px;padding:10px 22px;min-height:44px;background:#171409;border-color:#171409;font-size:12px}.terms .remember span{display:block}
@media(max-width:980px) and (min-width:761px){.terms .terms-heading{grid-template-columns:1fr}.terms .terms-intro{max-width:620px}.terms .term-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.terms .term-card:last-child{grid-column:1/-1}}
@media(max-width:760px){.rule-summary{padding:24px 0 32px}.rule-summary-grid{grid-template-columns:1fr;gap:14px}.rule-summary-card{padding:20px}.rule-summary-card ul{font-size:13px}.terms{padding:44px 0 32px}.terms .terms-heading{grid-template-columns:1fr;gap:16px;margin-bottom:22px}.terms .term-card:last-child{grid-column:auto}.terms .term-card{padding:20px}.refund-grid{grid-template-columns:1fr;gap:10px}.refund-card{padding:16px}.terms .remember{padding:20px}.terms .terms-intro{max-width:none}}
</style>
</head>
<body>
<?php $publicHeaderActive = 'rules'; $publicHeaderHome = false; include __DIR__ . '/includes/public_header.php'; ?>

<main>
  <section class="hero">
    <div class="wrap hero-layout">
      <div>
        <div class="eyebrow">House terms</div>
        <h1>Venue rules made simple</h1>
        <p>Clear expectations for hosts, guests and vendors, so your celebration stays relaxed and the venue remains ready for the next family.</p>
        <div class="hero-actions">
          <a class="btn" href="<?php echo h(app_url('availability.php', '')); ?>">Check Availability</a>
          <a class="btn light" href="#booking-terms">View Booking Terms</a>
        </div>
      </div>
      <aside class="hero-panel" aria-label="Important venue rules">
        <h2>Must know before booking</h2>
        <ul class="priority-list">
          <li><span class="priority-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M11 5 6 9H3v6h3l5 4zM22 9l-6 6M16 9l6 6"></path></svg></span><span class="priority-copy"><strong>10:00 PM sound limit</strong>Outdoor DJ and amplified music must stop on time.</span></li>
          <li><span class="priority-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="m5.6 5.6 12.8 12.8M9 16h6M10 13h4M11 10h2"></path></svg></span><span class="priority-copy"><strong>No vehicles or hot equipment on lawn</strong>Keep burners, generators and heavy items in service areas.</span></li>
          <li><span class="priority-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18M8 15l2 2 5-5"></path></svg></span><span class="priority-copy"><strong>One event per day</strong>The venue is private for your booking, with checkout by 10:00 AM next day.</span></li>
        </ul>
      </aside>
    </div>
  </section>

  <section class="snapshot" aria-label="Venue rule summary">
    <div class="wrap snapshot-grid">
      <article class="snapshot-card"><strong>300</strong><span>maximum guest capacity</span></article>
      <article class="snapshot-card"><strong>11:00 AM</strong><span>standard check-in time</span></article>
      <article class="snapshot-card"><strong>10:00 AM</strong><span>checkout the next day</span></article>
      <article class="snapshot-card"><strong>Rs. 10,000</strong><span>refundable security deposit</span></article>
    </div>
  </section>

  <nav class="page-nav" aria-label="Rules page sections">
    <div class="wrap">
      <a href="#venue-property">Venue &amp; property</a>
      <a href="#event-vendor-safety">Event, vendor &amp; safety</a>
      <a href="#booking-terms">Booking terms</a>
    </div>
  </nav>

  <?php $ruleNumber = 1; ?>
  <?php foreach ($ruleGroups as $groupTitle => $rules): ?>
    <?php
      $sectionId = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $groupTitle));
      $sectionId = trim($sectionId, '-');
      $groupIntro = $groupTitle === 'Venue & property'
          ? 'Timings, rooms, washrooms, lawn care and electricity responsibilities at a glance.'
          : 'Vendor setup, music, alcohol, pets, clean-up and safety expectations for event day.';
    ?>
    <section class="rule-section" id="<?php echo h($sectionId); ?>">
      <div class="wrap">
        <div class="section-head">
          <div>
            <div class="eyebrow">Rules <?php echo $groupTitle === 'Venue & property' ? '01' : '02'; ?></div>
            <h2><?php echo h($groupTitle); ?></h2>
          </div>
          <p><?php echo h($groupIntro); ?></p>
        </div>
        <div class="rule-grid">
          <?php foreach ($rules as $rule): ?>
            <details class="rule-card" name="rules-<?php echo h($sectionId); ?>">
              <summary>
                <span class="num" aria-hidden="true"><?php echo str_pad((string) $ruleNumber++, 2, '0', STR_PAD_LEFT); ?></span>
                <h3><?php echo h($rule[0]); ?></h3>
              </summary>
              <p><?php echo h($rule[1]); ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="rule-summary" aria-label="Welcome and not allowed at the venue">
    <div class="wrap rule-summary-grid">
      <article class="rule-summary-card welcome">
        <h2><span class="rule-summary-icon" aria-hidden="true">✓</span>Welcome</h2>
        <ul>
          <li>Outside decorators and caterers</li>
          <li>Pets, supervised by their owners</li>
          <li>Setup from check-in time</li>
          <li>Licensed alcohol service</li>
        </ul>
      </article>
      <article class="rule-summary-card not-allowed">
        <h2><span class="rule-summary-icon" aria-hidden="true">−</span>Not allowed</h2>
        <ul>
          <li>Vehicles, burners, tandoors or generators on the lawn</li>
          <li>Drilling, nailing or cutting without approval</li>
          <li>Outdoor DJ past 10:00 PM</li>
          <li>Fireworks or flame effects without approval</li>
        </ul>
      </article>
    </div>
  </section>

  <section class="terms" id="booking-terms">
    <div class="wrap">
      <div class="terms-heading">
        <div>
          <div class="eyebrow">Booking terms</div>
          <h2>Confirm with clear expectations</h2>
        </div>
        <p class="terms-intro">The practical booking points most families ask about before confirming a date.</p>
      </div>
      <div class="term-grid">
        <?php foreach ($bookingTerms as $term): ?>
          <article class="term-card">
            <h3><?php echo h($term[0]); ?></h3>
            <p><?php echo h($term[1]); ?></p>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="refund-grid" aria-label="Cancellation refunds on the advance">
        <div class="refund-card"><strong>100%</strong><span>Refund, more than 30 days before the event</span></div>
        <div class="refund-card"><strong>50%</strong><span>Refund, between 30 and 15 days</span></div>
        <div class="refund-card"><strong>0%</strong><span>Refund, within 15 days of the event</span></div>
      </div>
      <div class="remember">
        <span>Please remember: enjoy the celebration, respect the property, follow the timings, and leave it ready for the next celebration.</span>
        <a class="btn light" href="<?php echo h(app_url('availability.php', '')); ?>">Check Availability</a>
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
