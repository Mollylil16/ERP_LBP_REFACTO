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
  const moduleSelect = document.querySelector(
    "select[data-portal-module-filter]",
  );
  const moduleCards = Array.from(
    document.querySelectorAll("[data-module-card]"),
  );
  const countLabel = document.getElementById("moduleSearchCount");
  const emptyState = document.getElementById("moduleEmptyState");
  const resetButton = document.getElementById("moduleFilterReset");

  if (!moduleSelect || moduleCards.length === 0) return;

  const updateResults = () => {
    const selected = new Set(
      Array.from(moduleSelect.selectedOptions)
        .map((option) => option.value)
        .filter(Boolean),
    );
    let visibleCount = 0;

    moduleCards.forEach((card) => {
      const isVisible =
        selected.size === 0 || selected.has(card.dataset.moduleKey || "");
      card.hidden = !isVisible;
      if (isVisible) visibleCount += 1;
    });

    if (countLabel) {
      countLabel.textContent = `${visibleCount} module${visibleCount > 1 ? "s" : ""} disponible${visibleCount > 1 ? "s" : ""}`;
    }
    if (emptyState) emptyState.hidden = visibleCount !== 0;
    if (resetButton) resetButton.hidden = selected.size === 0;

    // Un titre de groupe qui surplombe une rangee vide fait croire a une
    // tuile perdue. Les acces rapides s effacent aussi pendant une recherche :
    // on cherche precisement ce qui n y est pas.
    document.querySelectorAll("[data-portail-groupe]").forEach((groupe) => {
      const reste = groupe.querySelectorAll("[data-module-card]:not([hidden])");
      groupe.hidden = reste.length === 0;
    });

    const rapides = document.querySelector("[data-portail-rapides]");
    if (rapides) rapides.hidden = selected.size !== 0;
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

/*
 * Les acces rapides du portail : la rangee du haut suit ce que la personne
 * ouvre vraiment.
 *
 * Rien ne remonte au serveur. Le compte des ouvertures reste dans ce
 * navigateur, et un poste partage ne revele donc que ce qu on y a fait.
 */
document.addEventListener("DOMContentLoaded", () => {
  const rangee = document.querySelector("[data-portail-rapides] .portail-rangee");
  if (!rangee) return;

  const CLE = "lbp.portail.ouvertures";
  const MAX = 5;

  const lire = () => {
    try {
      const brut = window.localStorage.getItem(CLE);
      const compte = brut ? JSON.parse(brut) : {};
      return compte && typeof compte === "object" ? compte : {};
    } catch (e) {
      // Navigation privee, stockage refuse : la rangee par defaut suffit.
      return {};
    }
  };

  const compte = lire();

  // Deux ouvertures au moins : une seule visite ne dit pas une habitude, et
  // reordonner des le premier clic deroute plus qu il n aide.
  const classees = Object.keys(compte)
    .filter((cle) => compte[cle] >= 2)
    .sort((a, b) => compte[b] - compte[a]);

  if (classees.length >= 2) {
    const toutes = Array.from(
      document.querySelectorAll("[data-module-card]"),
    );
    const choisies = classees
      .map((cle) => toutes.find((t) => t.dataset.moduleKey === cle))
      .filter(Boolean)
      .slice(0, MAX);

    if (choisies.length >= 2) {
      rangee.innerHTML = "";
      choisies.forEach((modele) => {
        const copie = modele.cloneNode(true);
        copie.removeAttribute("data-module-card");
        copie.setAttribute("data-portail-rapide", "1");
        rangee.appendChild(copie);
      });
    }
  }

  document.querySelectorAll("[data-module-key]").forEach((tuile) => {
    tuile.addEventListener("click", () => {
      const cle = tuile.dataset.moduleKey;
      if (!cle) return;
      try {
        const actuel = lire();
        actuel[cle] = (actuel[cle] || 0) + 1;
        window.localStorage.setItem(CLE, JSON.stringify(actuel));
      } catch (e) {
        // Ne jamais empecher l ouverture du module pour un compteur.
      }
    });
  });
});
