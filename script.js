/* ============================================
   МАГНЕТАР — главный скрипт
   ============================================ */

// ============================================
// УТИЛИТЫ
// ============================================
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

// ============================================
// МОБИЛЬНОЕ МЕНЮ
// ============================================
(function initBurger() {
  const burger = $('#burger');
  const nav = $('.header__nav');
  if (!burger || !nav) return;

  burger.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('is-open');
    burger.classList.toggle('is-active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
  });

  // Закрытие при клике по ссылке
  $$('.header__nav a').forEach(a => {
    a.addEventListener('click', () => {
      nav.classList.remove('is-open');
      burger.classList.remove('is-active');
      document.body.style.overflow = '';
    });
  });
})();

// ============================================
// МОДАЛКА
// ============================================
(function initModal() {
  const modal = $('#modal');
  if (!modal) return;

  const openBtns  = $$('[data-open-form]');
  const closeBtns = $$('[data-close-modal]');

  const open = () => {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  const close = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  openBtns.forEach(btn => btn.addEventListener('click', open));
  closeBtns.forEach(btn => btn.addEventListener('click', close));

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
  });
})();

// ============================================
// ОТПРАВКА ФОРМЫ
// ============================================
async function submitForm(form, statusEl) {
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn.textContent;

  const name    = form.name?.value.trim()    || '';
  const phone   = form.phone?.value.trim()   || '';
  const message = form.message?.value.trim() || '';

  // Валидация
  if (!name) {
    setStatus(statusEl, 'Укажите имя', 'error');
    return;
  }
  if (!phone) {
    setStatus(statusEl, 'Укажите телефон', 'error');
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Отправляем…';
  setStatus(statusEl, '', '');

  try {
    const response = await fetch('send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, phone, message })
    });

    const result = await response.json();

    if (result.success) {
      setStatus(statusEl, '✓ Заявка отправлена! Мы свяжемся с вами.', 'success');
      form.reset();

      // Закрываем модалку через 2 секунды, если она открыта
      const modal = form.closest('.modal');
      if (modal) {
        setTimeout(() => {
          modal.classList.remove('is-open');
          document.body.style.overflow = '';
        }, 2000);
      }
    } else {
      throw new Error(result.error || 'Ошибка отправки');
    }
  } catch (err) {
    console.error(err);
    setStatus(
      statusEl,
      'Ошибка: ' + err.message + '. Позвоните нам: 8 (351) 211-27-70',
      'error'
    );
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
}

function setStatus(el, text, type) {
  if (!el) return;
  el.textContent = text;
  el.className = 'request-form__status' + (type ? ` request-form__status--${type}` : '');
}

// Привязка к формам
(function initForms() {
  const mainForm   = $('#requestForm');
  const mainStatus = $('#formStatus');
  if (mainForm) {
    mainForm.addEventListener('submit', e => {
      e.preventDefault();
      submitForm(mainForm, mainStatus);
    });
  }

  const modalForm   = $('#modalForm');
  const modalStatus = $('#modalStatus');
  if (modalForm) {
    modalForm.addEventListener('submit', e => {
      e.preventDefault();
      submitForm(modalForm, modalStatus);
    });
  }
})();

// ============================================
// ЗАЛИПАЮЩАЯ ШАПКА (усиление при скролле)
// ============================================
(function initHeaderScroll() {
  const header = $('#header');
  if (!header) return;

  const onScroll = () => {
    if (window.scrollY > 40) {
      header.classList.add('is-scrolled');
    } else {
      header.classList.remove('is-scrolled');
    }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();

// ============================================
// ПЛАВНЫЙ СКРОЛЛ ПО ЯКОРЯМ (с учётом высоты шапки)
// ============================================
(function initSmoothScroll() {
  const header = $('#header');
  const headerHeight = header ? header.offsetHeight : 0;

  $$('a[href^="#"]').forEach(link => {
    const href = link.getAttribute('href');
    if (href === '#' || href.length < 2) return;

    link.addEventListener('click', e => {
      const target = document.querySelector(href);
      if (!target) return;

      e.preventDefault();
      const top = target.getBoundingClientRect().top + window.scrollY - headerHeight - 16;
      window.scrollTo({ top, behavior: 'smooth' });
    });
  });
})();

// ============================================
// АНИМАЦИЯ ПОЯВЛЕНИЯ СЕКЦИЙ (Intersection Observer)
// ============================================
(function initReveal() {
  const items = $$('.section__header, .industry-card, .tech-block, .stat, .services__list li, .cta__card');
  if (!items.length) return;

  // Уважаем настройки пользователя
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReduced) {
    items.forEach(el => el.classList.add('is-visible'));
    return;
  }

  items.forEach(el => el.classList.add('reveal'));

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -40px 0px'
  });

  items.forEach(el => observer.observe(el));
})();

// ============================================
// МАСКА ТЕЛЕФОНА (простая, без библиотек)
// ============================================
(function initPhoneMask() {
  const inputs = $$('input[type="tel"]');

  inputs.forEach(input => {
    input.addEventListener('input', () => {
      let value = input.value.replace(/\D/g, '');
      if (value.startsWith('8')) value = '7' + value.slice(1);
      if (!value.startsWith('7')) value = '7' + value;

      let formatted = '+7';
      if (value.length > 1)  formatted += ' (' + value.slice(1, 4);
      if (value.length >= 5) formatted += ') ' + value.slice(4, 7);
      if (value.length >= 8) formatted += '-' + value.slice(7, 9);
      if (value.length >= 10) formatted += '-' + value.slice(9, 11);

      input.value = formatted;
    });

    input.addEventListener('focus', () => {
      if (!input.value) input.value = '+7 ';
    });

    input.addEventListener('blur', () => {
      if (input.value === '+7 ' || input.value === '+7') input.value = '';
    });
  });
})();

// ============================================
// ДУБЛИРОВАНИЕ БЕГУЩЕЙ СТРОКИ (для бесшовного цикла)
// ============================================
(function initTicker() {
  const content = $('#tickerContent');
  if (!content) return;
  // Дублируем содержимое для бесшовного скролла
  content.innerHTML += content.innerHTML;
})();

// ============================================
// ПОДСВЕТКА АКТИВНОЙ СЕКЦИИ В МЕНЮ (опционально)
// ============================================
(function initActiveNav() {
  const sections = $$('section[id]');
  const navLinks = $$('.header__nav a[href^="#"]');
  if (!sections.length || !navLinks.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id;
        navLinks.forEach(link => {
          link.classList.toggle('is-active', link.getAttribute('href') === '#' + id);
        });
      }
    });
  }, { threshold: 0.4 });

  sections.forEach(s => observer.observe(s));
})();

// ============================================
// ЛОГ В КОНСОЛЬ (для отладки)
// ============================================
console.log('[Магнетар] Главная страница загружена');