/*
 * Messagerie interne — l'interface (écran /messagerie et panneau latéral).
 *
 * Trois parties :
 *   - Magasin : l'état partagé de la page (discussions, présence, lectures,
 *     messages déjà chargés). Il écoute window.LbpReleve et ne connaît rien
 *     d'autre de la façon dont les nouvelles arrivent ;
 *   - Veille : pastille de l'en-tête, titre de l'onglet, son, session ;
 *   - Application : une instance par charpente [data-msg-app] (l'écran, ou le
 *     panneau, créé à la première ouverture).
 *
 * Sécurité : tout texte venu du serveur passe par textContent ou par des
 * nœuds texte : jamais de HTML construit à partir d’une chaîne.
 */
(function () {
  "use strict";

  var configuration = document.querySelector("[data-msg-config]");
  if (!configuration || !window.LbpReleve || typeof fetch !== "function") {
    return;
  }

  var CFG = {
    api: (configuration.getAttribute("data-api") || "").replace(/\/$/, ""),
    csrf: configuration.getAttribute("data-csrf") || "",
    moi: parseInt(configuration.getAttribute("data-moi"), 10) || 0,
    moiNom: configuration.getAttribute("data-moi-nom") || "",
    connexion: configuration.getAttribute("data-connexion") || "",
    plein: configuration.getAttribute("data-plein") || "",
    direction: configuration.getAttribute("data-direction") === "1"
  };

  var NS = "http://www.w3.org/2000/svg";
  var MOUVEMENT_REDUIT = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var TACTILE = window.matchMedia && window.matchMedia("(pointer: coarse)").matches;
  var LARGEUR_UNE_COLONNE = 768;
  var REGROUPEMENT_SECONDES = 300;

  // ==================================================================
  // Utilitaires
  // ==================================================================

  function el(tag, proprietes, enfants) {
    var noeud = document.createElement(tag);
    if (proprietes) {
      Object.keys(proprietes).forEach(function (cle) {
        var valeur = proprietes[cle];
        if (valeur === null || valeur === undefined || valeur === false) {
          return;
        }
        if (cle === "class") {
          noeud.className = valeur;
        } else if (cle === "text") {
          noeud.textContent = String(valeur);
        } else if (cle.indexOf("on") === 0 && typeof valeur === "function") {
          noeud.addEventListener(cle.slice(2), valeur);
        } else {
          noeud.setAttribute(cle, valeur === true ? "" : String(valeur));
        }
      });
    }
    ajouter(noeud, enfants);
    return noeud;
  }

  function ajouter(noeud, enfants) {
    if (enfants === null || enfants === undefined) {
      return;
    }
    (Array.isArray(enfants) ? enfants : [enfants]).forEach(function (enfant) {
      if (enfant === null || enfant === undefined || enfant === false) {
        return;
      }
      noeud.appendChild(typeof enfant === "string" ? document.createTextNode(enfant) : enfant);
    });
  }

  function icone(nom, classe) {
    var svg = document.createElementNS(NS, "svg");
    svg.setAttribute("class", "msg-i" + (classe ? " " + classe : ""));
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("focusable", "false");
    var use = document.createElementNS(NS, "use");
    use.setAttribute("href", "#msg-i-" + nom);
    svg.appendChild(use);
    return svg;
  }

  function vider(noeud) {
    while (noeud.firstChild) {
      noeud.removeChild(noeud.firstChild);
    }
  }

  function texteMasque(texte) {
    return el("span", { class: "msg-sr", text: texte });
  }

  function attendre(fonction, delai) {
    var minuterie = null;
    return function () {
      var args = arguments;
      var contexte = this;
      window.clearTimeout(minuterie);
      minuterie = window.setTimeout(function () { fonction.apply(contexte, args); }, delai);
    };
  }

  function sansAccents(texte) {
    return String(texte || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
  }

  function initiales(nom) {
    var mots = String(nom || "?").trim().split(/\s+/).filter(Boolean);
    if (mots.length === 0) {
      return "?";
    }
    var premiere = mots[0].charAt(0);
    var seconde = mots.length > 1 ? mots[mots.length - 1].charAt(0) : (mots[0].charAt(1) || "");
    return (premiere + seconde).toUpperCase();
  }

  /* Prénom usuel : les noms de l'ERP sont saisis « NOM Prénoms ». */
  function nomCourt(nom) {
    var mots = String(nom || "").trim().split(/\s+/).filter(Boolean);
    if (mots.length <= 1) {
      return mots[0] || "";
    }
    var prenom = mots.find(function (mot) { return mot !== mot.toUpperCase(); });
    var choisi = prenom || mots[mots.length > 2 ? 2 : 1];
    return choisi.charAt(0).toUpperCase() + choisi.slice(1).toLowerCase();
  }

  function teinte(identifiant) {
    return "msg-c" + (Math.abs(parseInt(identifiant, 10) || 0) % 8);
  }

  function avatar(nom, identifiant, classe) {
    return el("span", { class: "msg-avatar " + teinte(identifiant) + (classe ? " " + classe : ""), "aria-hidden": "true", text: initiales(nom) });
  }

  function apercuDe(message) {
    if (message.supprime && message.texte === null) {
      return message.libelle || "Message supprimé";
    }
    var texte = String(message.texte || "").replace(/\s+/g, " ").trim();
    // Un fichier envoyé sans légende : on dit ce que c'est, comme le serveur.
    if (texte === "" && message.pieces_jointes && message.pieces_jointes.length) {
      var piece = message.pieces_jointes[0];
      texte = piece.categorie === "image" ? "Photo" : "Document : " + piece.nom;
    }
    return texte.length > 120 ? texte.slice(0, 119) + "…" : texte;
  }

  // ------------------------------------------------------------------
  // Fichiers : ce que le serveur accepte (MessagerieFormats)
  // ------------------------------------------------------------------

  var FICHIERS = {
    max: 10,
    tailleMax: 25 * 1024 * 1024,
    categories: {
      pdf: "pdf", jpg: "image", jpeg: "image", png: "image", webp: "image",
      doc: "word", docx: "word", xls: "tableur", xlsx: "tableur", csv: "tableur",
      ppt: "presentation", pptx: "presentation", txt: "texte"
    },
    formats: "PDF, images JPG, PNG ou WEBP, Word, Excel, PowerPoint, TXT ou CSV"
  };

  var CATEGORIES = {
    pdf: { libelle: "PDF", icone: "fichier-texte" },
    image: { libelle: "Image", icone: "image" },
    word: { libelle: "Word", icone: "fichier-texte" },
    tableur: { libelle: "Tableur", icone: "tableur" },
    presentation: { libelle: "Présentation", icone: "presentation" },
    texte: { libelle: "Texte", icone: "fichier-texte" }
  };

  function extensionDe(nom) {
    var correspondance = /\.([a-z0-9]{1,8})$/i.exec(String(nom || ""));
    return correspondance ? correspondance[1].toLowerCase() : "";
  }

  function categorieDe(nom) {
    return FICHIERS.categories[extensionDe(nom)] || null;
  }

  /* 1 234 567 octets => « 1,2 Mo ». */
  function tailleLisible(octets) {
    var n = Math.max(0, Number(octets) || 0);
    if (n < 1024) {
      return n + " o";
    }
    if (n < 1024 * 1024) {
      return Math.max(1, Math.round(n / 1024)) + " Ko";
    }
    var mo = n / (1024 * 1024);
    return (mo < 10 ? mo.toFixed(1).replace(".", ",") : String(Math.round(mo))) + " Mo";
  }

  /* La raison pour laquelle on refuse un fichier avant de l'envoyer, ou null. */
  function raisonRefus(fichier) {
    if (!categorieDe(fichier.name)) {
      return "ce format n’est pas accepté (" + FICHIERS.formats + ").";
    }
    if (fichier.size === 0) {
      return "le fichier est vide.";
    }
    if (fichier.size > FICHIERS.tailleMax) {
      return "il pèse " + tailleLisible(fichier.size) + ", au-delà des 25 Mo permis.";
    }
    return null;
  }

  /* Pastille d'un type de fichier : icône et extension, teintée par catégorie. */
  function vignetteDocument(piece, classe) {
    var categorie = CATEGORIES[piece.categorie] ? piece.categorie : "texte";
    return el("span", { class: "msg-type msg-type--" + categorie + (classe ? " " + classe : ""), "aria-hidden": "true" }, [
      icone(CATEGORIES[categorie].icone),
      el("span", { class: "msg-type-ext", text: String(piece.extension || extensionDe(piece.nom) || "").toUpperCase().slice(0, 4) })
    ]);
  }

  function urlTelechargement(piece) {
    return piece.url + (piece.url.indexOf("?") === -1 ? "?" : "&") + "telecharger=1";
  }

  // ------------------------------------------------------------------
  // Dates — le serveur parle en heure d'Abidjan, qui est GMT toute l'année.
  // ------------------------------------------------------------------

  var MOIS = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];
  var JOURS = ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"];

  function versDate(chaine) {
    if (!chaine) {
      return null;
    }
    var date = new Date(String(chaine).replace(" ", "T") + "Z");
    return isNaN(date.getTime()) ? null : date;
  }

  function cleJour(date) {
    return date.toISOString().slice(0, 10);
  }

  function ecartJours(date) {
    var aujourdHui = Date.UTC(new Date().getUTCFullYear(), new Date().getUTCMonth(), new Date().getUTCDate());
    var jour = Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate());
    return Math.round((aujourdHui - jour) / 86400000);
  }

  function heure(date) {
    var m = date.getUTCMinutes();
    return date.getUTCHours() + " h " + (m < 10 ? "0" : "") + m;
  }

  function libelleJour(date) {
    var ecart = ecartJours(date);
    if (ecart === 0) {
      return "Aujourd’hui";
    }
    if (ecart === 1) {
      return "Hier";
    }
    if (ecart > 1 && ecart < 7) {
      var jour = JOURS[date.getUTCDay()];
      return jour.charAt(0).toUpperCase() + jour.slice(1);
    }
    var texte = date.getUTCDate() + " " + MOIS[date.getUTCMonth()];
    if (date.getUTCFullYear() !== new Date().getUTCFullYear()) {
      texte += " " + date.getUTCFullYear();
    }
    return texte;
  }

  function libelleListe(date) {
    var ecart = ecartJours(date);
    if (ecart === 0) {
      return heure(date);
    }
    if (ecart === 1) {
      return "Hier";
    }
    if (ecart > 1 && ecart < 7) {
      return JOURS[date.getUTCDay()];
    }
    var j = date.getUTCDate();
    var m = date.getUTCMonth() + 1;
    return (j < 10 ? "0" : "") + j + "/" + (m < 10 ? "0" : "") + m + "/" + String(date.getUTCFullYear()).slice(2);
  }

  function libelleDateComplete(date) {
    return libelleJour(date) + " à " + heure(date);
  }

  function libellePresence(presence) {
    if (!presence) {
      return "";
    }
    if (presence.en_ligne) {
      return "en ligne";
    }
    var vu = versDate(presence.vu_at);
    if (!vu) {
      return "";
    }
    var ecart = ecartJours(vu);
    if (ecart === 0) {
      return "vu à " + heure(vu);
    }
    if (ecart === 1) {
      return "vu hier à " + heure(vu);
    }
    var j = vu.getUTCDate();
    var m = vu.getUTCMonth() + 1;
    return "vu le " + (j < 10 ? "0" : "") + j + "/" + (m < 10 ? "0" : "") + m + " à " + heure(vu);
  }

  // ==================================================================
  // Appels au serveur
  // ==================================================================

  function ErreurApi(message, statut) {
    this.message = message;
    this.statut = statut;
  }

  var MESSAGES_PAR_STATUT = {
    0: "Connexion au serveur impossible. Vérifiez votre réseau puis réessayez.",
    401: "Votre session a expiré. Reconnectez-vous.",
    403: "Cette action ne vous est pas permise.",
    404: "Cette discussion n’existe plus ou ne vous est pas accessible.",
    419: "La page a expiré. Rechargez-la puis recommencez.",
    429: "Vous envoyez beaucoup de messages : patientez une minute.",
    500: "Le serveur a rencontré une erreur. Réessayez dans un instant."
  };

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
        throw new ErreurApi(MESSAGES_PAR_STATUT[0], 0);
      })
      .then(function (reponse) {
        return reponse.json().catch(function () { return null; }).then(function (donnees) {
          if (reponse.status === 401) {
            window.LbpReleve.signalerSessionExpiree(donnees && donnees.message);
          }
          if (!reponse.ok || !donnees || donnees.ok !== true) {
            var message = donnees && donnees.message ? donnees.message : (MESSAGES_PAR_STATUT[reponse.status] || MESSAGES_PAR_STATUT[500]);
            throw new ErreurApi(message, reponse.status);
          }
          return donnees;
        });
      });
  }

  var MESSAGES_FICHIER = {
    0: "Connexion interrompue pendant l’envoi. Réessayez.",
    413: "Fichier trop lourd : 25 Mo au plus.",
    422: "Ce fichier n’a pas pu être accepté.",
    429: "Trop d’envois en une minute : patientez un instant puis réessayez."
  };

  /*
   * Envoi d'un formulaire multipart (un fichier) par XMLHttpRequest, seul à
   * dire où en est l'envoi. Rend { promesse, annuler } ; la promesse est
   * rejetée avec une ErreurApi (statut -1 si l'envoi a été annulé).
   */
  function envoyerFormulaire(chemin, donnees, surProgression) {
    var xhr = new XMLHttpRequest();
    var promesse = new Promise(function (resoudre, rejeter) {
      xhr.open("POST", CFG.api + chemin);
      xhr.setRequestHeader("Accept", "application/json");
      xhr.setRequestHeader("X-CSRF-TOKEN", CFG.csrf);
      if (surProgression && xhr.upload) {
        xhr.upload.addEventListener("progress", function (evenement) {
          if (evenement.lengthComputable && evenement.total > 0) {
            surProgression(evenement.loaded / evenement.total);
          }
        });
      }
      xhr.addEventListener("load", function () {
        var reponse = null;
        try { reponse = JSON.parse(xhr.responseText); } catch (e) { reponse = null; }
        if (xhr.status === 401) {
          window.LbpReleve.signalerSessionExpiree(reponse && reponse.message);
        }
        if (xhr.status >= 200 && xhr.status < 300 && reponse && reponse.ok === true) {
          resoudre(reponse);
          return;
        }
        var message = reponse && reponse.message ? reponse.message
          : (MESSAGES_FICHIER[xhr.status] || MESSAGES_PAR_STATUT[xhr.status] || MESSAGES_PAR_STATUT[500]);
        rejeter(new ErreurApi(message, xhr.status));
      });
      xhr.addEventListener("error", function () { rejeter(new ErreurApi(MESSAGES_FICHIER[0], 0)); });
      xhr.addEventListener("abort", function () { rejeter(new ErreurApi("Envoi annulé.", -1)); });
      xhr.send(donnees);
    });
    return { promesse: promesse, annuler: function () { xhr.abort(); } };
  }

  function messageErreur(erreur) {
    return erreur && erreur.message ? erreur.message : MESSAGES_PAR_STATUT[500];
  }

  // ==================================================================
  // Notifications passagères
  // ==================================================================

  function toast(texte, ton) {
    var zone = document.querySelector("[data-msg-toasts]");
    if (!zone) {
      return;
    }
    var noeud = el("div", { class: "msg-toast" + (ton ? " msg-toast--" + ton : "") }, [
      icone(ton === "erreur" ? "alerte" : "coche"),
      el("span", { text: texte })
    ]);
    zone.appendChild(noeud);
    window.setTimeout(function () {
      noeud.classList.add("is-sortant");
      window.setTimeout(function () { noeud.remove(); }, 300);
    }, ton === "erreur" ? 6000 : 3500);
  }

  // ==================================================================
  // Son discret (Web Audio, sans fichier)
  // ==================================================================

  var Son = (function () {
    var contexte = null;
    var dernier = 0;

    function obtenir() {
      if (contexte) {
        return contexte;
      }
      var Classe = window.AudioContext || window.webkitAudioContext;
      if (!Classe) {
        return null;
      }
      contexte = new Classe();
      return contexte;
    }

    // Les navigateurs n'autorisent le son qu'après un geste de l'utilisateur.
    document.addEventListener("pointerdown", function deverrouiller() {
      var c = obtenir();
      if (c && c.state === "suspended") {
        c.resume().catch(function () {});
      }
      document.removeEventListener("pointerdown", deverrouiller);
    });

    return {
      jouer: function () {
        var maintenant = Date.now();
        if (maintenant - dernier < 1500) {
          return;
        }
        dernier = maintenant;
        var c = obtenir();
        if (!c || c.state !== "running") {
          return;
        }
        [[660, 0], [880, 0.11]].forEach(function (note) {
          var oscillateur = c.createOscillator();
          var volume = c.createGain();
          var debut = c.currentTime + note[1];
          oscillateur.type = "sine";
          oscillateur.frequency.value = note[0];
          volume.gain.setValueAtTime(0.0001, debut);
          volume.gain.exponentialRampToValueAtTime(0.06, debut + 0.02);
          volume.gain.exponentialRampToValueAtTime(0.0001, debut + 0.16);
          oscillateur.connect(volume).connect(c.destination);
          oscillateur.start(debut);
          oscillateur.stop(debut + 0.18);
        });
      }
    };
  })();

  // ==================================================================
  // Magasin : l'état partagé de la page
  // ==================================================================

  var Magasin = (function () {
    var conversations = new Map();
    var ordre = [];
    var charge = false;
    var chargement = null;
    var preferences = null;
    var chargementPreferences = null;
    var presence = new Map();
    var lectures = new Map();
    var caches = new Map();
    var total = 0;
    var abonnes = {};
    var vues = [];

    function emettre(nom, donnees) {
      (abonnes[nom] || []).slice().forEach(function (fonction) { fonction(donnees); });
    }

    function trier() {
      ordre = Array.from(conversations.values()).sort(function (a, b) {
        var da = a.dernier_message_at || a.created_at || "";
        var db = b.dernier_message_at || b.created_at || "";
        if (da !== db) {
          return da < db ? 1 : -1;
        }
        return b.id - a.id;
      }).map(function (c) { return c.id; });
    }

    function recompterTotal() {
      var somme = 0;
      conversations.forEach(function (c) { somme += c.non_lus || 0; });
      total = somme;
      emettre("total", total);
    }

    function chargerConversations() {
      if (chargement) {
        return chargement;
      }
      chargement = appel("GET", "/conversations").then(function (reponse) {
        var nouvelles = new Map();
        (reponse.conversations || []).forEach(function (c) { nouvelles.set(c.id, c); });
        conversations = nouvelles;
        charge = true;
        trier();
        recompterTotal();
        emettre("conversations");
        return conversations;
      }).then(function (resultat) {
        chargement = null;
        return resultat;
      }, function (erreur) {
        chargement = null;
        throw erreur;
      });
      return chargement;
    }

    var rafraichir = attendre(function () {
      chargerConversations().catch(function () {});
    }, 300);

    function chargerPreferences() {
      if (preferences) {
        return Promise.resolve(preferences);
      }
      if (!chargementPreferences) {
        chargementPreferences = appel("GET", "/preferences").then(function (r) {
          preferences = r.preferences;
          emettre("preferences", preferences);
          return preferences;
        }, function (erreur) {
          chargementPreferences = null;
          throw erreur;
        });
      }
      return chargementPreferences;
    }

    function lecturesDe(conversationId) {
      if (!lectures.has(conversationId)) {
        lectures.set(conversationId, new Map());
      }
      return lectures.get(conversationId);
    }

    /* Range un message dans le cache d'une discussion ; vrai s'il est nouveau. */
    function ranger(cache, message) {
      if (cache.parId.has(message.id)) {
        var ancien = cache.parId.get(message.id);
        Object.assign(ancien, message);
        return false;
      }
      cache.parId.set(message.id, message);
      var liste = cache.messages;
      if (liste.length === 0 || liste[liste.length - 1].id < message.id) {
        liste.push(message);
      } else {
        var i = liste.findIndex(function (m) { return m.id > message.id; });
        liste.splice(i, 0, message);
      }
      return true;
    }

    function mettreAJourDernier(conversation, message) {
      if (!conversation.dernier_message || message.id >= conversation.dernier_message.id) {
        conversation.dernier_message = {
          id: message.id,
          auteur_id: message.auteur.id,
          auteur_nom: message.auteur.nom,
          apercu: apercuDe(message),
          supprime: !!message.supprime,
          created_at: message.created_at
        };
        conversation.dernier_message_at = message.created_at;
      }
    }

    function affichee(conversationId) {
      return vues.some(function (fonction) { return fonction(conversationId); });
    }

    function sonner(messages) {
      var candidats = messages.filter(function (m) { return m.auteur.id !== CFG.moi; });
      if (candidats.length === 0) {
        return;
      }
      chargerPreferences().then(function (p) {
        if (!p.son_actif) {
          return;
        }
        var audible = candidats.some(function (m) {
          var c = conversations.get(m.conversation_id);
          return !(c && c.sourdine) && !affichee(m.conversation_id);
        });
        if (audible) {
          Son.jouer();
        }
      }).catch(function () {});
    }

    function traiterReleve(r) {
      total = r.non_lus_total || 0;

      (r.presence || []).forEach(function (p) { presence.set(p.user_id, p); });
      if ((r.presence || []).length) {
        emettre("presence");
      }

      var nouveaux = r.messages || [];
      if (!charge) {
        emettre("total", total);
        if (nouveaux.some(function (m) { return m.auteur.id !== CFG.moi; })) {
          // Premier message reçu sur cette page : on charge les discussions
          // pour savoir lesquelles sont en sourdine.
          chargerConversations().then(function () { sonner(nouveaux); }).catch(function () {});
        }
        return;
      }

      var inconnues = false;
      var parConversation = new Map();
      nouveaux.forEach(function (m) {
        var c = conversations.get(m.conversation_id);
        if (!c) {
          inconnues = true;
          return;
        }
        mettreAJourDernier(c, m);
        if (m.auteur.id === CFG.moi) {
          c.dernier_lu_id = Math.max(c.dernier_lu_id || 0, m.id);
        }
        var cache = caches.get(m.conversation_id);
        if (cache && ranger(cache, m)) {
          if (!parConversation.has(m.conversation_id)) {
            parConversation.set(m.conversation_id, []);
          }
          parConversation.get(m.conversation_id).push(m);
        }
      });

      var nonLus = r.non_lus || {};
      conversations.forEach(function (c) { c.non_lus = parseInt(nonLus[String(c.id)], 10) || 0; });

      var modifies = r.messages_modifies || [];
      modifies.forEach(function (m) {
        var cache = caches.get(m.conversation_id);
        if (cache && cache.parId.has(m.id)) {
          Object.assign(cache.parId.get(m.id), m);
        }
        var c = conversations.get(m.conversation_id);
        if (c && c.dernier_message && c.dernier_message.id === m.id) {
          c.dernier_message.apercu = apercuDe(m);
          c.dernier_message.supprime = !!m.supprime;
        }
      });

      var conversationsLues = new Set();
      (r.lectures || []).forEach(function (l) {
        var carte = lecturesDe(l.conversation_id);
        carte.set(l.user_id, Math.max(carte.get(l.user_id) || 0, l.dernier_message_lu_id));
        conversationsLues.add(l.conversation_id);
        if (l.user_id === CFG.moi) {
          var c = conversations.get(l.conversation_id);
          if (c) {
            c.dernier_lu_id = Math.max(c.dernier_lu_id || 0, l.dernier_message_lu_id);
          }
        }
      });

      (r.conversations_quittees || []).forEach(function (id) {
        conversations.delete(id);
        caches.delete(id);
        emettre("quittee", id);
      });

      var modifiees = r.conversations_modifiees || [];
      if (inconnues || modifiees.length) {
        rafraichir();
      }

      trier();
      emettre("total", total);
      emettre("conversations");
      if (parConversation.size) {
        emettre("messages", parConversation);
      }
      if (modifies.length) {
        emettre("modifies", modifies);
      }
      if (conversationsLues.size) {
        emettre("lectures", conversationsLues);
      }
      modifiees.forEach(function (id) { emettre("modifiee", id); });

      sonner(nouveaux);
    }

    window.LbpReleve.on("releve", traiterReleve);

    return {
      on: function (nom, fonction) {
        (abonnes[nom] = abonnes[nom] || []).push(fonction);
      },
      /* Une vue dit si elle montre une discussion (pour le son et la lecture). */
      declarerVue: function (fonction) {
        vues.push(fonction);
      },
      charger: chargerConversations,
      rafraichir: rafraichir,
      estCharge: function () { return charge; },
      conversation: function (id) { return conversations.get(id) || null; },
      liste: function () { return ordre.map(function (id) { return conversations.get(id); }).filter(Boolean); },
      total: function () { return total; },
      presence: function (userId) { return presence.get(userId) || null; },
      preferences: chargerPreferences,
      fixerPreferences: function (p) { preferences = p; emettre("preferences", p); },
      lectures: lecturesDe,
      cache: function (conversationId) {
        if (!caches.has(conversationId)) {
          caches.set(conversationId, { messages: [], parId: new Map(), plusAnciens: true, charge: false });
        }
        return caches.get(conversationId);
      },
      ranger: ranger,
      mettreAJourDernier: function (message) {
        var c = conversations.get(message.conversation_id);
        if (c) {
          mettreAJourDernier(c, message);
          trier();
          emettre("conversations");
        }
      },
      marquerLuLocalement: function (conversationId, jusquA) {
        var c = conversations.get(conversationId);
        if (c) {
          c.dernier_lu_id = Math.max(c.dernier_lu_id || 0, jusquA);
          c.non_lus = 0;
          recompterTotal();
          emettre("conversations");
        }
      },
      oublier: function (conversationId) {
        conversations.delete(conversationId);
        caches.delete(conversationId);
        trier();
        recompterTotal();
        emettre("conversations");
      }
    };
  })();

  // ==================================================================
  // Veille : pastille de l'en-tête, titre de l'onglet, session
  // ==================================================================

  var Veille = (function () {
    var titreDeBase = document.title.replace(/^\(\d+\)\s*/, "");

    function afficherTotal(total) {
      document.querySelectorAll("[data-msg-total]").forEach(function (pastille) {
        pastille.textContent = total > 99 ? "99+" : String(total);
        pastille.hidden = total <= 0;
      });
      document.querySelectorAll(".msg-entete-btn").forEach(function (bouton) {
        bouton.setAttribute("aria-label", total > 0
          ? "Messagerie, " + total + (total > 1 ? " messages non lus" : " message non lu")
          : "Messagerie");
      });
      document.title = (total > 0 ? "(" + total + ") " : "") + titreDeBase;
    }

    Magasin.on("total", afficherTotal);

    window.LbpReleve.on("session-expiree", function (info) {
      document.querySelectorAll("[data-msg='alerte']").forEach(function (zone) {
        vider(zone);
        zone.className = "msg-alerte msg-alerte--session";
        ajouter(zone, [
          icone("cadenas"),
          el("span", { text: info.message || MESSAGES_PAR_STATUT[401] }),
          el("a", { class: "msg-btn msg-btn--primaire", href: info.connexion || CFG.connexion, text: "Se reconnecter" })
        ]);
        zone.hidden = false;
      });
      document.querySelectorAll(".msg-entete-btn").forEach(function (bouton) {
        bouton.classList.add("is-hors-session");
        bouton.setAttribute("title", "Messagerie : session expirée, reconnectez-vous");
      });
    });

    window.LbpReleve.on("connexion", function (etat) {
      document.querySelectorAll("[data-msg='alerte']").forEach(function (zone) {
        if (zone.classList.contains("msg-alerte--session")) {
          return;
        }
        vider(zone);
        if (etat.enLigne) {
          zone.hidden = true;
          return;
        }
        zone.className = "msg-alerte";
        ajouter(zone, [icone("alerte"), el("span", { text: "Connexion au serveur perdue. Nouvel essai en cours…" })]);
        zone.hidden = false;
      });
    });

    return { afficherTotal: afficherTotal };
  })();

  // ==================================================================
  // Menu flottant (actions d'un message, d'un membre)
  // ==================================================================

  var Menu = (function () {
    var ouvert = null;
    var declencheur = null;

    function fermer(rendreFocus) {
      if (!ouvert) {
        return;
      }
      ouvert.remove();
      ouvert = null;
      if (declencheur) {
        declencheur.setAttribute("aria-expanded", "false");
        if (rendreFocus) {
          declencheur.focus();
        }
      }
      declencheur = null;
    }

    function ouvrir(bouton, elements, conteneur) {
      fermer(false);
      if (elements.length === 0) {
        return;
      }
      declencheur = bouton;
      bouton.setAttribute("aria-expanded", "true");
      var menu = el("div", { class: "msg-menu", role: "menu" });
      elements.forEach(function (element) {
        var item = el("button", { type: "button", role: "menuitem", class: "msg-menu-item" + (element.danger ? " is-danger" : ""), tabindex: "-1" }, [
          icone(element.icone),
          el("span", { text: element.libelle })
        ]);
        item.addEventListener("click", function () {
          fermer(false);
          element.action();
        });
        menu.appendChild(item);
      });
      menu.addEventListener("keydown", function (evenement) {
        var items = Array.from(menu.querySelectorAll("[role=menuitem]"));
        var index = items.indexOf(document.activeElement);
        if (evenement.key === "ArrowDown") {
          evenement.preventDefault();
          items[(index + 1) % items.length].focus();
        } else if (evenement.key === "ArrowUp") {
          evenement.preventDefault();
          items[(index - 1 + items.length) % items.length].focus();
        } else if (evenement.key === "Home") {
          evenement.preventDefault();
          items[0].focus();
        } else if (evenement.key === "End") {
          evenement.preventDefault();
          items[items.length - 1].focus();
        } else if (evenement.key === "Escape") {
          evenement.preventDefault();
          evenement.stopPropagation();
          fermer(true);
        } else if (evenement.key === "Tab") {
          fermer(false);
        }
      });
      (conteneur || document.body).appendChild(menu);
      ouvert = menu;
      placer(menu, bouton);
      menu.querySelector("[role=menuitem]").focus();
    }

    function placer(menu, bouton) {
      var r = bouton.getBoundingClientRect();
      var largeur = menu.offsetWidth;
      var hauteur = menu.offsetHeight;
      var gauche = Math.min(Math.max(8, r.right - largeur), window.innerWidth - largeur - 8);
      var haut = r.bottom + 4;
      if (haut + hauteur > window.innerHeight - 8) {
        haut = Math.max(8, r.top - hauteur - 4);
      }
      menu.style.left = gauche + "px";
      menu.style.top = haut + "px";
    }

    document.addEventListener("pointerdown", function (evenement) {
      if (ouvert && !ouvert.contains(evenement.target) && evenement.target !== declencheur && !(declencheur && declencheur.contains(evenement.target))) {
        fermer(false);
      }
    }, true);
    window.addEventListener("resize", function () { fermer(false); });
    document.addEventListener("scroll", function (evenement) {
      if (ouvert && !ouvert.contains(evenement.target)) {
        fermer(false);
      }
    }, true);

    return { ouvrir: ouvrir, fermer: fermer, estOuvert: function () { return ouvert !== null; } };
  })();

  // ==================================================================
  // Pièces jointes : favori, rendu dans une bulle ou le classeur
  // ==================================================================

  function majBoutonFavori(bouton, actif) {
    var libelle = actif ? "Retirer des favoris" : "Ajouter aux favoris";
    bouton.setAttribute("aria-pressed", actif ? "true" : "false");
    bouton.setAttribute("title", libelle);
    bouton.setAttribute("aria-label", libelle + " : " + (bouton.getAttribute("data-nom") || "fichier"));
  }

  /* Le favori est personnel : il range le fichier dans « Favoris » du classeur. */
  function boutonFavori(piece, classe) {
    var bouton = el("button", { type: "button", class: "msg-favori" + (classe ? " " + classe : ""), "data-nom": piece.nom }, icone("etoile"));
    majBoutonFavori(bouton, !!piece.favori);
    bouton.addEventListener("click", function (evenement) {
      evenement.stopPropagation();
      var actif = !piece.favori;
      bouton.disabled = true;
      majBoutonFavori(bouton, actif);
      appel("POST", "/pieces/" + piece.id + "/favori", { actif: actif }).then(function (r) {
        piece.favori = !!r.favori;
        majBoutonFavori(bouton, piece.favori);
        toast(piece.favori ? "Ajouté à vos favoris, dans Documents." : "Retiré de vos favoris.");
      }).catch(function (erreur) {
        majBoutonFavori(bouton, !!piece.favori);
        toast(messageErreur(erreur), "erreur");
      }).then(function () {
        bouton.disabled = false;
      });
    });
    return bouton;
  }

  function libelleType(piece) {
    return (CATEGORIES[piece.categorie] || CATEGORIES.texte).libelle;
  }

  /*
   * Une photo : la vignette réserve sa place grâce aux dimensions connues,
   * pour que le fil ne saute pas quand elle arrive.
   */
  function pieceImage(piece, surOuvrir) {
    var l = piece.largeur > 0 ? piece.largeur : 4;
    var h = piece.hauteur > 0 ? piece.hauteur : 3;
    var largeur = Math.round(Math.max(150, Math.min(300, 320 * l / h, piece.largeur || 300)));
    var cadre = el("div", { class: "msg-piece-image" });
    cadre.style.width = largeur + "px";
    var bouton = el("button", { type: "button", class: "msg-piece-image-btn", "aria-label": "Voir la photo en grand : " + piece.nom, title: "Voir en grand" });
    bouton.style.aspectRatio = l + " / " + h;
    var image = el("img", { src: piece.url_miniature || piece.url, alt: "", loading: "lazy", decoding: "async", width: String(l), height: String(h) });
    image.addEventListener("error", function () {
      cadre.classList.add("is-indisponible");
      vider(bouton);
      ajouter(bouton, [icone("image"), el("span", { text: "Image indisponible" })]);
    });
    bouton.appendChild(image);
    bouton.addEventListener("click", function () { surOuvrir(piece); });
    ajouter(cadre, [bouton, boutonFavori(piece, "msg-favori--sur-image")]);
    return cadre;
  }

  /* Un document : son type, son nom, sa taille, l'aperçu et le téléchargement. */
  function pieceDocument(piece, surApercu) {
    var actions = el("div", { class: "msg-piece-doc-actions" });
    if (piece.categorie === "pdf") {
      // Un téléphone n'affiche pas un PDF dans la page : nouvel onglet.
      if (TACTILE || !surApercu) {
        actions.appendChild(el("a", { class: "msg-piece-action", href: piece.url, target: "_blank", rel: "noopener", "aria-label": "Ouvrir " + piece.nom + " dans un nouvel onglet" }, [icone("ouvrir"), el("span", { text: "Ouvrir" })]));
      } else {
        actions.appendChild(el("button", { type: "button", class: "msg-piece-action", "aria-label": "Aperçu de " + piece.nom, onclick: function () { surApercu(piece); } }, [icone("oeil"), el("span", { text: "Aperçu" })]));
      }
    }
    actions.appendChild(el("a", { class: "msg-piece-action", href: urlTelechargement(piece), download: piece.nom, "aria-label": "Télécharger " + piece.nom + ", " + tailleLisible(piece.taille) }, [icone("telecharger"), el("span", { text: "Télécharger" })]));

    return el("div", { class: "msg-piece-doc" }, [
      el("div", { class: "msg-piece-doc-tete" }, [
        vignetteDocument(piece),
        el("span", { class: "msg-piece-doc-infos" }, [
          el("strong", { class: "msg-piece-nom", text: piece.nom, title: piece.nom }),
          el("small", { text: tailleLisible(piece.taille) + " · " + libelleType(piece) })
        ]),
        boutonFavori(piece)
      ]),
      actions
    ]);
  }

  // ==================================================================
  // Visionneuse plein écran (photos et PDF)
  // ==================================================================

  var Visionneuse = (function () {
    var dialogue = null;
    var r = {};
    var elements = [];
    var index = 0;
    var retour = null;
    var departX = null;

    function construire() {
      r.nom = el("strong", { class: "msg-vis-nom" });
      r.details = el("small", { class: "msg-vis-details" });
      r.telecharger = el("a", { class: "msg-vis-btn", href: "#" }, [icone("telecharger"), el("span", { class: "msg-vis-btn-libelle", text: "Télécharger" })]);
      r.onglet = el("a", { class: "msg-vis-btn msg-vis-btn--icone", href: "#", target: "_blank", rel: "noopener", title: "Ouvrir dans un nouvel onglet", "aria-label": "Ouvrir dans un nouvel onglet" }, icone("ouvrir"));
      r.fermer = el("button", { type: "button", class: "msg-vis-btn msg-vis-btn--icone", title: "Fermer (Échap)", "aria-label": "Fermer la visionneuse" }, icone("fermer"));
      r.scene = el("div", { class: "msg-vis-scene" });
      r.precedent = el("button", { type: "button", class: "msg-vis-nav msg-vis-nav--precedent", title: "Précédente (flèche gauche)", "aria-label": "Image précédente" }, icone("precedent"));
      r.suivant = el("button", { type: "button", class: "msg-vis-nav msg-vis-nav--suivant", title: "Suivante (flèche droite)", "aria-label": "Image suivante" }, icone("suivant"));
      r.legende = el("p", { class: "msg-vis-legende" });
      r.annonce = el("p", { class: "msg-sr", "aria-live": "polite" });

      dialogue = el("dialog", { class: "msg-visionneuse", "aria-label": "Visionneuse" }, [
        el("header", { class: "msg-vis-tete" }, [
          el("div", { class: "msg-vis-titre" }, [r.nom, r.details]),
          el("div", { class: "msg-vis-actions" }, [r.telecharger, r.onglet, r.fermer])
        ]),
        el("div", { class: "msg-vis-corps" }, [r.precedent, r.scene, r.suivant]),
        r.legende,
        r.annonce
      ]);

      r.fermer.addEventListener("click", fermer);
      r.precedent.addEventListener("click", function () { aller(-1); });
      r.suivant.addEventListener("click", function () { aller(1); });
      r.scene.addEventListener("click", function (evenement) {
        if (evenement.target === r.scene) {
          fermer();
        }
      });
      dialogue.addEventListener("keydown", function (evenement) {
        if (evenement.key === "ArrowLeft") {
          evenement.preventDefault();
          aller(-1);
        } else if (evenement.key === "ArrowRight") {
          evenement.preventDefault();
          aller(1);
        }
      });
      // Glisser du doigt pour passer d'une photo à l'autre.
      r.scene.addEventListener("touchstart", function (evenement) {
        departX = evenement.touches.length === 1 ? evenement.touches[0].clientX : null;
      }, { passive: true });
      r.scene.addEventListener("touchend", function (evenement) {
        if (departX === null || !evenement.changedTouches.length) {
          return;
        }
        var ecart = evenement.changedTouches[0].clientX - departX;
        departX = null;
        if (Math.abs(ecart) > 60) {
          aller(ecart < 0 ? 1 : -1);
        }
      }, { passive: true });
      dialogue.addEventListener("close", function () {
        // L'événement arrive après coup : la visionneuse a pu être rouverte.
        if (dialogue.open) {
          return;
        }
        vider(r.scene);
        document.documentElement.classList.remove("msg-vis-ouverte");
        if (retour && document.contains(retour)) {
          retour.focus({ preventScroll: true });
        }
      });
      document.body.appendChild(dialogue);
    }

    function afficher() {
      var element = elements[index];
      var piece = element.piece;
      vider(r.scene);
      r.nom.textContent = piece.nom;
      r.nom.setAttribute("title", piece.nom);
      var details = [];
      if (element.auteur) {
        details.push(element.auteur);
      }
      if (element.date) {
        details.push(libelleDateComplete(element.date));
      }
      details.push(tailleLisible(piece.taille));
      if (elements.length > 1) {
        details.push((index + 1) + " sur " + elements.length);
      }
      r.details.textContent = details.join(" · ");
      r.telecharger.setAttribute("href", urlTelechargement(piece));
      r.telecharger.setAttribute("download", piece.nom);
      r.telecharger.setAttribute("aria-label", "Télécharger " + piece.nom);
      r.onglet.setAttribute("href", piece.url);

      if (piece.categorie === "image") {
        r.scene.classList.add("is-chargement");
        var image = el("img", { class: "msg-vis-image", src: piece.url, alt: element.legende || piece.nom });
        image.addEventListener("load", function () { r.scene.classList.remove("is-chargement"); });
        image.addEventListener("error", function () {
          r.scene.classList.remove("is-chargement");
          vider(r.scene);
          r.scene.appendChild(el("p", { class: "msg-vis-erreur" }, [icone("alerte"), el("span", { text: "Cette image n’a pas pu être chargée." })]));
        });
        r.scene.appendChild(image);
        var suivante = elements[(index + 1) % elements.length];
        if (elements.length > 1 && suivante.piece.categorie === "image") {
          new Image().src = suivante.piece.url;
        }
      } else {
        r.scene.classList.remove("is-chargement");
        r.scene.appendChild(el("iframe", { class: "msg-vis-pdf", src: piece.url, title: "Aperçu de " + piece.nom }));
      }

      r.legende.textContent = element.legende || "";
      r.legende.hidden = !element.legende;
      r.precedent.hidden = elements.length < 2;
      r.suivant.hidden = elements.length < 2;
      dialogue.classList.toggle("is-pdf", piece.categorie !== "image");
      r.annonce.textContent = elements.length > 1 ? "Image " + (index + 1) + " sur " + elements.length + " : " + piece.nom : piece.nom;
    }

    function aller(pas) {
      if (!dialogue || !dialogue.open || elements.length < 2) {
        return;
      }
      index = (index + pas + elements.length) % elements.length;
      afficher();
    }

    function fermer() {
      if (dialogue && dialogue.open) {
        dialogue.close();
      }
    }

    /* elements : [{ piece, auteur, date, legende }] ; depart : l'index affiché. */
    function ouvrir(liste, depart) {
      if (!liste.length) {
        return;
      }
      if (!dialogue) {
        construire();
      }
      elements = liste;
      index = Math.min(Math.max(0, depart || 0), liste.length - 1);
      retour = document.activeElement;
      afficher();
      if (!dialogue.open) {
        if (typeof dialogue.showModal === "function") {
          dialogue.showModal();
        } else {
          dialogue.setAttribute("open", "");
        }
      }
      document.documentElement.classList.add("msg-vis-ouverte");
      r.fermer.focus({ preventScroll: true });
    }

    return { ouvrir: ouvrir, fermer: fermer };
  })();

  // ==================================================================
  // Application : une charpente [data-msg-app]
  // ==================================================================

  function creerApplication(racine) {
    var mode = racine.getAttribute("data-mode") || "plein";
    var refs = {};
    racine.querySelectorAll("[data-msg]").forEach(function (n) {
      refs[n.getAttribute("data-msg")] = n;
    });
    var dialogues = {};
    racine.querySelectorAll("[data-msg-dialogue]").forEach(function (d) {
      dialogues[d.getAttribute("data-msg-dialogue")] = d;
    });

    var etat = {
      filtre: "toutes",
      recherche: "",
      actifId: null,
      detail: null,
      envoisEnCours: 0,
      reponseA: null,
      edition: null,
      brouillons: new Map(),
      noeuds: new Map(),
      dernierRendu: null,
      separateurNonLu: 0,
      nouveauxEnBas: 0,
      chargementAncien: false,
      epingleIndex: 0,
      visible: mode === "plein",
      lectureEnvoyee: 0
    };

    // --------------------------------------------------------------
    // Disposition : une ou deux colonnes
    // --------------------------------------------------------------

    function uneColonne() {
      return mode === "panneau" || racine.clientWidth < LARGEUR_UNE_COLONNE;
    }

    function montrerVue(vue) {
      racine.setAttribute("data-vue", vue);
    }

    // --------------------------------------------------------------
    // Liste des discussions
    // --------------------------------------------------------------

    function filtrees() {
      var recherche = sansAccents(etat.recherche.trim());
      return Magasin.liste().filter(function (c) {
        if (etat.filtre === "archivees") {
          if (!c.archivee) { return false; }
        } else if (c.archivee) {
          return false;
        }
        if (etat.filtre === "non_lues" && !(c.non_lus > 0)) {
          return false;
        }
        if (etat.filtre === "groupes" && c.type === "direct") {
          return false;
        }
        if (recherche && sansAccents(c.nom).indexOf(recherche) === -1) {
          return false;
        }
        return true;
      });
    }

    function apercuListe(c, preferences) {
      if (!c.dernier_message) {
        return c.type === "direct" ? "Aucun message pour l’instant" : (c.description || "Groupe créé");
      }
      if (preferences && preferences.apercu_masque) {
        return c.non_lus > 0 ? "Nouveau message" : "Message";
      }
      var d = c.dernier_message;
      var prefixe = "";
      if (d.auteur_id === CFG.moi) {
        prefixe = "Vous : ";
      } else if (c.type !== "direct") {
        prefixe = nomCourt(d.auteur_nom) + " : ";
      }
      return prefixe + d.apercu;
    }

    var preferencesCourantes = null;

    function rendreListe() {
      var liste = refs.convs;
      var focusId = document.activeElement && document.activeElement.closest && document.activeElement.closest(".msg-conv")
        ? document.activeElement.closest(".msg-conv").getAttribute("data-id") : null;
      vider(liste);

      if (!Magasin.estCharge()) {
        return;
      }
      var elements = filtrees();
      elements.forEach(function (c) {
        liste.appendChild(elementListe(c));
      });

      vider(refs["liste-etat"]);
      if (elements.length === 0) {
        refs["liste-etat"].hidden = false;
        var messages = {
          toutes: ["Pas encore de discussion", "Écrivez à un collègue pour commencer."],
          non_lues: ["Tout est lu", "Aucune discussion n’attend de réponse."],
          groupes: ["Aucun groupe", "Créez un groupe pour échanger à plusieurs."],
          archivees: ["Aucune discussion archivée", "Les discussions archivées sortent de la liste principale."]
        };
        var texte = etat.recherche.trim() ? ["Aucune discussion trouvée", "Aucun nom ne correspond à « " + etat.recherche.trim() + " »."] : messages[etat.filtre];
        ajouter(refs["liste-etat"], [
          el("strong", { text: texte[0] }),
          el("p", { text: texte[1] })
        ]);
        if (etat.filtre === "toutes" && !etat.recherche.trim()) {
          refs["liste-etat"].appendChild(el("button", { type: "button", class: "msg-btn msg-btn--primaire", onclick: ouvrirAnnuaire }, [icone("nouvelle"), el("span", { text: "Nouvelle discussion" })]));
        }
      } else {
        refs["liste-etat"].hidden = true;
      }

      if (focusId) {
        var cible = liste.querySelector('.msg-conv[data-id="' + focusId + '"]');
        if (cible) {
          cible.focus();
        }
      }
    }

    function elementListe(c) {
      var date = versDate(c.dernier_message ? c.dernier_message.created_at : c.created_at);
      var nonLus = c.non_lus || 0;
      var actif = c.id === etat.actifId;
      var description = c.nom + (nonLus ? ", " + nonLus + (nonLus > 1 ? " messages non lus" : " message non lu") : "");

      var bouton = el("button", {
        type: "button",
        class: "msg-conv" + (actif ? " is-active" : "") + (nonLus ? " is-non-lue" : ""),
        "data-id": c.id,
        "aria-current": actif ? "true" : null,
        title: description
      }, [
        avatarConversation(c),
        el("span", { class: "msg-conv-corps" }, [
          el("span", { class: "msg-conv-ligne" }, [
            el("span", { class: "msg-conv-nom" }, [
              c.type === "annonces" ? icone("annonces", "msg-i--petit") : null,
              el("span", { text: c.nom })
            ]),
            date ? el("time", { class: "msg-conv-heure", datetime: date.toISOString(), text: libelleListe(date) }) : null
          ]),
          el("span", { class: "msg-conv-ligne" }, [
            el("span", { class: "msg-conv-apercu" + (c.dernier_message && c.dernier_message.supprime ? " is-supprime" : ""), text: apercuListe(c, preferencesCourantes) }),
            el("span", { class: "msg-conv-marques" }, [
              c.sourdine ? el("span", { class: "msg-conv-sourdine", title: "En sourdine" }, [icone("sourdine", "msg-i--petit"), texteMasque("En sourdine")]) : null,
              nonLus ? el("span", { class: "msg-pastille" + (c.sourdine ? " is-discrete" : ""), text: nonLus > 99 ? "99+" : String(nonLus) }) : null
            ])
          ])
        ])
      ]);
      bouton.addEventListener("click", function () { ouvrir(c.id); });
      return el("li", null, bouton);
    }

    function avatarConversation(c) {
      if (c.type === "direct") {
        var correspondant = c.correspondant || { id: c.id, nom: c.nom };
        var p = Magasin.presence(correspondant.id);
        var noeud = avatar(correspondant.nom, correspondant.id);
        if (p && p.en_ligne) {
          noeud.appendChild(el("span", { class: "msg-avatar-enligne" }));
        }
        return noeud;
      }
      var nomIcone = c.type === "annonces" ? "annonces" : "groupe";
      var a = el("span", { class: "msg-avatar msg-avatar--groupe " + teinte(c.id), "aria-hidden": "true" }, icone(nomIcone));
      if (c.photo) {
        // La photo du groupe prend la place de l'icône ; si elle ne se charge
        // pas, l'icône revient.
        vider(a);
        a.classList.add("msg-avatar--photo");
        var photo = el("img", { src: c.photo, alt: "", decoding: "async" });
        photo.addEventListener("error", function () {
          var parent = photo.parentNode;
          if (parent) {
            parent.classList.remove("msg-avatar--photo");
            parent.replaceChild(icone(nomIcone), photo);
          }
        });
        a.appendChild(photo);
      }
      return a;
    }

    // Navigation au clavier dans la liste : flèches haut et bas.
    refs.convs.addEventListener("keydown", function (evenement) {
      if (evenement.key !== "ArrowDown" && evenement.key !== "ArrowUp") {
        return;
      }
      var items = Array.from(refs.convs.querySelectorAll(".msg-conv"));
      var index = items.indexOf(document.activeElement);
      if (index === -1) {
        return;
      }
      evenement.preventDefault();
      var suivant = items[evenement.key === "ArrowDown" ? Math.min(items.length - 1, index + 1) : Math.max(0, index - 1)];
      suivant.focus();
    });

    racine.querySelectorAll("[data-msg-filtre]").forEach(function (bouton) {
      bouton.addEventListener("click", function () {
        etat.filtre = bouton.getAttribute("data-msg-filtre");
        racine.querySelectorAll("[data-msg-filtre]").forEach(function (b) {
          b.setAttribute("aria-pressed", b === bouton ? "true" : "false");
        });
        rendreListe();
      });
    });

    // --------------------------------------------------------------
    // Recherche : noms de discussions (local) et messages (serveur)
    // --------------------------------------------------------------

    var chercherMessages = attendre(function (texte) {
      if (texte.length < 2) {
        refs.resultats.hidden = true;
        vider(refs.resultats);
        return;
      }
      appel("GET", "/recherche?q=" + encodeURIComponent(texte)).then(function (r) {
        if (refs["recherche-liste"].value.trim() !== texte) {
          return;
        }
        rendreResultats(refs.resultats, r.resultats || [], texte, true);
      }).catch(function (erreur) {
        refs.resultats.hidden = false;
        vider(refs.resultats);
        refs.resultats.appendChild(el("p", { class: "msg-erreur", text: messageErreur(erreur) }));
      });
    }, 350);

    refs["recherche-liste"].addEventListener("input", function () {
      etat.recherche = refs["recherche-liste"].value;
      rendreListe();
      chercherMessages(etat.recherche.trim());
    });
    refs["recherche-liste"].addEventListener("keydown", function (evenement) {
      if (evenement.key === "Escape" && refs["recherche-liste"].value) {
        evenement.stopPropagation();
        refs["recherche-liste"].value = "";
        etat.recherche = "";
        rendreListe();
        chercherMessages("");
      } else if (evenement.key === "ArrowDown") {
        var premier = refs.convs.querySelector(".msg-conv");
        if (premier) {
          evenement.preventDefault();
          premier.focus();
        }
      }
    });

    function surligner(texte, requete) {
      var mots = requete.split(/\s+/).filter(function (m) { return m.length >= 2; })
        .map(function (m) { return m.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); });
      if (mots.length === 0) {
        return [texte];
      }
      var morceaux = String(texte).split(new RegExp("(" + mots.join("|") + ")", "gi"));
      return morceaux.map(function (morceau, i) {
        return i % 2 === 1 ? el("mark", { text: morceau }) : morceau;
      });
    }

    function rendreResultats(zone, resultats, requete, avecDiscussion) {
      vider(zone);
      zone.hidden = false;
      zone.appendChild(el("h3", { class: "msg-resultats-titre", text: resultats.length
        ? resultats.length + (resultats.length > 1 ? " messages trouvés" : " message trouvé")
        : "Aucun message trouvé" }));
      var liste = el("ul", { class: "msg-resultats-liste" });
      resultats.forEach(function (m) {
        var c = Magasin.conversation(m.conversation_id);
        var date = versDate(m.created_at);
        var bouton = el("button", { type: "button", class: "msg-resultat" }, [
          el("span", { class: "msg-resultat-ligne" }, [
            el("strong", { text: avecDiscussion && c ? c.nom : m.auteur.nom }),
            date ? el("time", { datetime: date.toISOString(), text: libelleListe(date) }) : null
          ]),
          el("span", { class: "msg-resultat-extrait" }, [
            avecDiscussion && c && c.type !== "direct" ? nomCourt(m.auteur.nom) + " : " : "",
            el("span", null, surligner(apercuDe(m), requete))
          ])
        ]);
        bouton.addEventListener("click", function () {
          ouvrir(m.conversation_id, m.id);
        });
        liste.appendChild(el("li", null, bouton));
      });
      zone.appendChild(liste);
    }

    // --------------------------------------------------------------
    // Ouvrir une discussion
    // --------------------------------------------------------------

    function ouvrir(conversationId, messageCible) {
      if (etat.actifId && etat.actifId !== conversationId) {
        etat.brouillons.set(etat.actifId, refs["saisie-champ"].value);
      }
      var change = etat.actifId !== conversationId;
      etat.actifId = conversationId;
      etat.reponseA = null;
      etat.edition = null;
      etat.nouveauxEnBas = 0;
      etat.epingleIndex = 0;
      etat.lectureEnvoyee = 0;
      fermerInfos();
      fermerRechercheFil();
      afficherContexte();

      refs.accueil.hidden = true;
      refs.conversation.hidden = false;
      montrerVue("fil");
      if (mode === "plein" && uneColonne() && change) {
        try { history.pushState({ msgConversation: conversationId }, "", "#c=" + conversationId); } catch (e) { /* sans effet */ }
      }
      memoriserDansLAdresse(conversationId);

      var resume = Magasin.conversation(conversationId);
      rendreEntete(resume, null);
      rendreListe();

      if (change) {
        refs["saisie-champ"].value = etat.brouillons.get(conversationId) || "";
        refs.depot.hidden = true;
        rendrePlateau();
        ajusterSaisie();
        vider(refs.messages);
        refs.messages.appendChild(el("div", { class: "msg-messages-etat" }, [el("span", { class: "msg-chargement", "aria-hidden": "true" }), el("span", { text: "Chargement des messages…" })]));
      }

      var cache = Magasin.cache(conversationId);
      var promesses = [appel("GET", "/conversations/" + conversationId)];
      promesses.push(cache.charge ? Promise.resolve(null) : appel("GET", "/conversations/" + conversationId + "/messages"));

      return Promise.all(promesses).then(function (reponses) {
        if (etat.actifId !== conversationId) {
          return;
        }
        etat.detail = reponses[0].conversation;
        var carte = Magasin.lectures(conversationId);
        (etat.detail.membres || []).forEach(function (m) {
          carte.set(m.id, Math.max(carte.get(m.id) || 0, m.dernier_lu_id || 0));
        });
        if (reponses[1]) {
          reponses[1].messages.forEach(function (m) { Magasin.ranger(cache, m); });
          cache.plusAnciens = !!reponses[1].plus_anciens;
          cache.charge = true;
        }
        var resumeActuel = Magasin.conversation(conversationId);
        etat.separateurNonLu = resumeActuel && resumeActuel.non_lus > 0 ? (resumeActuel.dernier_lu_id || 0) : 0;
        rendreEntete(resumeActuel, etat.detail);
        rendreEpingles();
        rendreSaisieAutorisee();
        rendreMessages("ouverture");
        if (messageCible) {
          allerA(messageCible);
        }
        if (!TACTILE && etat.detail.peut_ecrire && !messageCible) {
          refs["saisie-champ"].focus({ preventScroll: true });
        }
        marquerLu();
      }).catch(function (erreur) {
        if (etat.actifId !== conversationId) {
          return;
        }
        vider(refs.messages);
        refs.messages.appendChild(el("div", { class: "msg-messages-etat msg-messages-etat--erreur" }, [
          icone("alerte"),
          el("span", { text: messageErreur(erreur) }),
          el("button", { type: "button", class: "msg-btn", text: "Réessayer", onclick: function () { ouvrir(conversationId); } })
        ]));
        if (erreur && erreur.statut === 404) {
          Magasin.oublier(conversationId);
        }
      });
    }

    function memoriserDansLAdresse(conversationId) {
      if (mode !== "plein") {
        var lien = document.querySelector("[data-msg-tiroir] a[href]");
        if (lien && CFG.plein) {
          lien.setAttribute("href", CFG.plein + (conversationId ? "#c=" + conversationId : ""));
        }
        return;
      }
      if (!uneColonne()) {
        try { history.replaceState(history.state, "", conversationId ? "#c=" + conversationId : location.pathname + location.search); } catch (e) { /* sans effet */ }
      }
    }

    function revenirALaListe() {
      if (etat.actifId) {
        etat.brouillons.set(etat.actifId, refs["saisie-champ"].value);
      }
      etat.actifId = null;
      etat.detail = null;
      fermerInfos();
      montrerVue("liste");
      refs.conversation.hidden = true;
      refs.accueil.hidden = false;
      memoriserDansLAdresse(null);
      rendreListe();
      var premier = refs.convs.querySelector(".msg-conv");
      if (premier && !TACTILE) {
        premier.focus();
      }
    }

    function rendreEntete(resume, detail) {
      var source = detail || resume;
      if (!source) {
        return;
      }
      refs["tete-nom"].textContent = nomAffiche(source, detail);
      vider(refs["tete-avatar"]);
      var a = detail && detail.acces_direction && source.type === "direct"
        ? el("span", { class: "msg-avatar msg-avatar--groupe " + teinte(source.id) }, icone("discussion"))
        : avatarConversation(source);
      refs["tete-avatar"].className = a.className;
      while (a.firstChild) {
        refs["tete-avatar"].appendChild(a.firstChild);
      }
      refs["tete-sous-titre"].textContent = sousTitre(source, detail);
      refs["tete-direction"].hidden = !(detail && detail.acces_direction);
    }

    /*
     * La Direction qui ouvre une discussion directe dont elle n'est pas
     * membre y voit deux collègues : le nom porte les deux.
     */
    function nomAffiche(source, detail) {
      if (detail && detail.acces_direction && source.type === "direct") {
        return (detail.membres || []).map(function (m) { return m.nom; }).join(" et ") || source.nom;
      }
      return source.nom;
    }

    function sousTitre(source, detail) {
      if (source.type === "direct" && detail && detail.acces_direction) {
        return "Discussion directe";
      }
      if (source.type === "direct") {
        var correspondant = source.correspondant;
        if (correspondant && correspondant.actif === false) {
          return "Compte désactivé";
        }
        return correspondant ? libellePresence(Magasin.presence(correspondant.id)) : "";
      }
      var prefixe = source.type === "annonces" ? "Annonces · " : "";
      if (!detail) {
        return prefixe + "Groupe";
      }
      var membres = (detail.membres || []).slice().sort(function (a, b) {
        return (a.id === CFG.moi ? -1 : 0) - (b.id === CFG.moi ? -1 : 0);
      }).map(function (m) { return m.id === CFG.moi ? "Vous" : nomCourt(m.nom); });
      var texte = membres.slice(0, 6).join(", ") + (membres.length > 6 ? " et " + (membres.length - 6) + " autres" : "");
      return prefixe + texte;
    }

    function rendreSaisieAutorisee() {
      var d = etat.detail;
      var peut = !!(d && d.peut_ecrire);
      refs.saisie.hidden = !peut;
      refs["saisie-fermee"].hidden = peut;
      if (!peut && d) {
        var texte = "Seuls les administrateurs peuvent écrire";
        if (d.acces_direction) {
          texte = "Lecture seule : vous consultez cette discussion au titre de la Direction.";
        } else if (d.type === "direct" && d.correspondant && d.correspondant.actif === false) {
          texte = "Ce collègue n’a plus accès à la messagerie.";
        }
        refs["saisie-fermee-texte"].textContent = texte;
        etat.reponseA = null;
        etat.edition = null;
        afficherContexte();
      }
      rendrePlateau();
      ajusterSaisie();
    }

    // --------------------------------------------------------------
    // Fil des messages
    // --------------------------------------------------------------

    function messagesAffiches() {
      var cache = Magasin.cache(etat.actifId);
      return cache.messages;
    }

    function estEnBas() {
      var zone = refs.messages;
      return zone.scrollHeight - zone.scrollTop - zone.clientHeight < 80;
    }

    function allerEnBas(doux) {
      var zone = refs.messages;
      zone.scrollTo({ top: zone.scrollHeight, behavior: doux && !MOUVEMENT_REDUIT ? "smooth" : "auto" });
    }

    /*
     * Rendu complet du fil : à l'ouverture, après le chargement d'anciens
     * messages ou une modification. La position est gardée par rapport au
     * bas, ce qui couvre aussi l'ajout de messages plus anciens en haut.
     */
    function rendreMessages(raison) {
      var zone = refs.messages;
      var etaitEnBas = estEnBas();
      var distanceDuBas = zone.scrollHeight - zone.scrollTop;
      var cache = Magasin.cache(etat.actifId);

      zone.setAttribute("aria-live", "off");
      vider(zone);
      etat.noeuds = new Map();
      etat.dernierRendu = null;

      if (cache.plusAnciens) {
        var sentinelle = el("div", { class: "msg-plus-anciens" }, el("button", { type: "button", class: "msg-btn msg-btn--discret", text: "Afficher les messages plus anciens", onclick: chargerAnciens }));
        zone.appendChild(sentinelle);
        observerSentinelle(sentinelle);
      } else if (cache.messages.length) {
        zone.appendChild(el("p", { class: "msg-debut", text: "Début de la discussion" }));
      }

      if (cache.messages.length === 0) {
        zone.appendChild(el("div", { class: "msg-messages-etat" }, [
          icone("discussion"),
          el("span", { text: etat.detail && etat.detail.peut_ecrire ? "Aucun message. Écrivez le premier." : "Aucun message." })
        ]));
      }

      cache.messages.forEach(function (m) { ajouterNoeud(m, zone); });
      enAttente.forEach(function (e) {
        if (e.conversationId === etat.actifId) {
          ajouterNoeud(e.message, zone);
        }
      });

      window.setTimeout(function () { zone.setAttribute("aria-live", "polite"); }, 50);

      if (raison === "ouverture") {
        var separateur = zone.querySelector(".msg-separateur-non-lus");
        if (separateur) {
          zone.scrollTop = Math.max(0, separateur.offsetTop - 60);
        } else {
          allerEnBas(false);
        }
      } else if (raison === "anciens") {
        zone.scrollTop = zone.scrollHeight - distanceDuBas;
      } else if (etaitEnBas) {
        allerEnBas(false);
      } else {
        zone.scrollTop = zone.scrollHeight - distanceDuBas;
      }
      majBoutonBas();
    }

    function ajouterNoeud(m, zone) {
      var date = versDate(m.created_at) || new Date();
      var jour = cleJour(date);
      var precedent = etat.dernierRendu;

      if (!precedent || precedent.jour !== jour) {
        zone.appendChild(el("div", { class: "msg-jour", role: "separator" }, el("span", { text: libelleJour(date) })));
        precedent = null;
      }
      var nonLuIci = etat.separateurNonLu > 0 && typeof m.id === "number" && m.id > etat.separateurNonLu
        && m.auteur.id !== CFG.moi && !zone.querySelector(".msg-separateur-non-lus");
      if (nonLuIci) {
        var resume = Magasin.conversation(etat.actifId);
        var n = resume ? resume.non_lus : 0;
        zone.appendChild(el("div", { class: "msg-separateur-non-lus", role: "separator" }, el("span", { text: n > 1 ? n + " messages non lus" : "Message non lu" })));
        precedent = null;
      }

      var suite = !!(precedent && precedent.auteurId === m.auteur.id && (date.getTime() - precedent.temps) / 1000 < REGROUPEMENT_SECONDES);
      var noeud = bulle(m, suite);
      zone.appendChild(noeud);
      etat.noeuds.set(m.id, noeud);
      etat.dernierRendu = { jour: jour, auteurId: m.auteur.id, temps: date.getTime() };
    }

    function linkifier(texte) {
      var morceaux = String(texte).split(/(https?:\/\/[^\s<>"']+)/g);
      return morceaux.map(function (morceau, i) {
        if (i % 2 === 0) {
          return morceau;
        }
        var propre = morceau.replace(/[.,;:!?)\]]+$/, "");
        var reste = morceau.slice(propre.length);
        try {
          var url = new URL(propre);
          if (url.protocol !== "http:" && url.protocol !== "https:") {
            return morceau;
          }
        } catch (e) {
          return morceau;
        }
        var lien = el("a", { href: propre, target: "_blank", rel: "noopener noreferrer", text: propre });
        return reste ? el("span", null, [lien, reste]) : lien;
      });
    }

    function bulle(m, suite) {
      var moi = m.auteur.id === CFG.moi;
      var d = etat.detail || {};
      var groupe = (d.type && d.type !== "direct") || d.acces_direction;
      var date = versDate(m.created_at);
      var enAttenteDEnvoi = typeof m.id !== "number";

      var contenu = [];
      if (groupe && !moi && !suite) {
        contenu.push(el("span", { class: "msg-auteur " + teinte(m.auteur.id), text: m.auteur.nom }));
      }
      if (m.transfere) {
        contenu.push(el("span", { class: "msg-mention-transfert" }, [icone("transferer", "msg-i--petit"), el("span", { text: "Transféré" })]));
      }
      if (m.reponse_a) {
        var citation = el("button", { type: "button", class: "msg-citation", title: "Aller au message cité" }, [
          el("strong", { text: m.reponse_a.auteur_nom === CFG.moiNom ? "Vous" : m.reponse_a.auteur_nom }),
          el("span", { class: m.reponse_a.supprime ? "is-supprime" : "", text: m.reponse_a.apercu })
        ]);
        citation.addEventListener("click", function () { allerA(m.reponse_a.id); });
        contenu.push(citation);
      }
      var pieces = m.pieces_jointes || [];
      var aDuTexte = String(m.texte || "") !== "";
      var media = false;
      if (m.supprime && m.texte === null) {
        contenu.push(el("p", { class: "msg-texte msg-texte--supprime" }, [icone("supprimer", "msg-i--petit"), el("span", { text: m.libelle || "Message supprimé" })]));
      } else {
        if (pieces.length) {
          media = pieces.every(function (p) { return p.categorie === "image"; });
          contenu.push(el("div", { class: "msg-pieces" + (m.supprime ? " is-supprime" : "") }, pieces.map(function (p) {
            return p.categorie === "image"
              ? pieceImage(p, function (piece) { ouvrirImage(piece); })
              : pieceDocument(p, function (piece) { Visionneuse.ouvrir([{ piece: piece, auteur: m.auteur.nom, date: versDate(m.created_at), legende: m.texte }], 0); });
          })));
        }
        // Un fichier envoyé sans légende n'a pas de texte : pas de paragraphe vide.
        if (aDuTexte) {
          contenu.push(el("p", { class: "msg-texte" + (pieces.length ? " msg-texte--legende" : "") + (m.supprime ? " msg-texte--supprime-direction" : "") }, linkifier(m.texte)));
        } else if (!pieces.length) {
          contenu.push(el("p", { class: "msg-texte msg-texte--supprime" }, [icone("fichier", "msg-i--petit"), el("span", { text: "Pièce jointe indisponible" })]));
        }
        if (m.supprime) {
          contenu.push(el("span", { class: "msg-mention-supprime" }, [icone("supprimer", "msg-i--petit"), el("span", { text: "Supprimé par son auteur — visible de la Direction seule" })]));
        }
      }

      var meta = el("span", { class: "msg-meta" }, [
        m.modifie ? el("span", { class: "msg-modifie", text: "modifié" }) : null,
        date ? el("time", { datetime: date.toISOString(), title: libelleDateComplete(date), text: heure(date) }) : null,
        moi ? accuse(m) : null
      ]);
      contenu.push(meta);

      var corps = el("div", { class: "msg-bulle" + (pieces.length ? " msg-bulle--pieces" : "") + (media ? " msg-bulle--media" : "") + (media && !aDuTexte ? " msg-bulle--media-seul" : "") }, contenu);
      var ligne = el("div", {
        class: "msg-ligne " + (moi ? "msg-ligne--moi" : "msg-ligne--autre") + (suite ? " msg-suite" : "") + (m.supprime ? " is-supprime" : "") + (m.erreur ? " is-echec" : ""),
        "data-id": String(m.id)
      }, [corps]);

      if (!enAttenteDEnvoi) {
        var actions = el("button", { type: "button", class: "msg-bulle-menu", "aria-haspopup": "menu", "aria-expanded": "false", "aria-label": "Actions sur le message de " + (moi ? "vous" : m.auteur.nom) + (date ? " à " + heure(date) : "") }, icone("chevron-bas"));
        actions.addEventListener("click", function () { ouvrirMenuMessage(m, actions); });
        corps.appendChild(actions);
        if (TACTILE) {
          appuiLong(corps, function () { ouvrirMenuMessage(m, actions); });
        }
      } else if (m.erreur) {
        ligne.appendChild(el("div", { class: "msg-echec" }, [
          icone("alerte", "msg-i--petit"),
          el("span", { text: m.erreur }),
          el("button", { type: "button", class: "msg-lien", text: "Réessayer", onclick: function () { reessayer(m.id); } }),
          el("button", { type: "button", class: "msg-lien", text: "Abandonner", onclick: function () { abandonner(m.id); } })
        ]));
      }
      return ligne;
    }

    function appuiLong(noeud, action) {
      var minuterie = null;
      noeud.addEventListener("touchstart", function (evenement) {
        if (evenement.target.closest("a, button")) {
          return;
        }
        minuterie = window.setTimeout(function () {
          minuterie = null;
          if (navigator.vibrate) {
            navigator.vibrate(10);
          }
          action();
        }, 450);
      }, { passive: true });
      ["touchend", "touchmove", "touchcancel"].forEach(function (nom) {
        noeud.addEventListener(nom, function () {
          window.clearTimeout(minuterie);
        }, { passive: true });
      });
    }

    /* La visionneuse parcourt toutes les photos déjà chargées de la discussion. */
    function ouvrirImage(piece) {
      var liste = [];
      var depart = 0;
      Magasin.cache(etat.actifId).messages.forEach(function (m) {
        (m.pieces_jointes || []).forEach(function (p) {
          if (p.categorie !== "image") {
            return;
          }
          if (p.id === piece.id) {
            depart = liste.length;
          }
          liste.push({ piece: p, auteur: m.auteur.nom, date: versDate(m.created_at), legende: m.texte || "" });
        });
      });
      if (!liste.length) {
        liste.push({ piece: piece, auteur: "", date: null, legende: piece.legende || "" });
      }
      Visionneuse.ouvrir(liste, depart);
    }

    /* Accusé d'un message envoyé : en cours, envoyé, lu par une partie, lu. */
    function accuse(m) {
      if (typeof m.id !== "number") {
        return m.erreur
          ? el("span", { class: "msg-accuse is-echec" }, [icone("alerte", "msg-i--petit"), texteMasque("Non envoyé")])
          : el("span", { class: "msg-accuse" }, [icone("horloge", "msg-i--petit"), texteMasque("Envoi en cours")]);
      }
      var lecteurs = 0;
      var autres = 0;
      var carte = Magasin.lectures(m.conversation_id);
      ((etat.detail && etat.detail.membres) || []).forEach(function (membre) {
        if (membre.id === CFG.moi) {
          return;
        }
        autres += 1;
        if ((carte.get(membre.id) || 0) >= m.id) {
          lecteurs += 1;
        }
      });
      var tous = autres > 0 && lecteurs === autres;
      var texte = tous ? "Lu" : (lecteurs > 0 ? "Lu par " + lecteurs + " sur " + autres : "Envoyé");
      return el("span", { class: "msg-accuse" + (tous ? " is-lu" : ""), title: texte }, [
        icone(lecteurs > 0 ? "double-coche" : "coche", "msg-i--petit"),
        texteMasque(texte)
      ]);
    }

    function majAccuses() {
      etat.noeuds.forEach(function (noeud, id) {
        if (typeof id !== "number") {
          return;
        }
        var ancien = noeud.querySelector(".msg-accuse");
        var m = Magasin.cache(etat.actifId).parId.get(id);
        if (ancien && m) {
          ancien.replaceWith(accuse(m));
        }
      });
    }

    function remplacerNoeud(m) {
      var ancien = etat.noeuds.get(m.id);
      if (!ancien) {
        return;
      }
      var neuf = bulle(m, ancien.classList.contains("msg-suite"));
      ancien.replaceWith(neuf);
      etat.noeuds.set(m.id, neuf);
    }

    function allerA(messageId, tentatives) {
      var noeud = etat.noeuds.get(messageId);
      var cache = Magasin.cache(etat.actifId);
      if (!noeud) {
        if (cache.plusAnciens && (tentatives || 0) < 20) {
          chargerAnciens().then(function () { allerA(messageId, (tentatives || 0) + 1); });
        } else {
          toast("Ce message n’est plus disponible.", "erreur");
        }
        return;
      }
      noeud.scrollIntoView({ block: "center", behavior: MOUVEMENT_REDUIT ? "auto" : "smooth" });
      noeud.classList.remove("is-cible");
      void noeud.offsetWidth;
      noeud.classList.add("is-cible");
      var bouton = noeud.querySelector(".msg-bulle-menu");
      if (bouton) {
        bouton.focus({ preventScroll: true });
      }
    }

    var observateur = null;
    function observerSentinelle(sentinelle) {
      if (!("IntersectionObserver" in window)) {
        return;
      }
      if (observateur) {
        observateur.disconnect();
      }
      observateur = new IntersectionObserver(function (entrees) {
        if (entrees.some(function (e) { return e.isIntersecting; })) {
          chargerAnciens();
        }
      }, { root: refs.messages, rootMargin: "200px 0px 0px 0px" });
      observateur.observe(sentinelle);
    }

    function chargerAnciens() {
      var conversationId = etat.actifId;
      var cache = Magasin.cache(conversationId);
      if (etat.chargementAncien || !cache.plusAnciens || cache.messages.length === 0) {
        return Promise.resolve();
      }
      etat.chargementAncien = true;
      var plusAncien = cache.messages[0].id;
      return appel("GET", "/conversations/" + conversationId + "/messages?avant=" + plusAncien).then(function (r) {
        r.messages.forEach(function (m) { Magasin.ranger(cache, m); });
        cache.plusAnciens = !!r.plus_anciens;
        if (etat.actifId === conversationId) {
          rendreMessages("anciens");
        }
      }).catch(function (erreur) {
        toast(messageErreur(erreur), "erreur");
      }).then(function () {
        etat.chargementAncien = false;
      });
    }

    refs.messages.addEventListener("scroll", attendre(function () {
      majBoutonBas();
      if (estEnBas()) {
        etat.nouveauxEnBas = 0;
        majBoutonBas();
        marquerLu();
      }
    }, 120));

    function majBoutonBas() {
      var bouton = racine.querySelector("[data-msg-action='vers-bas']");
      var zone = refs.messages;
      var loin = zone.scrollHeight - zone.scrollTop - zone.clientHeight > 300;
      bouton.hidden = !loin && etat.nouveauxEnBas === 0;
      if (estEnBas()) {
        bouton.hidden = true;
      }
      refs["vers-bas-compte"].hidden = etat.nouveauxEnBas === 0;
      refs["vers-bas-compte"].textContent = String(etat.nouveauxEnBas);
    }

    // --------------------------------------------------------------
    // Lecture
    // --------------------------------------------------------------

    function filVisible() {
      if (!etat.visible || !etat.actifId || document.visibilityState !== "visible") {
        return false;
      }
      return !uneColonne() || racine.getAttribute("data-vue") === "fil";
    }

    function marquerLu() {
      var d = etat.detail;
      if (!d || !d.membre || !filVisible() || !estEnBas()) {
        return;
      }
      var messages = messagesAffiches();
      if (messages.length === 0) {
        return;
      }
      var dernier = messages[messages.length - 1].id;
      var resume = Magasin.conversation(etat.actifId);
      var dejaLu = Math.max(resume ? resume.dernier_lu_id || 0 : 0, etat.lectureEnvoyee);
      if (dernier <= dejaLu && !(resume && resume.non_lus > 0)) {
        return;
      }
      var conversationId = etat.actifId;
      etat.lectureEnvoyee = dernier;
      Magasin.marquerLuLocalement(conversationId, dernier);
      appel("POST", "/conversations/" + conversationId + "/lu", { jusqu_a: dernier }).catch(function () {
        etat.lectureEnvoyee = 0;
      });
    }

    Magasin.declarerVue(function (conversationId) {
      return conversationId === etat.actifId && filVisible() && document.hasFocus();
    });

    // --------------------------------------------------------------
    // Saisie et envoi
    // --------------------------------------------------------------

    var enAttente = [];
    var compteurAttente = 0;

    function ajusterSaisie() {
      var champ = refs["saisie-champ"];
      champ.style.height = "auto";
      champ.style.height = Math.min(champ.scrollHeight, 160) + "px";
      champ.style.overflowY = champ.scrollHeight > 160 ? "auto" : "hidden";
      refs.envoyer.disabled = champ.value.trim() === "" && fichiersPrets().length === 0;
    }

    // --------------------------------------------------------------
    // Fichiers à envoyer : le plateau au-dessus de la saisie
    // --------------------------------------------------------------

    var plateaux = new Map();
    var compteurFichiers = 0;

    function plateauDe(conversationId) {
      if (!plateaux.has(conversationId)) {
        plateaux.set(conversationId, { fichiers: [], refus: [], enCours: null });
      }
      return plateaux.get(conversationId);
    }

    function fichiersPrets() {
      var plateau = etat.actifId ? plateaux.get(etat.actifId) : null;
      return plateau ? plateau.fichiers.filter(function (f) { return f.statut === "pret"; }) : [];
    }

    function peutJoindre() {
      return !!(etat.actifId && etat.detail && etat.detail.peut_ecrire);
    }

    function deuxChiffres(n) {
      return (n < 10 ? "0" : "") + n;
    }

    /* Une capture collée s'appelle « image.png » : on lui donne un nom parlant. */
    function nomDeCapture(fichier) {
      var extension = extensionDe(fichier.name) || (fichier.type === "image/jpeg" ? "jpg" : (fichier.type === "image/webp" ? "webp" : "png"));
      var d = new Date();
      return "Capture du " + deuxChiffres(d.getDate()) + "-" + deuxChiffres(d.getMonth() + 1) + "-" + d.getFullYear()
        + " à " + deuxChiffres(d.getHours()) + " h " + deuxChiffres(d.getMinutes()) + "." + extension;
    }

    function ajouterFichiers(liste, origine) {
      var fichiers = Array.from(liste || []);
      if (!fichiers.length) {
        return;
      }
      if (!peutJoindre()) {
        toast(etat.detail ? "Vous ne pouvez pas envoyer de fichier dans cette discussion." : "Ouvrez d’abord une discussion.", "erreur");
        return;
      }
      if (etat.edition) {
        annulerContexte();
      }
      var conversationId = etat.actifId;
      var plateau = plateauDe(conversationId);
      plateau.refus = [];
      var places = FICHIERS.max - plateau.fichiers.length;
      var ecartes = 0;
      fichiers.forEach(function (fichier) {
        if (origine === "collage" && (!fichier.name || /^image\.(png|jpe?g|webp)$/i.test(fichier.name))) {
          try { fichier = new File([fichier], nomDeCapture(fichier), { type: fichier.type }); } catch (e) { /* nom gardé */ }
        }
        var raison = raisonRefus(fichier);
        if (raison) {
          plateau.refus.push("« " + fichier.name + " » n’est pas joint : " + raison);
          return;
        }
        if (places <= 0) {
          ecartes += 1;
          return;
        }
        places -= 1;
        compteurFichiers += 1;
        var categorie = categorieDe(fichier.name);
        plateau.fichiers.push({
          cle: "f" + compteurFichiers,
          conversationId: conversationId,
          fichier: fichier,
          nom: fichier.name,
          taille: fichier.size,
          categorie: categorie,
          extension: extensionDe(fichier.name),
          legende: "",
          statut: "pret",
          progression: 0,
          envoi: null,
          erreur: null,
          reponseAId: null,
          apercu: categorie === "image" && window.URL && URL.createObjectURL ? URL.createObjectURL(fichier) : null
        });
      });
      if (ecartes) {
        plateau.refus.push(ecartes + (ecartes > 1 ? " fichiers n’ont pas été joints" : " fichier n’a pas été joint") + " : 10 fichiers au plus par envoi.");
      }
      rendrePlateau();
      ajusterSaisie();
      if (!TACTILE) {
        refs["saisie-champ"].focus({ preventScroll: true });
      }
    }

    function retirerDuPlateau(f) {
      var plateau = plateauDe(f.conversationId);
      plateau.fichiers = plateau.fichiers.filter(function (x) { return x !== f; });
      if (f.apercu) {
        URL.revokeObjectURL(f.apercu);
        f.apercu = null;
      }
    }

    function majPlateauSiActif(conversationId) {
      if (conversationId === etat.actifId) {
        rendrePlateau();
        ajusterSaisie();
      }
    }

    function texteStatut(f) {
      if (f.statut === "attente") {
        return "En attente";
      }
      return f.progression >= 1 ? "Rangement en cours…" : "Envoi… " + Math.round(f.progression * 100) + " %";
    }

    function majProgression(f) {
      if (!f.refsProgression) {
        return;
      }
      var pourcent = Math.round(f.progression * 100);
      f.refsProgression.barre.style.width = pourcent + "%";
      f.refsProgression.piste.setAttribute("aria-valuenow", String(pourcent));
      f.refsProgression.statut.textContent = texteStatut(f);
    }

    function petitBouton(nomIcone, libelle, action) {
      return el("button", { type: "button", class: "msg-icone-btn", title: libelle, "aria-label": libelle, onclick: action }, icone(nomIcone));
    }

    function elementPlateau(f) {
      var vignette = f.apercu
        ? el("span", { class: "msg-plateau-vignette" }, el("img", { src: f.apercu, alt: "" }))
        : vignetteDocument({ categorie: f.categorie, extension: f.extension, nom: f.nom }, "msg-plateau-vignette");
      var infos = el("div", { class: "msg-plateau-infos" }, [
        el("strong", { class: "msg-plateau-nom", text: f.nom, title: f.nom }),
        el("small", { text: tailleLisible(f.taille) + " · " + libelleType(f) })
      ]);
      var actions = el("div", { class: "msg-plateau-actions" });
      f.refsProgression = null;

      if (f.statut === "pret") {
        var champ = el("input", { type: "text", class: "msg-plateau-legende", maxlength: "4000", placeholder: "Légende (facultative)", "aria-label": "Légende de " + f.nom, "data-cle": f.cle, enterkeyhint: "send", autocomplete: "off" });
        champ.value = f.legende;
        champ.addEventListener("input", function () { f.legende = champ.value; });
        champ.addEventListener("keydown", function (evenement) {
          if (evenement.key === "Enter" && !evenement.isComposing) {
            evenement.preventDefault();
            soumettre();
          }
        });
        infos.appendChild(champ);
        actions.appendChild(petitBouton("fermer", "Retirer " + f.nom, function () {
          retirerDuPlateau(f);
          majPlateauSiActif(f.conversationId);
        }));
      } else {
        if (f.legende.trim()) {
          infos.appendChild(el("span", { class: "msg-plateau-legende-texte", text: f.legende.trim() }));
        }
        if (f.statut === "erreur") {
          infos.appendChild(el("p", { class: "msg-plateau-erreur" }, [icone("alerte", "msg-i--petit"), el("span", { text: f.erreur })]));
          actions.appendChild(el("button", { type: "button", class: "msg-lien", text: "Réessayer", "aria-label": "Réessayer l’envoi de " + f.nom, onclick: function () {
            f.statut = "attente";
            f.erreur = null;
            majPlateauSiActif(f.conversationId);
            suivantFichier(f.conversationId);
          } }));
          actions.appendChild(petitBouton("fermer", "Retirer " + f.nom, function () {
            retirerDuPlateau(f);
            majPlateauSiActif(f.conversationId);
          }));
        } else {
          var pourcent = Math.round(f.progression * 100);
          var barre = el("span", { class: "msg-progression-barre" });
          barre.style.width = pourcent + "%";
          var piste = el("span", { class: "msg-progression", role: "progressbar", "aria-label": "Envoi de " + f.nom, "aria-valuemin": "0", "aria-valuemax": "100", "aria-valuenow": String(pourcent) }, barre);
          var statut = el("small", { class: "msg-plateau-statut", text: texteStatut(f) });
          ajouter(infos, [piste, statut]);
          f.refsProgression = { piste: piste, barre: barre, statut: statut };
          actions.appendChild(petitBouton("fermer", "Annuler l’envoi de " + f.nom, function () { annulerFichier(f); }));
        }
      }
      return el("li", { class: "msg-plateau-item is-" + f.statut, "data-cle": f.cle }, [vignette, infos, actions]);
    }

    function rendrePlateau() {
      var zone = refs.plateau;
      var plateau = etat.actifId ? plateaux.get(etat.actifId) : null;
      var actif = document.activeElement;
      var cleFocus = actif && zone.contains(actif) && actif.tagName === "INPUT" ? actif.getAttribute("data-cle") : null;
      var position = cleFocus ? actif.selectionStart : null;
      vider(zone);
      if (!plateau || (!plateau.fichiers.length && !plateau.refus.length)) {
        zone.hidden = true;
        return;
      }
      var enEnvoi = plateau.fichiers.filter(function (f) { return f.statut === "attente" || f.statut === "envoi"; });
      if (!peutJoindre() && !enEnvoi.length) {
        zone.hidden = true;
        return;
      }
      zone.hidden = false;
      var prets = plateau.fichiers.filter(function (f) { return f.statut === "pret"; });
      var total = plateau.fichiers.reduce(function (somme, f) { return somme + f.taille; }, 0);
      var n = plateau.fichiers.length;
      var titre = enEnvoi.length
        ? "Envoi en cours · " + enEnvoi.length + (enEnvoi.length > 1 ? " fichiers restants" : " fichier restant")
        : (n ? n + (n > 1 ? " fichiers" : " fichier") + " · " + tailleLisible(total) : "Fichiers non joints");
      var tete = el("div", { class: "msg-plateau-tete" }, [icone("trombone", "msg-i--petit"), el("strong", { class: "msg-plateau-titre", text: titre })]);
      if (enEnvoi.length) {
        tete.appendChild(el("button", { type: "button", class: "msg-lien", text: "Tout annuler", onclick: function () { toutAnnuler(plateau); } }));
      } else if (n) {
        tete.appendChild(el("button", { type: "button", class: "msg-lien", text: "Tout retirer", onclick: function () {
          plateau.fichiers.slice().forEach(retirerDuPlateau);
          plateau.refus = [];
          majPlateauSiActif(etat.actifId);
        } }));
      } else {
        tete.appendChild(petitBouton("fermer", "Fermer ce message", function () {
          plateau.refus = [];
          majPlateauSiActif(etat.actifId);
        }));
      }
      zone.appendChild(tete);
      if (plateau.refus.length) {
        zone.appendChild(el("ul", { class: "msg-plateau-refus", role: "alert" }, plateau.refus.map(function (texte) {
          return el("li", null, [icone("alerte", "msg-i--petit"), el("span", { text: texte })]);
        })));
      }
      if (n) {
        zone.appendChild(el("ul", { class: "msg-plateau-liste", "aria-label": "Fichiers joints" }, plateau.fichiers.map(elementPlateau)));
      }
      if (prets.length) {
        zone.appendChild(el("p", { class: "msg-plateau-aide", text: "Le texte écrit en bas devient la légende du premier fichier qui n’en a pas. Puis Envoyer." }));
      }
      if (cleFocus) {
        var champ = zone.querySelector('input[data-cle="' + cleFocus + '"]');
        if (champ) {
          champ.focus({ preventScroll: true });
          try { champ.setSelectionRange(position, position); } catch (e) { /* sans effet */ }
        }
      }
    }

    function annulerFichier(f) {
      if (f.statut === "envoi" && f.envoi) {
        f.envoi.annuler();
        return;
      }
      retirerDuPlateau(f);
      majPlateauSiActif(f.conversationId);
    }

    function toutAnnuler(plateau) {
      plateau.fichiers.filter(function (f) { return f.statut === "attente"; }).forEach(retirerDuPlateau);
      if (plateau.enCours && plateau.enCours.envoi) {
        plateau.enCours.envoi.annuler();
      }
      majPlateauSiActif(etat.actifId);
    }

    /* Les fichiers partent l'un après l'autre ; une erreur n'arrête pas les suivants. */
    function envoyerFichiers() {
      var conversationId = etat.actifId;
      var prets = fichiersPrets();
      var champ = refs["saisie-champ"];
      var texte = champ.value.trim();
      var reponseAId = etat.reponseA ? etat.reponseA.id : null;
      var sansLegende = texte ? prets.find(function (f) { return !f.legende.trim(); }) : null;
      if (sansLegende) {
        sansLegende.legende = texte;
        champ.value = "";
      } else if (texte) {
        // Chaque fichier a sa légende : le texte part d'abord, seul, avec la réponse.
        envoyerTexte();
        reponseAId = null;
      }
      prets[0].reponseAId = reponseAId;
      plateauDe(conversationId).refus = [];
      prets.forEach(function (f) {
        f.statut = "attente";
        f.progression = 0;
      });
      etat.brouillons.delete(conversationId);
      annulerContexte();
      ajusterSaisie();
      rendrePlateau();
      suivantFichier(conversationId);
    }

    function suivantFichier(conversationId) {
      var plateau = plateauDe(conversationId);
      if (plateau.enCours) {
        return;
      }
      var f = plateau.fichiers.find(function (x) { return x.statut === "attente"; });
      if (!f) {
        majPlateauSiActif(conversationId);
        return;
      }
      plateau.enCours = f;
      f.statut = "envoi";
      f.progression = 0;
      f.erreur = null;
      majPlateauSiActif(conversationId);

      var donnees = new FormData();
      donnees.append("fichier", f.fichier, f.nom);
      if (f.legende.trim()) {
        donnees.append("legende", f.legende.trim());
      }
      if (f.reponseAId) {
        donnees.append("reponse_a_id", String(f.reponseAId));
      }
      f.envoi = envoyerFormulaire("/conversations/" + conversationId + "/fichiers", donnees, function (part) {
        f.progression = part;
        majProgression(f);
      });
      f.envoi.promesse.then(function (r) {
        retirerDuPlateau(f);
        recevoirMessageEnvoye(conversationId, r.message);
      }, function (erreur) {
        if (erreur.statut === -1) {
          retirerDuPlateau(f);
        } else {
          f.statut = "erreur";
          f.erreur = messageErreur(erreur);
        }
      }).then(function () {
        f.envoi = null;
        plateau.enCours = null;
        majPlateauSiActif(conversationId);
        suivantFichier(conversationId);
      });
    }

    function recevoirMessageEnvoye(conversationId, message) {
      var cache = Magasin.cache(conversationId);
      var nouveau = Magasin.ranger(cache, message);
      Magasin.mettreAJourDernier(message);
      Magasin.marquerLuLocalement(conversationId, message.id);
      if (conversationId === etat.actifId && nouveau && !etat.noeuds.has(message.id)) {
        var vide = refs.messages.querySelector(".msg-messages-etat");
        if (vide) {
          vide.remove();
        }
        ajouterNoeud(cache.parId.get(message.id), refs.messages);
        allerEnBas(true);
      }
    }

    function choisirFichiers(bouton) {
      if (!peutJoindre()) {
        toast("Vous ne pouvez pas envoyer de fichier dans cette discussion.", "erreur");
        return;
      }
      if (!TACTILE) {
        refs["fichier-choix"].click();
        return;
      }
      // Sur un téléphone : l'appareil photo, la galerie ou un document.
      Menu.ouvrir(bouton, [
        { icone: "appareil", libelle: "Prendre une photo", action: function () { refs["fichier-photo"].click(); } },
        { icone: "image", libelle: "Photo de la galerie", action: function () { refs["fichier-galerie"].click(); } },
        { icone: "fichier", libelle: "Document", action: function () { refs["fichier-choix"].click(); } }
      ], racine.closest("[data-msg-tiroir]") || document.body);
    }

    ["fichier-choix", "fichier-galerie", "fichier-photo"].forEach(function (cle) {
      refs[cle].addEventListener("change", function () {
        ajouterFichiers(refs[cle].files, "choix");
        refs[cle].value = "";
      });
    });

    // Coller une capture d'écran (Ctrl+V). Un copier d'Excel porte aussi une
    // image du tableau : quand il y a du texte, c'est le texte qu'on colle.
    refs["saisie-champ"].addEventListener("paste", function (evenement) {
      var presse = evenement.clipboardData;
      if (!presse || !presse.files || presse.files.length === 0 || presse.getData("text/plain")) {
        return;
      }
      evenement.preventDefault();
      ajouterFichiers(presse.files, "collage");
    });

    // Glisser-déposer sur la conversation.
    var profondeurDepot = 0;
    function porteDesFichiers(evenement) {
      var types = evenement.dataTransfer ? Array.from(evenement.dataTransfer.types || []) : [];
      return types.indexOf("Files") !== -1;
    }
    refs.fil.addEventListener("dragenter", function (evenement) {
      if (!porteDesFichiers(evenement)) {
        return;
      }
      evenement.preventDefault();
      profondeurDepot += 1;
      refs.depot.hidden = !peutJoindre();
    });
    refs.fil.addEventListener("dragover", function (evenement) {
      if (!porteDesFichiers(evenement)) {
        return;
      }
      evenement.preventDefault();
      evenement.dataTransfer.dropEffect = peutJoindre() ? "copy" : "none";
    });
    refs.fil.addEventListener("dragleave", function (evenement) {
      if (!porteDesFichiers(evenement)) {
        return;
      }
      profondeurDepot = Math.max(0, profondeurDepot - 1);
      if (profondeurDepot === 0) {
        refs.depot.hidden = true;
      }
    });
    refs.fil.addEventListener("drop", function (evenement) {
      if (!porteDesFichiers(evenement)) {
        return;
      }
      evenement.preventDefault();
      profondeurDepot = 0;
      refs.depot.hidden = true;
      ajouterFichiers(evenement.dataTransfer.files, "depot");
    });

    refs["saisie-champ"].addEventListener("input", ajusterSaisie);
    refs["saisie-champ"].addEventListener("keydown", function (evenement) {
      if (evenement.key === "Enter" && !evenement.shiftKey && !evenement.isComposing && !TACTILE) {
        evenement.preventDefault();
        soumettre();
      } else if (evenement.key === "Escape" && (etat.reponseA || etat.edition)) {
        evenement.preventDefault();
        evenement.stopPropagation();
        annulerContexte();
      } else if (evenement.key === "ArrowUp" && refs["saisie-champ"].value === "") {
        var miens = messagesAffiches().filter(function (m) { return m.auteur.id === CFG.moi && m.modifiable; });
        if (miens.length) {
          evenement.preventDefault();
          commencerEdition(miens[miens.length - 1]);
        }
      }
    });
    refs.saisie.addEventListener("submit", function (evenement) {
      evenement.preventDefault();
      soumettre();
    });

    function soumettre() {
      if (!etat.actifId) {
        return;
      }
      if (!etat.edition && fichiersPrets().length) {
        envoyerFichiers();
        return;
      }
      envoyerTexte();
    }

    function envoyerTexte() {
      var champ = refs["saisie-champ"];
      var texte = champ.value.replace(/\s+$/, "");
      if (texte.trim() === "" || !etat.actifId) {
        return;
      }
      if (etat.edition) {
        var cible = etat.edition;
        appel("POST", "/messages/" + cible.id + "/modifier", { texte: texte }).then(function (r) {
          var cache = Magasin.cache(r.message.conversation_id);
          Magasin.ranger(cache, r.message);
          if (r.message.conversation_id === etat.actifId) {
            remplacerNoeud(cache.parId.get(r.message.id));
          }
        }).catch(function (erreur) {
          toast(messageErreur(erreur), "erreur");
        });
        champ.value = "";
        annulerContexte();
        ajusterSaisie();
        return;
      }

      compteurAttente += 1;
      var provisoire = {
        id: "e" + compteurAttente,
        conversation_id: etat.actifId,
        auteur: { id: CFG.moi, nom: CFG.moiNom },
        texte: texte,
        supprime: false,
        libelle: null,
        modifie: false,
        transfere: false,
        reponse_a: etat.reponseA ? { id: etat.reponseA.id, auteur_nom: etat.reponseA.auteur.nom, apercu: apercuDe(etat.reponseA), supprime: false } : null,
        modifiable: false,
        created_at: new Date().toISOString().slice(0, 19).replace("T", " ")
      };
      var envoi = { conversationId: etat.actifId, message: provisoire, reponseAId: etat.reponseA ? etat.reponseA.id : null };
      enAttente.push(envoi);
      champ.value = "";
      etat.brouillons.delete(etat.actifId);
      annulerContexte();
      ajusterSaisie();

      var zone = refs.messages;
      var vide = zone.querySelector(".msg-messages-etat");
      if (vide) {
        vide.remove();
      }
      ajouterNoeud(provisoire, zone);
      allerEnBas(true);
      expedier(envoi);
    }

    function expedier(envoi) {
      envoi.message.erreur = null;
      appel("POST", "/conversations/" + envoi.conversationId + "/messages", { texte: envoi.message.texte, reponse_a_id: envoi.reponseAId })
        .then(function (r) {
          retirerAttente(envoi);
          var cache = Magasin.cache(envoi.conversationId);
          var nouveau = Magasin.ranger(cache, r.message);
          Magasin.mettreAJourDernier(r.message);
          Magasin.marquerLuLocalement(envoi.conversationId, r.message.id);
          if (envoi.conversationId !== etat.actifId) {
            return;
          }
          var noeud = etat.noeuds.get(envoi.message.id);
          etat.noeuds.delete(envoi.message.id);
          if (noeud && nouveau) {
            var neuf = bulle(cache.parId.get(r.message.id), noeud.classList.contains("msg-suite"));
            noeud.replaceWith(neuf);
            etat.noeuds.set(r.message.id, neuf);
          } else if (noeud) {
            noeud.remove();
          }
        })
        .catch(function (erreur) {
          envoi.message.erreur = messageErreur(erreur);
          if (envoi.conversationId === etat.actifId) {
            var noeud = etat.noeuds.get(envoi.message.id);
            if (noeud) {
              var neuf = bulle(envoi.message, noeud.classList.contains("msg-suite"));
              noeud.replaceWith(neuf);
              etat.noeuds.set(envoi.message.id, neuf);
            }
          }
        });
    }

    function retirerAttente(envoi) {
      enAttente = enAttente.filter(function (e) { return e !== envoi; });
    }

    function reessayer(idProvisoire) {
      var envoi = enAttente.find(function (e) { return e.message.id === idProvisoire; });
      if (!envoi) {
        return;
      }
      var noeud = etat.noeuds.get(idProvisoire);
      if (noeud) {
        var neuf = bulle(Object.assign(envoi.message, { erreur: null }), noeud.classList.contains("msg-suite"));
        noeud.replaceWith(neuf);
        etat.noeuds.set(idProvisoire, neuf);
      }
      expedier(envoi);
    }

    function abandonner(idProvisoire) {
      var envoi = enAttente.find(function (e) { return e.message.id === idProvisoire; });
      if (!envoi) {
        return;
      }
      retirerAttente(envoi);
      var noeud = etat.noeuds.get(idProvisoire);
      if (noeud) {
        noeud.remove();
        etat.noeuds.delete(idProvisoire);
      }
      if (envoi.conversationId === etat.actifId && !refs["saisie-champ"].value) {
        refs["saisie-champ"].value = envoi.message.texte;
        ajusterSaisie();
      }
    }

    function afficherContexte() {
      var cible = etat.edition || etat.reponseA;
      refs["saisie-contexte"].hidden = !cible;
      if (!cible) {
        return;
      }
      vider(refs["saisie-contexte-icone"]);
      refs["saisie-contexte-icone"].appendChild(icone(etat.edition ? "modifier" : "repondre"));
      refs["saisie-contexte-titre"].textContent = etat.edition
        ? ((etat.edition.pieces_jointes || []).length ? "Modifier la légende" : "Modifier votre message")
        : "Réponse à " + (cible.auteur.id === CFG.moi ? "vous-même" : cible.auteur.nom);
      refs["saisie-contexte-apercu"].textContent = apercuDe(cible);
    }

    function annulerContexte() {
      var enEdition = !!etat.edition;
      etat.reponseA = null;
      etat.edition = null;
      afficherContexte();
      if (enEdition) {
        refs["saisie-champ"].value = etat.brouillons.get(etat.actifId) || "";
        ajusterSaisie();
      }
    }

    function commencerReponse(m) {
      etat.edition = null;
      etat.reponseA = m;
      afficherContexte();
      refs["saisie-champ"].focus();
    }

    function commencerEdition(m) {
      etat.brouillons.set(etat.actifId, etat.edition ? etat.brouillons.get(etat.actifId) || "" : refs["saisie-champ"].value);
      etat.reponseA = null;
      etat.edition = m;
      afficherContexte();
      refs["saisie-champ"].value = m.texte || "";
      ajusterSaisie();
      refs["saisie-champ"].focus();
      refs["saisie-champ"].setSelectionRange(refs["saisie-champ"].value.length, refs["saisie-champ"].value.length);
    }

    // --------------------------------------------------------------
    // Menu d'un message
    // --------------------------------------------------------------

    function ouvrirMenuMessage(m, bouton) {
      var d = etat.detail || {};
      var moi = m.auteur.id === CFG.moi;
      var membre = !!d.membre;
      var elements = [];
      var cache = Magasin.cache(etat.actifId);
      var actuel = cache.parId.get(m.id) || m;

      if (d.peut_ecrire && !actuel.supprime) {
        elements.push({ icone: "repondre", libelle: "Répondre", action: function () { commencerReponse(actuel); } });
      }
      if (actuel.texte && navigator.clipboard) {
        elements.push({ icone: "copier", libelle: "Copier le texte", action: function () {
          navigator.clipboard.writeText(actuel.texte).then(function () { toast("Texte copié."); }, function () { toast("La copie a été refusée par le navigateur.", "erreur"); });
        } });
      }
      var piecesDuMessage = actuel.pieces_jointes || [];
      if (piecesDuMessage.length === 1) {
        elements.push({ icone: "telecharger", libelle: "Télécharger le fichier", action: function () {
          var lien = el("a", { href: urlTelechargement(piecesDuMessage[0]), download: piecesDuMessage[0].nom, hidden: true });
          document.body.appendChild(lien);
          lien.click();
          lien.remove();
        } });
      }
      if (actuel.modifiable && d.peut_ecrire) {
        elements.push({ icone: "modifier", libelle: "Modifier", action: function () { commencerEdition(actuel); } });
      }
      if (membre && !actuel.supprime) {
        elements.push({ icone: "transferer", libelle: "Transférer", action: function () { ouvrirTransfert(actuel); } });
      }
      if (d.peut_ecrire && !actuel.supprime) {
        var epingle = (d.epingles || []).some(function (e) { return e.id === actuel.id; });
        elements.push({ icone: "epingle", libelle: epingle ? "Retirer l’épingle" : "Épingler", action: function () { basculerEpingle(actuel, !epingle); } });
      }
      if (moi && d.type !== "direct") {
        elements.push({ icone: "oeil", libelle: "Lu par…", action: function () { ouvrirLuPar(actuel); } });
      }
      var adminGroupe = d.mon_role === "admin" && d.type !== "direct";
      if (membre && !actuel.supprime && (moi || adminGroupe)) {
        elements.push({ icone: "supprimer", libelle: "Supprimer", danger: true, action: function () { supprimerMessage(actuel); } });
      }
      Menu.ouvrir(bouton, elements, racine.closest("[data-msg-tiroir]") || document.body);
    }

    function supprimerMessage(m) {
      confirmer("Supprimer ce message pour tous les membres de la discussion ?", "Supprimer").then(function (ok) {
        if (!ok) {
          return;
        }
        appel("POST", "/messages/" + m.id + "/supprimer").then(function (r) {
          var cache = Magasin.cache(r.message.conversation_id);
          Magasin.ranger(cache, r.message);
          if (r.message.conversation_id === etat.actifId) {
            remplacerNoeud(cache.parId.get(r.message.id));
          }
          var resume = Magasin.conversation(r.message.conversation_id);
          if (resume && resume.dernier_message && resume.dernier_message.id === r.message.id) {
            resume.dernier_message.apercu = apercuDe(r.message);
            resume.dernier_message.supprime = true;
            rendreListe();
          }
        }).catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
      });
    }

    function basculerEpingle(m, epingler) {
      appel("POST", "/conversations/" + etat.actifId + (epingler ? "/epingler" : "/desepingler"), { message_id: m.id })
        .then(function () {
          toast(epingler ? "Message épinglé." : "Épingle retirée.");
          return rechargerDetail();
        })
        .catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
    }

    function rechargerDetail() {
      var conversationId = etat.actifId;
      if (!conversationId) {
        return Promise.resolve();
      }
      return appel("GET", "/conversations/" + conversationId).then(function (r) {
        if (etat.actifId !== conversationId) {
          return;
        }
        var avant = etat.detail;
        etat.detail = r.conversation;
        var carte = Magasin.lectures(conversationId);
        (etat.detail.membres || []).forEach(function (m) {
          carte.set(m.id, Math.max(carte.get(m.id) || 0, m.dernier_lu_id || 0));
        });
        rendreEntete(Magasin.conversation(conversationId), etat.detail);
        rendreEpingles();
        rendreSaisieAutorisee();
        if (!refs.infos.hidden) {
          rendreInfos();
        }
        if (avant && avant.type !== etat.detail.type) {
          rendreMessages("maj");
        } else {
          majAccuses();
        }
      }).catch(function (erreur) {
        if (erreur && erreur.statut === 404) {
          quitteeAffichee(conversationId);
        }
      });
    }

    // --------------------------------------------------------------
    // Épinglés
    // --------------------------------------------------------------

    function rendreEpingles() {
      var zone = refs.epingles;
      var liste = (etat.detail && etat.detail.epingles) || [];
      vider(zone);
      zone.hidden = liste.length === 0;
      if (!liste.length) {
        return;
      }
      if (etat.epingleIndex >= liste.length) {
        etat.epingleIndex = 0;
      }
      var m = liste[etat.epingleIndex];
      var bouton = el("button", { type: "button", class: "msg-epingle", title: "Aller au message épinglé" }, [
        icone("epingle"),
        el("span", { class: "msg-epingle-texte" }, [
          el("strong", { text: liste.length > 1 ? "Épinglé " + (etat.epingleIndex + 1) + "/" + liste.length : "Épinglé" }),
          el("span", { text: m.auteur.nom + " : " + apercuDe(m) })
        ])
      ]);
      bouton.addEventListener("click", function () {
        allerA(m.id);
        if (liste.length > 1) {
          etat.epingleIndex = (etat.epingleIndex + 1) % liste.length;
          rendreEpingles();
        }
      });
      zone.appendChild(bouton);
      if (etat.detail.peut_ecrire) {
        var retirer = el("button", { type: "button", class: "msg-icone-btn", title: "Retirer cette épingle", "aria-label": "Retirer cette épingle" }, icone("fermer"));
        retirer.addEventListener("click", function () { basculerEpingle(m, false); });
        zone.appendChild(retirer);
      }
    }

    // --------------------------------------------------------------
    // Recherche dans la discussion
    // --------------------------------------------------------------

    var resultatsFil = el("div", { class: "msg-resultats msg-resultats--fil", hidden: true });
    refs["recherche-fil"].after(resultatsFil);

    function ouvrirRechercheFil() {
      refs["recherche-fil"].hidden = false;
      refs["recherche-fil-champ"].focus();
    }

    function fermerRechercheFil() {
      refs["recherche-fil"].hidden = true;
      refs["recherche-fil-champ"].value = "";
      refs["recherche-fil-compte"].textContent = "";
      resultatsFil.hidden = true;
      vider(resultatsFil);
    }

    var chercherDansFil = attendre(function () {
      var texte = refs["recherche-fil-champ"].value.trim();
      var conversationId = etat.actifId;
      if (texte.length < 2) {
        resultatsFil.hidden = true;
        refs["recherche-fil-compte"].textContent = texte.length ? "2 caractères au moins" : "";
        return;
      }
      appel("GET", "/recherche?q=" + encodeURIComponent(texte) + "&conversation_id=" + conversationId).then(function (r) {
        if (etat.actifId !== conversationId) {
          return;
        }
        var n = (r.resultats || []).length;
        refs["recherche-fil-compte"].textContent = n ? n + " résultat" + (n > 1 ? "s" : "") : "Aucun résultat";
        rendreResultats(resultatsFil, r.resultats || [], texte, false);
      }).catch(function (erreur) {
        refs["recherche-fil-compte"].textContent = messageErreur(erreur);
      });
    }, 350);

    refs["recherche-fil-champ"].addEventListener("input", chercherDansFil);
    refs["recherche-fil-champ"].addEventListener("keydown", function (evenement) {
      if (evenement.key === "Escape") {
        evenement.stopPropagation();
        fermerRechercheFil();
        racine.querySelector("[data-msg-action='recherche-fil']").focus();
      }
    });

    // --------------------------------------------------------------
    // Panneau d'informations
    // --------------------------------------------------------------

    function ouvrirInfos() {
      if (!etat.detail) {
        return;
      }
      refs.infos.hidden = false;
      racine.setAttribute("data-infos", "1");
      rendreInfos();
      var premier = refs.infos.querySelector("button");
      if (premier) {
        premier.focus();
      }
    }

    function fermerInfos() {
      if (refs.infos.hidden) {
        return;
      }
      refs.infos.hidden = true;
      racine.removeAttribute("data-infos");
    }

    function interrupteur(libelle, aide, actif, surChangement) {
      var entree = el("input", { type: "checkbox", role: "switch" });
      entree.checked = !!actif;
      entree.addEventListener("change", function () { surChangement(entree.checked, entree); });
      return el("label", { class: "msg-interrupteur" }, [
        el("span", { class: "msg-interrupteur-texte" }, [el("strong", { text: libelle }), aide ? el("small", { text: aide }) : null]),
        entree,
        el("span", { class: "msg-interrupteur-piste", "aria-hidden": "true" })
      ]);
    }

    function rendreInfos() {
      var d = etat.detail;
      var zone = refs.infos;
      vider(zone);
      if (!d) {
        return;
      }
      var groupe = d.type !== "direct";
      var fermer = el("button", { type: "button", class: "msg-icone-btn", title: "Fermer", "aria-label": "Fermer les informations" }, icone("fermer"));
      fermer.addEventListener("click", function () {
        fermerInfos();
        racine.querySelector("[data-msg-action='infos']").focus();
      });
      zone.appendChild(el("header", { class: "msg-infos-tete" }, [fermer, el("h3", { text: groupe ? "Infos du groupe" : "Infos de la discussion" })]));

      var corps = el("div", { class: "msg-infos-corps" });
      zone.appendChild(corps);

      var grandAvatar = avatarConversation(d);
      grandAvatar.classList.add("msg-avatar--grand");
      var typeLibelle = d.type === "direct" ? "Discussion directe"
        : (d.type === "annonces" ? "Annonces" : "Groupe") + " · " + (d.membres || []).length + " membre" + ((d.membres || []).length > 1 ? "s" : "");
      corps.appendChild(el("section", { class: "msg-infos-identite" }, [
        grandAvatar,
        groupe && d.peut_administrer ? controlesPhoto(d) : null,
        el("h4", { text: nomAffiche(d, d) }),
        el("p", { class: "msg-infos-type", text: typeLibelle }),
        d.type === "direct" ? el("p", { class: "msg-infos-presence", text: sousTitre(d, d) }) : null,
        d.description ? el("p", { class: "msg-infos-description", text: d.description }) : null,
        d.acces_direction ? el("p", { class: "msg-lecture-seule" }, [icone("oeil"), el("span", { text: "Lecture seule — accès Direction" })]) : null
      ]));

      if (d.membre) {
        var reglages = el("section", { class: "msg-infos-section" });
        reglages.appendChild(interrupteur("Sourdine", "Pas de son pour cette discussion.", d.sourdine, function (valeur, entree) {
          appel("POST", "/conversations/" + d.id + "/sourdine", { actif: valeur }).then(function () {
            d.sourdine = valeur;
            var resume = Magasin.conversation(d.id);
            if (resume) {
              resume.sourdine = valeur;
            }
            rendreListe();
          }).catch(function (erreur) {
            entree.checked = !valeur;
            toast(messageErreur(erreur), "erreur");
          });
        }));
        if (d.peut_administrer) {
          reglages.appendChild(interrupteur("Discussion d’annonces", "Seuls les administrateurs y écrivent.", d.type === "annonces", function (valeur, entree) {
            appel("POST", "/conversations/" + d.id + "/annonces", { actif: valeur }).then(function () {
              Magasin.rafraichir();
              return rechargerDetail();
            }).catch(function (erreur) {
              entree.checked = !valeur;
              toast(messageErreur(erreur), "erreur");
            });
          }));
        }
        corps.appendChild(reglages);

        var actions = el("section", { class: "msg-infos-section msg-infos-actions" });
        if (d.peut_administrer) {
          actions.appendChild(boutonAction("modifier", "Modifier le nom et la description", ouvrirRenommer));
          actions.appendChild(boutonAction("ajout-membre", "Ajouter des membres", ouvrirAjoutMembres));
        }
        actions.appendChild(boutonAction("archive", d.archivee ? "Désarchiver la discussion" : "Archiver la discussion", function () {
          var valeur = !d.archivee;
          appel("POST", "/conversations/" + d.id + "/archiver", { actif: valeur }).then(function () {
            d.archivee = valeur;
            var resume = Magasin.conversation(d.id);
            if (resume) {
              resume.archivee = valeur;
            }
            toast(valeur ? "Discussion archivée. Retrouvez-la dans « Archivées »." : "Discussion désarchivée.");
            rendreListe();
            rendreInfos();
          }).catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
        }));
        // Une discussion à deux ne se quitte pas : elle s'archive.
        if (groupe) {
          actions.appendChild(boutonAction("quitter", "Quitter le groupe", function () {
            var seulAdmin = d.mon_role === "admin" && (d.membres || []).filter(function (m) { return m.role === "admin"; }).length <= 1 && (d.membres || []).length > 1;
            var texte = "Quitter « " + d.nom + " » ? Vous ne recevrez plus ses messages."
              + (seulAdmin ? " Vous êtes le seul administrateur : le membre le plus ancien le deviendra." : "");
            confirmer(texte, "Quitter").then(function (ok) {
              if (!ok) {
                return;
              }
              appel("POST", "/conversations/" + d.id + "/quitter").then(function () {
                toast("Vous avez quitté « " + d.nom + " ».");
                Magasin.oublier(d.id);
                revenirALaListe();
              }).catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
            });
          }, true));
        }
        corps.appendChild(actions);
      }

      if (groupe || d.acces_direction) {
        var membres = el("section", { class: "msg-infos-section" });
        membres.appendChild(el("h4", { class: "msg-infos-titre", text: (d.membres || []).length + " membre" + ((d.membres || []).length > 1 ? "s" : "") }));
        var liste = el("ul", { class: "msg-membres" });
        (d.membres || []).forEach(function (m) { liste.appendChild(ligneMembre(d, m)); });
        membres.appendChild(liste);
        corps.appendChild(membres);
      }
    }

    /* Photo du groupe : un administrateur la choisit, la change ou la retire. */
    function controlesPhoto(d) {
      var champ = el("input", { type: "file", class: "msg-fichier-champ", accept: "image/jpeg,image/png,image/webp", hidden: true, tabindex: "-1", "aria-hidden": "true" });
      var erreur = el("p", { class: "msg-erreur", role: "alert", hidden: true });
      var choisir = el("button", { type: "button", class: "msg-btn msg-btn--petit" }, [icone("appareil"), el("span", { text: d.photo ? "Changer la photo" : "Choisir une photo" })]);
      var zone = el("div", { class: "msg-infos-photo" }, [champ, choisir]);

      function appliquer(photo, message) {
        d.photo = photo;
        var resume = Magasin.conversation(d.id);
        if (resume) {
          resume.photo = photo;
        }
        rendreListe();
        rendreEntete(Magasin.conversation(d.id), d);
        rendreInfos();
        toast(message);
      }

      function montrerErreur(texte) {
        erreur.textContent = texte;
        erreur.hidden = false;
      }

      choisir.addEventListener("click", function () { champ.click(); });
      champ.addEventListener("change", function () {
        var fichier = champ.files && champ.files[0];
        champ.value = "";
        if (!fichier) {
          return;
        }
        erreur.hidden = true;
        if (categorieDe(fichier.name) !== "image") {
          montrerErreur("Choisissez une image JPG, PNG ou WEBP.");
          return;
        }
        var raison = raisonRefus(fichier);
        if (raison) {
          montrerErreur("Cette image n’est pas acceptée : " + raison);
          return;
        }
        var donnees = new FormData();
        donnees.append("photo", fichier, fichier.name);
        choisir.disabled = true;
        choisir.lastChild.textContent = "Envoi de la photo…";
        envoyerFormulaire("/conversations/" + d.id + "/photo", donnees).promesse.then(function (r) {
          appliquer(r.photo, "Photo du groupe mise à jour.");
        }).catch(function (e) {
          choisir.disabled = false;
          choisir.lastChild.textContent = d.photo ? "Changer la photo" : "Choisir une photo";
          montrerErreur(messageErreur(e));
        });
      });

      if (d.photo) {
        zone.appendChild(el("button", { type: "button", class: "msg-btn msg-btn--petit msg-btn--discret-danger", onclick: function () {
          confirmer("Retirer la photo du groupe « " + d.nom + " » ? L’icône du groupe reviendra.", "Retirer").then(function (ok) {
            if (!ok) {
              return;
            }
            appel("POST", "/conversations/" + d.id + "/photo/retirer").then(function () {
              appliquer(null, "Photo du groupe retirée.");
            }).catch(function (e) { montrerErreur(messageErreur(e)); });
          });
        } }, [icone("supprimer"), el("span", { text: "Retirer" })]));
      }
      zone.appendChild(erreur);
      return zone;
    }

    function boutonAction(nomIcone, libelle, action, danger) {
      return el("button", { type: "button", class: "msg-infos-action" + (danger ? " is-danger" : ""), onclick: action }, [icone(nomIcone), el("span", { text: libelle })]);
    }

    function ligneMembre(d, m) {
      var moi = m.id === CFG.moi;
      var p = Magasin.presence(m.id);
      var a = avatar(m.nom, m.id);
      if (p && p.en_ligne) {
        a.appendChild(el("span", { class: "msg-avatar-enligne" }));
      }
      var ligne = el("li", { class: "msg-membre" }, [
        a,
        el("span", { class: "msg-membre-texte" }, [
          el("strong", { text: moi ? "Vous" : m.nom }),
          el("small", { text: !m.actif ? "Compte désactivé" : (moi ? "" : libellePresence(p)) })
        ]),
        m.role === "admin" ? el("span", { class: "msg-role", text: "Admin" }) : null
      ]);
      if (!moi) {
        var elements = [];
        if (m.actif && !d.acces_direction) {
          elements.push({ icone: "discussion", libelle: "Envoyer un message", action: function () { ouvrirDirecte(m.id); } });
        }
        if (d.peut_administrer) {
          elements.push(m.role === "admin"
            ? { icone: "bouclier", libelle: "Retirer le rôle d’admin", action: function () { changerRole(d, m, "membre"); } }
            : { icone: "bouclier", libelle: "Nommer administrateur", action: function () { changerRole(d, m, "admin"); } });
          elements.push({ icone: "retrait-membre", libelle: "Retirer du groupe", danger: true, action: function () { retirerMembre(d, m); } });
        }
        if (elements.length) {
          var bouton = el("button", { type: "button", class: "msg-icone-btn", "aria-haspopup": "menu", "aria-expanded": "false", "aria-label": "Actions pour " + m.nom, title: "Actions" }, icone("chevron-bas"));
          bouton.addEventListener("click", function () { Menu.ouvrir(bouton, elements, racine.closest("[data-msg-tiroir]") || document.body); });
          ligne.appendChild(bouton);
        }
      }
      return ligne;
    }

    function changerRole(d, m, role) {
      appel("POST", "/conversations/" + d.id + "/membres/role", { user_id: m.id, role: role }).then(function () {
        toast(role === "admin" ? m.nom + " est administrateur." : m.nom + " n’est plus administrateur.");
        return rechargerDetail();
      }).catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
    }

    function retirerMembre(d, m) {
      confirmer("Retirer " + m.nom + " du groupe « " + d.nom + " » ?", "Retirer").then(function (ok) {
        if (!ok) {
          return;
        }
        appel("POST", "/conversations/" + d.id + "/membres/retirer", { user_id: m.id }).then(function () {
          toast(m.nom + " a été retiré du groupe.");
          return rechargerDetail();
        }).catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
      });
    }

    // --------------------------------------------------------------
    // Dialogues
    // --------------------------------------------------------------

    var retourFocus = null;

    function ouvrirDialogue(nom) {
      var dialogue = dialogues[nom];
      retourFocus = document.activeElement;
      if (typeof dialogue.showModal === "function") {
        dialogue.showModal();
      } else {
        dialogue.setAttribute("open", "");
      }
      return dialogue;
    }

    function fermerDialogue(dialogue) {
      if (typeof dialogue.close === "function") {
        dialogue.close();
      } else {
        dialogue.removeAttribute("open");
      }
    }

    Object.keys(dialogues).forEach(function (nom) {
      var dialogue = dialogues[nom];
      dialogue.addEventListener("close", function () {
        if (retourFocus && document.contains(retourFocus)) {
          retourFocus.focus();
        }
      });
      dialogue.addEventListener("click", function (evenement) {
        if (evenement.target === dialogue) {
          fermerDialogue(dialogue);
        }
      });
      dialogue.querySelectorAll("[data-msg-fermer-dialogue]").forEach(function (bouton) {
        bouton.addEventListener("click", function () { fermerDialogue(dialogue); });
      });
    });

    function confirmer(texte, libelleOk) {
      return new Promise(function (resoudre) {
        var dialogue = dialogues.confirmer;
        refs["confirmer-texte"].textContent = texte;
        var ok = refs["confirmer-ok"];
        ok.textContent = libelleOk || "Confirmer";
        var resolu = false;
        function fin(valeur) {
          if (resolu) {
            return;
          }
          resolu = true;
          ok.removeEventListener("click", surOk);
          dialogue.removeEventListener("close", surFermeture);
          resoudre(valeur);
        }
        function surOk() {
          fin(true);
          fermerDialogue(dialogue);
        }
        function surFermeture() {
          fin(false);
        }
        ok.addEventListener("click", surOk);
        dialogue.addEventListener("close", surFermeture);
        ouvrirDialogue("confirmer");
        ok.focus();
      });
    }

    // Annuaire : nouvelle discussion directe
    var chercherAnnuaire = attendre(function (champ, liste, surChoix, exclus, choisis) {
      var q = champ.value.trim();
      appel("GET", "/annuaire" + (q ? "?q=" + encodeURIComponent(q) : "")).then(function (r) {
        if (champ.value.trim() !== q) {
          return;
        }
        rendreCollegues(liste, (r.collegues || []).filter(function (c) { return !(exclus && exclus.has(c.id)); }), surChoix, choisis);
      }).catch(function (erreur) {
        vider(liste);
        liste.appendChild(el("li", { class: "msg-choix-vide", text: messageErreur(erreur) }));
      });
    }, 250);

    function rendreCollegues(liste, collegues, surChoix, choisis) {
      vider(liste);
      if (collegues.length === 0) {
        liste.appendChild(el("li", { class: "msg-choix-vide", text: "Aucun collègue ne correspond." }));
        return;
      }
      collegues.forEach(function (c) {
        var p = Magasin.presence(c.id);
        var details = [c.fonction, c.agence].filter(Boolean).join(" · ");
        var selectionne = choisis && choisis.has(c.id);
        var bouton = el("button", { type: "button", class: "msg-choix-item", "aria-pressed": choisis ? (selectionne ? "true" : "false") : null }, [
          avatar(c.nom, c.id),
          el("span", { class: "msg-choix-texte" }, [el("strong", { text: c.nom }), el("small", { text: details || (p && p.en_ligne ? "en ligne" : "") })]),
          choisis ? el("span", { class: "msg-case", "aria-hidden": "true" }, icone("coche", "msg-i--petit")) : null
        ]);
        bouton.addEventListener("click", function () { surChoix(c, bouton); });
        liste.appendChild(el("li", null, bouton));
      });
    }

    function ouvrirAnnuaire() {
      ouvrirDialogue("annuaire");
      refs["annuaire-champ"].value = "";
      vider(refs["annuaire-liste"]);
      refs["annuaire-liste"].appendChild(el("li", { class: "msg-choix-vide", text: "Chargement…" }));
      chercherAnnuaire(refs["annuaire-champ"], refs["annuaire-liste"], function (c) {
        fermerDialogue(dialogues.annuaire);
        ouvrirDirecte(c.id);
      });
      refs["annuaire-champ"].focus();
    }

    refs["annuaire-champ"].addEventListener("input", function () {
      chercherAnnuaire(refs["annuaire-champ"], refs["annuaire-liste"], function (c) {
        fermerDialogue(dialogues.annuaire);
        ouvrirDirecte(c.id);
      });
    });

    function ouvrirDirecte(userId) {
      appel("POST", "/directes", { user_id: userId }).then(function (r) {
        return Magasin.charger().then(function () { ouvrir(r.conversation_id); });
      }).catch(function (erreur) { toast(messageErreur(erreur), "erreur"); });
    }

    // Sélection multiple (nouveau groupe, ajout de membres)
    function selecteur(prefixe, exclus) {
      var choisis = new Map();
      var champ = refs[prefixe + "-recherche"];
      var liste = refs[prefixe + "-annuaire"];
      var jetons = refs[prefixe + "-jetons"];

      function rendreJetons() {
        vider(jetons);
        choisis.forEach(function (c) {
          var retirer = el("button", { type: "button", class: "msg-jeton", "aria-label": "Retirer " + c.nom }, [el("span", { text: nomCourt(c.nom) || c.nom }), icone("fermer", "msg-i--petit")]);
          retirer.addEventListener("click", function () {
            choisis.delete(c.id);
            rendreJetons();
            chercher();
          });
          jetons.appendChild(retirer);
        });
        if (choisis.size === 0) {
          jetons.appendChild(el("span", { class: "msg-jetons-vide", text: "Aucun collègue choisi." }));
        }
      }

      function basculer(c) {
        if (choisis.has(c.id)) {
          choisis.delete(c.id);
        } else {
          choisis.set(c.id, c);
        }
        rendreJetons();
        var focus = c.id;
        rendreCollegues(liste, derniers, basculer, new Set(choisis.keys()));
        var boutons = liste.querySelectorAll(".msg-choix-item");
        derniers.forEach(function (d, i) {
          if (d.id === focus && boutons[i]) {
            boutons[i].focus();
          }
        });
      }

      var derniers = [];
      var chercher = attendre(function () {
        var q = champ.value.trim();
        appel("GET", "/annuaire" + (q ? "?q=" + encodeURIComponent(q) : "")).then(function (r) {
          if (champ.value.trim() !== q) {
            return;
          }
          derniers = (r.collegues || []).filter(function (c) { return !(exclus && exclus.has(c.id)); });
          rendreCollegues(liste, derniers, basculer, new Set(choisis.keys()));
        }).catch(function (erreur) {
          vider(liste);
          liste.appendChild(el("li", { class: "msg-choix-vide", text: messageErreur(erreur) }));
        });
      }, 250);

      champ.value = "";
      champ.oninput = chercher;
      rendreJetons();
      vider(liste);
      chercher();
      return { choisis: choisis };
    }

    var selectionGroupe = null;
    function ouvrirNouveauGroupe() {
      refs["groupe-nom"].value = "";
      refs["groupe-description"].value = "";
      refs["groupe-erreur"].hidden = true;
      selectionGroupe = selecteur("groupe", new Set([CFG.moi]));
      ouvrirDialogue("groupe");
      refs["groupe-nom"].focus();
    }

    refs["groupe-form"].addEventListener("submit", function (evenement) {
      evenement.preventDefault();
      var nom = refs["groupe-nom"].value.trim();
      if (!nom) {
        afficherErreur("groupe-erreur", "Donnez un nom au groupe.");
        refs["groupe-nom"].focus();
        return;
      }
      var membres = selectionGroupe ? Array.from(selectionGroupe.choisis.keys()) : [];
      var bouton = refs["groupe-form"].querySelector("[type=submit]");
      bouton.disabled = true;
      appel("POST", "/groupes", { nom: nom, description: refs["groupe-description"].value.trim(), membres: membres }).then(function (r) {
        fermerDialogue(dialogues.groupe);
        toast("Groupe « " + nom + " » créé.");
        return Magasin.charger().then(function () { ouvrir(r.conversation_id); });
      }).catch(function (erreur) {
        afficherErreur("groupe-erreur", messageErreur(erreur));
      }).then(function () {
        bouton.disabled = false;
      });
    });

    var selectionAjout = null;
    function ouvrirAjoutMembres() {
      var d = etat.detail;
      refs["ajout-erreur"].hidden = true;
      selectionAjout = selecteur("ajout", new Set((d.membres || []).map(function (m) { return m.id; })));
      ouvrirDialogue("ajout-membres");
      refs["ajout-recherche"].focus();
    }

    refs["ajout-form"].addEventListener("submit", function (evenement) {
      evenement.preventDefault();
      var ids = selectionAjout ? Array.from(selectionAjout.choisis.keys()) : [];
      if (!ids.length) {
        afficherErreur("ajout-erreur", "Choisissez au moins un collègue.");
        return;
      }
      appel("POST", "/conversations/" + etat.actifId + "/membres/ajouter", { user_ids: ids }).then(function (r) {
        fermerDialogue(dialogues["ajout-membres"]);
        var n = (r.ajoutes || ids).length;
        toast(n > 1 ? n + " membres ajoutés." : "Membre ajouté.");
        return rechargerDetail();
      }).catch(function (erreur) { afficherErreur("ajout-erreur", messageErreur(erreur)); });
    });

    function ouvrirRenommer() {
      var d = etat.detail;
      refs["renommer-nom"].value = d.nom || "";
      refs["renommer-description"].value = d.description || "";
      refs["renommer-erreur"].hidden = true;
      ouvrirDialogue("renommer");
      refs["renommer-nom"].focus();
    }

    refs["renommer-form"].addEventListener("submit", function (evenement) {
      evenement.preventDefault();
      var nom = refs["renommer-nom"].value.trim();
      if (!nom) {
        afficherErreur("renommer-erreur", "Le nom du groupe ne peut pas être vide.");
        return;
      }
      appel("POST", "/conversations/" + etat.actifId + "/renommer", { nom: nom, description: refs["renommer-description"].value.trim() }).then(function () {
        fermerDialogue(dialogues.renommer);
        toast("Groupe mis à jour.");
        Magasin.rafraichir();
        return rechargerDetail();
      }).catch(function (erreur) { afficherErreur("renommer-erreur", messageErreur(erreur)); });
    });

    function afficherErreur(cle, texte) {
      refs[cle].textContent = texte;
      refs[cle].hidden = false;
    }

    // Transfert
    function ouvrirTransfert(m) {
      var liste = refs["transfert-liste"];
      vider(liste);
      var cibles = Magasin.liste().filter(function (c) {
        if (c.type === "annonces" && c.mon_role !== "admin") {
          return false;
        }
        return !(c.correspondant && c.correspondant.actif === false);
      });
      if (!cibles.length) {
        liste.appendChild(el("li", { class: "msg-choix-vide", text: "Aucune discussion où transférer." }));
      }
      cibles.forEach(function (c) {
        var bouton = el("button", { type: "button", class: "msg-choix-item" }, [
          avatarConversation(c),
          el("span", { class: "msg-choix-texte" }, [el("strong", { text: c.nom }), el("small", { text: c.type === "direct" ? "Discussion directe" : (c.type === "annonces" ? "Annonces" : "Groupe") })])
        ]);
        bouton.addEventListener("click", function () {
          bouton.disabled = true;
          appel("POST", "/messages/" + m.id + "/transferer", { conversation_id: c.id }).then(function (r) {
            fermerDialogue(dialogues.transfert);
            toast("Message transféré à « " + c.nom + " ».");
            var cache = Magasin.cache(c.id);
            if (cache.charge) {
              Magasin.ranger(cache, r.message);
            }
            Magasin.mettreAJourDernier(r.message);
          }).catch(function (erreur) {
            bouton.disabled = false;
            toast(messageErreur(erreur), "erreur");
          });
        });
        liste.appendChild(el("li", null, bouton));
      });
      ouvrirDialogue("transfert");
      var premier = liste.querySelector("button");
      if (premier) {
        premier.focus();
      }
    }

    // Lu par
    function ouvrirLuPar(m) {
      var zone = refs["lu-par-contenu"];
      vider(zone);
      zone.appendChild(el("p", { class: "msg-dialogue-aide", text: "Chargement…" }));
      ouvrirDialogue("lu-par");
      appel("GET", "/messages/" + m.id + "/lu-par").then(function (r) {
        vider(zone);
        zone.appendChild(el("p", { class: "msg-lu-par-apercu", text: apercuDe(m) }));
        function bloc(titre, personnes, avecHeure) {
          var section = el("section", { class: "msg-lu-par-bloc" }, [el("h4", { text: titre + " (" + personnes.length + ")" })]);
          var ul = el("ul", { class: "msg-membres" });
          personnes.forEach(function (p) {
            var date = avecHeure ? versDate(p.lu_at) : null;
            ul.appendChild(el("li", { class: "msg-membre" }, [
              avatar(p.nom, p.id),
              el("span", { class: "msg-membre-texte" }, [el("strong", { text: p.nom }), date ? el("small", { text: libelleDateComplete(date) }) : null])
            ]));
          });
          if (!personnes.length) {
            ul.appendChild(el("li", { class: "msg-choix-vide", text: avecHeure ? "Personne pour l’instant." : "Tout le monde l’a lu." }));
          }
          section.appendChild(ul);
          return section;
        }
        zone.appendChild(bloc("Lu par", r.lu_par || [], true));
        zone.appendChild(bloc("Pas encore lu", r.non_lu_par || [], false));
      }).catch(function (erreur) {
        vider(zone);
        zone.appendChild(el("p", { class: "msg-erreur", text: messageErreur(erreur) }));
      });
    }

    // Réglages personnels
    function ouvrirReglages() {
      refs["reglages-erreur"].hidden = true;
      var cases = dialogues.reglages.querySelectorAll("[data-msg-preference]");
      cases.forEach(function (c) { c.disabled = true; });
      ouvrirDialogue("reglages");
      Magasin.preferences().then(function (p) {
        cases.forEach(function (c) {
          c.checked = !!p[c.getAttribute("data-msg-preference")];
          c.disabled = false;
        });
        cases[0].focus();
      }).catch(function (erreur) { afficherErreur("reglages-erreur", messageErreur(erreur)); });
    }

    dialogues.reglages.querySelectorAll("[data-msg-preference]").forEach(function (caseACocher) {
      caseACocher.addEventListener("change", function () {
        var cle = caseACocher.getAttribute("data-msg-preference");
        var corps = {};
        corps[cle] = caseACocher.checked;
        refs["reglages-erreur"].hidden = true;
        appel("POST", "/preferences", corps).then(function (r) {
          Magasin.fixerPreferences(r.preferences);
        }).catch(function (erreur) {
          caseACocher.checked = !caseACocher.checked;
          afficherErreur("reglages-erreur", messageErreur(erreur));
        });
      });
    });

    // --------------------------------------------------------------
    // Actions déclarées dans la charpente
    // --------------------------------------------------------------

    var actions = {
      "nouvelle-discussion": ouvrirAnnuaire,
      "nouveau-groupe": ouvrirNouveauGroupe,
      reglages: ouvrirReglages,
      retour: function () {
        if (mode === "plein" && history.state && history.state.msgConversation) {
          history.back();
        } else {
          revenirALaListe();
        }
      },
      infos: function () {
        if (refs.infos.hidden) {
          ouvrirInfos();
        } else {
          fermerInfos();
        }
      },
      "recherche-fil": function () {
        if (refs["recherche-fil"].hidden) {
          ouvrirRechercheFil();
        } else {
          fermerRechercheFil();
        }
      },
      "fermer-recherche-fil": fermerRechercheFil,
      "annuler-contexte": annulerContexte,
      joindre: choisirFichiers,
      "vers-bas": function () {
        etat.nouveauxEnBas = 0;
        allerEnBas(true);
        majBoutonBas();
        refs["saisie-champ"].focus({ preventScroll: true });
      }
    };

    racine.addEventListener("click", function (evenement) {
      var cible = evenement.target.closest("[data-msg-action]");
      if (!cible || !racine.contains(cible)) {
        return;
      }
      var action = actions[cible.getAttribute("data-msg-action")];
      if (action) {
        evenement.preventDefault();
        action(cible);
      }
    });

    racine.addEventListener("keydown", function (evenement) {
      if (evenement.key !== "Escape" || evenement.defaultPrevented || Menu.estOuvert()) {
        return;
      }
      if (evenement.target.closest("dialog")) {
        return;
      }
      if (!refs.infos.hidden) {
        evenement.preventDefault();
        evenement.stopPropagation();
        fermerInfos();
        racine.querySelector("[data-msg-action='infos']").focus();
      } else if (uneColonne() && racine.getAttribute("data-vue") === "fil" && mode === "plein") {
        evenement.preventDefault();
        actions.retour();
      }
    });

    // --------------------------------------------------------------
    // Événements du magasin
    // --------------------------------------------------------------

    Magasin.on("conversations", function () {
      rendreListe();
      if (etat.actifId) {
        var resume = Magasin.conversation(etat.actifId);
        if (resume && !etat.detail) {
          rendreEntete(resume, null);
        }
      }
    });

    Magasin.on("preferences", function (p) {
      preferencesCourantes = p;
      rendreListe();
    });

    Magasin.on("messages", function (parConversation) {
      if (!etat.actifId || !parConversation.has(etat.actifId)) {
        return;
      }
      var zone = refs.messages;
      var etaitEnBas = estEnBas();
      var vide = zone.querySelector(".msg-messages-etat");
      if (vide) {
        vide.remove();
      }
      parConversation.get(etat.actifId).forEach(function (m) {
        if (etat.noeuds.has(m.id)) {
          return;
        }
        // Mon propre message, déjà affiché en attente : on remplace.
        if (m.auteur.id === CFG.moi) {
          var envoi = enAttente.find(function (e) { return e.conversationId === m.conversation_id && e.message.texte === m.texte && !e.message.erreur; });
          if (envoi) {
            var noeud = etat.noeuds.get(envoi.message.id);
            if (noeud) {
              var neuf = bulle(m, noeud.classList.contains("msg-suite"));
              noeud.replaceWith(neuf);
              etat.noeuds.delete(envoi.message.id);
              etat.noeuds.set(m.id, neuf);
              envoi.remplace = true;
              return;
            }
          }
        }
        ajouterNoeud(m, zone);
        if (m.auteur.id !== CFG.moi && !etaitEnBas) {
          etat.nouveauxEnBas += 1;
        }
      });
      if (etaitEnBas) {
        allerEnBas(true);
      }
      majBoutonBas();
      marquerLu();
    });

    Magasin.on("modifies", function (messages) {
      messages.forEach(function (m) {
        if (m.conversation_id === etat.actifId) {
          var actuel = Magasin.cache(etat.actifId).parId.get(m.id) || m;
          remplacerNoeud(actuel);
        }
      });
      if (etat.detail && messages.some(function (m) { return m.conversation_id === etat.actifId; })) {
        (etat.detail.epingles || []).forEach(function (e, i) {
          var maj = messages.find(function (m) { return m.id === e.id; });
          if (maj) {
            etat.detail.epingles[i] = maj;
          }
        });
        rendreEpingles();
      }
    });

    Magasin.on("lectures", function (conversations) {
      if (etat.actifId && conversations.has(etat.actifId)) {
        majAccuses();
      }
    });

    Magasin.on("presence", function () {
      if (etat.actifId) {
        rendreEntete(Magasin.conversation(etat.actifId), etat.detail);
      }
    });

    Magasin.on("modifiee", function (id) {
      if (id === etat.actifId) {
        rechargerDetail();
      }
    });

    function quitteeAffichee(id) {
      if (id !== etat.actifId) {
        return;
      }
      etat.detail = null;
      vider(refs.messages);
      refs.messages.appendChild(el("div", { class: "msg-messages-etat" }, [
        icone("cadenas"),
        el("span", { text: "Vous ne faites plus partie de cette discussion." }),
        el("button", { type: "button", class: "msg-btn", text: "Retour aux discussions", onclick: revenirALaListe })
      ]));
      refs.saisie.hidden = true;
      refs["saisie-fermee"].hidden = true;
      refs.epingles.hidden = true;
      fermerInfos();
    }

    Magasin.on("quittee", function (id) {
      if (etat.detail && etat.detail.acces_direction) {
        return;
      }
      quitteeAffichee(id);
    });

    document.addEventListener("visibilitychange", marquerLu);
    window.addEventListener("focus", marquerLu);

    window.addEventListener("resize", attendre(function () {
      if (!uneColonne()) {
        racine.setAttribute("data-vue", etat.actifId ? "fil" : "liste");
      }
    }, 150));

    if (mode === "plein") {
      window.addEventListener("popstate", function () {
        var cible = lireAdresse();
        var message = lireMessageCible();
        if (cible) {
          if (cible !== etat.actifId) {
            ouvrir(cible, message);
          } else if (message) {
            allerA(message);
          }
        } else if (etat.actifId && uneColonne()) {
          revenirALaListe();
        }
      });
    }

    function lireAdresse() {
      var correspondance = /(?:^|[#&?])c=(\d+)/.exec(location.hash + "&" + location.search);
      return correspondance ? parseInt(correspondance[1], 10) : null;
    }

    /* « #c=12&m=345 » : le message à montrer, depuis le classeur par exemple. */
    function lireMessageCible() {
      var correspondance = /(?:^|[#&?])m=(\d+)/.exec(location.hash + "&" + location.search);
      return correspondance ? parseInt(correspondance[1], 10) : null;
    }

    // --------------------------------------------------------------
    // Démarrage
    // --------------------------------------------------------------

    ajusterSaisie();
    Magasin.preferences().then(function (p) {
      preferencesCourantes = p;
    }).catch(function () {});

    function demarrer() {
      Magasin.charger().then(function () {
        rendreListe();
        var cible = mode === "plein" ? lireAdresse() : null;
        if (cible) {
          ouvrir(cible, lireMessageCible());
        }
      }).catch(function (erreur) {
        vider(refs["liste-etat"]);
        refs["liste-etat"].hidden = false;
        ajouter(refs["liste-etat"], [
          el("strong", { text: "Discussions indisponibles" }),
          el("p", { text: messageErreur(erreur) }),
          el("button", { type: "button", class: "msg-btn", text: "Réessayer", onclick: demarrer })
        ]);
      });
    }
    demarrer();

    return {
      montrer: function () {
        etat.visible = true;
        marquerLu();
      },
      cacher: function () {
        etat.visible = false;
        Menu.fermer(false);
      },
      focaliser: function () {
        var cible = etat.actifId && racine.getAttribute("data-vue") === "fil"
          ? refs["saisie-champ"]
          : refs["recherche-liste"];
        if (cible && !cible.closest("[hidden]")) {
          cible.focus({ preventScroll: true });
        }
      },
      fermerSurEchap: function () {
        if (!refs.infos.hidden) {
          fermerInfos();
          return false;
        }
        if (!refs["recherche-fil"].hidden) {
          fermerRechercheFil();
          return false;
        }
        return true;
      }
    };
  }

  // ==================================================================
  // Panneau latéral
  // ==================================================================

  function installerPanneau() {
    var tiroir = document.querySelector("[data-msg-tiroir]");
    var gabarit = document.querySelector("template[data-msg-gabarit]");
    var boutons = document.querySelectorAll("[data-msg-ouvrir]");
    if (!tiroir || !gabarit || boutons.length === 0) {
      return;
    }
    var application = null;
    var declencheur = null;

    function ouvrir(bouton) {
      declencheur = bouton || declencheur;
      if (!application) {
        var corps = tiroir.querySelector("[data-msg-tiroir-corps]");
        corps.appendChild(gabarit.content.cloneNode(true));
        application = creerApplication(corps.querySelector("[data-msg-app]"));
      }
      tiroir.hidden = false;
      document.body.classList.add("has-msg-tiroir");
      boutons.forEach(function (b) { b.setAttribute("aria-expanded", "true"); });
      window.requestAnimationFrame(function () {
        tiroir.classList.add("is-ouvert");
        application.montrer();
        application.focaliser();
      });
    }

    function fermer() {
      tiroir.classList.remove("is-ouvert");
      document.body.classList.remove("has-msg-tiroir");
      boutons.forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
      if (application) {
        application.cacher();
      }
      window.setTimeout(function () {
        if (!tiroir.classList.contains("is-ouvert")) {
          tiroir.hidden = true;
        }
      }, MOUVEMENT_REDUIT ? 0 : 220);
      if (declencheur) {
        declencheur.focus();
      }
    }

    boutons.forEach(function (bouton) {
      bouton.setAttribute("aria-controls", "msg-tiroir");
      bouton.setAttribute("aria-expanded", "false");
      bouton.addEventListener("click", function (evenement) {
        if (evenement.ctrlKey || evenement.metaKey || evenement.shiftKey || evenement.button === 1) {
          return;
        }
        evenement.preventDefault();
        if (tiroir.hidden) {
          ouvrir(bouton);
        } else {
          fermer();
        }
      });
    });
    tiroir.id = "msg-tiroir";

    tiroir.querySelector("[data-msg-tiroir-fermer]").addEventListener("click", fermer);
    tiroir.addEventListener("keydown", function (evenement) {
      if (evenement.key !== "Escape" || evenement.defaultPrevented || Menu.estOuvert() || evenement.target.closest("dialog")) {
        return;
      }
      if (application && !application.fermerSurEchap()) {
        evenement.preventDefault();
        return;
      }
      evenement.preventDefault();
      fermer();
    });
  }

  // ==================================================================
  // Mise en route
  // ==================================================================

  window.LbpReleve.demarrer({ api: CFG.api, connexion: CFG.connexion });

  // Ce que l'écran « Documents » (messagerie-documents.js) réutilise.
  window.LbpMessagerie = {
    config: CFG,
    el: el,
    ajouter: ajouter,
    icone: icone,
    vider: vider,
    appel: appel,
    messageErreur: messageErreur,
    toast: toast,
    attendre: attendre,
    versDate: versDate,
    libelleListe: libelleListe,
    libelleDateComplete: libelleDateComplete,
    tailleLisible: tailleLisible,
    libelleType: libelleType,
    categories: CATEGORIES,
    vignetteDocument: vignetteDocument,
    urlTelechargement: urlTelechargement,
    boutonFavori: boutonFavori,
    tactile: TACTILE,
    Visionneuse: Visionneuse
  };

  document.querySelectorAll(".msg-page [data-msg-app]").forEach(function (racine) {
    creerApplication(racine);
  });
  installerPanneau();
})();
