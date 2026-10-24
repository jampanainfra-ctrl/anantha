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
  const leadingSpace = /^[\s\u200B]/u;
  const spacingErrors = new WeakMap();
  const textTypes = new Set(['text','search','email','tel','url','password','number']);
  const requiredText = field => field instanceof HTMLTextAreaElement || (field instanceof HTMLInputElement && textTypes.has(field.type));
  function validateSpacing(field) {
    if (!requiredText(field)) return true;
    const previous = spacingErrors.get(field);
    if (!field.required || field.disabled || field.readOnly || !leadingSpace.test(field.value)) {
      if (previous && field.validationMessage === previous) field.setCustomValidity('');
      spacingErrors.delete(field);
      return true;
    }
    const title = titleFor(field);
    const message = title.charAt(0).toUpperCase() + title.slice(1) + ' must not start with a space.';
    field.setCustomValidity(message);
    spacingErrors.set(field, message);
    return false;
  }
  function blocksLeadingInsertion(field, inserted) {
    if (!requiredText(field) || !field.required || field.disabled || field.readOnly || !inserted) return false;
    const start = field.selectionStart;
    const end = field.selectionEnd;
    if (typeof start === 'number') return leadingSpace.test(field.value.slice(0,start) + inserted + field.value.slice(end));
    // Email and number inputs do not expose selection offsets and sanitize whitespace themselves.
    return leadingSpace.test(inserted) || leadingSpace.test(field.value);
  }
  const fieldErrors = new WeakMap();
  const touchedFields = new WeakSet();
  let errorId = 0;
  let focusQueued = false;
  const sentence = message => /[.!?]$/.test(message.trim()) ? message.trim() : message.trim() + '.';
  function fieldMessage(field) {
    const validity = field.validity;
    if (!validity || !field.willValidate || validity.valid) return '';
    const title = titleFor(field);
    if (validity.customError) return sentence(field.validationMessage);
    if (validity.valueMissing) {
      if (field.type === 'checkbox') return 'Please accept this condition before continuing.';
      if (field.type === 'radio') return 'Please select an option before continuing.';
      if (field.type === 'file') return 'Please choose a file.';
      if (field.tagName === 'SELECT') return 'Please select ' + title + '.';
      return 'Please enter ' + title + '.';
    }
    if (validity.typeMismatch) return field.type === 'email' ? 'Please enter a valid email address.' : 'Please enter a valid website address.';
    if (validity.patternMismatch) {
      if (['phone','whatsapp_number'].includes(field.name)) return 'Please enter a valid 10-digit mobile number.';
      if (field.name === 'pin_code') return 'Please enter a valid 6-digit PIN code.';
      return 'Please enter a valid ' + title + '.';
    }
    if (validity.tooShort) return 'Please enter at least ' + field.minLength + ' characters.';
    if (validity.tooLong) return 'Please use no more than ' + field.maxLength + ' characters.';
    if (validity.rangeUnderflow) return field.type === 'date' ? 'Please choose a date on or after ' + field.min + '.' : 'Please enter a value of at least ' + field.min + '.';
    if (validity.rangeOverflow) return field.type === 'date' ? 'Please choose a date on or before ' + field.max + '.' : 'Please enter a value no greater than ' + field.max + '.';
    if (validity.stepMismatch) return field.step === '1' || !field.step ? 'Please enter a whole number.' : 'Please enter a number in increments of ' + field.step + '.';
    if (validity.badInput) return 'Please enter a valid number.';
    return sentence(field.validationMessage || 'Please check this field');
  }
  function errorRecord(field) {
    let record = fieldErrors.get(field);
    if (record && record.message.isConnected) return record;
    const message = document.createElement('span');
    message.id = 'form-field-error-' + (++errorId);
    message.className = 'form-field-error';
    message.setAttribute('aria-live', 'polite');
    message.hidden = true;
    const group = field.closest('.password-field');
    const label = field.labels && field.labels[0];
    const anchor = group || (['checkbox','radio'].includes(field.type) && label?.contains(field) ? label : field);
    anchor.after(message);
    const descriptions = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (!descriptions.includes(message.id)) descriptions.push(message.id);
    field.setAttribute('aria-describedby', descriptions.join(' '));
    const warnings = label?.contains(field) ? Array.from(label.querySelectorAll('.field-warning.err')) : [];
    record = {message, warnings, previousInvalid: field.getAttribute('aria-invalid')};
    fieldErrors.set(field, record);
    return record;
  }
  function showFieldError(field, forcedMessage) {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) return false;
    const message = forcedMessage || fieldMessage(field);
    let record = fieldErrors.get(field);
    if (!message) {
      if (record) {
        record.message.hidden = true;
        if (record.message.textContent) record.message.textContent = '';
        record.warnings.forEach(warning => warning.removeAttribute('data-inline-duplicate'));
        field.classList.remove('has-field-error');
        if (record.previousInvalid === null) field.removeAttribute('aria-invalid');
        else field.setAttribute('aria-invalid', record.previousInvalid);
      }
      return false;
    }
    record = errorRecord(field);
    const text = sentence(message);
    if (record.message.textContent !== text) record.message.textContent = text;
    record.message.hidden = false;
    record.warnings.forEach(warning => warning.setAttribute('data-inline-duplicate', 'true'));
    field.classList.add('has-field-error');
    field.setAttribute('aria-invalid', 'true');
    return true;
  }
  function focusError(field) {
    if (focusQueued) return;
    focusQueued = true;
    queueMicrotask(() => {
      focusQueued = false;
      if (field.isConnected && field.getClientRects().length) field.focus();
    });
  }
  function blockedSpace(event, inserted) {
    if (!blocksLeadingInsertion(event.target, inserted)) return;
    event.preventDefault();
    touchedFields.add(event.target);
    showFieldError(event.target, 'Please do not start ' + titleFor(event.target) + ' with a space.');
  }
  document.addEventListener('beforeinput', event => {
    if (!event.isComposing && event.cancelable && event.inputType?.startsWith('insert')) blockedSpace(event, event.data);
  }, true);
  document.addEventListener('paste', event => blockedSpace(event, event.clipboardData?.getData('text')), true);
  document.addEventListener('input', event => {
    validateSpacing(event.target);
    if (touchedFields.has(event.target) || fieldErrors.has(event.target) || spacingErrors.has(event.target)) showFieldError(event.target);
  });
  document.addEventListener('change', event => {
    validateSpacing(event.target);
    touchedFields.add(event.target);
    showFieldError(event.target);
  });
  document.addEventListener('focusout', event => {
    validateSpacing(event.target);
    touchedFields.add(event.target);
    showFieldError(event.target);
  });
  document.addEventListener('invalid', event => {
    event.preventDefault();
    touchedFields.add(event.target);
    validateSpacing(event.target);
    showFieldError(event.target);
    focusError(event.target);
  }, true);
  document.addEventListener('submit', event => {
    if (!(event.target instanceof HTMLFormElement)) return;
    const skipNative = event.submitter?.formNoValidate || event.target.noValidate;
    let invalid;
    Array.from(event.target.elements).forEach(field => {
      const spacingValid = validateSpacing(field);
      if (skipNative && spacingValid) return;
      touchedFields.add(field);
      if (showFieldError(field) && !invalid) invalid = field;
    });
    if (invalid) { event.preventDefault(); event.stopImmediatePropagation(); focusError(invalid); }
  }, true);
  function refreshWarning(target) {
    const warning = target instanceof Element ? target.closest('.field-warning') : null;
    if (!warning) return;
    const label = warning.closest('label');
    const field = label && Array.from(label.querySelectorAll('input,select,textarea')).find(input => !['checkbox','radio','hidden'].includes(input.type));
    if (field && (touchedFields.has(field) || fieldErrors.has(field))) showFieldError(field);
  }
  function enhance(field) {
    if (field.type === 'hidden' || field.matches('button,input[type="submit"],input[type="button"],input[type="reset"]')) return;
    validateSpacing(field);
    if (touchedFields.has(field) || fieldErrors.has(field)) showFieldError(field);
    const title = titleFor(field);
    if (field.matches('select')) {
      const option = Array.from(field.options).find(o => o.value === '');
      if (option && /^(select|choose|auto selected|please select|--|$)/i.test(option.textContent.trim()) && !option.textContent.trim().toLowerCase().startsWith('please select')) option.textContent = 'Please select ' + title;
    } else if (!skipTypes.has(field.type)) {
      const prompt = 'Please enter ' + title;
      if (field.placeholder !== prompt) field.placeholder = prompt;
    }
    if (!field.required) return;
    // In admin tables, mark the column once instead of repeating stars in every row.
    const cell = field.closest('td');
    const table = cell && cell.closest('table');
    if (document.body.matches('[class^="admin-"]') && table && table.tHead) {
      const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
      const columnIndex = Array.from(cell.parentElement.cells)
        .slice(0, cell.cellIndex).reduce((total, item) => total + item.colSpan, 0);
      let column = 0;
      const heading = headerRow && Array.from(headerRow.cells).find(item => {
        const matches = columnIndex >= column && columnIndex < column + item.colSpan;
        column += item.colSpan;
        return matches;
      });
      if (heading) {
        if (!heading.querySelector('.required-mark')) {
          const marker = document.createElement('span');
          marker.className = 'required-mark';
          marker.setAttribute('aria-hidden', 'true');
          marker.textContent = ' *';
          heading.appendChild(marker);
        }
        const label = field.labels && field.labels[0];
        if (label) label.querySelectorAll('.required-mark').forEach(marker => marker.remove());
        if (field.previousElementSibling && field.previousElementSibling.classList.contains('required-mark')) {
          field.previousElementSibling.remove();
        }
        if (!label && !field.hasAttribute('aria-label')) field.setAttribute('aria-label', title);
        return;
      }
    }
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
      for (const record of records) {
        if (record.type === 'attributes') enhance(record.target);
        else {
          refreshWarning(record.target);
          for (const node of record.addedNodes) scan(node);
        }
      }
    }).observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:['required','disabled','type']});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
