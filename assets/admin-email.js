(() => {
  const method = document.getElementById('mail-transport');
  if (!method) return;
  const update = () => {
    const smtp = method.value === 'smtp';
    document.getElementById('php-mail-help').hidden = smtp;
    document.querySelectorAll('[data-smtp-field]').forEach(group => {
      group.hidden = !smtp;
      group.querySelectorAll('input,select').forEach(field => {
        field.disabled = !smtp;
        if (field.name === 'host' || field.name === 'port') field.required = smtp;
      });
    });
  };
  method.addEventListener('change', update);
  update();
})();