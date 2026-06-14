(() => {
  const norm = (value) => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();

  function optionLabel(option) {
    return option.dataset.searchLabel || option.textContent || '';
  }

  function optionMeta(option) {
    return option.dataset.searchMeta || '';
  }

  function enhanceSelect(select) {
    if (select.dataset.enhancedSelect === 'true') return;
    select.dataset.enhancedSelect = 'true';
    select.classList.add('finea-select-native-hidden');

    const multiple = select.multiple || select.dataset.multiple === 'true';
    const root = document.createElement('div');
    root.className = 'finea-select-search';
    root.dataset.name = select.name;

    const control = document.createElement('div');
    control.className = 'finea-select-search-control';
    control.setAttribute('role', 'combobox');
    control.setAttribute('aria-expanded', 'false');
    control.tabIndex = -1;

    const input = document.createElement('input');
    input.className = 'finea-select-search-input';
    input.type = 'text';
    input.autocomplete = 'off';
    input.placeholder = select.dataset.placeholder || select.getAttribute('placeholder') || 'Rechercher ou sélectionner...';

    const arrow = document.createElement('span');
    arrow.className = 'finea-select-search-arrow';
    arrow.textContent = '⌄';

    const menu = document.createElement('div');
    menu.className = 'finea-select-search-menu';

    select.parentNode.insertBefore(root, select.nextSibling);
    root.appendChild(control);
    control.appendChild(input);
    control.appendChild(arrow);
    root.appendChild(menu);

    const realOptions = () => Array.from(select.options).filter((option) => option.value !== '');
    const selected = () => realOptions().filter((option) => option.selected);

    function clearBadges() {
      Array.from(control.querySelectorAll('.finea-select-badge')).forEach((element) => element.remove());
    }

    function syncControlText() {
      clearBadges();
      if (multiple) {
        selected().forEach((option) => {
          const badge = document.createElement('span');
          badge.className = 'finea-select-badge';
          const text = document.createElement('span');
          text.textContent = optionLabel(option).trim();
          const remove = document.createElement('button');
          remove.type = 'button';
          remove.setAttribute('aria-label', 'Retirer ' + text.textContent);
          remove.textContent = '×';
          remove.addEventListener('click', (event) => {
            event.stopPropagation();
            option.selected = false;
            input.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            render();
          });
          badge.appendChild(text);
          badge.appendChild(remove);
          control.insertBefore(badge, input);
        });
        input.value = '';
        input.placeholder = selected().length ? 'Ajouter une sélection...' : (select.dataset.placeholder || 'Rechercher ou sélectionner...');
      } else if (!root.classList.contains('is-open')) {
        const current = selected()[0];
        input.value = current ? optionLabel(current).trim() : '';
      }
    }

    function addOptionButton(option) {
      const label = optionLabel(option);
      const meta = optionMeta(option);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'finea-select-search-option' + (option.selected ? ' is-selected' : '');

      const strong = document.createElement('strong');
      strong.textContent = label;
      button.appendChild(strong);
      if (meta) {
        const small = document.createElement('small');
        small.textContent = meta;
        button.appendChild(small);
      }
      button.addEventListener('click', () => {
        if (multiple) {
          option.selected = !option.selected;
          input.value = '';
        } else {
          realOptions().forEach((item) => { item.selected = false; });
          option.selected = true;
          close();
        }
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncControlText();
        if (multiple) render();
      });
      menu.appendChild(button);
    }

    function render() {
      const query = norm(input.value);
      menu.innerHTML = '';
      let count = 0;
      realOptions().forEach((option) => {
        const searchable = `${optionLabel(option)} ${optionMeta(option)}`;
        if (query && !norm(searchable).includes(query)) return;
        count += 1;
        addOptionButton(option);
      });
      if (count === 0) {
        const empty = document.createElement('div');
        empty.className = 'finea-select-search-empty';
        empty.textContent = 'Aucun résultat';
        menu.appendChild(empty);
      }
      if (multiple) syncControlText();
    }

    function open() {
      root.classList.add('is-open');
      control.setAttribute('aria-expanded', 'true');
      if (!multiple) input.value = '';
      render();
    }

    function close() {
      root.classList.remove('is-open');
      control.setAttribute('aria-expanded', 'false');
      syncControlText();
    }

    input.addEventListener('focus', open);
    input.addEventListener('input', () => { if (!root.classList.contains('is-open')) open(); else render(); });
    control.addEventListener('click', () => { input.focus(); open(); });
    document.addEventListener('click', (event) => { if (!root.contains(event.target)) close(); });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        menu.querySelector('button')?.focus();
      }
    });
    menu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { input.focus(); close(); }
    });
    syncControlText();
  }

  function enhanceDropzone(zone) {
    const input = zone.querySelector('input[type="file"]');
    const preview = zone.querySelector('[data-file-preview]');
    if (!input || zone.dataset.enhancedDropzone === 'true') return;
    zone.dataset.enhancedDropzone = 'true';
    const show = () => {
      if (!preview) return;
      const names = Array.from(input.files || []).map((file) => file.name);
      preview.textContent = names.length ? names.join(', ') : '';
    };
    ['dragenter', 'dragover'].forEach((eventName) => zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.add('is-dragover');
    }));
    ['dragleave', 'drop'].forEach((eventName) => zone.addEventListener(eventName, () => zone.classList.remove('is-dragover')));
    zone.addEventListener('drop', (event) => {
      event.preventDefault();
      input.files = event.dataTransfer.files;
      show();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    input.addEventListener('change', show);
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-select-search]').forEach(enhanceSelect);
    document.querySelectorAll('[data-dropzone]').forEach(enhanceDropzone);
  });
})();
