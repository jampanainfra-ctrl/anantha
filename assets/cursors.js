(() => {
  const media = matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference) and (forced-colors: none)');
  const accent = document.createElement('div');
  accent.className = 'cursor-accent'; accent.setAttribute('aria-hidden', 'true');
  let x = 0, y = 0, targetX = 0, targetY = 0, frame = null, initialized = false;
  const animate = () => {
    x += (targetX - x) * .24; y += (targetY - y) * .24;
    accent.style.transform = `translate3d(${x}px, ${y}px, 0)`;
    frame = Math.abs(targetX - x) + Math.abs(targetY - y) > .15 ? requestAnimationFrame(animate) : null;
  };
  const hide = () => {
    accent.classList.remove('is-visible', 'is-down'); initialized = false;
    if (frame) cancelAnimationFrame(frame); frame = null;
  };
  const changeMode = () => {
    hide();
    if (media.matches) document.body.appendChild(accent);
    else accent.remove();
  };
  document.addEventListener('pointermove', event => {
    if (!media.matches || event.pointerType !== 'mouse') return;
    targetX = event.clientX; targetY = event.clientY;
    if (!initialized) { x = targetX; y = targetY; initialized = true; }
    const target = event.target instanceof Element ? event.target : null;
    accent.classList.toggle('is-link', !!target?.closest('a[href],button,summary,select,[role="button"]'));
    accent.classList.toggle('is-editing', !!target?.closest('input,textarea,[contenteditable="true"]'));
    accent.classList.add('is-visible');
    if (!frame) frame = requestAnimationFrame(animate);
  }, {passive: true});
  document.addEventListener('pointerdown', () => accent.classList.add('is-down'), {passive: true});
  document.addEventListener('pointerup', () => accent.classList.remove('is-down'), {passive: true});
  document.documentElement.addEventListener('pointerleave', hide);
  document.addEventListener('keydown', event => { if (event.key === 'Tab') hide(); });
  window.addEventListener('blur', hide);
  document.addEventListener('visibilitychange', () => { if (document.hidden) hide(); });
  media.addEventListener('change', changeMode);
  changeMode();
})();