/*
 * Messagerie — l'écran « Documents » (/messagerie/documents).
 *
 * Le classeur de tous les fichiers des discussions dont on est membre, lu
 * dans l'API /documents : liste ou vignettes (choix gardé sur ce poste), tri
 * par colonne, filtres, recherche, pagination de 50. Les filtres vivent dans
 * l'adresse : un rechargement ou un lien partagé retrouve la même vue.
 *
 * Il s'appuie sur window.LbpMessagerie (messagerie.js, chargé avant) pour les
 * appels, les icônes, la visionneuse et le favori. Tout texte venu du
 * serveur passe par textContent : jamais de HTML construit d'une chaîne.
 */
(function () {
  "use strict";

  var racine = document.querySelector("[data-msg-documents]");
  var M = window.LbpMessagerie;
  if (!racine || !M) {
    return;
  }

  var el = M.el;
  var icone = M.icone;
  var vider = M.vider;
  var CLE_VUE = "lbp-msg-documents-vue";
  var LARGEUR_TELEPHONE = 720;
  var ORDRE_PAR_DEFAUT = { date: "desc", taille: "desc", nom: "asc", expediteur: "asc", type: "asc" };
  var FILTRES = ["conversation_id", "expediteur_id", "categorie", "du", "au"];

  var refs = {};
  racine.querySelectorAll("[data-docs]").forEach(function (n) {
    refs[n.getAttribute("data-docs")] = n;
  });

  var etat = lireAdresse();
  etat.vue = lireVue();
  var dernier = null;
  var numeroRequete = 0;

  // ------------------------------------------------------------------
  // État dans l'adresse et préférence d'affichage
  // ------------------------------------------------------------------

  function lireAdresse() {
    var p = new URLSearchParams(location.search);
    var tri = p.get("tri");
    tri = ORDRE_PAR_DEFAUT[tri] ? tri : "date";
    var ordre = p.get("ordre");
    return {
      q: p.get("q") || "",
      tri: tri,
      ordre: ordre === "asc" || ordre === "desc" ? ordre : ORDRE_PAR_DEFAUT[tri],
      conversation_id: p.get("conversation_id") || "",
      expediteur_id: p.get("expediteur_id") || "",
      categorie: p.get("categorie") || "",
      du: p.get("du") || "",
      au: p.get("au") || "",
      favoris: p.get("favoris") === "1",
      page: Math.max(1, parseInt(p.get("page"), 10) || 1)
    };
  }

  function parametres(avecPage) {
    var p = new URLSearchParams();
    if (etat.q.trim()) {
      p.set("q", etat.q.trim());
    }
    if (etat.tri !== "date") {
      p.set("tri", etat.tri);
    }
    if (etat.ordre !== ORDRE_PAR_DEFAUT[etat.tri]) {
      p.set("ordre", etat.ordre);
    }
    FILTRES.forEach(function (cle) {
      if (etat[cle]) {
        p.set(cle, etat[cle]);
      }
    });
    if (etat.favoris) {
      p.set("favoris", "1");
    }
    if (avecPage && etat.page > 1) {
      p.set("page", String(etat.page));
    }
    return p;
  }

  function ecrireAdresse() {
    var texte = parametres(true).toString();
    try {
      history.replaceState(null, "", location.pathname + (texte ? "?" + texte : ""));
    } catch (e) { /* sans effet */ }
  }

  function lireVue() {
    try {
      return window.localStorage.getItem(CLE_VUE) === "vignettes" ? "vignettes" : "liste";
    } catch (e) {
      return "liste";
    }
  }

  function garderVue(vue) {
    try { window.localStorage.setItem(CLE_VUE, vue); } catch (e) { /* sans effet */ }
  }

  // ------------------------------------------------------------------
  // Filtres
  // ------------------------------------------------------------------

  function remplirChamps() {
    refs.q.value = etat.q;
    refs.categorie.value = etat.categorie;
    refs.du.value = etat.du;
    refs.au.value = etat.au;
    refs.favoris.checked = etat.favoris;
    refs.tri.value = etat.tri;
    majOrdre();
    majVue();
  }

  function nombreDeFiltres() {
    return FILTRES.filter(function (cle) { return !!etat[cle]; }).length + (etat.favoris ? 1 : 0);
  }

  function majCompteurFiltres() {
    var n = nombreDeFiltres();
    refs["nb-filtres"].textContent = String(n);
    refs["nb-filtres"].hidden = n === 0;
    refs.effacer.hidden = n === 0 && !etat.q.trim();
  }

  function ajouterOption(select, valeur, libelle) {
    select.appendChild(el("option", { value: String(valeur), text: libelle }));
  }

  /* Les listes viennent de l'API : discussions de l'utilisateur, collègues actifs. */
  function chargerListes() {
    M.appel("GET", "/conversations").then(function (r) {
      var conversations = (r.conversations || []).slice().sort(function (a, b) {
        return String(a.nom).localeCompare(String(b.nom), "fr", { sensitivity: "base" });
      });
      conversations.forEach(function (c) {
        ajouterOption(refs.conversation_id, c.id, c.nom + (c.archivee ? " (archivée)" : ""));
      });
      refs.conversation_id.value = etat.conversation_id;
    }).catch(function () { /* la liste reste à « Toutes » */ });

    M.appel("GET", "/annuaire").then(function (r) {
      ajouterOption(refs.expediteur_id, M.config.moi, "Moi");
      (r.collegues || []).forEach(function (c) {
        ajouterOption(refs.expediteur_id, c.id, c.nom);
      });
      refs.expediteur_id.value = etat.expediteur_id;
    }).catch(function () { /* la liste reste à « Tous » */ });
  }

  function changer(cle, valeur) {
    etat[cle] = valeur;
    etat.page = 1;
    charger();
  }

  var chercher = M.attendre(function () {
    changer("q", refs.q.value);
  }, 350);

  refs.q.addEventListener("input", chercher);
  refs.q.addEventListener("keydown", function (evenement) {
    if (evenement.key === "Enter") {
      evenement.preventDefault();
      changer("q", refs.q.value);
    }
  });
  refs.filtres.addEventListener("submit", function (evenement) {
    evenement.preventDefault();
  });
  ["conversation_id", "expediteur_id", "categorie", "du", "au"].forEach(function (cle) {
    refs[cle].addEventListener("change", function () { changer(cle, refs[cle].value); });
  });
  refs.favoris.addEventListener("change", function () { changer("favoris", refs.favoris.checked); });

  function effacerFiltres() {
    etat.q = "";
    FILTRES.forEach(function (cle) { etat[cle] = ""; });
    etat.favoris = false;
    etat.page = 1;
    refs.conversation_id.value = "";
    refs.expediteur_id.value = "";
    remplirChamps();
    charger();
    refs.q.focus();
  }
  refs.effacer.addEventListener("click", effacerFiltres);

  refs["basculer-filtres"].addEventListener("click", function () {
    var ouvert = !refs.detail.classList.contains("is-ouvert");
    refs.detail.classList.toggle("is-ouvert", ouvert);
    refs["basculer-filtres"].setAttribute("aria-expanded", ouvert ? "true" : "false");
    if (ouvert) {
      var premier = refs.detail.querySelector("select, input");
      if (premier) {
        premier.focus();
      }
    }
  });

  // ------------------------------------------------------------------
  // Tri et affichage
  // ------------------------------------------------------------------

  function majOrdre() {
    var decroissant = etat.ordre === "desc";
    var libelle = decroissant ? "Ordre décroissant" : "Ordre croissant";
    refs.ordre.setAttribute("title", libelle + " (cliquer pour inverser)");
    refs.ordre.setAttribute("aria-label", libelle + ", cliquer pour inverser");
    refs.ordre.classList.toggle("is-croissant", !decroissant);
  }

  function trier(colonne) {
    if (etat.tri === colonne) {
      etat.ordre = etat.ordre === "asc" ? "desc" : "asc";
    } else {
      etat.tri = colonne;
      etat.ordre = ORDRE_PAR_DEFAUT[colonne];
    }
    etat.page = 1;
    refs.tri.value = etat.tri;
    majOrdre();
    charger();
  }

  refs.tri.addEventListener("change", function () {
    etat.tri = refs.tri.value;
    etat.ordre = ORDRE_PAR_DEFAUT[etat.tri] || "asc";
    etat.page = 1;
    majOrdre();
    charger();
  });
  refs.ordre.addEventListener("click", function () { trier(etat.tri); });

  function majVue() {
    racine.querySelectorAll("[data-docs-vue]").forEach(function (b) {
      b.setAttribute("aria-pressed", b.getAttribute("data-docs-vue") === etat.vue ? "true" : "false");
    });
  }

  racine.querySelectorAll("[data-docs-vue]").forEach(function (bouton) {
    bouton.addEventListener("click", function () {
      etat.vue = bouton.getAttribute("data-docs-vue");
      garderVue(etat.vue);
      majVue();
      if (dernier) {
        rendre(dernier);
      }
    });
  });

  refs["page-precedente"].addEventListener("click", function () { allerPage(etat.page - 1); });
  refs["page-suivante"].addEventListener("click", function () { allerPage(etat.page + 1); });

  function allerPage(page) {
    etat.page = page;
    charger().then(function () {
      racine.scrollIntoView({ block: "start", behavior: "auto" });
    });
  }

  // ------------------------------------------------------------------
  // Chargement
  // ------------------------------------------------------------------

  function montrerEtat(enfants, ton) {
    vider(refs.etat);
    refs.etat.className = "msg-docs-etat" + (ton ? " msg-docs-etat--" + ton : "");
    M.ajouter(refs.etat, enfants);
    refs.etat.hidden = false;
  }

  function charger() {
    majCompteurFiltres();
    refs["erreur-filtres"].hidden = true;
    if (etat.du && etat.au && etat.du > etat.au) {
      refs["erreur-filtres"].textContent = "La date « du » est après la date « au » : corrigez la période.";
      refs["erreur-filtres"].hidden = false;
      return Promise.resolve();
    }
    ecrireAdresse();
    var numero = ++numeroRequete;
    refs.resultats.setAttribute("aria-busy", "true");
    if (!dernier) {
      montrerEtat([el("span", { class: "msg-chargement", "aria-hidden": "true" }), el("p", { text: "Chargement des documents…" })]);
    } else {
      racine.classList.add("is-chargement");
    }
    return M.appel("GET", "/documents?" + parametres(true).toString()).then(function (r) {
      if (numero !== numeroRequete) {
        return;
      }
      // Une page devenue vide (filtre plus strict) : on revient à la dernière.
      if (r.documents.length === 0 && r.total > 0 && etat.page > r.pages) {
        etat.page = r.pages;
        return charger();
      }
      dernier = r;
      rendre(r);
    }).catch(function (erreur) {
      if (numero !== numeroRequete) {
        return;
      }
      refs.resultats.hidden = true;
      refs.pagination.hidden = true;
      refs.compte.textContent = "";
      montrerEtat([
        icone("alerte"),
        el("strong", { text: "Les documents n’ont pas pu être chargés" }),
        el("p", { text: M.messageErreur(erreur) }),
        el("button", { type: "button", class: "msg-btn", text: "Réessayer", onclick: charger })
      ], "erreur");
    }).then(function () {
      if (numero === numeroRequete) {
        refs.resultats.removeAttribute("aria-busy");
        racine.classList.remove("is-chargement");
      }
    });
  }

  // ------------------------------------------------------------------
  // Rendu
  // ------------------------------------------------------------------

  function rendre(r) {
    var documents = r.documents || [];
    refs.compte.textContent = r.total === 0 ? "" : r.total + (r.total > 1 ? " documents" : " document")
      + (r.pages > 1 ? " · page " + r.page + " sur " + r.pages : "");

    if (documents.length === 0) {
      refs.resultats.hidden = true;
      refs.pagination.hidden = true;
      rendreVide();
      return;
    }
    refs.etat.hidden = true;
    vider(refs.resultats);
    refs.resultats.hidden = false;
    refs.resultats.appendChild(etat.vue === "vignettes" ? grille(documents) : tableau(documents));

    refs.pagination.hidden = r.pages <= 1;
    refs["page-num"].textContent = "Page " + r.page + " sur " + r.pages;
    refs["page-precedente"].disabled = r.page <= 1;
    refs["page-suivante"].disabled = r.page >= r.pages;
  }

  function rendreVide() {
    var filtre = nombreDeFiltres() > 0 || etat.q.trim();
    if (etat.favoris && nombreDeFiltres() === 1 && !etat.q.trim()) {
      montrerEtat([
        el("span", { class: "msg-docs-etat-icone" }, icone("etoile")),
        el("strong", { text: "Aucun favori pour l’instant" }),
        el("p", { text: "Touchez l’étoile d’un document, ici ou dans une discussion, pour le retrouver dans cette liste." }),
        el("button", { type: "button", class: "msg-btn", text: "Voir tous les documents", onclick: function () {
          refs.favoris.checked = false;
          changer("favoris", false);
        } })
      ]);
    } else if (filtre) {
      montrerEtat([
        el("span", { class: "msg-docs-etat-icone" }, icone("recherche")),
        el("strong", { text: "Aucun document ne correspond" }),
        el("p", { text: "Essayez d’autres mots, une autre période, ou effacez les filtres." }),
        el("button", { type: "button", class: "msg-btn", text: "Effacer les filtres", onclick: effacerFiltres })
      ]);
    } else {
      montrerEtat([
        el("span", { class: "msg-docs-etat-icone" }, icone("dossier")),
        el("strong", { text: "Aucun document pour l’instant" }),
        el("p", { text: "Les fichiers envoyés dans vos discussions, photos comprises, viendront se ranger ici." }),
        el("a", { class: "msg-btn msg-btn--primaire", href: M.config.plein }, [icone("discussion"), el("span", { text: "Ouvrir les discussions" })])
      ]);
    }
  }

  function lienMessage(d) {
    return M.config.plein + "#c=" + d.conversation.id + "&m=" + d.message_id;
  }

  function nomExpediteur(d) {
    return d.expediteur.id === M.config.moi ? "Vous" : d.expediteur.nom;
  }

  function apercuPossible(d) {
    return d.categorie === "image" || d.categorie === "pdf";
  }

  function ouvrirApercu(d) {
    if (d.categorie === "pdf" && M.tactile) {
      window.open(d.url, "_blank", "noopener");
      return;
    }
    if (d.categorie !== "image") {
      M.Visionneuse.ouvrir([element(d)], 0);
      return;
    }
    var images = (dernier ? dernier.documents : []).filter(function (x) { return x.categorie === "image"; });
    var depart = Math.max(0, images.findIndex(function (x) { return x.id === d.id; }));
    M.Visionneuse.ouvrir(images.map(element), depart);
  }

  function element(d) {
    return { piece: d, auteur: d.expediteur.nom, date: M.versDate(d.envoye_at), legende: d.legende || "" };
  }

  function visuel(d, classe) {
    if (d.categorie === "image" && d.url_miniature) {
      var image = el("img", { src: d.url_miniature, alt: "", loading: "lazy", decoding: "async" });
      var cadre = el("span", { class: "msg-docs-miniature " + classe }, image);
      image.addEventListener("error", function () {
        cadre.replaceWith(M.vignetteDocument(d, classe));
      });
      return cadre;
    }
    return M.vignetteDocument(d, classe);
  }

  function boutonsActions(d) {
    var actions = [];
    if (apercuPossible(d)) {
      actions.push(el("button", { type: "button", class: "msg-icone-btn", title: "Aperçu", "aria-label": "Aperçu de " + d.nom, onclick: function () { ouvrirApercu(d); } }, icone("oeil")));
    } else {
      // Pas d'aperçu pour ce format : la place reste, les colonnes restent alignées.
      actions.push(el("span", { class: "msg-docs-sans-action", "aria-hidden": "true" }));
    }
    actions.push(el("a", { class: "msg-icone-btn", href: M.urlTelechargement(d), download: d.nom, title: "Télécharger", "aria-label": "Télécharger " + d.nom + ", " + M.tailleLisible(d.taille) }, icone("telecharger")));
    actions.push(M.boutonFavori(d, "msg-docs-favori"));
    actions.push(el("a", { class: "msg-icone-btn", href: lienMessage(d), title: "Aller au message", "aria-label": "Aller au message qui porte " + d.nom + ", dans " + d.conversation.nom }, icone("aller")));
    return actions;
  }

  function titreDocument(d) {
    var nom = el("span", { class: "msg-docs-nom-texte", text: d.nom, title: d.nom });
    if (!apercuPossible(d)) {
      return nom;
    }
    return el("button", { type: "button", class: "msg-docs-nom-btn", title: "Aperçu de " + d.nom, onclick: function () { ouvrirApercu(d); } }, nom);
  }

  function dateCourte(d) {
    var date = M.versDate(d.envoye_at);
    return date ? el("time", { datetime: date.toISOString(), title: M.libelleDateComplete(date), text: M.libelleListe(date) }) : null;
  }

  var COLONNES = [
    { cle: "nom", libelle: "Nom" },
    { cle: "type", libelle: "Type" },
    { cle: "taille", libelle: "Taille" },
    { cle: "expediteur", libelle: "Expéditeur" },
    { cle: null, libelle: "Discussion" },
    { cle: "date", libelle: "Date" }
  ];

  function tableau(documents) {
    var entete = el("tr", null, COLONNES.map(function (c) {
      if (!c.cle) {
        return el("th", { scope: "col", class: "msg-docs-col-" + "discussion", text: c.libelle });
      }
      var actif = etat.tri === c.cle;
      var bouton = el("button", { type: "button", class: "msg-docs-tri-btn" + (actif ? " is-actif" : ""), onclick: function () { trier(c.cle); } }, [
        el("span", { text: c.libelle }),
        icone(actif ? (etat.ordre === "asc" ? "chevron-haut" : "chevron-bas") : "tri", "msg-i--petit")
      ]);
      bouton.setAttribute("aria-label", "Trier par " + c.libelle.toLowerCase() + (actif ? (etat.ordre === "asc" ? ", actuellement croissant" : ", actuellement décroissant") : ""));
      return el("th", { scope: "col", class: "msg-docs-col-" + c.cle, "aria-sort": actif ? (etat.ordre === "asc" ? "ascending" : "descending") : null }, bouton);
    }).concat([el("th", { scope: "col", class: "msg-docs-col-actions" }, el("span", { class: "msg-sr", text: "Actions" }))]));

    var corps = el("tbody", null, documents.map(function (d) {
      return el("tr", null, [
        el("td", { class: "msg-docs-col-nom" }, el("div", { class: "msg-docs-nom" }, [
          visuel(d, "msg-docs-visuel"),
          el("span", { class: "msg-docs-nom-corps" }, [
            titreDocument(d),
            d.legende ? el("small", { class: "msg-docs-legende", text: d.legende, title: d.legende }) : null,
            el("small", { class: "msg-docs-resume", text: M.tailleLisible(d.taille) + " · " + nomExpediteur(d) + " · " + d.conversation.nom })
          ])
        ])),
        el("td", { class: "msg-docs-col-type", text: M.libelleType(d) }),
        el("td", { class: "msg-docs-col-taille", text: M.tailleLisible(d.taille) }),
        el("td", { class: "msg-docs-col-expediteur", text: nomExpediteur(d) }),
        el("td", { class: "msg-docs-col-discussion", text: d.conversation.nom }),
        el("td", { class: "msg-docs-col-date" }, dateCourte(d)),
        el("td", { class: "msg-docs-col-actions" }, el("div", { class: "msg-docs-actions" }, boutonsActions(d)))
      ]);
    }));

    return el("div", { class: "msg-docs-table-cadre" }, el("table", { class: "msg-docs-table" }, [
      el("caption", { class: "msg-sr", text: "Documents, triés par " + (COLONNES.find(function (c) { return c.cle === etat.tri; }) || COLONNES[5]).libelle.toLowerCase() }),
      el("thead", null, entete),
      corps
    ]));
  }

  function grille(documents) {
    return el("ul", { class: "msg-docs-grille", "aria-label": "Documents" }, documents.map(function (d) {
      var image = visuel(d, "msg-docs-carte-visuel");
      var haut = apercuPossible(d)
        ? el("button", { type: "button", class: "msg-docs-carte-haut", "aria-label": "Aperçu de " + d.nom, onclick: function () { ouvrirApercu(d); } }, image)
        : el("span", { class: "msg-docs-carte-haut" }, image);
      return el("li", { class: "msg-docs-carte" }, [
        haut,
        el("div", { class: "msg-docs-carte-infos" }, [
          el("strong", { class: "msg-docs-carte-nom", text: d.nom, title: d.nom }),
          el("small", null, [M.tailleLisible(d.taille) + " · " + nomExpediteur(d) + " · ", dateCourte(d)]),
          el("small", { class: "msg-docs-carte-discussion", text: d.conversation.nom })
        ]),
        el("div", { class: "msg-docs-actions" }, boutonsActions(d))
      ]);
    }));
  }

  // ------------------------------------------------------------------
  // Démarrage
  // ------------------------------------------------------------------

  if (window.innerWidth > LARGEUR_TELEPHONE || nombreDeFiltres() > 0) {
    refs.detail.classList.add("is-ouvert");
    refs["basculer-filtres"].setAttribute("aria-expanded", "true");
  }
  remplirChamps();
  chargerListes();
  charger();
})();
