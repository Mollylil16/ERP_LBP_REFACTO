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

  /*
   * Ce qui permet de reconnaitre le contenu d une cellule sans rien savoir
   * de la colonne. Vingt-deux composants fabriquent leurs tableaux a la
   * main et aucun ne declare ce qu il y met : on ne dispose que du texte.
   */

  // Les unites qu on retire avant de se demander si c est un nombre.
  const UNITES = /\s*(?:F|FCFA|XOF|EUR|\u20AC|\$|USD|kg|g|t|%|m3|m\u00B3|h|j)\.?$/i;

  /*
   * Les separateurs de milliers qu on croise dans les montants formates :
   * espace ordinaire, insecable, insecable fine, fine, separateur de
   * chiffres, et l apostrophe. Ecrits en echappements : a l oeil nu, dans
   * un fichier, rien ne distingue une insecable d une espace ordinaire.
   */
  const SEPARATEURS = /[ \u00A0\u202F\u2009\u2007']/g;

  // jj/mm/aaaa, avec ou sans heure.
  const DATE_FR = /^\d{1,2}\/\d{1,2}\/\d{4}(?:\s+(?:\u00E0\s+)?\d{1,2}[:h]\d{2})?$/;

  const GRAS = /^(?:STRONG|B)$/;

  /**
   * Les marques que porte une ligne devenue carte.
   *
   * La passe precedente a pose le nom de chaque colonne. Celle-ci regarde ce
   * que la cellule CONTIENT, pour que la carte ait une hierarchie plutot que
   * six lignes qui se valent : un titre, un badge d etat au coin, une date en
   * legende, et une seule valeur en vedette.
   *
   * Aucune de ces regles ne connait le sens d une colonne. C est voulu : la
   * meme passe sert les vingt-deux composants, et celui qui en ecrira un
   * vingt-troisieme n aura rien a declarer.
   */
  const marquerLaCarte = (ligne) => {
    const cellules = [...ligne.children].filter(
      (c) => !c.dataset.vide && !c.dataset.pleineLargeur,
    );

    cellules.forEach((cellule, rang) => {
      const texte = cellule.textContent.trim();
      const enfants = [...cellule.children];

      /*
       * Le composant a lui-meme mis toute la valeur en gras : c est sa facon
       * de dire « c est celle-la qui compte ». On s en sert pour choisir la
       * vedette, plus bas.
       */
      if (
        enfants.length === 1 &&
        GRAS.test(enfants[0].tagName) &&
        enfants[0].textContent.trim() === texte
      ) {
        cellule.dataset.fort = "1";
      }

      /*
       * Tout ce qui se touche. Jamais deplace dans la carte : deplacer une
       * cible, c est casser l ordre du clavier.
       *
       * Une exception : la premiere cellule quand elle ne contient qu un lien
       * portant tout son texte. Un numero de facture cliquable reste le titre
       * de la carte — et il ne bouge pas non plus, il est deja en tete.
       */
      const champs = cellule.querySelector(
        "button, input, select, textarea, form, [role='button']",
      );
      const liens = cellule.querySelectorAll("a");
      const lienTitre =
        !champs &&
        liens.length === 1 &&
        rang === 0 &&
        liens[0].textContent.trim() === texte;

      if (champs || (liens.length > 0 && !lienTitre)) {
        cellule.dataset.interactif = "1";
        return;
      }

      // Un etat : la cellule ne porte qu un badge, et ce badge est tout son
      // texte.
      const badges = cellule.querySelectorAll("[class*='badge']");
      if (badges.length === 1 && texte !== "" && badges[0].textContent.trim() === texte) {
        cellule.dataset.badge = "1";
        return;
      }

      /*
       * Un nombre : on retire l unite, puis les separateurs de milliers, et
       * ce qui reste doit etre un nombre et rien d autre.
       *
       *   « 163 035 333 F » oui      « 11 088,2 kg » oui
       *   « 1 699 »         oui      « 0 F »         oui, et nul
       *   « FA-3403-2026-000366 » non — des lettres et des tirets
       *   « 28/10/2026 »          non — des barres obliques
       */
      const corps = texte.replace(UNITES, "").trim().replace(SEPARATEURS, "");
      if (corps !== "" && /^[+-]?\d+(?:[.,]\d+)?$/.test(corps)) {
        cellule.dataset.nombre = "1";

        // Zero n est pas une information a crier : « 0 F » restant du veut
        // dire « rien a faire ».
        if (parseFloat(corps.replace(",", ".")) === 0) {
          cellule.dataset.zero = "1";
        }

        return;
      }

      if (DATE_FR.test(texte)) {
        cellule.dataset.date = "1";
        return;
      }

      // Au-dela de vingt-deux caracteres, la valeur ne tient pas dans ce qui
      // reste a droite de l etiquette : elle passe dessous, pleine largeur.
      if (texte.length > 22) {
        cellule.dataset.long = "1";
      }
    });

    /*
     * Le titre : la premiere cellule qui dit ce qu EST la ligne. Ni un
     * nombre, ni une date, ni un badge, ni un champ de formulaire.
     */
    const titre = cellules.find(
      (c) =>
        !c.dataset.interactif &&
        !c.dataset.badge &&
        !c.dataset.nombre &&
        !c.dataset.date &&
        c.textContent.trim() !== "",
    );

    if (titre) {
      titre.dataset.titre = "1";
      // Le titre n a pas d etiquette : rien a passer au-dessus de lui.
      delete titre.dataset.long;

      // « 531 saisie(s) » derriere le gras du nom d agence : tout ce qui suit
      // le gras d ouverture est un sous-texte du titre.
      const parties = [...titre.children];
      if (parties.length > 1 && GRAS.test(parties[0].tagName)) {
        parties.slice(1).forEach((partie) => {
          partie.dataset.sous = "1";
        });
      }

      /*
       * Le badge et la date ne montent en en-tete que s il y a un titre sous
       * lequel les ranger : sinon la carte s ouvrirait sur une rangee a
       * moitie vide. Un seul de chaque — un deuxieme badge reste une ligne de
       * donnees ordinaire, avec son etiquette.
       */
      const badge = cellules.find((c) => c.dataset.badge);
      if (badge) badge.dataset.badgeChapeau = "1";

      const date = cellules.find((c) => c.dataset.date);
      if (date) date.dataset.dateChapeau = "1";
    }

    /*
     * Une seule valeur en vedette par carte : celle qu on vient chercher sur
     * cet ecran.
     *
     *   1. le nombre non nul que le composant a lui-meme mis en gras ;
     *   2. sinon le premier nombre non nul ;
     *   3. sinon le premier nombre — une carte tout a zero garde un point
     *      d appui.
     */
    const nombres = cellules.filter((c) => c.dataset.nombre);
    const vedette =
      nombres.find((c) => c.dataset.fort && !c.dataset.zero) ||
      nombres.find((c) => !c.dataset.zero) ||
      nombres[0];

    if (vedette) {
      vedette.dataset.vedette = "1";
    }
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

        marquerLaCarte(ligne);
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
