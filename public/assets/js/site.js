document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('[data-site-menu]');
  const nav = document.querySelector('[data-site-nav]');
  menu?.addEventListener('click', () => nav?.classList.toggle('is-open'));

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
  const items = [];
  const renderCart = () => {
    if (cartItems) {
      cartItems.innerHTML = items.length
        ? items.map((item) => `<div class="site-cart-item"><strong>${escapeHtml(item.name)}</strong><span>${formatPrice(item.price)} XOF</span></div>`).join('')
        : '<p>Votre sélection est vide.</p>';
    }
    if (cartCount) cartCount.textContent = String(items.length);
    if (cartTotal) cartTotal.textContent = `${formatPrice(items.reduce((sum, item) => sum + item.price, 0))} XOF`;
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
});

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  }[character]));
}

function formatPrice(value) {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value);
}
