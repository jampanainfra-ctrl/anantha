<style>
#siteFooter{background:#10241f;color:#e4dac3;padding:42px 0 0;font-family:'Inter',sans-serif}
#siteFooter a{text-decoration:none}
#siteFooter .public-footer__inner{width:min(1180px,calc(100% - 36px));margin:auto}
#siteFooter .public-footer__row{display:grid;grid-template-columns:1.2fr repeat(3,1fr);gap:24px;align-items:start}
#siteFooter .public-footer__brand{display:flex;align-items:center;gap:10px;color:#f1cf8f;font-weight:800}
#siteFooter .public-footer__brand span{font-family:'Instrument Serif',Georgia,serif;font-size:1.65rem;font-weight:400;letter-spacing:0}
#siteFooter .public-footer__brand img{width:44px;height:44px;border-radius:50%;object-fit:cover}
#siteFooter .public-footer__col strong{display:block;color:#f1cf8f;margin-bottom:8px}
#siteFooter .public-footer__col a,#siteFooter .public-footer__text{display:block;margin:8px 0 0;color:rgba(228,218,195,.76);line-height:1.6}
#siteFooter .public-footer__facts{display:grid;gap:7px;margin-top:10px;color:rgba(228,218,195,.76);line-height:1.5}
#siteFooter .public-footer__facts span{display:flex;align-items:center;gap:8px}
#siteFooter .public-footer__facts span:before{content:"";width:6px;height:6px;border-radius:50%;background:#f1cf8f}
#siteFooter .public-footer__col a:hover{color:#f1cf8f}
#siteFooter .public-footer__credit{margin-top:34px;border-top:1px solid rgba(228,218,195,.14);padding:18px 0;text-align:center;color:rgba(228,218,195,.68);font-size:.86rem}
#siteFooter .public-footer__signature{display:inline-flex;align-items:center;gap:11px;flex-wrap:wrap;justify-content:center}
#siteFooter .public-footer__signature-label{letter-spacing:.14em;text-transform:uppercase;font-size:.72rem;font-weight:800;color:rgba(228,218,195,.58)}
#siteFooter .public-footer__studio{display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(241,207,143,.34);border-radius:999px;padding:7px 13px 7px 8px;background:linear-gradient(135deg,rgba(241,207,143,.14),rgba(255,255,255,.035));color:#f1cf8f;font-weight:800;letter-spacing:.02em;box-shadow:0 10px 28px -24px rgba(241,207,143,.72);transition:background .2s ease,color .2s ease,border-color .2s ease,transform .2s ease,box-shadow .2s ease}
#siteFooter .public-footer__studio-logo{width:30px;height:30px;border-radius:50%;object-fit:cover;background:#071018;box-shadow:0 0 0 2px rgba(241,207,143,.28)}
#siteFooter .public-footer__studio:after{content:"";width:7px;height:7px;border-top:2px solid currentColor;border-right:2px solid currentColor;transform:rotate(45deg);transition:transform .2s ease}
#siteFooter .public-footer__studio:hover{background:#f1cf8f;color:#10241f;border-color:#f1cf8f;transform:translateY(-2px);box-shadow:0 14px 34px -22px rgba(241,207,143,.95)}
#siteFooter .public-footer__studio:hover:after{transform:translateX(2px) rotate(45deg)}
@media(max-width:920px){#siteFooter .public-footer__row{grid-template-columns:1fr}}
@media(max-width:540px){#siteFooter .public-footer__inner{width:min(100% - 26px,1180px)}}
</style>
<footer id="siteFooter">
  <div class="public-footer__inner">
    <div class="public-footer__row">
      <div class="public-footer__col">
        <div class="public-footer__brand"><img src="assets/logo.jpeg" alt="Anantha logo" loading="lazy" decoding="async"><span>Anantha Event Lawns</span></div>
        <span class="public-footer__text">DRK Raju Township, Plot 61, Kommadi Rd, Gandhi Nagar, Madhurawada, Madhuravada, Andhra Pradesh 530045, India</span>
      </div>
      <div class="public-footer__col">
        <strong>Visit</strong>
        <a href="index.php#top">Home</a>
        <a href="gallery.php">Gallery</a>
        <a href="blogs.php">Blogs</a>
        <a href="rules.php">Venue rules</a>
        <a href="index.php#visit">Contact</a>
      </div>
      <div class="public-footer__col">
        <strong>Reach us</strong>
        <a href="tel:+917995011199">+91 79950 11199</a>
        <a href="mailto:<?php echo h(CONTACT_EMAIL); ?>"><?php echo h(CONTACT_EMAIL); ?></a>
        <a href="https://instagram.com/ananthaeventlawns" target="_blank" rel="noopener">@ananthaeventlawns</a>
      </div>
      <div class="public-footer__col">
        <strong>Ceremony</strong>
        <div class="public-footer__facts">
          <span>One event per day</span>
          <span>Up to 300 guests</span>
          <span>Private quotation</span>
        </div>
        <span class="public-footer__text">&copy; <?php echo date('Y'); ?> Anantha Event Lawns</span>
      </div>
    </div>
    <div class="public-footer__credit">
      <div class="public-footer__signature">
        <span class="public-footer__signature-label">Digital presence engineered by</span>
        <a class="public-footer__studio" href="https://manacoding.online" target="_blank" rel="noopener"><img class="public-footer__studio-logo" src="assets/manacoding-logo.jpg" alt="ManaCoding logo" loading="lazy" decoding="async"><span>ManaCoding</span></a>
      </div>
    </div>
  </div>
</footer>
