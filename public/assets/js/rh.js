document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("moduleSidebar");
  const menuButton = document.querySelector("[data-module-menu]");

  menuButton?.addEventListener("click", () => {
    sidebar?.classList.toggle("is-open");
  });

  document.querySelectorAll("[data-coming-soon]").forEach((link) => {
    link.addEventListener("click", (event) => event.preventDefault());
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
      label.className = "finea-dropzone finea-child-dropzone";
      label.setAttribute("data-finea-dropzone", "");
      label.innerHTML = `
        <input type="file" name="child_birth_certificates[]" accept="image/*,.pdf" required>
        <span class="finea-dropzone-icon">⇪</span>
        <strong>Enfant ${i}</strong>
        <span>Extrait de naissance obligatoire pour cet enfant.</span>
        <div class="finea-file-preview" data-finea-file-preview></div>`;
      childrenContainer.appendChild(label);
    }
    window.FineaComponents?.init?.();
  };
  childrenInput?.addEventListener("input", renderChildren);
  renderChildren();
});
