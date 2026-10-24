(() => {
  const viewport = document.getElementById('guestStories');
  if (!viewport) return;
  const cards = Array.from(viewport.querySelectorAll('.guest-story'));
  const previous = document.querySelector('[data-story-direction="-1"]');
  const next = document.querySelector('[data-story-direction="1"]');
  const count = document.querySelector('.guest-stories-count');
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  const update = () => {
    const bounds = viewport.getBoundingClientRect();
    const visible = cards.map((card, index) => ({index, bounds: card.getBoundingClientRect()}))
      .filter(item => item.bounds.left < bounds.right - 10 && item.bounds.right > bounds.left + 10);
    if (count && visible.length) {
      const text = `${visible[0].index + 1}–${visible[visible.length - 1].index + 1} of ${cards.length}`;
      if (count.textContent !== text) count.textContent = text;
    }
    if (previous) previous.disabled = viewport.scrollLeft < 3;
    if (next) next.disabled = viewport.scrollLeft >= viewport.scrollWidth - viewport.clientWidth - 3;
  };
  let scrollAnimation, finishTimer, scrollVersion = 0;
  const cancelScroll = () => { if (scrollAnimation) cancelAnimationFrame(scrollAnimation); clearTimeout(finishTimer); scrollAnimation = null; scrollVersion++; };
  const move = direction => {
    const distance = cards[0].getBoundingClientRect().width + (parseFloat(getComputedStyle(viewport).columnGap) || 0);
    const left = Math.max(0, Math.min(viewport.scrollWidth - viewport.clientWidth, viewport.scrollLeft + direction * distance));
    cancelScroll();
    if (reducedMotion.matches) { viewport.scrollTo({left, behavior: 'instant'}); update(); return; }
    const from = viewport.scrollLeft, start = performance.now(), version = scrollVersion;
    const tick = now => {
      if (version !== scrollVersion) return;
      const progress = Math.max(0, Math.min(1, (now - start) / 360));
      const eased = 1 - Math.pow(1 - progress, 3);
      viewport.scrollTo({left: from + (left - from) * eased, behavior: 'instant'});
      scrollAnimation = progress < 1 ? requestAnimationFrame(tick) : null;
      if (progress === 1) clearTimeout(finishTimer);
    };
    scrollAnimation = requestAnimationFrame(tick);
    // Finish at the intended card even when background tabs throttle frames.
    finishTimer = setTimeout(() => { cancelScroll(); viewport.scrollTo({left, behavior: 'instant'}); update(); }, 420);
  };
  [previous, next].filter(Boolean).forEach(button => button.addEventListener('click', () => move(Number(button.dataset.storyDirection))));
  viewport.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault(); move(event.key === 'ArrowLeft' ? -1 : 1);
    }
  });
  viewport.addEventListener('pointerdown', cancelScroll, {passive: true});
  viewport.addEventListener('wheel', cancelScroll, {passive: true});
  let frame;
  viewport.addEventListener('scroll', () => {
    if (frame) return;
    frame = requestAnimationFrame(() => { frame = null; update(); });
  }, {passive: true});
  new ResizeObserver(update).observe(viewport);
  update();
})();