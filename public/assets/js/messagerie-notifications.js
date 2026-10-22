/*
 * Messagerie interne — notifications sur cet appareil (lot 4).
 *
 * Trois choses :
 *   - l'abonnement du navigateur aux notifications push : le bandeau
 *     « Activer les notifications » à la première ouverture, l'interrupteur
 *     et l'essai dans « Mes réglages », et ce qui empêche de les recevoir
 *     (refus du navigateur, iPhone sans le portail sur l'écran d'accueil) ;
 *   - le service worker /messagerie/sw.js, qui reçoit les notifications et
 *     se tait quand le portail est déjà sous les yeux de l'utilisateur ;
 *   - le clic sur une notification : le service worker demande à l'onglet
 *     de la messagerie d'aller au message (#c=12&m=345), sans recharger.
 *
 * La demande d'autorisation part toujours d'un clic : les navigateurs
 * refusent qu'une page la pose d'elle-même.
 *
 * Le balisage est rendu par le composant Messagerie (masqué) ; ce fichier ne
 * fait que montrer, masquer et écrire du texte (textContent) : jamais de
 * HTML construit à partir d'une chaîne.
 */
(function () {
  "use strict";

  var configuration = document.querySelector("[data-msg-config]");
  if (!configuration || typeof fetch !== "function" || typeof Promise !== "function") {
    return;
  }

  var CFG = {
    api: (configuration.getAttribute("data-api") || "").replace(/\/$/, ""),
    csrf: configuration.getAttribute("data-csrf") || "",
    moi: configuration.getAttribute("data-moi") || "",
    sw: configuration.getAttribute("data-notif-sw") || "",
    portee: configuration.getAttribute("data-notif-portee") || ""
  };

  // Le bandeau fermé (« Plus tard ») ne revient pas pour cet utilisateur.
  var CLE_BANDEAU = "lbp-msg-notif-bandeau-" + CFG.moi;
  // Qui a abonné ce navigateur : sur un ordinateur partagé, l'abonnement
  // d'un collègue n'est jamais repris par un autre compte.
  var CLE_TITULAIRE = "lbp-msg-notif-titulaire";

  var ua = navigator.userAgent || "";
  var IOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  var INSTALLE = (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) || navigator.standalone === true;
  var SECURISE = window.isSecureContext !== false;
  var SUPPORT = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;

  var etat = {
    verifie: false,   // l'état de l'abonnement a été relu
    abonne: false,    // ce navigateur est abonné pour l'utilisateur connecté
    occupe: false,    // une activation ou une désactivation est en cours
    refuseIci: false  // refusé en réponse à notre demande, sur cette page
  };

  // ------------------------------------------------------------------
  // Outils
  // ------------------------------------------------------------------

  function lire(cle) {
    try { return window.localStorage.getItem(cle); } catch (e) { return null; }
  }

  function ecrire(cle, valeur) {
    try {
      if (valeur === null) {
        window.localStorage.removeItem(cle);
      } else {
        window.localStorage.setItem(cle, valeur);
      }
    } catch (e) { /* stockage indisponible : sans effet */ }
  }

  function tous(selecteur) {
    return Array.prototype.slice.call(document.querySelectorAll(selecteur));
  }

  function permission() {
    return SUPPORT ? Notification.permission : "default";
  }

  function Erreur(message, code) {
    var e = new Error(message);
    e.code = code || "";
    return e;
  }

  function appel(methode, chemin, corps) {
    var entetes = { Accept: "application/json" };
    var options = { method: methode, credentials: "same-origin", cache: "no-store", headers: entetes };
    if (methode !== "GET") {
      entetes["Content-Type"] = "application/json";
      entetes["X-CSRF-TOKEN"] = CFG.csrf;
      options.body = JSON.stringify(corps || {});
    }

    return fetch(CFG.api + chemin, options)
      .catch(function () {
        throw Erreur("Connexion au serveur impossible. Vérifiez votre réseau puis réessayez.");
      })
      .then(function (reponse) {
        return reponse.json().catch(function () { return null; }).then(function (donnees) {
          if (reponse.status === 401 && window.LbpReleve) {
            window.LbpReleve.signalerSessionExpiree(donnees && donnees.message);
          }
          if (!reponse.ok || !donnees || donnees.ok !== true) {
            throw Erreur(donnees && donnees.message ? donnees.message : "Le serveur a rencontré une erreur. Réessayez dans un instant.");
          }
          return donnees;
        });
      });
  }

  /* La clé publique VAPID (base64 « url ») en octets, pour subscribe(). */
  function octets(base64) {
    var complement = "====".slice(0, (4 - (base64.length % 4)) % 4);
    var brut = window.atob((base64 + complement).replace(/-/g, "+").replace(/_/g, "/"));
    var resultat = new Uint8Array(brut.length);
    for (var i = 0; i < brut.length; i++) {
      resultat[i] = brut.charCodeAt(i);
    }
    return resultat;
  }

  function memeCle(abonnement, cle) {
    var actuelle = abonnement.options && abonnement.options.applicationServerKey;
    if (!actuelle) {
      return true;
    }
    var a = new Uint8Array(actuelle);
    if (a.length !== cle.length) {
      return false;
    }
    for (var i = 0; i < a.length; i++) {
      if (a[i] !== cle[i]) {
        return false;
      }
    }
    return true;
  }

  // ------------------------------------------------------------------
  // Service worker et abonnement
  // ------------------------------------------------------------------

  var enregistrementEnCours = null;

  function enregistrement() {
    if (!enregistrementEnCours) {
      enregistrementEnCours = navigator.serviceWorker.register(CFG.sw, { scope: CFG.portee, updateViaCache: "none" })
        .catch(function (e) {
          enregistrementEnCours = null;
          throw e;
        });
    }
    return enregistrementEnCours;
  }

  /* subscribe() exige un service worker actif ; le premier s'installe. */
  function actif(reg) {
    if (reg.active) {
      return Promise.resolve(reg);
    }
    var travailleur = reg.installing || reg.waiting;
    if (!travailleur) {
      return Promise.resolve(reg);
    }
    return new Promise(function (resoudre) {
      var fini = false;
      function terminer() {
        if (!fini) {
          fini = true;
          resoudre(reg);
        }
      }
      travailleur.addEventListener("statechange", function () {
        if (travailleur.state === "activated") {
          terminer();
        }
      });
      window.setTimeout(terminer, 10000);
    });
  }

  function abonnementLocal() {
    if (!SUPPORT) {
      return Promise.resolve(null);
    }
    return navigator.serviceWorker.getRegistration(CFG.portee).then(function (reg) {
      return reg ? reg.pushManager.getSubscription() : null;
    });
  }

  function inscrire(abonnement) {
    var json = abonnement.toJSON();
    return appel("POST", "/notifications/abonner", {
      endpoint: json.endpoint,
      p256dh: json.keys && json.keys.p256dh,
      auth: json.keys && json.keys.auth,
      _csrf_token: CFG.csrf
    }).then(function () {
      ecrire(CLE_TITULAIRE, String(CFG.moi));
    });
  }

  function abonner() {
    return enregistrement().then(function (reg) {
      return Promise.all([actif(reg), appel("GET", "/notifications/cle-publique")]).then(function (r) {
        var cle = octets(r[1].cle);
        return reg.pushManager.getSubscription().then(function (existant) {
          // Un abonnement fait avec une autre clé serveur ne recevrait rien.
          if (existant && !memeCle(existant, cle)) {
            return existant.unsubscribe().then(function () { return null; });
          }
          return existant;
        }).then(function (existant) {
          return existant || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: cle });
        });
      });
    }).then(inscrire);
  }

  /*
   * Relit l'état au chargement. Un abonnement de ce navigateur enregistré
   * pour un autre compte (ordinateur partagé) est retiré : on n'affiche pas
   * ici les messages de quelqu'un d'autre.
   */
  function verifier() {
    if (!SUPPORT || !SECURISE) {
      etat.verifie = true;
      rendre();
      return Promise.resolve();
    }

    return abonnementLocal().then(function (abonnement) {
      if (!abonnement) {
        etat.abonne = false;
        return null;
      }
      return appel("POST", "/notifications/etat", { endpoint: abonnement.endpoint, _csrf_token: CFG.csrf }).then(function (r) {
        if (r.abonne) {
          etat.abonne = true;
          return null;
        }
        etat.abonne = false;
        if (r.autre || lire(CLE_TITULAIRE) !== String(CFG.moi) || permission() !== "granted") {
          return abonnement.unsubscribe();
        }
        // Ouvert par cet utilisateur mais oublié du serveur : on le réinscrit.
        return inscrire(abonnement).then(function () { etat.abonne = true; });
      });
    }).catch(function () {
      /* état inconnu : l'interrupteur reste sur « désactivé » */
    }).then(function () {
      etat.verifie = true;
      rendre();
    });
  }

  // ------------------------------------------------------------------
  // Actions
  // ------------------------------------------------------------------

  function activer() {
    if (etat.occupe) {
      return;
    }
    effacerRetours();
    if (!SUPPORT || !SECURISE || (IOS && !INSTALLE)) {
      rendre();
      return;
    }

    // En premier, et dans le clic : sinon le navigateur refuse de demander.
    var demande;
    try {
      demande = Notification.requestPermission();
    } catch (e) {
      demande = null;
    }

    etat.occupe = true;
    rendre();

    Promise.resolve(demande).then(function () {
      if (permission() !== "granted") {
        etat.refuseIci = permission() === "denied";
        throw Erreur("", "refuse");
      }
      return abonner();
    }).then(function () {
      etat.abonne = true;
      ecrire(CLE_BANDEAU, "1");
      annoncer("Notifications activées sur cet appareil.");
    }).catch(function (e) {
      if (e && e.code !== "refuse") {
        signaler(e && e.message ? e.message : "Activation impossible. Réessayez dans un instant.");
      }
    }).then(function () {
      etat.occupe = false;
      rendre();
    });
  }

  function desactiver() {
    if (etat.occupe) {
      return;
    }
    effacerRetours();
    etat.occupe = true;
    rendre();

    abonnementLocal().then(function (abonnement) {
      if (!abonnement) {
        return null;
      }
      return appel("POST", "/notifications/desabonner", { endpoint: abonnement.endpoint, _csrf_token: CFG.csrf })
        .then(function () { return abonnement.unsubscribe(); });
    }).then(function () {
      etat.abonne = false;
      ecrire(CLE_TITULAIRE, null);
      annoncer("Notifications désactivées sur cet appareil.");
    }).catch(function (e) {
      signaler(e && e.message ? e.message : "Désactivation impossible. Réessayez dans un instant.");
    }).then(function () {
      etat.occupe = false;
      rendre();
    });
  }

  function essayer() {
    if (etat.occupe) {
      return;
    }
    effacerRetours();
    etat.occupe = true;
    rendre();

    abonnementLocal().then(function (abonnement) {
      if (!abonnement) {
        throw Erreur("Cet appareil n’est pas abonné. Activez d’abord les notifications.");
      }
      return appel("POST", "/notifications/test", { endpoint: abonnement.endpoint, _csrf_token: CFG.csrf });
    }).then(function () {
      annoncer("Notification envoyée : elle doit apparaître dans quelques secondes.");
    }).catch(function (e) {
      signaler(e && e.message ? e.message : "Envoi impossible.");
    }).then(function () {
      etat.occupe = false;
      rendre();
    });
  }

  // ------------------------------------------------------------------
  // Affichage
  // ------------------------------------------------------------------

  /* Ce qui empêche de recevoir les notifications, ou « active ». */
  function situation() {
    if (!SECURISE) {
      return "non-securise";
    }
    if (IOS && !INSTALLE) {
      return "ios";
    }
    if (!SUPPORT) {
      return IOS ? "ios-ancien" : "non-supporte";
    }
    if (permission() === "denied") {
      return "refuse";
    }
    return etat.abonne ? "active" : "";
  }

  function varianteBandeau() {
    if (!etat.verifie || lire(CLE_BANDEAU) === "1" || etat.abonne) {
      return "";
    }
    if (etat.refuseIci) {
      return "refuse";
    }
    if (IOS && !INSTALLE && SECURISE) {
      return "ios";
    }
    if (SUPPORT && SECURISE && permission() === "default") {
      return "standard";
    }
    return "";
  }

  function rendre() {
    var cas = situation();
    var possible = SUPPORT && SECURISE && !(IOS && !INSTALLE) && permission() !== "denied";

    tous("[data-msg-notif-reglages]").forEach(function (section) {
      var interrupteur = section.querySelector("[data-msg-notif-interrupteur]");
      if (interrupteur) {
        interrupteur.checked = etat.abonne;
        interrupteur.disabled = etat.occupe || !etat.verifie || (!possible && !etat.abonne);
      }
      section.querySelectorAll("[data-msg-notif-message]").forEach(function (p) {
        p.hidden = p.getAttribute("data-msg-notif-message") !== cas;
      });
      var essai = section.querySelector("[data-msg-notif-action=\"essai\"]");
      if (essai) {
        essai.disabled = etat.occupe || !etat.abonne;
      }
      section.setAttribute("aria-busy", etat.occupe ? "true" : "false");
    });

    var variante = varianteBandeau();
    tous("[data-msg-notif-bandeau]").forEach(function (bandeau) {
      bandeau.hidden = variante === "";
      bandeau.querySelectorAll("[data-msg-notif-variante]").forEach(function (partie) {
        partie.hidden = (" " + partie.getAttribute("data-msg-notif-variante") + " ").indexOf(" " + variante + " ") === -1;
      });
      var bouton = bandeau.querySelector("[data-msg-notif-action=\"activer\"]");
      if (bouton) {
        bouton.disabled = etat.occupe;
      }
    });
  }

  function effacerRetours() {
    tous("[data-msg-notif-erreur], [data-msg-notif-retour]").forEach(function (p) {
      p.hidden = true;
      p.textContent = "";
    });
  }

  function annoncer(texte) {
    tous("[data-msg-notif-retour]").forEach(function (p) {
      p.textContent = texte;
      p.hidden = false;
    });
  }

  function signaler(texte) {
    tous("[data-msg-notif-erreur]").forEach(function (p) {
      p.textContent = texte;
      p.hidden = false;
    });
  }

  // ------------------------------------------------------------------
  // Écoutes
  // ------------------------------------------------------------------

  document.addEventListener("click", function (evenement) {
    var cible = evenement.target && evenement.target.closest ? evenement.target.closest("[data-msg-notif-action], [data-msg-action=\"reglages\"]") : null;
    if (!cible) {
      return;
    }
    var action = cible.getAttribute("data-msg-notif-action");
    if (action === "activer") {
      activer();
    } else if (action === "plus-tard") {
      ecrire(CLE_BANDEAU, "1");
      etat.refuseIci = false;
      rendre();
    } else if (action === "essai") {
      essayer();
    } else if (cible.getAttribute("data-msg-action") === "reglages") {
      // L'autorisation a pu changer dans le navigateur depuis le chargement.
      effacerRetours();
      rendre();
    }
  });

  document.addEventListener("change", function (evenement) {
    var cible = evenement.target;
    if (!cible || !cible.hasAttribute || !cible.hasAttribute("data-msg-notif-interrupteur")) {
      return;
    }
    if (cible.checked) {
      activer();
    } else {
      desactiver();
    }
  });

  // Le panneau latéral est créé à sa première ouverture : on y reporte l'état.
  var tiroir = document.querySelector("[data-msg-tiroir-corps]");
  if (tiroir && typeof MutationObserver === "function") {
    new MutationObserver(rendre).observe(tiroir, { childList: true });
  }

  /*
   * Clic sur une notification : le service worker demande à l'onglet de la
   * messagerie d'aller au message. Changer l'ancre suffit, l'écran suit
   * l'adresse (#c=12&m=345) sans se recharger.
   */
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.addEventListener("message", function (evenement) {
      var d = evenement.data || {};
      if (d.type !== "lbp-messagerie-ouvrir" || typeof d.url !== "string") {
        return;
      }
      var cible;
      try {
        cible = new URL(d.url, window.location.href);
      } catch (e) {
        return;
      }
      if (cible.origin !== window.location.origin) {
        return;
      }
      if (evenement.ports && evenement.ports[0]) {
        evenement.ports[0].postMessage({ ok: true });
      }
      if (cible.pathname.replace(/\/$/, "") === window.location.pathname.replace(/\/$/, "")) {
        if (window.location.hash !== cible.hash) {
          window.location.hash = cible.hash;
        }
      } else {
        window.location.assign(cible.href);
      }
    });
    if (typeof navigator.serviceWorker.startMessages === "function") {
      navigator.serviceWorker.startMessages();
    }
  }

  rendre();
  verifier();
})();
