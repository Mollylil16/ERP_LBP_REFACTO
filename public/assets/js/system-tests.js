(() => {
  const config = window.ERP_HEALTH_TESTS || {};
  const moduleGrid = document.querySelector('.health-module-grid');
  const cards = Array.from(document.querySelectorAll('[data-health-module-card]'));
  const runAllButton = document.querySelector('[data-health-run-all]');
  const consoleBox = document.querySelector('[data-health-console]');
  const consoleTitle = document.querySelector('[data-health-console-title]');
  const consoleMsg = document.querySelector('[data-health-console-message]');
  const consoleProgress = document.querySelector('[data-health-progress]');
  const results = document.querySelector('[data-health-results]');
  const resultsTitle = document.querySelector('[data-health-results-title]');
  const resultsStatus = document.querySelector('[data-health-results-status]');
  const resultList = document.querySelector('[data-health-check-list]');
  const scoreEl = document.querySelector('[data-health-score]');
  const globalLabel = document.querySelector('[data-health-global-label]');
  const gaugeCaption = document.querySelector('[data-health-gauge-caption]');
  const modal = document.querySelector('[data-health-modal]');
  const modalTitle = document.querySelector('[data-health-modal-title]');
  const modalBody = document.querySelector('[data-health-modal-body]');
  const maintenanceModal = document.querySelector('[data-maintenance-modal]');
  const maintenanceModalMessage = document.querySelector('[data-maintenance-modal-message]');
  const maintenanceReasonInput = document.querySelector('[data-maintenance-reason-input]');
  const maintenanceConfirmButton = document.querySelector('[data-maintenance-confirm]');
  const reports = new Map();
  let running = false;
  let pendingMaintenanceInfo = null;

  const labels = {
    passed: 'OK',
    warning: 'À vérifier',
    failed: 'Échec',
    running: 'En cours',
    pending: 'En attente',
  };

  function moduleInfo(card) {
    return {
      card,
      slug: card.dataset.healthModuleCard || '',
      label: card.dataset.healthModuleLabel || card.dataset.healthModuleCard || 'Module',
      status: card.querySelector('[data-health-card-status]'),
      bar: card.querySelector('[data-health-card-bar]'),
      progress: card.querySelector('[data-health-card-progress]'),
    };
  }

  function statusClass(status) {
    return `health-pill health-pill-${status || 'warning'}`;
  }

  function setBusy(value) {
    running = value;
    if (runAllButton) {
      runAllButton.disabled = value;
      runAllButton.textContent = value ? 'Tests en cours…' : 'Lancer le test complet';
    }
    document.querySelectorAll('[data-health-run-module]').forEach(button => {
      button.disabled = value;
    });
    document.querySelectorAll('[data-maintenance-toggle]').forEach(button => {
      button.disabled = value;
    });
  }

  function showConsole(title, message) {
    if (!consoleBox) return;
    consoleBox.hidden = false;
    consoleTitle.textContent = title;
    consoleMsg.textContent = message;
  }

  function setGlobalProgress(percent, label, caption = 'Progression globale') {
    const value = Math.max(0, Math.min(100, Math.round(percent)));
    if (scoreEl) scoreEl.textContent = `${value}%`;
    const ring = scoreEl?.closest('.health-gauge__ring');
    if (ring) ring.style.setProperty('--score', value);
    if (globalLabel) globalLabel.textContent = label;
    if (gaugeCaption) gaugeCaption.textContent = caption;
    if (consoleProgress) consoleProgress.style.width = `${value}%`;
  }

  function setFocus(slug = null) {
    moduleGrid?.classList.toggle('is-running', Boolean(slug));
    cards.forEach(card => {
      const active = card.dataset.healthModuleCard === slug;
      card.classList.toggle('is-focus', active);
      card.classList.toggle('is-muted', Boolean(slug) && !active);
    });
    if (slug) {
      document.querySelector(`[data-health-module-card="${CSS.escape(slug)}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function setCardProgress(info, percent) {
    const value = Math.max(0, Math.min(100, Math.round(percent)));
    if (info.bar) info.bar.style.width = `${value}%`;
    if (info.progress) info.progress.textContent = `${value}%`;
  }

  function setCardStatus(info, status, text = '') {
    if (info.status) {
      info.status.className = statusClass(status);
      info.status.textContent = text || labels[status] || status;
    }
    info.card.classList.toggle('is-testing', status === 'running');
    info.card.classList.toggle('is-complete', ['passed', 'warning', 'failed'].includes(status));
  }

  function resetCards() {
    reports.clear();
    cards.forEach(card => {
      const info = moduleInfo(card);
      card.classList.remove('is-focus', 'is-muted', 'is-testing', 'is-complete');
      setCardProgress(info, 0);
      setCardStatus(info, 'pending', card.dataset.maintenance === '1' ? 'Maintenance • attente' : 'En attente');
    });
  }

  function simulateProgress(info, onProgress) {
    let value = 8;
    setCardProgress(info, value);
    onProgress(value);
    const timer = window.setInterval(() => {
      value = Math.min(88, value + Math.max(1, Math.round((90 - value) / 7)));
      setCardProgress(info, value);
      onProgress(value);
    }, 650);

    return () => window.clearInterval(timer);
  }

  async function requestModule(slug) {
    const body = new URLSearchParams();
    body.set('_csrf_token', config.csrfToken || '');
    const response = await fetch(
      `${config.endpoints.runModule}${encodeURIComponent(slug)}`,
      {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': config.csrfToken || '' },
        body,
      }
    );
    const payload = await response.json();
    if (!response.ok && !payload.status) {
      throw new Error(payload.message || `Erreur HTTP ${response.status}`);
    }
    return payload;
  }

  function errorPayload(slug, error) {
    return {
      scope: 'module',
      module: slug,
      status: 'failed',
      score: 0,
      checks: [{
        name: 'Erreur interface ou réseau',
        status: 'failed',
        message: error?.message || 'Erreur inconnue',
        details: { stack: error?.stack || '' },
      }],
    };
  }

  function completeCard(info, payload) {
    setCardProgress(info, 100);
    setCardStatus(
      info,
      payload.status || 'failed',
      `${info.card.dataset.maintenance === '1' ? 'Maintenance • ' : ''}${labels[payload.status] || payload.status || 'Échec'} • ${Number(payload.score || 0)}%`
    );
  }

  function globalStatus(payloads) {
    if (payloads.some(payload => payload.status === 'failed')) return 'failed';
    if (payloads.some(payload => payload.status === 'warning')) return 'warning';
    return 'passed';
  }

  function renderModuleReport(info, payload) {
    reports.set(info.slug, { info, payload });
    results.hidden = false;
    resultsTitle.textContent = `Résultat du module : ${info.label}`;
    resultsStatus.className = statusClass(payload.status);
    resultsStatus.textContent = `${labels[payload.status] || payload.status} • ${Number(payload.score || 0)}%`;
    resultList.innerHTML = '';

    (payload.checks || []).forEach((check, index) => {
      const item = document.createElement('article');
      item.className = 'health-check';
      item.innerHTML = `
        <div>
          <span class="${statusClass(check.status)}">${escapeHtml(labels[check.status] || check.status)}</span>
          <h4>${escapeHtml(check.name || 'Contrôle')}</h4>
          <p>${escapeHtml(check.message || '')}</p>
        </div>
        <button type="button" data-health-report-module="${escapeHtml(info.slug)}" data-health-check-index="${index}">Voir détails</button>
      `;
      resultList.appendChild(item);
    });
  }

  function renderGlobalReport() {
    const entries = Array.from(reports.values());
    const payloads = entries.map(entry => entry.payload);
    const status = globalStatus(payloads);
    const score = payloads.length
      ? Math.round(payloads.reduce((sum, payload) => sum + Number(payload.score || 0), 0) / payloads.length)
      : 0;

    results.hidden = false;
    resultsTitle.textContent = 'Rapport global par module';
    resultsStatus.className = statusClass(status);
    resultsStatus.textContent = `${labels[status]} • score moyen ${score}%`;
    resultList.innerHTML = '';
    resultList.className = 'health-check-list health-global-report';

    entries.forEach(({ info, payload }) => {
      const failed = (payload.checks || []).filter(check => check.status === 'failed').length;
      const warning = (payload.checks || []).filter(check => check.status === 'warning').length;
      const item = document.createElement('article');
      item.className = `health-module-report health-module-report--${payload.status || 'failed'}`;
      item.innerHTML = `
        <div class="health-module-report__identity">
          <span class="${statusClass(payload.status)}">${escapeHtml(labels[payload.status] || payload.status)}</span>
          <div><strong>${escapeHtml(info.label)}${info.card.dataset.maintenance === '1' ? ' • Maintenance' : ''}</strong><small>${(payload.checks || []).length} contrôle(s) • ${failed} échec(s) • ${warning} alerte(s)</small></div>
        </div>
        <strong class="health-module-report__score">${Number(payload.score || 0)}%</strong>
        <button type="button" data-health-report-module="${escapeHtml(info.slug)}">Rapport détaillé</button>
      `;
      resultList.appendChild(item);
    });

    setGlobalProgress(100, 'Tests terminés', `Score santé moyen : ${score}%`);
    showConsole(
      'Test complet terminé',
      `${entries.length} module(s) testés. Score santé moyen : ${score}%.`
    );
  }

  async function runOne(info, options = {}) {
    const { index = 0, total = 1, standalone = false } = options;
    setFocus(info.slug);
    setCardStatus(info, 'running', 'En cours • 0%');
    showConsole(
      standalone ? `Test du module ${info.label}` : `Module ${index + 1}/${total} • ${info.label}`,
      'Les contrôles serveur sont en cours. Le module suivant démarrera automatiquement.'
    );

    const stopSimulation = simulateProgress(info, localProgress => {
      setCardStatus(info, 'running', `En cours • ${Math.round(localProgress)}%`);
      const globalProgress = standalone
        ? localProgress
        : ((index + localProgress / 100) / total) * 100;
      setGlobalProgress(
        globalProgress,
        standalone ? info.label : `${index + 1}/${total} • ${info.label}`,
        standalone ? 'Progression du module' : 'Progression globale des modules'
      );
    });

    let payload;
    try {
      payload = await requestModule(info.slug);
    } catch (error) {
      payload = errorPayload(info.slug, error);
    } finally {
      stopSimulation();
    }

    completeCard(info, payload);
    reports.set(info.slug, { info, payload });
    return payload;
  }

  async function runAllSequentially() {
    if (running || cards.length === 0) return;
    setBusy(true);
    resetCards();
    results.hidden = true;
    resultList.className = 'health-check-list';
    setGlobalProgress(0, 'Préparation', `0/${cards.length} module`);
    showConsole('Préparation du test complet', `${cards.length} modules seront testés à tour de rôle.`);

    const infos = cards.map(moduleInfo);
    for (let index = 0; index < infos.length; index += 1) {
      await runOne(infos[index], { index, total: infos.length });
      setGlobalProgress(
        ((index + 1) / infos.length) * 100,
        `${index + 1}/${infos.length} module(s) terminé(s)`,
        'Progression globale des modules'
      );
    }

    setFocus(null);
    renderGlobalReport();
    setBusy(false);
  }

  async function runStandalone(info) {
    if (running) return;
    setBusy(true);
    reports.clear();
    results.hidden = true;
    resultList.className = 'health-check-list';
    setCardProgress(info, 0);
    const payload = await runOne(info, { standalone: true });
    setFocus(null);
    renderModuleReport(info, payload);
    setGlobalProgress(100, 'Test terminé', `Score du module : ${Number(payload.score || 0)}%`);
    showConsole(`Test terminé • ${info.label}`, `Score obtenu : ${Number(payload.score || 0)}%.`);
    setBusy(false);
  }

  function closeMaintenanceModal() {
    if (maintenanceModal) maintenanceModal.hidden = true;
    pendingMaintenanceInfo = null;
  }

  function openMaintenanceModal(info) {
    pendingMaintenanceInfo = info;
    if (maintenanceModalMessage) {
      maintenanceModalMessage.textContent = `Le module ${info.label} deviendra temporairement inaccessible. Indiquez la raison affichée aux utilisateurs.`;
    }
    if (maintenanceReasonInput) {
      maintenanceReasonInput.value = info.card.dataset.maintenanceReason || '';
      maintenanceReasonInput.setCustomValidity('');
    }
    if (maintenanceModal) maintenanceModal.hidden = false;
    window.setTimeout(() => maintenanceReasonInput?.focus(), 0);
  }

  async function toggleMaintenance(info, reason = '') {
    if (running) return;
    const currentlyMaintained = info.card.dataset.maintenance === '1';
    if (!currentlyMaintained && reason.length < 5) {
      maintenanceReasonInput?.focus();
      showConsole('Motif requis', 'Indiquez la raison de la maintenance avant de désactiver le module.');
      return;
    }

    const button = info.card.querySelector('[data-maintenance-toggle]');
    button.disabled = true;
    const body = new URLSearchParams();
    body.set('_csrf_token', config.csrfToken || '');
    body.set('maintenance', currentlyMaintained ? '0' : '1');
    body.set('reason', reason);

    try {
      const response = await fetch(
        `${config.endpoints.maintenance}${encodeURIComponent(info.slug)}`,
        { method: 'POST', headers: { 'X-CSRF-TOKEN': config.csrfToken || '' }, body }
      );
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.message || 'Impossible de modifier le mode maintenance.');
      }
      const maintenance = Boolean(payload.state?.is_maintenance);
      const savedReason = payload.state?.reason || '';
      info.card.dataset.maintenance = maintenance ? '1' : '0';
      info.card.dataset.maintenanceReason = savedReason;
      info.card.classList.toggle('is-maintenance', maintenance);
      const display = info.card.querySelector('[data-maintenance-reason-display]');
      if (display) {
        display.textContent = savedReason;
        display.hidden = !maintenance;
      }
      button.textContent = maintenance ? 'Remettre en service' : 'Mode maintenance';
      setCardStatus(info, maintenance ? 'maintenance' : 'pending', maintenance ? 'Maintenance' : 'En attente');
      showConsole(
        maintenance ? `${info.label} est en maintenance` : `${info.label} est de nouveau disponible`,
        maintenance ? savedReason : 'Le module est accessible depuis le portail.'
      );
    } catch (error) {
      showConsole('Modification impossible', error.message);
    } finally {
      button.disabled = false;
    }
  }

  function openDetails(title, payload) {
    modalTitle.textContent = title || 'Détails';
    modalBody.textContent = JSON.stringify(payload || {}, null, 2);
    modal.hidden = false;
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#039;',
      '"': '&quot;',
    }[char]));
  }

  runAllButton?.addEventListener('click', runAllSequentially);

  cards.forEach(card => {
    const info = moduleInfo(card);
    card.querySelector('[data-health-run-module]')?.addEventListener('click', () => runStandalone(info));
    card.querySelector('[data-health-open-details]')?.addEventListener('click', () => {
      const report = reports.get(info.slug);
      openDetails(
        report ? `Rapport • ${info.label}` : `Module ${info.label}`,
        report?.payload || { message: 'Lancez le test de ce module pour afficher son rapport.' }
      );
    });
    card.querySelector('[data-maintenance-toggle]')?.addEventListener('click', () => {
      if (info.card.dataset.maintenance === '1') {
        toggleMaintenance(info);
        return;
      }
      openMaintenanceModal(info);
    });
  });

  resultList?.addEventListener('click', event => {
    const button = event.target.closest('[data-health-report-module]');
    if (!button) return;
    const report = reports.get(button.dataset.healthReportModule);
    if (!report) return;
    const checkIndex = button.dataset.healthCheckIndex;
    const payload = checkIndex === undefined
      ? report.payload
      : report.payload.checks?.[Number(checkIndex)];
    openDetails(
      checkIndex === undefined
        ? `Rapport • ${report.info.label}`
        : payload?.name || `Contrôle • ${report.info.label}`,
      payload
    );
  });

  document.querySelectorAll('[data-health-close-modal]').forEach(button => {
    button.addEventListener('click', () => {
      modal.hidden = true;
    });
  });

  document.querySelectorAll('[data-maintenance-close]').forEach(button => {
    button.addEventListener('click', closeMaintenanceModal);
  });

  maintenanceConfirmButton?.addEventListener('click', async () => {
    if (!pendingMaintenanceInfo) return;
    const reason = maintenanceReasonInput?.value?.trim() || '';
    if (reason.length < 5) {
      maintenanceReasonInput?.focus();
      maintenanceReasonInput?.setCustomValidity('Le motif doit contenir au moins 5 caractères.');
      maintenanceReasonInput?.reportValidity();
      return;
    }

    maintenanceReasonInput?.setCustomValidity('');
    maintenanceConfirmButton.disabled = true;
    const info = pendingMaintenanceInfo;
    await toggleMaintenance(info, reason);
    maintenanceConfirmButton.disabled = false;
    if (info.card.dataset.maintenance === '1') closeMaintenanceModal();
  });
})();
