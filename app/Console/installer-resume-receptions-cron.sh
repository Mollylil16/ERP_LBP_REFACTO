#!/usr/bin/env bash
#
# Installe le résumé du soir de la réception des colis dans la messagerie.
#
# À lancer UNE FOIS sur le serveur, depuis n'importe quel répertoire, avec
# l'adresse publique de l'ERP (les liens du message doivent s'ouvrir depuis
# un téléphone) :
#   LBP_URL=https://votre-domaine bash app/Console/installer-resume-receptions-cron.sh
#
# Même principe que installer-groupes-messagerie-cron.sh : chemin du projet
# et PHP détectés seuls, essai avant installation (un essai n'envoie rien),
# jamais de doublon (relancé, il remplace la ligne existante), journal hors
# de la racine web.
#
# Chaque jour à 18 h 00, heure d'Abidjan. Le serveur est à l'heure d'Abidjan
# (UTC+0 toute l'année) : la ligne de la crontab est « 0 18 * * * ». Si
# l'horloge du serveur n'est pas à UTC+0, le script s'arrête et dit quelle
# heure régler.
#
# Pour retirer la tâche :
#   bash app/Console/installer-resume-receptions-cron.sh --desinstaller

set -euo pipefail

MARQUEUR="# LBP-RESUME-RECEPTIONS"
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT="$RACINE/app/Console/ResumeReceptionsMessagerie.php"
# Hors de la racine web : en production, le dépôt EST la racine web, et tout
# fichier qu'on y pose se télécharge.
JOURNAL="${LBP_LOG:-$HOME/logs/lbp_resume_receptions.log}"

echo "Projet   : $RACINE"

# --- Désinstallation -------------------------------------------------------
if [ "${1:-}" = "--desinstaller" ]; then
    if crontab -l 2>/dev/null | grep -q "$MARQUEUR"; then
        crontab -l 2>/dev/null | grep -v "$MARQUEUR" | crontab -
        echo "Tâche retirée de la crontab."
    else
        echo "Aucune tâche du résumé des réceptions n'était installée."
    fi
    exit 0
fi

# --- Vérifications ---------------------------------------------------------
if [ ! -f "$SCRIPT" ]; then
    echo "ERREUR : script introuvable à l'emplacement attendu :" >&2
    echo "  $SCRIPT" >&2
    exit 1
fi

URL="${LBP_URL:-}"
URL="${URL%/}"
case "$URL" in
    http://*|https://*) ;;
    *)
        echo "ERREUR : précisez l'adresse publique de l'ERP :" >&2
        echo "  LBP_URL=https://votre-domaine bash $0" >&2
        exit 1
        ;;
esac
# Elle entre telle quelle dans la crontab : rien d'autre qu'une adresse.
if printf '%s' "$URL" | grep -q '[^A-Za-z0-9:/._~%-]'; then
    echo "ERREUR : adresse invalide (une adresse de site, sans espace ni paramètre) : $URL" >&2
    exit 1
fi

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [ -z "$PHP_BIN" ]; then
    echo "ERREUR : aucun interpréteur PHP trouvé dans le PATH." >&2
    echo "Relancez en le précisant :  PHP_BIN=/usr/local/bin/php LBP_URL=$URL bash $0" >&2
    exit 1
fi

echo "PHP      : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"
echo "Adresse  : $URL"

# 18 h à Abidjan = 18 h UTC. La crontab suit l'horloge du serveur.
DECALAGE="$(date +%z)"
echo "Horloge  : $(date '+%Y-%m-%d %H:%M %Z') (décalage $DECALAGE)"
if [ "$DECALAGE" != "+0000" ]; then
    echo "ERREUR : le serveur n'est pas à l'heure d'Abidjan (UTC+0) : « 0 18 * * * » n'y tomberait pas à 18 h." >&2
    echo "Programmez la commande à l'heure locale qui correspond à 18 h UTC, avec le planificateur de l'hébergeur :" >&2
    echo "  $PHP_BIN $SCRIPT --url=$URL >> $JOURNAL 2>&1" >&2
    exit 1
fi

if ! command -v crontab >/dev/null 2>&1; then
    echo "ERREUR : la commande crontab est absente de ce serveur." >&2
    echo "Programmez alors cette commande par le planificateur de votre hébergeur, chaque jour à 18 h 00 :" >&2
    echo "  0 18 * * * $PHP_BIN $SCRIPT --url=$URL >> $JOURNAL 2>&1" >&2
    exit 1
fi

# --- Essai avant d'installer quoi que ce soit -------------------------------
# --essai calcule les résumés du jour sans rien envoyer ni réserver.
echo
echo "Essai du script avant installation (rien n'est envoyé)..."
if ! SORTIE="$("$PHP_BIN" "$SCRIPT" --essai --json --url="$URL" 2>&1)"; then
    echo "ERREUR : le script a échoué, la tâche n'est pas installée." >&2
    echo "$SORTIE" >&2
    exit 1
fi
echo "  $SORTIE"

# --- Journal ---------------------------------------------------------------
mkdir -p "$(dirname "$JOURNAL")"
touch "$JOURNAL"

# --- Installation ----------------------------------------------------------
LIGNE="0 18 * * * $PHP_BIN $SCRIPT --url=$URL >> $JOURNAL 2>&1 $MARQUEUR"

{ crontab -l 2>/dev/null | grep -v "$MARQUEUR" || true; echo "$LIGNE"; } | crontab -

echo
echo "Tâche installée :"
echo "  $LIGNE"
echo
echo "Journal : tail -f $JOURNAL"
