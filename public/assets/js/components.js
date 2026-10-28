(() => {
  const initSelectSearch = (select) => {
    if (select.dataset.fineaEnhanced === "1") return;
    select.dataset.fineaEnhanced = "1";
    select.classList.add("finea-native-select");

    const multiple = select.hasAttribute("multiple") || select.multiple || select.dataset.multiple === "1";
    const placeholder =
      select.dataset.placeholder || "Rechercher et sélectionner...";
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

    const source = Array.from(select.options).map((option) => ({
      value: option.value,
      label: option.textContent || "",
    }));
    const normalize = (value) =>
      value
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();

    const selectedValues = () =>
      Array.from(select.selectedOptions)
        .map((option) => option.value)
        .filter((value) => value !== "");
    const setSelected = (value, selected) => {
      Array.from(select.options).forEach((option) => {
        if (option.value === value) option.selected = selected;
      });
      select.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const renderBadges = () => {
      control
        .querySelectorAll(".finea-select-badge")
        .forEach((badge) => badge.remove());
      if (!multiple) return;
      selectedValues().forEach((value) => {
        const item = source.find((option) => option.value === value);
        const badge = document.createElement("span");
        badge.className = "finea-select-badge";
        badge.textContent = item?.label || value;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.setAttribute("aria-label", `Retirer ${item?.label || value}`);
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
      input.placeholder =
        selected && selected.value !== "" ? selected.textContent : placeholder;
    };

    const renderMenu = () => {
      const term = normalize(input.value);
      const selected = new Set(selectedValues());
      const items = source.filter((option) => {
        if (multiple && selected.has(option.value)) return false;
        return normalize(option.label).includes(term);
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
        if (!multiple && select.value === option.value)
          button.classList.add("is-active");
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

    const open = () => {
      wrapper.classList.add("is-open");
      renderMenu();
    };
    control.addEventListener("click", () => {
      input.focus();
      open();
    });
    input.addEventListener("focus", open);
    input.addEventListener("input", renderMenu);
    document.addEventListener("click", (event) => {
      if (!wrapper.contains(event.target)) wrapper.classList.remove("is-open");
    });
    select.addEventListener("change", () => {
      renderBadges();
      renderSingleLabel();
      renderMenu();
    });
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
      reader.onload = () => {
        preview.innerHTML = `<img src="${reader.result}" alt="Aperçu"><span>${file.name}</span>`;
      };
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
    ["dragenter", "dragover"].forEach((eventName) =>
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.add("is-dragging");
      }),
    );
    ["dragleave", "drop"].forEach((eventName) =>
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.remove("is-dragging");
      }),
    );
    zone.addEventListener("drop", (event) => {
      const files = event.dataTransfer?.files;
      if (!files?.length) return;
      const dt = new DataTransfer();
      Array.from(files)
        .slice(0, input.multiple ? files.length : 1)
        .forEach((file) => dt.items.add(file));
      input.files = dt.files;
      previewFile(input);
    });
    input.addEventListener("change", () => previewFile(input));
  };

  const init = () => {
    document
      .querySelectorAll(
        "select[data-finea-select-search], select[data-select-search]",
      )
      .forEach(initSelectSearch);
    document
      .querySelectorAll("[data-finea-dropzone], [data-dropzone]")
      .forEach(initDropzone);
    document.querySelectorAll("[data-color-picker]").forEach((picker) => {
      const input = picker.querySelector("[data-color-input]");
      const output = picker.querySelector("[data-color-output]");
      const swatches = [...picker.querySelectorAll("[data-color-value]")];
      if (!input || input.dataset.colorEnhanced === "1") return;
      input.dataset.colorEnhanced = "1";
      const sync = (value, notify = false) => {
        input.value = value;
        if (output) output.textContent = value.toUpperCase();
        swatches.forEach((swatch) =>
          swatch.classList.toggle(
            "is-selected",
            swatch.dataset.colorValue === value.toLowerCase(),
          ),
        );
        if (notify) input.dispatchEvent(new Event("change", { bubbles: true }));
      };
      input.addEventListener("input", () => sync(input.value));
      swatches.forEach((swatch) =>
        swatch.addEventListener("click", () =>
          sync(swatch.dataset.colorValue, true),
        ),
      );
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
    document
      .querySelector(".module-nav-link.is-active")
      ?.scrollIntoView({ block: "nearest" });
    document.querySelectorAll("[data-modal-open]").forEach((button) => {
      if (button.dataset.modalEnhanced === "1") return;
      button.dataset.modalEnhanced = "1";
      button.addEventListener("click", () =>
        document.getElementById(button.dataset.modalOpen)?.showModal(),
      );
    });
    document.querySelectorAll("[data-modal]").forEach((modal) => {
      if (modal.dataset.modalEnhanced === "1") return;
      modal.dataset.modalEnhanced = "1";
      modal
        .querySelectorAll("[data-modal-close]")
        .forEach((button) =>
          button.addEventListener("click", () => modal.close()),
        );
      modal.addEventListener("click", (event) => {
        if (event.target === modal) modal.close();
      });
    });
  };

  /**
   * Menu lateral sur telephone et tablette.
   *
   * Le bouton « Menu » existe dans tous les modules, mais son ecouteur ne
   * vivait que dans rh.js, charge par le seul module RH : ailleurs, sous
   * 850 px, la barre laterale restait hors ecran et le bouton ne faisait
   * rien — la navigation etait perdue. Il vit desormais ici, avec le voile,
   * la fermeture au clic exterieur et la touche Echap.
   */
  const menuMobile = () => {
    const sidebar = document.getElementById("moduleSidebar");
    const bouton = document.querySelector("[data-module-menu]");

    if (!sidebar || !bouton || bouton.dataset.menuPret === "1") return;

    bouton.dataset.menuPret = "1";
    bouton.setAttribute("aria-expanded", "false");
    bouton.setAttribute("aria-controls", "moduleSidebar");

    let voile = document.querySelector(".module-sidebar-backdrop");
    if (!voile) {
      voile = document.createElement("div");
      voile.className = "module-sidebar-backdrop";
      document.body.appendChild(voile);
    }

    const basculer = (ouvrir) => {
      sidebar.classList.toggle("is-open", ouvrir);
      voile.classList.toggle("is-visible", ouvrir);
      bouton.setAttribute("aria-expanded", ouvrir ? "true" : "false");
      document.body.classList.toggle("has-sidebar-open", ouvrir);
    };

    bouton.addEventListener("click", () =>
      basculer(!sidebar.classList.contains("is-open")),
    );

    // Safari sur iPhone n'envoie pas toujours « click » a un simple div :
    // l'appui est ecoute aussi, et le clic qui suit ne fait alors rien.
    const fermer = () => basculer(false);
    voile.addEventListener("click", fermer);
    voile.addEventListener("touchend", fermer);

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") fermer();
    });

    // Suivre un lien ferme le menu : sinon il recouvre la page qui s'ouvre.
    sidebar.addEventListener("click", (event) => {
      if (event.target.closest("a")) fermer();
    });

    /*
     * Tourner la tablette repasse en presentation de bureau. Le menu doit se
     * refermer : sans cela le verrou de defilement du body restait pose et la
     * page entiere semblait figee.
     */
    window.addEventListener("resize", () => {
      if (window.innerWidth > 850 && sidebar.classList.contains("is-open")) {
        fermer();
      }
    });
  };

  /**
   * Les tableaux, lisibles au telephone.
   *
   * Un tableau de sept colonnes ne tient pas sur 390 px. Jusqu ici il y etait
   * comprime : chaque colonne tombait sous soixante pixels et les mots se
   * coupaient en plein milieu. Le lire devenait un travail.
   *
   * Sous 640 px chaque ligne devient donc une carte, et chaque cellule porte
   * le nom de sa colonne a gauche de sa valeur. Les vingt-deux ecrans qui
   * fabriquent leurs tableaux a la main n ont rien a changer : l etiquette est
   * posee ici, depuis l en-tete que le tableau porte deja.
   *
   * Si ce script ne s execute pas, le tableau reste un tableau : on perd le
   * confort, jamais l information.
   */
  const tableauxEnCartes = (racine = document) => {
    /*
     * Tous les tableaux de la coque, et pas seulement ceux qui portent la
     * classe du systeme : le point de caisse et le tableau de bord RH ecrivent
     * un <table> nu, et ce sont deux des ecrans les plus ouverts.
     *
     * Les documents imprimables — bordereaux, rapports, journal de caisse —
     * sont des pages a part, sans coque et sans ce script : ils gardent leurs
     * tableaux tels quels, ce qui est bien ce qu on veut sur du papier.
     */
    const tableaux = racine.querySelectorAll(
      ".module-content table, .finea-shell table, table.finea-table",
    );

    tableaux.forEach((table) => {
      if (table.dataset.cartesPretes === "1") return;

      /*
       * Un tableau peut refuser la mise en cartes. Une matrice de droits se
       * lit par colonnes — un role, vingt permissions cote a cote —, et
       * l empiler en vingt lignes la rendrait illisible. Elle declare alors
       * data-mobile="defilement" et garde son defilement lateral.
       */
      if (table.dataset.mobile) {
        table.dataset.cartesPretes = "1";
        return;
      }

      const lignesEntete = table.querySelectorAll("thead tr");
      const entetes = lignesEntete.length
        ? [...lignesEntete[lignesEntete.length - 1].querySelectorAll("th")].map((th) =>
            th.textContent.trim(),
          )
        : [];

      // Sans en-tete, une cellule ne peut pas porter son etiquette : ce
      // tableau-la garde son defilement, qui reste lisible.
      if (entetes.length === 0) {
        table.dataset.cartesPretes = "1";
        return;
      }

      table.dataset.cartesPretes = "1";

      /*
       * Trois colonnes tiennent deja dans la largeur d un telephone. Les
       * empiler en carte tripleraient la hauteur de la page sans rien
       * apprendre de plus.
       */
      if (entetes.length <= 3) {
        table.dataset.mobile = "etroit";
        return;
      }

      table.dataset.mobile = "cartes";

      const enveloppe = table.closest(".finea-table-wrap, .finea-table-wrapper");
      if (enveloppe) enveloppe.dataset.mobile = "cartes";

      table.querySelectorAll("tbody tr, tfoot tr").forEach((ligne) => {
        let colonne = 0;

        [...ligne.children].forEach((cellule) => {
          const portee = cellule.colSpan || 1;

          /*
           * Une cellule qui couvre plusieurs colonnes — un total, un « aucune
           * ligne » — n appartient a aucune d elles. Elle reste pleine
           * largeur, sans etiquette qui mentirait.
           */
          if (portee === 1 && entetes[colonne]) {
            cellule.dataset.etiquette = entetes[colonne];
          } else {
            cellule.dataset.pleineLargeur = "1";
          }

          /*
           * Une cellule vide ne se voit pas dans un tableau. En carte, elle
           * afficherait une etiquette suivie de rien : autant la retirer.
           */
          if (
            !cellule.textContent.trim() &&
            !cellule.querySelector("button, a, input, select, textarea, svg, img")
          ) {
            cellule.dataset.vide = "1";
          }

          colonne += portee;
        });
      });
    });
  };

  document.addEventListener("DOMContentLoaded", () => {
    init();
    menuMobile();
    tableauxEnCartes();
  });
  window.FineaComponents = { init, menuMobile, tableauxEnCartes };
})();
