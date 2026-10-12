<?php
$publicHeaderActive = $publicHeaderActive ?? '';
$publicHeaderHome = $publicHeaderHome ?? false;
$homeHref = $publicHeaderHome ? '#top' : 'index.php#top';
$aboutHref = $publicHeaderHome ? '#about' : 'index.php#about';
$occasionsHref = $publicHeaderHome ? '#ideas' : 'index.php#ideas';
$contactHref = $publicHeaderHome ? '#visit' : 'index.php#visit';
$availabilityHref = $publicHeaderActive === 'availability' ? '#enquiry' : 'availability.php';
$navClass = static function (string $key) use ($publicHeaderActive): string {
    return $publicHeaderActive === $key ? ' class="active" aria-current="page"' : '';
};
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700;800;900&display=swap');
#siteHeader{position:fixed;top:0;left:0;right:0;z-index:100;padding:18px 0;transition:background .4s ease,padding .4s ease,box-shadow .4s ease}
#siteHeader.scrolled{background:rgba(23,51,43,.92);backdrop-filter:blur(10px);padding:12px 0;box-shadow:0 10px 30px -20px rgba(0,0,0,.4)}
#siteHeader .nav.container{display:flex;align-items:center;justify-content:space-between;gap:32px;width:100%;max-width:none;padding-left:clamp(20px,3.2vw,60px);padding-right:clamp(20px,2.8vw,52px)}
#siteHeader .brand{display:flex;flex-direction:column;align-items:flex-start;flex:0 0 auto;text-decoration:none;text-transform:uppercase;line-height:1}
#siteHeader .brand-name{color:#d9b878;font-family:Georgia,'Times New Roman',serif;font-size:1.22rem;letter-spacing:.22em;white-space:nowrap}
#siteHeader .brand-subtitle{margin-top:2px;color:rgba(228,218,195,.68);font-size:.68rem;font-weight:700;letter-spacing:.32em;white-space:nowrap}
#siteHeader .nav-links{display:flex;align-items:center;gap:24px;list-style:none;margin:0;padding:0}
#siteHeader .nav-links a{color:#e4dac3;font-size:.88rem;font-weight:500;position:relative;padding-bottom:4px;text-decoration:none;transition:color .2s ease}
#siteHeader .nav-links a:after{content:"";position:absolute;left:0;bottom:0;width:0;height:2px;background:#d9a050;transition:width .3s ease}
#siteHeader .nav-links a:hover,#siteHeader .nav-links a.active{color:#d9a050;font-weight:800}
#siteHeader .nav-links a:hover:after,#siteHeader .nav-links a.active:after{width:100%}
#siteHeader .nav-links .nav-cta{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border:1px solid #d9a050;border-radius:4px;padding:9px 16px;background:#d9a050;color:#261b10;font-weight:800;white-space:nowrap}
#siteHeader .nav-links .nav-cta:after{display:none}
#siteHeader .nav-links .nav-cta:hover,#siteHeader .nav-links .nav-cta.active{background:#f1cf8f;color:#261b10}
#siteHeader .mobile-nav-cta{display:none;align-items:center;justify-content:center;min-height:40px;border:1px solid #d9a050;border-radius:8px;padding:8px 12px;background:#d9a050;color:#261b10;font-size:.82rem;font-weight:900;line-height:1;text-decoration:none;white-space:nowrap;box-shadow:0 10px 24px -18px rgba(0,0,0,.8)}
#siteHeader .menu-toggle{display:none;width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(23,51,43,.28);color:#e4dac3;cursor:pointer;place-items:center}
#siteHeader .menu-toggle span,#siteHeader .menu-toggle span:before,#siteHeader .menu-toggle span:after{display:block;width:19px;height:2px;border-radius:2px;background:currentColor;content:"";transition:transform .3s ease,opacity .3s ease}
#siteHeader .menu-toggle span:before{transform:translateY(-7px)}#siteHeader .menu-toggle span:after{transform:translateY(5px)}#siteHeader .menu-toggle.is-open span{background:transparent}#siteHeader .menu-toggle.is-open span:before{transform:translateY(2px) rotate(45deg)}#siteHeader .menu-toggle.is-open span:after{transform:translateY(0) rotate(-45deg)}
#siteHeader .menu-close-row{display:none}#siteHeader .menu-close{width:44px;height:44px;border-radius:50%;border:1px solid rgba(228,218,195,.32);background:rgba(255,255,255,.06);color:#e4dac3;font-size:1.7rem;line-height:1;cursor:pointer}
.nav-backdrop{position:fixed;inset:0;z-index:99;background:rgba(8,28,23,.54);opacity:0;pointer-events:none;transition:opacity .3s ease}.nav-backdrop.open{opacity:1;pointer-events:auto}
@media(max-width:860px){#siteHeader .nav.container{justify-content:space-between;gap:18px}#siteHeader .brand-name{font-size:1rem}#siteHeader .brand-subtitle{font-size:.59rem}#siteHeader .nav-links{position:fixed;top:0;right:0;z-index:101;height:100vh;width:min(320px,86vw);background:#17332b;flex-direction:column;align-items:stretch;padding:100px 32px 32px;transform:translateX(100%);transition:transform .4s ease;gap:22px;box-shadow:-24px 0 50px -28px rgba(0,0,0,.65)}#siteHeader .nav-links.open{transform:translateX(0)}#siteHeader .menu-toggle{display:grid}#siteHeader .menu-close-row{display:flex;justify-content:flex-end;position:absolute;top:22px;right:24px}#siteHeader .nav-links .nav-cta{text-align:center}body.menu-open{overflow:hidden}}
html,body{max-width:100%;overflow-x:hidden}img,video,iframe{max-width:100%}input,select,textarea,button{font-size:16px}.btn,a.btn,button.btn{min-height:44px;text-align:center}.container,.wrap,.screen{max-width:100%}h1,h2,h3,p,a,strong,span,li,label,summary{overflow-wrap:anywhere}.page-nav .wrap,.tabs,.tab-buttons{scrollbar-width:thin}
@media(max-width:760px){
  #siteHeader{padding:12px 0}
  #siteHeader.scrolled{padding:10px 0}
  #siteHeader .nav.container{padding-left:16px;padding-right:16px}
  #siteHeader .brand-name{font-size:.92rem;letter-spacing:.18em}
  #siteHeader .brand-subtitle{font-size:.54rem;letter-spacing:.24em}
  #siteHeader .mobile-nav-cta{display:inline-flex;margin-left:auto}
  #siteHeader .menu-toggle{width:42px;height:42px;flex:0 0 42px}
  #siteHeader .nav-links{width:min(360px,100vw);height:100dvh;overflow-y:auto;padding:88px 24px 28px}
  #siteHeader .nav-links a{display:flex;align-items:center;min-height:44px;font-size:1rem}
  #siteHeader .nav-links .nav-cta{width:100%;border-radius:8px}
  .container,.wrap,.screen,.hero-inner{width:min(100% - 26px,1180px)!important}
  section,.content-section,.spaces,.terms,.faq,.packages,.occasion-strip,.blog-list,.rule-section{padding-top:52px;padding-bottom:52px}
  .hero,.seo-hero{min-height:auto!important;padding-top:116px!important;padding-bottom:52px!important}
  .hero h1,.seo-hero h1{font-size:clamp(2.15rem,12vw,3.55rem)!important;line-height:1.03!important}
  .hero p,.seo-hero p{font-size:1rem!important;line-height:1.65!important}
  .hero-actions,.cta-row,.remember,.selected-date,.field-group-title,.section-head,.gallery-head,.occasion-head,.review-head,.faq-head,.process-title{align-items:stretch!important;flex-direction:column!important}
  .hero-actions .btn,.cta-row .btn,.remember .btn,.link-list a,.google-card .btn{width:100%}
  .hero-grid,.hero-layout,.content,.section-head,.location-grid,.rules-intro,.faq-head,.visit-map-grid,.occasion-head,.review-head,.gallery-head,.dashboard-grid{grid-template-columns:1fr!important}
  .fact-grid,.snapshot-grid,.topic-grid,.feature-grid,.rule-grid,.term-grid,.package-grid,.occasion-grid,.nature-note,.reviews,.faq-grid,.grid,.public-footer__row,.foot-row{grid-template-columns:1fr!important}
  .quick-facts{margin-top:0!important}
  .fact,.snapshot-card,.topic,.feature-card,.rule-card,.term-card,.package-card,.review-card,.faq-item{min-width:0}
  .tabs,.page-nav .wrap,.tab-buttons{display:flex;flex-wrap:nowrap;overflow-x:auto;padding-bottom:10px}
  .tabs a,.page-nav a,.tab-buttons label{flex:0 0 auto}
  .gallery{column-count:1!important;columns:1!important}
  .occasion-board{min-height:340px;padding:22px}
  .occasion-tags span{font-size:.82rem}
  .review-form,.side-card{position:static!important}
  .score-card,.review-card,.review-form,.panel,.hero-panel{padding:18px!important}
  .field-group{padding:14px!important}
  .form-grid{grid-template-columns:1fr!important}
  .full,.wide{grid-column:auto!important}
  .calendar{margin-left:-2px;margin-right:-2px}
  .cal-head{align-items:flex-start!important;flex-direction:column!important;padding:14px}
  .cal-head h2{font-size:1.35rem!important}
  .cal-body{padding:10px}
  .week,.days{grid-template-columns:repeat(7,minmax(34px,1fr));gap:4px}
  .week span{font-size:.64rem}
  .day{min-height:48px;padding:5px;border-radius:6px}
  .day .num{font-size:.86rem}
  .day .tag{display:none}
  .legend{gap:10px;padding:0 10px 14px;font-size:.78rem}
  .calendar-note{padding:0 10px 14px}
}
@media(max-width:420px){
  #siteHeader .brand-name{font-size:.82rem}
  #siteHeader .brand-subtitle{font-size:.5rem}
  #siteHeader .nav.container{gap:8px;padding-left:12px;padding-right:12px}
  #siteHeader .mobile-nav-cta{min-height:38px;padding:8px 10px;font-size:.76rem}
  #siteHeader .menu-toggle{width:40px;height:40px;flex-basis:40px}
  .container,.wrap,.screen,.hero-inner{width:min(100% - 22px,1180px)!important}
  .hero h1,.seo-hero h1{font-size:clamp(2rem,14vw,3rem)!important}
  .week,.days{grid-template-columns:repeat(7,minmax(30px,1fr));gap:3px}
  .day{min-height:42px;padding:4px}
  .week span{font-size:.58rem}
}
#siteHeader{
  background:#17372c !important;
  padding:20px 0 !important;
}
#siteHeader.scrolled{
  background:#17372c !important;
  padding:14px 0 !important;
}
#siteHeader .nav.container{
  padding-left:clamp(36px,3.2vw,60px) !important;
  padding-right:clamp(20px,2.8vw,52px) !important;
}
#siteHeader .brand-name{
  font-family:'Instrument Serif',Georgia,serif !important;
  text-transform:uppercase !important;
  letter-spacing:.24em !important;
  font-size:clamp(1.45rem,1.8vw,1.78rem) !important;
  font-weight:400 !important;
  line-height:.9 !important;
  color:#e9d2a0 !important;
}
#siteHeader .brand-subtitle{
  margin-top:12px !important;
  color:#e6d9bd !important;
  font-size:.58rem !important;
  font-weight:800 !important;
  letter-spacing:.48em !important;
  text-transform:uppercase !important;
}
#siteHeader .nav-links{
  gap:28px !important;
}
#siteHeader .nav-links a{
  color:#d9d2bd !important;
  font-weight:800 !important;
}
#siteHeader .nav-links a:hover,
#siteHeader .nav-links a.active{
  color:#d9a050 !important;
}
#siteHeader .nav-links .nav-cta,
#siteHeader .mobile-nav-cta{
  background:#dda246 !important;
  border-color:#dda246 !important;
  border-radius:4px !important;
  color:#20170d !important;
  font-weight:900 !important;
  padding:14px 22px !important;
}
@media(max-width:860px){
  #siteHeader .brand-name{font-size:1.45rem !important}
  #siteHeader .brand-subtitle{letter-spacing:.34em !important}
}
@media(max-width:760px){
  #siteHeader .nav.container{padding-left:16px !important;padding-right:16px !important}
  #siteHeader .brand-name{font-size:1.35rem !important}
  #siteHeader .brand-subtitle{font-size:.48rem !important}
  #siteHeader .nav-links .nav-cta{border-radius:4px !important}
}

#siteHeader{
  background:transparent !important;
  backdrop-filter:none !important;
  box-shadow:none !important;
}
#siteHeader.scrolled{
  background:rgba(14,52,40,.94) !important;
  backdrop-filter:blur(10px) !important;
  box-shadow:0 10px 30px -20px rgba(0,0,0,.45) !important;
}
#siteHeader .brand-name{
  color:#efd9a9 !important;
  font-size:clamp(1.28rem,1.55vw,1.55rem) !important;
  line-height:.88 !important;
}
#siteHeader .brand-subtitle{
  color:rgba(255,250,240,.82) !important;
  margin-top:6px !important;
  font-size:.52rem !important;
  letter-spacing:.42em !important;
}
#siteHeader .nav-links a{
  color:#f0eadb !important;
}
#siteHeader .nav-links a:hover,
#siteHeader .nav-links a.active{
  color:#dda246 !important;
}
#siteHeader .nav-links .nav-cta,
#siteHeader .mobile-nav-cta{
  background:#dda246 !important;
  border-color:#dda246 !important;
  border-radius:4px !important;
  color:#11190f !important;
}
@media(max-width:760px){
  #siteHeader .nav.container{
    gap:8px !important;
    padding-left:12px !important;
    padding-right:12px !important;
  }
  #siteHeader .brand-name{
    font-size:clamp(.98rem,5vw,1.18rem) !important;
    letter-spacing:.2em !important;
  }
  #siteHeader .brand-subtitle{
    margin-top:4px !important;
    font-size:.43rem !important;
    letter-spacing:.32em !important;
  }
  #siteHeader .mobile-nav-cta{
    display:inline-flex !important;
    min-height:38px !important;
    max-width:132px !important;
    padding:8px 10px !important;
    font-size:.68rem !important;
    line-height:1.15 !important;
    white-space:normal !important;
  }
  #siteHeader .menu-toggle{
    width:38px !important;
    height:38px !important;
    flex:0 0 38px !important;
  }
}
@media(max-width:380px){
  #siteHeader .brand-name{
    font-size:.92rem !important;
    letter-spacing:.16em !important;
  }
  #siteHeader .brand-subtitle{
    font-size:.38rem !important;
    letter-spacing:.28em !important;
  }
  #siteHeader .mobile-nav-cta{
    max-width:108px !important;
    padding:7px 8px !important;
    font-size:.62rem !important;
  }
}
</style>
<header id="siteHeader">
  <div class="container nav">
    <a href="<?php echo h($homeHref); ?>" class="brand" aria-label="Anantha Event Lawns home">
      <span class="brand-name">Anantha</span>
      <span class="brand-subtitle">Event Lawns</span>
    </a>
    <ul class="nav-links" id="navLinks">
      <li class="menu-close-row"><button class="menu-close" id="menuClose" type="button" aria-label="Close menu">&times;</button></li>
      <li><a<?php echo $navClass('home'); ?> href="<?php echo h($homeHref); ?>">Home</a></li>
      <li><a<?php echo $navClass('gallery'); ?> href="gallery.php">Gallery</a></li>
      <li><a<?php echo $navClass('occasions'); ?> href="<?php echo h($occasionsHref); ?>">Ideas</a></li>
      <li><a<?php echo $navClass('rules'); ?> href="rules.php">Venue Rules</a></li>
      <li><a<?php echo $navClass('contact'); ?> href="<?php echo h($contactHref); ?>">Contact</a></li>
      <li><a class="nav-cta<?php echo $publicHeaderActive === 'availability' ? ' active' : ''; ?>"<?php echo $publicHeaderActive === 'availability' ? ' aria-current="page"' : ''; ?> href="<?php echo h($availabilityHref); ?>">Check Availability</a></li>
    </ul>
    <a class="mobile-nav-cta<?php echo $publicHeaderActive === 'availability' ? ' active' : ''; ?>" href="<?php echo h($availabilityHref); ?>"<?php echo $publicHeaderActive === 'availability' ? ' aria-current="page"' : ''; ?> aria-label="Check availability">Check Availability</a>
    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks"><span aria-hidden="true"></span></button>
  </div>
</header>
<div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

