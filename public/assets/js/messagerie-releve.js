/*
 * Messagerie interne — relevé des nouveautés.
 *
 * Ce fichier est le SEUL à parler au serveur pour savoir ce qui a changé.
 * Aujourd'hui il interroge /messagerie/api/nouveautes toutes les 3 s (onglet
 * visible) ou 30 s (onglet masqué). Si l'entreprise passe un jour sur un
 * serveur qui permet une connexion temps réel, on remplacera ce fichier par
 * une version qui écoute cette connexion : le reste du code ne dépend que de
 * l'interface ci-dessous, pas de la façon dont les nouvelles arrivent.
 *
 * Interface (window.LbpReleve) :
 *   demarrer({ api, connexion })  lance le relevé ; sans effet s'il tourne déjà
 *                                  (un seul relevé par onglet, même si l'écran
 *                                  et le panneau sont tous deux présents) ;
 *   on(evenement, fonction)        abonne ; renvoie la fonction de désabonnement ;
 *   maintenant()                   demande un relevé immédiat ;
 *   signalerSessionExpiree(msg)    une autre requête a reçu 401 : arrêt ;
 *   etat()                       { demarre, sessionExpiree, enLigne }.
 *
 * Événements :
 *   'releve'          la réponse complète d'un relevé (messages,
 *                      messages_modifies, lectures, non_lus, non_lus_total,
 *                      conversations_modifiees, conversations_quittees,
 *                      presence, serveur_at) ;
 *   'session-expiree' { message, connexion } : le relevé s'est arrêté ;
 *   'connexion'       { enLigne } : le serveur ne répond plus, ou de nouveau.
 */
(function () {
  "use strict";

  if (window.LbpReleve) {
    return;
  }

  var DELAI_VISIBLE = 3000;
  var DELAI_MASQUE = 30000;
  var DELAI_MAX_ERREUR = 30000;
  var DUREE_MAX_REQUETE = 15000;

  var abonnes = {};
  var options = null;
  var curseur = null;
  var serveurAt = null;
  var minuterie = null;
  var enCours = false;
  var relancer = false;
  var echecs = 0;
  var enLigne = true;
  var sessionExpiree = false;

  function emettre(nom, donnees) {
    (abonnes[nom] || []).slice().forEach(function (fonction) {
      try {
        fonction(donnees);
      } catch (erreur) {
        // Un abonné défaillant ne doit pas arrêter le relevé des autres.
        if (window.console) {
          console.error("[messagerie] abonné « " + nom + " » :", erreur);
        }
      }
    });
  }

  function delaiNormal() {
    return document.visibilityState === "hidden" ? DELAI_MASQUE : DELAI_VISIBLE;
  }

  function planifier(delai) {
    window.clearTimeout(minuterie);
    if (sessionExpiree) {
      return;
    }
    minuterie = window.setTimeout(relever, delai);
  }

  function changerEtatConnexion(valeur) {
    if (enLigne !== valeur) {
      enLigne = valeur;
      emettre("connexion", { enLigne: valeur });
    }
  }

  function adresse() {
    var parametres = [];
    if (curseur !== null) {
      // depuis=0 est un vrai curseur : seul son absence signifie « premier relevé ».
      parametres.push("depuis=" + encodeURIComponent(String(curseur)));
      if (serveurAt) {
        parametres.push("lectures_depuis=" + encodeURIComponent(serveurAt));
      }
    }
    return options.api.replace(/\/$/, "") + "/nouveautes" + (parametres.length ? "?" + parametres.join("&") : "");
  }

  function relever() {
    if (sessionExpiree) {
      return;
    }
    if (enCours) {
      relancer = true;
      return;
    }
    enCours = true;
    window.clearTimeout(minuterie);

    var controleur = typeof AbortController === "function" ? new AbortController() : null;
    var garde = controleur ? window.setTimeout(function () { controleur.abort(); }, DUREE_MAX_REQUETE) : null;
    var premier = curseur === null;

    fetch(adresse(), {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
      signal: controleur ? controleur.signal : undefined
    })
      .then(function (reponse) {
        if (reponse.status === 401) {
          return reponse.json().catch(function () { return {}; }).then(function (corps) {
            arreterSurSession(corps && corps.message);
            return null;
          });
        }
        if (!reponse.ok) {
          throw new Error("HTTP " + reponse.status);
        }
        return reponse.json();
      })
      .then(function (corps) {
        if (corps === null) {
          return;
        }
        if (!corps || corps.ok !== true) {
          throw new Error("Réponse inattendue");
        }
        echecs = 0;
        changerEtatConnexion(true);
        curseur = typeof corps.curseur === "number" ? corps.curseur : parseInt(corps.curseur, 10) || 0;
        serveurAt = corps.serveur_at || serveurAt;

        if (premier) {
          // Le premier appel ne rend que le curseur : on enchaîne aussitôt
          // pour obtenir les non-lus et la présence sans attendre 3 s.
          relancer = true;
          return;
        }
        emettre("releve", corps);
        if (corps.incomplet) {
          relancer = true;
        }
      })
      .catch(function () {
        echecs += 1;
        if (echecs >= 2) {
          changerEtatConnexion(false);
        }
      })
      .then(function () {
        window.clearTimeout(garde);
        enCours = false;
        if (sessionExpiree) {
          return;
        }
        if (relancer) {
          relancer = false;
          planifier(0);
          return;
        }
        var delai = echecs > 0
          ? Math.min(DELAI_MAX_ERREUR, delaiNormal() * Math.pow(2, Math.min(echecs, 4)))
          : delaiNormal();
        planifier(delai);
      });
  }

  function arreterSurSession(message) {
    if (sessionExpiree) {
      return;
    }
    sessionExpiree = true;
    window.clearTimeout(minuterie);
    emettre("session-expiree", {
      message: message || "Votre session a expiré. Reconnectez-vous.",
      connexion: options ? options.connexion : ""
    });
  }

  document.addEventListener("visibilitychange", function () {
    if (!options || sessionExpiree) {
      return;
    }
    if (document.visibilityState === "visible") {
      // De retour sur l'onglet : on rattrape tout de suite.
      relever();
    } else if (!enCours) {
      planifier(DELAI_MASQUE);
    }
  });

  window.addEventListener("online", function () {
    if (options && !sessionExpiree) {
      relever();
    }
  });

  window.LbpReleve = {
    demarrer: function (parametres) {
      if (options) {
        return;
      }
      options = { api: String(parametres.api || ""), connexion: String(parametres.connexion || "") };
      relever();
    },
    on: function (nom, fonction) {
      (abonnes[nom] = abonnes[nom] || []).push(fonction);
      return function () {
        abonnes[nom] = (abonnes[nom] || []).filter(function (f) { return f !== fonction; });
      };
    },
    maintenant: function () {
      if (options && !sessionExpiree) {
        relever();
      }
    },
    /* Une requête de l'écran a reçu 401 : inutile de continuer à relever. */
    signalerSessionExpiree: function (message) {
      arreterSurSession(message);
    },
    etat: function () {
      return { demarre: options !== null, sessionExpiree: sessionExpiree, enLigne: enLigne };
    }
  };
})();
