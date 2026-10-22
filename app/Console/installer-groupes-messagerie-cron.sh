#!/usr/bin/env bash
#
# Installe la passe nocturne des groupes automatiques de la messagerie.
#
# À lancer UNE FOIS sur le serveur, depuis n'importe quel répertoire :
#   bash app/Console/installer-groupes-messagerie-cron.sh
#
# Même principe que installer-alertes-cron.sh : chemin du projet et PHP
# détectés seuls, essai avant installation, jamais de doublon (relancé, il
# remplace la ligne existante).
#
# Pour retirer la tâche :
#   bash app/Console/installer-groupes-messagerie-cron.sh --desinstaller

set -euo pipefail

MARQUEUR="# LBP-GROUPES-MESSAGERIE"
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT="$RACINE/app/Console/SynchroniserGroupesMessagerie.php"
# Hors de la racine web : en production, le dépôt EST la racine web, et tout
# fichier qu'on y pose se télécharge.
JOURNAL="${LBP_LOG:-$HOME/logs/lbp_groupes_messagerie.log}"

echo "Projet   : $RACINE"

# --- Désinstallation -------------------------------------------------------
if [ "${1:-}" = "--desinstaller" ]; then
    if crontab -l 2>/dev/null | grep -q "$MARQUEUR"; then
        crontab -l 2>/dev/null | grep -v "$MARQUEUR" | crontab -
        echo "Tâche retirée de la crontab."
    else
        echo "Aucune tâche des groupes de la messagerie n'était installée."
    fi
    exit 0
fi

# --- Vérifications ---------------------------------------------------------
if [ ! -f "$SCRIPT" ]; then
    echo "ERREUR : script introuvable à l'emplacement attendu :" >&2
    echo "  $SCRIPT" >&2
    exit 1
fi

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [ -z "$PHP_BIN" ]; then
    echo "ERREUR : aucun interpréteur PHP trouvé dans le PATH." >&2
    echo "Relancez en le précisant :  PHP_BIN=/usr/local/bin/php bash $0" >&2
    exit 1
fi

echo "PHP      : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

if ! command -v crontab >/dev/null 2>&1; then
    echo "ERREUR : la commande crontab est absente de ce serveur." >&2
    echo "Programmez alors cette commande par le planificateur de votre hébergeur :" >&2
    echo "  30 2 * * * $PHP_BIN $SCRIPT >> $JOURNAL 2>&1" >&2
    exit 1
fi

# --- Essai avant d'installer quoi que ce soit -------------------------------
# La passe est idempotente : l'essai fait déjà le travail de la nuit.
echo
echo "Essai du script avant installation..."
if ! SORTIE="$("$PHP_BIN" "$SCRIPT" --json 2>&1)"; then
    echo "ERREUR : le script a échoué, la tâche n'est pas installée." >&2
    echo "$SORTIE" >&2
    exit 1
fi
echo "  $SORTIE"

# --- Journal ---------------------------------------------------------------
mkdir -p "$(dirname "$JOURNAL")"
touch "$JOURNAL"

# --- Installation ----------------------------------------------------------
# Chaque nuit à 2 h 30 : personne n'écrit, et les comptes modifiés dans la
# journée sont remis en ordre avant l'ouverture des agences.
LIGNE="30 2 * * * $PHP_BIN $SCRIPT >> $JOURNAL 2>&1 $MARQUEUR"

{ crontab -l 2>/dev/null | grep -v "$MARQUEUR" || true; echo "$LIGNE"; } | crontab -

echo
echo "Tâche installée :"
echo "  $LIGNE"
echo
echo "Journal : tail -f $JOURNAL"
