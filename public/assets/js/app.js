document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll('a[href^="#"]');

  links.forEach((link) => {
    link.addEventListener("click", (event) => {
      const targetId = link.getAttribute("href");

      if (!targetId || targetId === "#") {
        return;
      }

      const target = document.querySelector(targetId);

      if (!target) {
        return;
      }

      event.preventDefault();

      target.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    });
  });
});

// Recherche instantanée des modules du portail.
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("moduleSearchInput");
  const moduleCards = Array.from(document.querySelectorAll("[data-module-card]"));
  const countLabel = document.getElementById("moduleSearchCount");
  const emptyState = document.getElementById("moduleEmptyState");

  if (!searchInput || moduleCards.length === 0) {
    return;
  }

  const normalize = (value) =>
    value
      .toString()
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim();

  const updateResults = () => {
    const query = normalize(searchInput.value);
    let visibleCount = 0;

    moduleCards.forEach((card) => {
      const searchableText = normalize(card.dataset.search || "");
      const isVisible = query === "" || searchableText.includes(query);

      card.hidden = !isVisible;
      if (isVisible) visibleCount += 1;
    });

    if (countLabel) {
      countLabel.textContent = `${visibleCount} module${visibleCount > 1 ? "s" : ""} disponible${visibleCount > 1 ? "s" : ""}`;
    }

    if (emptyState) {
      emptyState.hidden = visibleCount !== 0;
    }
  };

  searchInput.addEventListener("input", updateResults);
  updateResults();
});
