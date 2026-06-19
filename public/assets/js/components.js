(() => {
  const initSelectSearch = (select) => {
    if (select.dataset.fineaEnhanced === "1") return;
    select.dataset.fineaEnhanced = "1";
    select.classList.add("finea-native-select");

    const multiple = select.multiple || select.dataset.multiple === "1";
    const placeholder = select.dataset.placeholder || "Rechercher et sélectionner...";
    const wrapper = document.createElement("div");
    wrapper.className = "finea-select-search";

    const control = document.createElement("div");
    control.className = "finea-select-control";
    control.tabIndex = 0;

    const input = document.createElement("input");
    input.type = "search";
    input.className = "finea-select-input";
    input.placeholder = placeholder;
    input.autocomplete = "off";

    const menu = document.createElement("div");
    menu.className = "finea-select-menu";

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    wrapper.appendChild(control);
    control.appendChild(input);
    wrapper.appendChild(menu);

    const source = Array.from(select.options).map((option) => ({ value: option.value, label: option.textContent || "" }));

    const selectedValues = () => Array.from(select.selectedOptions).map((option) => option.value).filter((value) => value !== "");
    const setSelected = (value, selected) => {
      Array.from(select.options).forEach((option) => {
        if (option.value === value) option.selected = selected;
      });
      select.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const renderBadges = () => {
      control.querySelectorAll(".finea-select-badge").forEach((badge) => badge.remove());
      if (!multiple) return;
      selectedValues().forEach((value) => {
        const item = source.find((option) => option.value === value);
        const badge = document.createElement("span");
        badge.className = "finea-select-badge";
        badge.textContent = item?.label || value;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.textContent = "×";
        remove.addEventListener("click", (event) => {
          event.stopPropagation();
          setSelected(value, false);
          renderBadges();
          renderMenu();
        });
        badge.appendChild(remove);
        control.insertBefore(badge, input);
      });
    };

    const renderSingleLabel = () => {
      if (multiple) return;
      const selected = select.selectedOptions[0];
      input.placeholder = selected && selected.value !== "" ? selected.textContent : placeholder;
    };

    const renderMenu = () => {
      const term = input.value.trim().toLowerCase();
      const selected = new Set(selectedValues());
      const items = source.filter((option) => {
        if (multiple && selected.has(option.value)) return false;
        return option.label.toLowerCase().includes(term);
      });
      menu.innerHTML = "";
      if (!items.length) {
        const empty = document.createElement("div");
        empty.className = "finea-select-empty";
        empty.textContent = "Aucun résultat";
        menu.appendChild(empty);
        return;
      }
      items.forEach((option) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "finea-select-option";
        button.textContent = option.label;
        if (!multiple && select.value === option.value) button.classList.add("is-active");
        button.addEventListener("click", () => {
          if (multiple) {
            setSelected(option.value, true);
            input.value = "";
            renderBadges();
            renderMenu();
            input.focus();
          } else {
            select.value = option.value;
            select.dispatchEvent(new Event("change", { bubbles: true }));
            input.value = "";
            renderSingleLabel();
            wrapper.classList.remove("is-open");
          }
        });
        menu.appendChild(button);
      });
    };

    const open = () => { wrapper.classList.add("is-open"); renderMenu(); };
    control.addEventListener("click", () => { input.focus(); open(); });
    input.addEventListener("focus", open);
    input.addEventListener("input", renderMenu);
    document.addEventListener("click", (event) => { if (!wrapper.contains(event.target)) wrapper.classList.remove("is-open"); });
    select.addEventListener("change", () => { renderBadges(); renderSingleLabel(); renderMenu(); });
    renderBadges();
    renderSingleLabel();
    renderMenu();
  };

  const previewFile = (input) => {
    const zone = input.closest("[data-finea-dropzone]");
    const preview = zone?.querySelector("[data-finea-file-preview]");
    const files = Array.from(input.files || []);
    if (!preview || files.length === 0) return;
    const file = files[0];
    if (file.type.startsWith("image/")) {
      const reader = new FileReader();
      reader.onload = () => { preview.innerHTML = `<img src="${reader.result}" alt="Aperçu"><span>${file.name}</span>`; };
      reader.readAsDataURL(file);
    } else {
      preview.textContent = files.map((item) => item.name).join(", ");
    }
    zone?.classList.add("has-file");
  };

  const initDropzone = (zone) => {
    if (zone.dataset.fineaDropzoneEnhanced === "1") return;
    zone.dataset.fineaDropzoneEnhanced = "1";
    const input = zone.querySelector('input[type="file"]');
    if (!input) return;
    ["dragenter", "dragover"].forEach((eventName) => zone.addEventListener(eventName, (event) => { event.preventDefault(); zone.classList.add("is-dragging"); }));
    ["dragleave", "drop"].forEach((eventName) => zone.addEventListener(eventName, (event) => { event.preventDefault(); zone.classList.remove("is-dragging"); }));
    zone.addEventListener("drop", (event) => {
      const files = event.dataTransfer?.files;
      if (!files?.length) return;
      const dt = new DataTransfer();
      Array.from(files).slice(0, input.multiple ? files.length : 1).forEach((file) => dt.items.add(file));
      input.files = dt.files;
      previewFile(input);
    });
    input.addEventListener("change", () => previewFile(input));
  };

  const init = () => {
    document.querySelectorAll("select[data-finea-select-search], select[data-select-search]").forEach(initSelectSearch);
    document.querySelectorAll("[data-finea-dropzone], [data-dropzone]").forEach(initDropzone);
    document.querySelectorAll("[data-color-picker]").forEach((picker) => {
      const input = picker.querySelector("[data-color-input]");
      const output = picker.querySelector("[data-color-output]");
      const swatches = [...picker.querySelectorAll("[data-color-value]")];
      if (!input || input.dataset.colorEnhanced === "1") return;
      input.dataset.colorEnhanced = "1";
      const sync = (value, notify = false) => {
        input.value = value;
        if (output) output.textContent = value.toUpperCase();
        swatches.forEach((swatch) => swatch.classList.toggle("is-selected", swatch.dataset.colorValue === value.toLowerCase()));
        if (notify) input.dispatchEvent(new Event("change", { bubbles: true }));
      };
      input.addEventListener("input", () => sync(input.value));
      swatches.forEach((swatch) => swatch.addEventListener("click", () => sync(swatch.dataset.colorValue, true)));
      sync(input.value);
    });
    document.querySelectorAll("[data-nav-group-toggle]").forEach((button) => {
      if (button.dataset.navigationEnhanced === "1") return;
      button.dataset.navigationEnhanced = "1";
      button.addEventListener("click", () => {
        const group = button.closest("[data-nav-group]");
        const collapsed = group?.classList.toggle("is-collapsed") ?? false;
        button.setAttribute("aria-expanded", collapsed ? "false" : "true");
      });
    });
    document.querySelector(".module-nav-link.is-active")?.scrollIntoView({ block: "nearest" });
    document.querySelectorAll("[data-modal-open]").forEach((button) => {
      if (button.dataset.modalEnhanced === "1") return;
      button.dataset.modalEnhanced = "1";
      button.addEventListener("click", () => document.getElementById(button.dataset.modalOpen)?.showModal());
    });
    document.querySelectorAll("[data-modal]").forEach((modal) => {
      if (modal.dataset.modalEnhanced === "1") return;
      modal.dataset.modalEnhanced = "1";
      modal.querySelectorAll("[data-modal-close]").forEach((button) => button.addEventListener("click", () => modal.close()));
      modal.addEventListener("click", (event) => {
        if (event.target === modal) modal.close();
      });
    });
  };

  document.addEventListener("DOMContentLoaded", init);
  window.FineaComponents = { init };
})();
