#!/usr/bin/env bash
#
# Enchaine, dans le bon ordre, la remise a l'endroit des dates d'encaissement.
#
#   bash app/Console/recaler-encaissements.sh [--depuis=AAAA-MM-JJ]
#
# Le correctif de code doit deja etre en ligne quand on lance ce script :
# sinon l'application continue d'ecrire des lignes decalees pendant qu'on
# repare les anciennes.
#
# Ce que le script enchaine
# -------------------------
#   1. une photo de l'etat actuel, avant toute modification
#   2. la sauvegarde des deux tables touchees
#   3. l'analyse, qui n'ecrit rien et d'ou sont lues les deux bornes
#   4. votre confirmation, en toutes lettres
#   5. la correction des dates
#   6. le recalcul des points de caisse figes
#   7. le rapport final, en texte et en document imprimable
#
# Tout s'arrete a la premiere erreur. Avant l'etape 5, rien n'a ete modifie :
# interrompre le script a ce stade ne laisse aucune trace.

set -euo pipefail

DEPUIS="2026-09-01"

for arg in "$@"; do
    case "$arg" in
        --depuis=*) DEPUIS="${arg#*=}" ;;
        *) echo "Option inconnue : $arg" >&2; exit 1 ;;
    esac
done

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RAPPORTS="${HOME}/rapports"
HORODATAGE="$(date +%Y%m%d-%H%M%S)"

PHP="${PHP_BIN:-php}"

titre() {
    echo
    echo "=============================================================================="
    echo "$1"
    echo "=============================================================================="
}

# --- Le dossier des rapports, hors de portee du serveur web -----------------
# Le .htaccess sert directement tout fichier existant : un rapport depose sous
# public_html serait telechargeable par n'importe qui, sans mot de passe.
mkdir -p "$RAPPORTS"
chmod 700 "$RAPPORTS"

# --- Garde-fou horaire ------------------------------------------------------
# Passe 15h, un reglement pris a l'instant porte legitimement une heure tardive
# et repond au meme critere que les lignes a recaler : la borne l'engloberait.
HEURE="$(TZ=Africa/Abidjan date +%H)"
JOUR="$(TZ=Africa/Abidjan date +%u)"

if [ "$HEURE" -ge 15 ] && [ "$JOUR" -lt 6 ]; then
    echo "Il est ${HEURE}h a Abidjan, un jour ouvre." >&2
    echo >&2
    echo "Un reglement pris cet apres-midi porte legitimement une heure tardive." >&2
    echo "La borne calculee par l'analyse l'engloberait et le reculerait d'un jour." >&2
    echo >&2
    echo "Reprendre demain matin avant 15h, ou un dimanche." >&2
    echo "Si la correction ne peut pas attendre, relever d'abord :" >&2
    echo "  SELECT MAX(id) FROM lbp_paiements;" >&2
    echo "  SELECT MAX(id) FROM lbp_factures;" >&2
    echo "puis appeler CorrigerDatesEncaissements.php a la main avec ces bornes." >&2
    exit 1
fi

cd "$RACINE"

# --- 1. Photo avant ---------------------------------------------------------
titre "1/7  Etat actuel, avant toute modification"

AVANT="${RAPPORTS}/avant-${HORODATAGE}.txt"
"$PHP" app/Console/AuditEncaissements.php --depuis="$DEPUIS" --fichier="$AVANT"
echo "Conserve : $AVANT"

# --- 2. Sauvegarde ----------------------------------------------------------
titre "2/7  Sauvegarde des deux tables modifiees"

LECTURE_CONFIG='$c = require "config/database.php"; echo $c["dbname"], "|", $c["username"], "|", $c["host"];'
IFS='|' read -r BASE UTILISATEUR HOTE <<< "$("$PHP" -r "$LECTURE_CONFIG")"

SAUVEGARDE="${RAPPORTS}/sauvegarde-${HORODATAGE}.sql"

echo "Base : ${BASE} sur ${HOTE}, utilisateur ${UTILISATEUR}"
echo

# Le mot de passe est lu dans config/database.php, celui-la meme dont le site se
# sert, et depose dans un fichier d'options lisible par vous seul, efface a la
# sortie du script quoi qu'il arrive. On ne le passe jamais sur la ligne de
# commande : il serait visible de tout utilisateur du serveur dans la liste des
# processus.
IDENTIFIANTS="$(mktemp "${RAPPORTS}/.mysql-XXXXXX")"
chmod 600 "$IDENTIFIANTS"
trap 'rm -f "$IDENTIFIANTS"' EXIT

ECRITURE_OPTIONS='$c = require "config/database.php";
$q = static fn ($v) => "\"" . addcslashes((string) $v, "\"\\") . "\"";
file_put_contents($argv[1], "[client]\nuser=" . $q($c["username"]) . "\npassword=" . $q($c["password"])
    . "\nhost=" . $q($c["host"]) . "\nport=" . (int) ($c["port"] ?? 3306) . "\n");'
"$PHP" -r "$ECRITURE_OPTIONS" "$IDENTIFIANTS"

# --no-tablespaces : sans lui, mysqldump reclame le privilege PROCESS, qu'un
# compte d'hebergement mutualise n'a presque jamais, et s'arrete en erreur.
# Les tablespaces ne servent pas a restaurer deux tables.
mysqldump --defaults-extra-file="$IDENTIFIANTS" --no-tablespaces "$BASE" lbp_paiements lbp_factures > "$SAUVEGARDE"
chmod 600 "$SAUVEGARDE"

echo "Sauvegarde : $SAUVEGARDE ($(wc -c < "$SAUVEGARDE") octets)"
echo
echo "Pour revenir en arriere en cas de besoin (mot de passe : celui de config/database.php) :"
echo "  mysql -h ${HOTE} -u ${UTILISATEUR} -p ${BASE} < ${SAUVEGARDE}"

# --- 3. Analyse -------------------------------------------------------------
titre "3/7  Ce qui serait corrige - aucune ecriture"

ANALYSE="${RAPPORTS}/analyse-${HORODATAGE}.txt"
"$PHP" app/Console/CorrigerDatesEncaissements.php --depuis="$DEPUIS" | tee "$ANALYSE"

# sed plutot que grep -oP : l'option -P exige que grep soit compile avec PCRE,
# ce qui n'est pas garanti sur un hebergement mutualise.
borne_depuis() {
    sed -n "s/^Identifiant de $1 le plus haut concerne : \([0-9][0-9]*\)$/\1/p" "$ANALYSE" | tail -1
}

BORNE_PAIEMENT="$(borne_depuis paiement || true)"
BORNE_FACTURE="$(borne_depuis facture || true)"

if [ -z "$BORNE_PAIEMENT" ] && [ -z "$BORNE_FACTURE" ]; then
    titre "Rien a corriger"
    echo "Aucune ligne decalee sur la periode depuis le ${DEPUIS}."
    echo "La sauvegarde reste disponible : $SAUVEGARDE"
    exit 0
fi

BORNE_PAIEMENT="${BORNE_PAIEMENT:-0}"
BORNE_FACTURE="${BORNE_FACTURE:-0}"

# --- 4. Confirmation --------------------------------------------------------
titre "4/7  Confirmation"

echo "Relisez la section 2 ci-dessus : elle dit ce que chaque journee perd et gagne."
echo
echo "Bornes retenues : paiements <= ${BORNE_PAIEMENT}, factures <= ${BORNE_FACTURE}"
echo "Au-dela de ces identifiants, rien ne sera touche."
echo
printf "Taper OUI en majuscules pour appliquer, autre chose pour arreter : "
read -r REPONSE

if [ "$REPONSE" != "OUI" ]; then
    titre "Arret demande"
    echo "Aucune modification n'a ete faite."
    echo "L'analyse reste consultable : $ANALYSE"
    exit 0
fi

# --- 5. Correction ----------------------------------------------------------
titre "5/7  Correction des dates"

"$PHP" app/Console/CorrigerDatesEncaissements.php \
    --appliquer \
    --depuis="$DEPUIS" \
    --id-paiement-max="$BORNE_PAIEMENT" \
    --id-facture-max="$BORNE_FACTURE"

# --- 6. Points de caisse ----------------------------------------------------
titre "6/7  Recalcul des points de caisse figes"

echo "D'abord sans ecrire, pour voir ce qui change :"
"$PHP" app/Console/RecalculerPointsCaisse.php --depuis="$DEPUIS"

echo
printf "Appliquer ce recalcul ? Taper OUI : "
read -r REPONSE_POINTS

if [ "$REPONSE_POINTS" = "OUI" ]; then
    "$PHP" app/Console/RecalculerPointsCaisse.php --depuis="$DEPUIS" --appliquer
else
    echo "Recalcul non applique. Les dates, elles, sont bien corrigees."
    echo "Le relancer plus tard :"
    echo "  $PHP app/Console/RecalculerPointsCaisse.php --depuis=${DEPUIS} --appliquer"
fi

# --- 7. Rapport final -------------------------------------------------------
titre "7/7  Rapport final"

APRES="${RAPPORTS}/audit-${HORODATAGE}"

"$PHP" app/Console/AuditEncaissements.php --depuis="$DEPUIS" --fichier="${APRES}.txt"
"$PHP" app/Console/AuditEncaissements.php --depuis="$DEPUIS" --json --fichier="${APRES}.json"
"$PHP" app/Console/RapportEncaissementsHtml.php "${APRES}.json" "${APRES}.html"

chmod 600 "${APRES}".* "$AVANT" "$ANALYSE"

titre "Termine"
echo "Avant        : $AVANT"
echo "Analyse      : $ANALYSE"
echo "Sauvegarde   : $SAUVEGARDE"
echo "Apres        : ${APRES}.txt"
echo "Document     : ${APRES}.html"
echo
echo "Recuperer le document sur votre poste :"
echo "  scp UTILISATEUR@SERVEUR:${APRES}.html ."
echo
echo "L'ouvrir dans un navigateur, puis Imprimer et choisir"
echo "« Enregistrer au format PDF »."
