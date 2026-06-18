(() => {
  const config = window.ERP_HEALTH_TESTS || {};
  const consoleBox = document.querySelector('[data-health-console]');
  const consoleTitle = document.querySelector('[data-health-console-title]');
  const consoleMsg = document.querySelector('[data-health-console-message]');
  const progress = document.querySelector('[data-health-progress]');
  const results = document.querySelector('[data-health-results]');
  const resultsTitle = document.querySelector('[data-health-results-title]');
  const resultsStatus = document.querySelector('[data-health-results-status]');
  const list = document.querySelector('[data-health-check-list]');
  const scoreEl = document.querySelector('[data-health-score]');
  const globalLabel = document.querySelector('[data-health-global-label]');
  const modal = document.querySelector('[data-health-modal]');
  const modalTitle = document.querySelector('[data-health-modal-title]');
  const modalBody = document.querySelector('[data-health-modal-body]');
  let lastChecks = [];

  const label = { passed: 'OK', warning: 'À vérifier', failed: 'Échec' };

  function showConsole(title, message) {
    consoleBox.hidden = false;
    consoleTitle.textContent = title;
    consoleMsg.textContent = message;
    progress.style.width = '18%';
  }

  function updateGlobal(payload) {
    if (!scoreEl) return;
    scoreEl.textContent = `${payload.score || 0}%`;
    const ring = scoreEl.closest('.health-gauge__ring');
    if (ring) ring.style.setProperty('--score', payload.score || 0);
    if (globalLabel) globalLabel.textContent = payload.status === 'passed' ? 'Très stable' : (payload.status === 'warning' ? 'À surveiller' : 'Critique');
  }

  function statusClass(status) { return `health-pill health-pill-${status || 'warning'}`; }

  function renderResult(payload) {
    lastChecks = payload.checks || [];
    results.hidden = false;
    resultsTitle.textContent = payload.scope === 'module' ? `Résultat module : ${payload.module}` : 'Résultat application complète';
    resultsStatus.className = statusClass(payload.status);
    resultsStatus.textContent = `${label[payload.status] || payload.status} • ${payload.score || 0}%`;
    list.innerHTML = '';

    lastChecks.forEach((check, index) => {
      const item = document.createElement('article');
      item.className = 'health-check';
      item.innerHTML = `
        <div>
          <span class="${statusClass(check.status)}">${label[check.status] || check.status}</span>
          <h4>${escapeHtml(check.name || 'Contrôle')}</h4>
          <p>${escapeHtml(check.message || '')}</p>
        </div>
        <button type="button" data-health-detail-index="${index}">Voir détails</button>
      `;
      list.appendChild(item);
    });

    document.querySelectorAll('[data-health-detail-index]').forEach(button => {
      button.addEventListener('click', () => openDetails(lastChecks[Number(button.dataset.healthDetailIndex)]));
    });

    updateGlobal(payload);
  }

  function updateCard(module, payload) {
    const card = document.querySelector(`[data-health-module-card="${CSS.escape(module)}"]`);
    if (!card) return;
    const status = card.querySelector('[data-health-card-status]');
    const bar = card.querySelector('[data-health-card-bar]');
    if (status) {
      status.className = statusClass(payload.status);
      status.textContent = `${label[payload.status] || payload.status} • ${payload.score || 0}%`;
    }
    if (bar) bar.style.width = `${payload.score || 0}%`;
  }

  async function post(url, title, module = null) {
    showConsole(title, 'Exécution des contrôles côté serveur. La sortie détaillée apparaîtra ici en cas d’erreur.');
    results.hidden = true;
    const body = new URLSearchParams();
    body.set('_csrf_token', config.csrfToken || '');
    try {
      const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': config.csrfToken || '' }, body });
      const payload = await response.json();
      progress.style.width = '100%';
      consoleMsg.textContent = payload.status === 'failed' ? 'Un problème a été détecté.' : 'Contrôle terminé.';
      renderResult(payload);
      if (module) updateCard(module, payload);
    } catch (error) {
      progress.style.width = '100%';
      const payload = { scope: 'client', module: module || 'application', status: 'failed', score: 0, checks: [{ name: 'Erreur interface', status: 'failed', message: error.message, details: { stack: error.stack } }] };
      renderResult(payload);
    }
  }

  function openDetails(check) {
    modalTitle.textContent = check?.name || 'Détails';
    modalBody.textContent = JSON.stringify(check || {}, null, 2);
    modal.hidden = false;
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  }

  document.querySelector('[data-health-run-all]')?.addEventListener('click', () => post(config.endpoints.runAll, 'Test complet ERP LBP'));
  document.querySelectorAll('[data-health-run-module]').forEach(button => {
    button.addEventListener('click', () => {
      const module = button.dataset.healthRunModule;
      post(`${config.endpoints.runModule}${encodeURIComponent(module)}`, `Test du module ${module}`, module);
    });
  });
  document.querySelectorAll('[data-health-open-details]').forEach(button => {
    button.addEventListener('click', () => {
      openDetails({ name: `Module ${button.dataset.healthOpenDetails}`, message: 'Lancez le test du module pour afficher le rapport détaillé.', details: {} });
    });
  });
  document.querySelectorAll('[data-health-close-modal]').forEach(button => button.addEventListener('click', () => modal.hidden = true));
})();
