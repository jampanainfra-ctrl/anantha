<?php
$chatbotFaq = 'Key rules: one event per day, Rs 10,000 security deposit, electricity by meter reading, and cancellation/reschedule terms shown on the Venue Rules page.';
if (function_exists('db_ready') && db_ready()) {
    try {
        ensure_terms_schema();
        $rows = db()->query("SELECT title, term_text FROM site_terms WHERE is_active=1 ORDER BY display_order ASC LIMIT 4")->fetchAll();
        if ($rows) {
            $parts = [];
            foreach ($rows as $row) {
                $parts[] = $row['title'] . ': ' . preg_replace('/\s+/', ' ', (string) $row['term_text']);
            }
            $chatbotFaq = implode(' ', $parts);
        }
    } catch (Throwable $e) {
        // Static fallback keeps the chat available when database setup is incomplete.
    }
}
?>
<div class="contact-stack" aria-label="Quick contact options">
  <a class="contact-fab call-fab" href="tel:+917995011199" aria-label="Call Anantha Event Lawns">
    <svg aria-hidden="true" viewBox="0 0 24 24">
      <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7A2 2 0 0 1 22 16.9Z"/>
    </svg>
  </a>
  <a class="contact-fab wa-fab" href="https://wa.me/<?php echo WHATSAPP_NUMBER; ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
    <svg aria-hidden="true" viewBox="0 0 32 32">
      <path d="M16 3C9 3 3.3 8.7 3.3 15.7c0 2.6.7 5 1.9 7.1L3 29l6.4-2.1c2 1.1 4.3 1.7 6.6 1.7 7 0 12.7-5.7 12.7-12.7C28.7 8.7 23 3 16 3zm0 23.1c-2.1 0-4.1-.6-5.8-1.6l-.4-.2-4.3 1.4 1.4-4.2-.3-.4a10.4 10.4 0 0 1-1.6-5.6C4.9 9.9 9.9 5 16 5s11 4.9 11 10.7-4.9 10.4-11 10.4zm5.9-7.8c-.3-.2-1.9-.9-2.2-1-.3-.1-.5-.2-.7.1-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-1.8-.9-3-1.6-4.3-3.6-.3-.5.3-.5.9-1.6.1-.2 0-.4 0-.5-.1-.2-.7-1.8-1-2.4-.3-.6-.5-.6-.7-.6h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1 2.9 1.2 3.1c.1.2 2.1 3.2 5 4.4.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.9-.8 2.1-1.5.3-.7.3-1.3.2-1.5-.1-.1-.3-.2-.6-.4z"/>
    </svg>
  </a>
<div class="site-chatbot" id="siteChatbot">
  <button class="chatbot-toggle" id="chatbotToggle" type="button" aria-label="Open Anantha chat" aria-expanded="false">
    <svg aria-hidden="true" viewBox="0 0 24 24">
      <path d="M5.5 15.5h-.8a2.7 2.7 0 0 1-2.7-2.7V6.7A2.7 2.7 0 0 1 4.7 4h9.6A2.7 2.7 0 0 1 17 6.7v6.1a2.7 2.7 0 0 1-2.7 2.7H9l-3.5 3v-3Z"/>
      <path d="M16.8 10.2h2.5a2.7 2.7 0 0 1 2.7 2.7v4.4a2.7 2.7 0 0 1-2.7 2.7h-.8v2l-2.4-2h-3.2a2.7 2.7 0 0 1-2.7-2.7v-1.7"/>
      <path d="M7.1 8.3h5.2M7.1 11.1h3.2"/>
    </svg>
  </button>
  <section class="chatbot-panel" id="chatbotPanel" aria-label="Anantha chat assistant">
    <div class="chatbot-head">
      <span class="chatbot-mark" aria-hidden="true">A</span>
      <div>
        <strong>Anantha Assistant</strong>
        <small>Venue help desk</small>
      </div>
      <button type="button" id="chatbotClose" aria-label="Close chat">&times;</button>
    </div>
    <div class="chatbot-log" id="chatbotLog">
      <p class="bot">Welcome to Anantha. Ask about dates, guest count, venue spaces or booking steps.</p>
    </div>
    <div class="chatbot-quick">
      <button type="button" data-chat="Give me an overview of Anantha">Overview</button>
      <button type="button" data-chat="Is my date available?">Availability</button>
      <button type="button" data-chat="Where is Anantha located?">Location</button>
      <button type="button" data-chat="What are the venue rules?">FAQ</button>
    </div>
    <form class="chatbot-form" id="chatbotForm">
      <input id="chatbotInput" type="text" autocomplete="off" placeholder="Ask about Anantha">
      <button type="submit">Send</button>
    </form>
  </section>
</div>
</div>
<style>
.contact-stack{position:fixed;right:24px;bottom:24px;z-index:96;display:grid;gap:12px;width:58px;font-family:'Inter',sans-serif}.contact-fab,.chatbot-toggle{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;text-decoration:none;cursor:pointer;transition:transform .24s ease,box-shadow .24s ease}.contact-fab:hover,.chatbot-toggle:hover{transform:translateY(-3px)}.contact-fab svg{width:28px;height:28px}.call-fab{background:#d9a050;color:#142f28;box-shadow:0 12px 26px -8px rgba(0,0,0,.4);animation:callPulse 2.6s ease-in-out infinite}.call-fab svg{fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.wa-fab{background:#25D366;color:#fff;box-shadow:0 12px 26px -8px rgba(0,0,0,.4);animation:pulse 2.6s ease-in-out infinite}.wa-fab svg{fill:currentColor}.site-chatbot{position:relative;width:58px;height:58px}.chatbot-toggle{position:relative;border:1px solid rgba(244,193,107,.46);background:linear-gradient(145deg,#173b31,#102820);color:#f4c16b;box-shadow:0 18px 38px -18px rgba(0,0,0,.68),0 0 0 6px rgba(217,160,80,.11);overflow:hidden}.chatbot-toggle:before{content:'';position:absolute;inset:7px;border-radius:50%;border:1px solid rgba(255,250,240,.14)}.chatbot-toggle:after{content:'';position:absolute;width:10px;height:10px;right:10px;top:10px;border-radius:50%;background:#f4c16b;box-shadow:0 0 0 4px rgba(244,193,107,.18)}.chatbot-toggle:hover{box-shadow:0 22px 44px -18px rgba(0,0,0,.72),0 0 0 8px rgba(217,160,80,.13)}.chatbot-toggle svg{position:relative;width:29px;height:29px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}.chatbot-panel{position:fixed;right:94px;bottom:24px;width:340px;max-width:calc(100vw - 132px);padding:0;max-height:calc(100vh - 48px);background:linear-gradient(180deg,#fffaf0,#f8f3e8);color:#1b2a24;border:1px solid rgba(20,47,40,.16);border-radius:10px;box-shadow:0 28px 78px -32px rgba(20,47,40,.72);overflow:hidden;transform:translateX(14px) scale(.98);transform-origin:bottom right;opacity:0;pointer-events:none;transition:.25s ease}.site-chatbot.open .chatbot-panel{transform:none;opacity:1;pointer-events:auto}.chatbot-head{display:grid;grid-template-columns:38px 1fr 32px;align-items:center;gap:11px;background:linear-gradient(135deg,#142f28,#245446);color:#e4dac3;padding:14px}.chatbot-mark{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:rgba(244,193,107,.16);border:1px solid rgba(244,193,107,.36);color:#f4c16b;font-family:'Inter',sans-serif;font-weight:700}.chatbot-head strong{display:block;color:#fff;font-size:.98rem}.chatbot-head small{display:block;margin-top:2px;color:rgba(228,218,195,.74);font-size:.76rem}.chatbot-head button{width:32px;height:32px;border-radius:50%;border:1px solid rgba(228,218,195,.25);background:rgba(255,255,255,.08);color:#e4dac3;font-size:1.24rem;line-height:1;cursor:pointer}.chatbot-log{display:grid;gap:9px;max-height:220px;overflow:auto;padding:14px;background:linear-gradient(180deg,rgba(255,255,255,.72),rgba(255,255,255,.28))}.chatbot-log p{margin:0;padding:10px 12px;border-radius:10px;line-height:1.45;font-size:.9rem}.chatbot-log .bot{background:#fff;color:#20483f;border:1px solid rgba(32,72,63,.1);box-shadow:0 8px 24px -18px rgba(20,47,40,.5)}.chatbot-log .user{background:#d9a050;color:#25190b;justify-self:end;max-width:86%;box-shadow:0 10px 24px -18px rgba(95,55,12,.55)}.chatbot-quick{display:flex;gap:7px;flex-wrap:wrap;padding:0 14px 12px;background:rgba(255,255,255,.28)}.chatbot-quick button,.chatbot-quick a{border:1px solid rgba(20,47,40,.13);background:#fff;color:#20483f;border-radius:999px;padding:8px 11px;font-weight:700;font-size:.84rem;line-height:normal;text-decoration:none;cursor:pointer;transition:background .2s ease,color .2s ease,transform .2s ease}.chatbot-quick button:hover,.chatbot-quick a:hover{background:#20483f;color:#fff;transform:translateY(-1px)}.chatbot-form{display:grid;grid-template-columns:1fr auto;gap:8px;padding:12px 14px 14px;border-top:1px solid rgba(20,47,40,.1);background:#fffaf0}.chatbot-form input{min-width:0;border:1px solid rgba(20,47,40,.16);border-radius:999px;padding:11px 13px;font:inherit;font-size:.9rem;background:#fff;color:#1b2a24;box-shadow:inset 0 1px 0 rgba(255,255,255,.85)}.chatbot-form button{border:0;border-radius:999px;background:#142f28;color:#fff;padding:0 15px;font-weight:800;cursor:pointer;transition:background .2s ease,transform .2s ease}.chatbot-form button:hover{background:#245446;transform:translateY(-1px)}@keyframes pulse{0%,100%{box-shadow:0 12px 26px -8px rgba(0,0,0,.4)}50%{box-shadow:0 12px 26px -4px rgba(37,211,102,.55)}}@keyframes callPulse{0%,100%{transform:translateY(0);box-shadow:0 12px 26px -8px rgba(0,0,0,.4)}50%{transform:translateY(-4px);box-shadow:0 14px 30px -6px rgba(217,160,80,.65)}}@media(max-width:620px){.contact-stack{right:16px;bottom:18px;gap:10px;width:52px}.contact-fab,.chatbot-toggle,.site-chatbot{width:52px;height:52px}.contact-fab svg{width:25px;height:25px}.chatbot-toggle svg{width:26px;height:26px}.chatbot-panel{right:16px;bottom:210px;width:calc(100vw - 32px);max-width:calc(100vw - 32px);max-height:calc(100vh - 232px);transform:translateY(14px) scale(.98)}.chatbot-log{max-height:196px}}
</style>
<script>
(() => {
  const root = document.getElementById('siteChatbot');
  if (!root || root.dataset.ready) return;
  root.dataset.ready = 'true';
  const toggle = document.getElementById('chatbotToggle');
  const close = document.getElementById('chatbotClose');
  const form = document.getElementById('chatbotForm');
  const input = document.getElementById('chatbotInput');
  const log = document.getElementById('chatbotLog');
  const faqText = <?php echo json_encode($chatbotFaq, JSON_UNESCAPED_SLASHES); ?>;
  const replies = [
    { keys: ['overview', 'about', 'anantha'], text: 'Anantha Event Lawns is a private event lawn in Kommadi, Madhurawada with one event per day, up to 300 guests, two AC rooms, one mini hall, washrooms, flood lights and lawn space for stage, food counters and seating.' },
    { keys: ['guest', 'capacity', 'people', '100', '150', '300'], text: 'Maximum venue capacity is 300 guests. The enquiry form suggests a package from your guest count.' },
    { keys: ['timing', 'time', 'hours', 'checkin', 'checkout'], text: 'Event timings are confirmed during enquiry. Mini hall use closes by 10:00 PM, and DJ or loud sound must follow local permission timing rules.' },
    { keys: ['space', 'included', 'lawn', 'room', 'hall', 'washroom', 'light', 'screen'], text: 'Included spaces: lawn, mini hall, garden, two deluxe AC rooms, flood lights, screen access, hand wash points and washrooms.' },
    { keys: ['location', 'where', 'map', 'address', 'directions'], text: 'Anantha is at Kommadi, Madhurawada, Visakhapatnam. Use the Get Directions button in the contact section for Google Maps.' },
    { keys: ['liquor', 'liquire', 'alcohol', 'dj', 'music', 'sound'], text: 'Liquor, DJ and amplified sound are allowed only with required permissions and within legal timing limits. The client and vendors are responsible for permits and safe conduct.' },
    { keys: ['faq', 'rule', 'rules', 'cancel', 'reschedule', 'deposit', 'electricity'], text: faqText },
    { keys: ['payment', 'pay', 'upi', 'advance'], text: 'Please do not share payment details in chat. The team shares quotation and payment steps only after verifying your enquiry.' },
    { keys: ['contact', 'phone', 'whatsapp'], text: 'You can call +91 79950 11199 or use the WhatsApp button for a quicker reply.' }
  ];
  const availabilityUrl = 'https://www.ananthaeventlawns.com/availability/';
  const add = (text, type) => {
    const p = document.createElement('p');
    p.className = type;
    p.textContent = text;
    if (type === 'bot' && text.endsWith(availabilityUrl)) {
      p.textContent = text.slice(0, -availabilityUrl.length);
      const link = document.createElement('a');
      link.href = availabilityUrl;
      link.textContent = availabilityUrl;
      link.style.overflowWrap = 'anywhere';
      p.appendChild(link);
    }
    log.appendChild(p);
    log.scrollTop = log.scrollHeight;
  };
  const parseDate = (text) => {
    const iso = text.match(/\b(20\d{2})[-/](0?[1-9]|1[0-2])[-/](0?[1-9]|[12]\d|3[01])\b/);
    if (iso) {
      return `${iso[1]}-${iso[2].padStart(2, '0')}-${iso[3].padStart(2, '0')}`;
    }
    const local = text.match(/\b(0?[1-9]|[12]\d|3[01])[-/](0?[1-9]|1[0-2])[-/](20\d{2})\b/);
    if (local) {
      return `${local[3]}-${local[2].padStart(2, '0')}-${local[1].padStart(2, '0')}`;
    }
    return '';
  };
  const checkAvailability = async (dateValue) => {
    const response = await fetch(`<?php echo app_url('api/availability.php', ''); ?>?date=${encodeURIComponent(dateValue)}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'Could not check availability.');
    if (data.status === 'past') {
      return `No, ${dateValue} is a past date and cannot be booked.`;
    }
    return data.available ? `Yes, ${dateValue} is available for booking. Please fill the enquiry form to confirm next steps. ${availabilityUrl}` : `No, ${dateValue} is already booked. Please choose another date. ${availabilityUrl}`;
  };
  const answer = async (text) => {
    const words = text.toLowerCase();
    const dateValue = parseDate(words);
    if (dateValue || ['availability', 'available', 'free', 'date', 'book', 'booking', 'calendar'].some(key => words.includes(key))) {
      if (!dateValue) {
        add('Please share the event date, for example 12-2-2026 or 2026-02-12. I will check the availability and reply yes or no.', 'bot');
        return;
      }
      try {
        add(await checkAvailability(dateValue), 'bot');
      } catch (error) {
        add(error.message || 'Could not check this date right now. Please use Check Availability.', 'bot');
      }
      return;
    }
    const match = replies.find(item => item.keys.some(key => words.includes(key)));
    add(match ? match.text : 'Please share your date, occasion and guest count. The Anantha team can confirm the best fit and next steps.', 'bot');
  };
  const send = (text) => {
    const value = text.trim();
    if (!value) return;
    add(value, 'user');
    answer(value);
    input.value = '';
  };
  toggle.addEventListener('click', () => {
    root.classList.toggle('open');
    toggle.setAttribute('aria-expanded', root.classList.contains('open') ? 'true' : 'false');
  });
  close.addEventListener('click', () => root.classList.remove('open'));
  form.addEventListener('submit', event => {
    event.preventDefault();
    send(input.value);
  });
  root.querySelectorAll('[data-chat]').forEach(button => button.addEventListener('click', () => send(button.dataset.chat)));
})();
</script>
