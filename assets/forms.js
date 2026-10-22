(() => {
  'use strict';
  const names = {
    name: 'full name', client_name: 'full name', phone: 'mobile number', whatsapp_number: 'WhatsApp number',
    email: 'email address', pin_code: 'PIN code', area_from: 'area or village', city_district: 'city or district',
    client_state: 'state', referred_by_reference: 'current referral code or member phone', event_type: 'occasion',
    guests: 'expected guest count', package_id: 'package', password: 'password', q: 'search term',
    note: 'additional details', referral_note: 'referral notes', booking_reference: 'booking reference',
    usage_limit: 'usage limit', eligibility_days: 'validity in days', setting_value: 'value', display_order: 'display order'
  };
  const skipTypes = new Set(['hidden','checkbox','radio','submit','button','reset','file','range','color','date','time','datetime-local','month','week','image']);
  function titleFor(field) {
    const label = field.labels && field.labels[0];
    const directText = label ? Array.from(label.childNodes).filter(n => n.nodeType === Node.TEXT_NODE).map(n => n.textContent).join(' ').trim() : '';
    const title = names[field.name] || directText || field.getAttribute('aria-label') || (field.name || field.id || 'value').replace(/[_-]+/g, ' ');
    return title.replace(/\((required|optional)\)/gi, '').replace(/\*/g, '').replace(/\s+/g, ' ').trim().replace(/[?:]$/, '').toLowerCase();
  }
  function enhance(field) {
    if (field.type === 'hidden' || field.matches('button,input[type="submit"],input[type="button"],input[type="reset"]')) return;
    const title = titleFor(field);
    if (field.matches('select')) {
      const option = Array.from(field.options).find(o => o.value === '');
      if (option && /^(select|choose|auto selected|please select|--|$)/i.test(option.textContent.trim()) && !option.textContent.trim().toLowerCase().startsWith('please select')) option.textContent = 'Please select ' + title;
    } else if (!skipTypes.has(field.type)) {
      const prompt = 'Please enter ' + title;
      if (field.placeholder !== prompt) field.placeholder = prompt;
    }
    if (!field.required) return;
    const label = field.labels && field.labels[0];
    if (label) {
      Array.from(label.childNodes).filter(n => n.nodeType === Node.TEXT_NODE).forEach(node => {
        if (/\(required\)/i.test(node.textContent)) node.textContent = node.textContent.replace(/\s*\(required\)/gi, '');
      });
      if (!label.querySelector('.required-mark')) {
        const marker = document.createElement('span');
        marker.className = 'required-mark'; marker.setAttribute('aria-hidden', 'true'); marker.textContent = ' *';
        if (label.contains(field)) label.insertBefore(marker, field);
        else label.appendChild(marker);
      }
    } else {
      if (!field.hasAttribute('aria-label')) field.setAttribute('aria-label', title);
      if (!field.previousElementSibling || !field.previousElementSibling.classList.contains('required-mark')) {
        const marker = document.createElement('span');
        marker.className = 'required-mark'; marker.setAttribute('aria-hidden', 'true'); marker.textContent = '* ';
        field.before(marker);
      }
    }
  }
  function scan(root) {
    if (root.nodeType !== Node.ELEMENT_NODE && root !== document) return;
    if (root.matches && root.matches('input,select,textarea')) enhance(root);
    root.querySelectorAll('input,select,textarea').forEach(enhance);
  }
  function start() {
    scan(document);
    new MutationObserver(records => {
      for (const record of records) for (const node of record.addedNodes) scan(node);
    }).observe(document.body, {childList:true, subtree:true});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
