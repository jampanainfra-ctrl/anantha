(() => {
  const feedback = document.querySelector('.copy-feedback');
  let timer;
  document.querySelectorAll('[data-copy-code]').forEach(button => {
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.copyCode);
        button.textContent = 'Copied';
        feedback.textContent = 'Referral code copied.';
        setTimeout(() => { button.textContent = 'Copy'; }, 1800);
      } catch {
        feedback.textContent = 'Copy unavailable. Select the code and copy it manually.';
      }
      clearTimeout(timer);
      timer = setTimeout(() => { feedback.textContent = ''; }, 3500);
    });
  });
})();