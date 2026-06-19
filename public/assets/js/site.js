document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('[data-site-menu]');
  const nav = document.querySelector('[data-site-nav]');
  menu?.addEventListener('click', () => {
    const open = nav?.classList.toggle('is-open') || false;
    menu.classList.toggle('is-open', open);
    menu.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('site-menu-open', open);
  });
  nav?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    nav.classList.remove('is-open');
    menu?.classList.remove('is-open');
    document.body.classList.remove('site-menu-open');
  }));

  const announcements = [...document.querySelectorAll('[data-announcement]')];
  if (announcements.length > 1) {
    let announcementIndex = 0;
    window.setInterval(() => {
      announcements[announcementIndex].classList.remove('is-active');
      announcementIndex = (announcementIndex + 1) % announcements.length;
      announcements[announcementIndex].classList.add('is-active');
    }, 6000);
  }

  const carousel = document.querySelector('[data-site-carousel]');
  if (carousel) {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
    let current = 0;
    let timer;
    const show = (index) => {
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
      dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
    };
    const autoplay = () => {
      window.clearInterval(timer);
      timer = window.setInterval(() => show(current + 1), 7000);
    };
    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => { show(current - 1); autoplay(); });
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => { show(current + 1); autoplay(); });
    dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.carouselDot)); autoplay(); }));
    carousel.addEventListener('mouseenter', () => window.clearInterval(timer));
    carousel.addEventListener('mouseleave', autoplay);
    show(0);
    if (slides.length > 1) autoplay();
  }

  const cart = document.querySelector('[data-cart]');
  const cartItems = document.querySelector('[data-cart-items]');
  const cartTotal = document.querySelector('[data-cart-total]');
  const cartCount = document.querySelector('[data-cart-count]');
  const cartStorageKey = 'lbp_site_cart';
  let items = [];
  try {
    const stored = JSON.parse(window.localStorage.getItem(cartStorageKey) || '[]');
    items = Array.isArray(stored) ? stored.filter((item) => item && typeof item.name === 'string') : [];
  } catch (_) {
    items = [];
  }
  const renderCart = () => {
    if (cartItems) {
      cartItems.innerHTML = items.length
        ? items.map((item) => `<div class="site-cart-item"><strong>${escapeHtml(item.name)}</strong><span>${formatPrice(item.price)} XOF</span></div>`).join('')
        : '<p>Votre sélection est vide.</p>';
    }
    if (cartCount) cartCount.textContent = String(items.length);
    if (cartTotal) cartTotal.textContent = `${formatPrice(items.reduce((sum, item) => sum + item.price, 0))} XOF`;
    document.querySelectorAll('[data-account-cart-count]').forEach((element) => {
      element.textContent = `${items.length} article${items.length > 1 ? 's' : ''}`;
    });
    window.localStorage.setItem(cartStorageKey, JSON.stringify(items));
  };
  document.querySelectorAll('[data-add-cart]').forEach((button) => {
    button.addEventListener('click', () => {
      items.push({ name: button.dataset.product || 'Offre', price: Number(button.dataset.price || 0) });
      renderCart();
      if (cart) cart.hidden = false;
      button.textContent = 'Ajouté ✓';
      window.setTimeout(() => { button.textContent = 'Ajouter'; }, 1300);
    });
  });
  document.querySelector('[data-cart-open]')?.addEventListener('click', () => { if (cart) cart.hidden = false; });
  document.querySelector('[data-cart-close]')?.addEventListener('click', () => { if (cart) cart.hidden = true; });
  renderCart();

  const search = document.querySelector('[data-agency-search]');
  const country = document.querySelector('[data-country-filter]');
  const cards = [...document.querySelectorAll('[data-agency-card]')];
  const markers = [...document.querySelectorAll('[data-map-marker]')];
  const count = document.querySelector('[data-agency-count]');
  const activate = (code) => {
    cards.forEach((card) => card.classList.toggle('is-active', card.dataset.code === code));
    markers.forEach((marker) => marker.classList.toggle('is-active', marker.dataset.code === code));
    cards.find((card) => card.dataset.code === code)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  };
  const filter = () => {
    const query = (search?.value || '').trim().toLowerCase();
    const selectedCountry = (country?.value || '').trim();
    let visible = 0;
    cards.forEach((card) => {
      const show = (!query || (card.dataset.search || '').includes(query))
        && (!selectedCountry || card.dataset.country === selectedCountry);
      card.style.display = show ? '' : 'none';
      const marker = markers.find((item) => item.dataset.code === card.dataset.code);
      if (marker) marker.style.display = show ? '' : 'none';
      if (show) visible += 1;
    });
    if (count) count.textContent = `${visible} agence(s)`;
  };
  cards.forEach((card) => card.addEventListener('click', () => activate(card.dataset.code)));
  markers.forEach((marker) => marker.addEventListener('click', () => activate(marker.dataset.code)));
  search?.addEventListener('input', filter);
  country?.addEventListener('change', filter);
  if (cards[0]) activate(cards[0].dataset.code);
  filter();

  const analytics = window.LBP_SITE_ANALYTICS;
  if (analytics?.endpoint) {
    let visitorId = localStorage.getItem('lbp_site_visitor');
    if (!visitorId) {
      visitorId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      localStorage.setItem('lbp_site_visitor', visitorId);
    }
    const sendEvent = (eventType, extra = {}) => {
      const payload = { visitor_id: visitorId, event_type: eventType, page_path: location.pathname + location.search,
        referrer: document.referrer, language: navigator.language || '',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        screen_size: `${screen.width}x${screen.height}`, ...extra };
      const body = JSON.stringify(payload);
      if (!navigator.sendBeacon?.(analytics.endpoint, new Blob([body], { type: 'application/json' }))) {
        fetch(analytics.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true });
      }
    };
    sendEvent('page_view');
    navigator.permissions?.query({ name: 'geolocation' }).then((permission) => {
      if (permission.state !== 'granted' || !navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition((position) => sendEvent('page_view', {
        target_key: 'authorized_location',
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      }), () => {}, { maximumAge: 86400000, timeout: 4000 });
    }).catch(() => {});
    document.addEventListener('click', (event) => {
      const target = event.target.closest('a,button');
      if (target) sendEvent('click', { target_key: target.dataset.analyticsKey || target.getAttribute('href') || target.type || 'button',
        target_label: (target.textContent || target.getAttribute('aria-label') || '').trim().slice(0, 255) });
    }, { capture: true });
  }
});

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  }[character]));
}

function formatPrice(value) {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value);
}
