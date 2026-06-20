document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll('a[href^="#"]');

  links.forEach((link) => {
    link.addEventListener("click", (event) => {
      const targetId = link.getAttribute("href");
      if (!targetId || targetId === "#") return;

      const target = document.querySelector(targetId);
      if (!target) return;

      event.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
});

// Filtrage multi-modules du portail via le select-search standard.
document.addEventListener("DOMContentLoaded", () => {
  const moduleSelect = document.querySelector("select[data-portal-module-filter]");
  const moduleCards = Array.from(document.querySelectorAll("[data-module-card]"));
  const countLabel = document.getElementById("moduleSearchCount");
  const emptyState = document.getElementById("moduleEmptyState");
  const resetButton = document.getElementById("moduleFilterReset");

  if (!moduleSelect || moduleCards.length === 0) return;

  const updateResults = () => {
    const selected = new Set(
      Array.from(moduleSelect.selectedOptions)
        .map((option) => option.value)
        .filter(Boolean)
    );
    let visibleCount = 0;

    moduleCards.forEach((card) => {
      const isVisible = selected.size === 0 || selected.has(card.dataset.moduleKey || "");
      card.hidden = !isVisible;
      if (isVisible) visibleCount += 1;
    });

    if (countLabel) {
      countLabel.textContent = `${visibleCount} module${visibleCount > 1 ? "s" : ""} disponible${visibleCount > 1 ? "s" : ""}`;
    }
    if (emptyState) emptyState.hidden = visibleCount !== 0;
    if (resetButton) resetButton.hidden = selected.size === 0;
  };

  moduleSelect.addEventListener("change", updateResults);
  resetButton?.addEventListener("click", () => {
    Array.from(moduleSelect.options).forEach((option) => {
      option.selected = false;
    });
    moduleSelect.dispatchEvent(new Event("change", { bubbles: true }));
  });

  updateResults();
});
