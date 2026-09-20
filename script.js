document.getElementById('requestForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const form = e.target;
  const statusEl = document.getElementById('formStatus');
  const submitBtn = form.querySelector('button[type="submit"]');

  const name = form.name.value.trim();
  const phone = form.phone.value.trim();
  const message = form.message.value.trim();

  // Простая валидация
  if (!name || !phone) {
    statusEl.textContent = 'Заполните имя и телефон';
    statusEl.className = 'request-form__status request-form__status--error';
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Отправляем...';
  statusEl.textContent = '';

  try {
    const response = await fetch('send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, phone, message })
    });

    const result = await response.json();

    if (result.success) {
      statusEl.textContent = 'Заявка отправлена! Мы свяжемся с вами.';
      statusEl.className = 'request-form__status request-form__status--success';
      form.reset();
    } else {
      throw new Error(result.error || 'Ошибка отправки');
    }
  } catch (err) {
    statusEl.textContent = 'Ошибка: ' + err.message + '. Позвоните нам: 8 (351) 211-27-70';
    statusEl.className = 'request-form__status request-form__status--error';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Отправить заявку';
  }
});