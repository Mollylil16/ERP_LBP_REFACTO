document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("moduleSidebar");
  const menuButton = document.querySelector("[data-module-menu]");

  menuButton?.addEventListener("click", () => {
    sidebar?.classList.toggle("is-open");
  });

  document.querySelectorAll("[data-coming-soon]").forEach((link) => {
    link.addEventListener("click", (event) => event.preventDefault());
  });
});

// RH UX: select-search leger + drag & drop avec apercu.
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("select[data-select-search]").forEach((select) => {
    if (select.dataset.enhanced === "1") return;
    select.dataset.enhanced = "1";
    const wrapper = document.createElement("div");
    wrapper.className = "rh-select-search";
    const search = document.createElement("input");
    search.type = "search";
    search.className = "finea-input rh-select-search-input";
    search.placeholder = "Rechercher...";
    select.parentNode?.insertBefore(wrapper, select);
    wrapper.appendChild(search);
    wrapper.appendChild(select);
    const options = Array.from(select.options).map((option) => ({
      value: option.value,
      text: option.textContent || "",
      selected: option.selected,
    }));
    search.addEventListener("input", () => {
      const term = search.value.trim().toLowerCase();
      const currentValue = select.value;
      select.innerHTML = "";
      options
        .filter((option) => option.value === "" || option.text.toLowerCase().includes(term))
        .forEach((option) => {
          const node = new Option(option.text, option.value, false, option.value === currentValue);
          select.add(node);
        });
    });
  });

  const previewFile = (input) => {
    const zone = input.closest("[data-dropzone]");
    const preview = zone?.querySelector("[data-file-preview]");
    const file = input.files?.[0];
    if (!preview || !file) return;
    if (file.type.startsWith("image/")) {
      const reader = new FileReader();
      reader.onload = () => {
        preview.innerHTML = `<img src="${reader.result}" alt="Aperçu"><span>${file.name}</span>`;
      };
      reader.readAsDataURL(file);
    } else {
      preview.textContent = file.name;
    }
    zone?.classList.add("has-file");
  };

  document.querySelectorAll("[data-dropzone]").forEach((zone) => {
    const input = zone.querySelector('input[type="file"]');
    if (!input) return;
    ["dragenter", "dragover"].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.add("is-dragging");
      });
    });
    ["dragleave", "drop"].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.remove("is-dragging");
      });
    });
    zone.addEventListener("drop", (event) => {
      const file = event.dataTransfer?.files?.[0];
      if (!file) return;
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      previewFile(input);
    });
    input.addEventListener("change", () => previewFile(input));
  });

  const childrenInput = document.querySelector("[data-children-count]");
  const childrenContainer = document.querySelector("[data-child-documents]");
  const renderChildren = () => {
    if (!childrenInput || !childrenContainer) return;
    const count = Math.max(0, Math.min(20, parseInt(childrenInput.value || "0", 10)));
    childrenContainer.innerHTML = "";
    if (count === 0) return;
    const title = document.createElement("h3");
    title.textContent = "Extraits de naissance des enfants";
    childrenContainer.appendChild(title);
    for (let i = 1; i <= count; i += 1) {
      const label = document.createElement("label");
      label.className = "rh-dropzone rh-child-dropzone";
      label.setAttribute("data-dropzone", "");
      label.innerHTML = `
        <input type="file" name="child_birth_certificates[]" accept="image/*,.pdf" required>
        <strong>Enfant ${i}</strong>
        <span>Extrait de naissance obligatoire pour cet enfant.</span>
        <div class="rh-file-preview" data-file-preview></div>`;
      childrenContainer.appendChild(label);
      const input = label.querySelector('input[type="file"]');
      input.addEventListener("change", () => previewFile(input));
    }
  };
  childrenInput?.addEventListener("input", renderChildren);
  renderChildren();
});
