#!/usr/bin/env bash
#
# Installe la tâche planifiée des alertes de direction dans la crontab.
#
# À lancer UNE FOIS sur le serveur, depuis n'importe quel répertoire :
#   bash app/Console/installer-alertes-cron.sh
#
# Le script détecte seul le chemin du projet et l'interpréteur PHP, vérifie que
# tout fonctionne avant d'installer, et ne crée jamais de doublon : relancé, il
# remplace simplement la ligne existante.
#
# Pour retirer la tâche :
#   bash app/Console/installer-alertes-cron.sh --desinstaller

set -euo pipefail

MARQUEUR="# LBP-ALERTES-DIRECTION"
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT="$RACINE/app/Console/CronAlertesDirection.php"
JOURNAL="${LBP_LOG:-/var/log/lbp_alertes.log}"

echo "Projet   : $RACINE"

# --- Désinstallation -------------------------------------------------------
if [ "${1:-}" = "--desinstaller" ]; then
    if crontab -l 2>/dev/null | grep -q "$MARQUEUR"; then
        crontab -l 2>/dev/null | grep -v "$MARQUEUR" | crontab -
        echo "Tâche retirée de la crontab."
    else
        echo "Aucune tâche LBP n'était installée."
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
    echo "Relancez en le précisant :  PHP_BIN=/usr/bin/php8.3 bash $0" >&2
    exit 1
fi

echo "PHP      : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

if ! command -v crontab >/dev/null 2>&1; then
    echo "ERREUR : la commande crontab est absente de ce serveur." >&2
    echo "Programmez alors cette commande par le planificateur de votre hébergeur :" >&2
    echo "  $PHP_BIN $SCRIPT" >&2
    exit 1
fi

# --- Essai à blanc avant d'installer quoi que ce soit -----------------------
echo
echo "Essai du script avant installation..."
if ! SORTIE="$("$PHP_BIN" "$SCRIPT" --json 2>&1)"; then
    echo "ERREUR : le script a échoué, la tâche n'est pas installée." >&2
    echo "$SORTIE" >&2
    exit 1
fi
echo "  $SORTIE"

# --- Journal ---------------------------------------------------------------
if ! touch "$JOURNAL" 2>/dev/null; then
    JOURNAL="$RACINE/storage/logs/lbp_alertes.log"
    mkdir -p "$(dirname "$JOURNAL")"
    touch "$JOURNAL"
    echo
    echo "Note : /var/log n'est pas accessible en écriture, le journal ira dans :"
    echo "  $JOURNAL"
fi

# --- Installation ----------------------------------------------------------
# Toutes les heures de 8 h à 20 h : au-delà, le directeur n'a pas à être réveillé.
LIGNE="0 8-20 * * * $PHP_BIN $SCRIPT >> $JOURNAL 2>&1 $MARQUEUR"

{ crontab -l 2>/dev/null | grep -v "$MARQUEUR" || true; echo "$LIGNE"; } | crontab -

echo
echo "Tâche installée :"
echo "  $LIGNE"
echo
echo "Les alertes de fraude, de décisions en souffrance et d'agences non clôturées"
echo "partiront désormais toutes les heures entre 8 h et 20 h."
echo "Les écarts de caisse, eux, alertent immédiatement, sans passer par cette tâche."
echo
echo "Journal : tail -f $JOURNAL"
