<?php

/**
 * Met en page l'audit des encaissements pour l'impression et le PDF.
 *
 * Usage :
 *   php app/Console/AuditEncaissements.php --depuis=2026-09-09 --json --fichier=~/rapports/audit.json
 *   php app/Console/RapportEncaissementsHtml.php ~/rapports/audit.json ~/rapports/audit.html
 *
 * Puis ouvrir le fichier HTML dans un navigateur et faire Imprimer, en
 * choisissant « Enregistrer au format PDF ». C'est la facon dont l'ERP produit
 * deja tous ses documents imprimes (voir views/finance/*_pdf.php) : aucune
 * bibliotheque PDF n'est installee, et le rendu du navigateur donne un
 * resultat plus fidele que ce qu'en ferait une conversion serveur.
 *
 * Ce script ne touche pas a la base
 * ---------------------------------
 * Il lit un fichier JSON et ecrit un fichier HTML. Rien d'autre. La lecture des
 * donnees est le travail de AuditEncaissements.php, qui n'execute que des
 * SELECT ; separer les deux garantit qu'une mise en page, meme mal ecrite, ne
 * pourra jamais rien modifier sur la production.
 *
 * Ou deposer le fichier produit
 * -----------------------------
 * Jamais sous public_html. Le .htaccess du projet sert directement tout fichier
 * existant, donc un rapport depose la serait telechargeable par n'importe qui,
 * sans mot de passe. Un dossier personnel en mode 700 convient :
 *   mkdir -p ~/rapports && chmod 700 ~/rapports
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script ne s'execute qu'en ligne de commande.\n");
}

$arguments = array_values(array_filter(
    array_slice($argv ?? [], 1),
    static fn (string $a): bool => !str_starts_with($a, '--')
));

if ($arguments === []) {
    exit(
        "Usage : php app/Console/RapportEncaissementsHtml.php <audit.json> [sortie.html]\n\n"
        . "Le fichier JSON se produit avec :\n"
        . "  php app/Console/AuditEncaissements.php --depuis=2026-09-09 --json --fichier=audit.json\n"
    );
}

$source = $arguments[0];
$destination = $arguments[1] ?? preg_replace('/\.json$/i', '', $source) . '.html';

if (!is_file($source)) {
    exit("Fichier introuvable : {$source}\n");
}

$donnees = json_decode((string) file_get_contents($source), true);

if (!is_array($donnees) || !is_array($donnees['audit'] ?? null)) {
    exit(
        "Le fichier ne ressemble pas a une sortie d'audit.\n"
        . "Attendu : la sortie de AuditEncaissements.php --json\n"
    );
}

$audit = $donnees['audit'];
$depuis = (string) ($donnees['depuis'] ?? '');

// ---------------------------------------------------------------------------
// Mise en forme
// ---------------------------------------------------------------------------

/** Echappe une valeur avant insertion dans le document. */
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/** Montant en francs, sans decimale : 1 309 600 */
$xof = static fn (mixed $v): string => number_format((float) $v, 0, ',', ' ');

/** Date seule, tiret cadratin si absente ou nulle. */
$jour = static function (mixed $v): string {
    $t = strtotime((string) $v);

    return ($t && date('Y', $t) > '1970') ? date('d/m/Y', $t) : '—';
};

/** Date et heure. */
$instant = static function (mixed $v): string {
    $t = strtotime((string) $v);

    return ($t && date('Y', $t) > '1970') ? date('d/m/Y H:i', $t) : '—';
};

/** Valeur d'une colonne, avec repli. */
$val = static fn (array $r, string $k, mixed $defaut = ''): mixed => $r[$k] ?? $defaut;

/** Section du rapport, garantie sous forme de liste. */
$liste = static function (mixed $v): array {
    return is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
};

$parAgence = $liste($audit['par_agence'] ?? []);
$parJour = $liste($audit['par_jour'] ?? []);
$parEncaisseur = $liste($audit['par_encaisseur'] ?? []);
$tardifs = $liste($audit['reglements_tardifs'] ?? []);
$desync = $liste($audit['desync_factures'] ?? []);
$anomalies = is_array($audit['anomalies'] ?? null) ? $audit['anomalies'] : [];

// ---------------------------------------------------------------------------
// Totaux
// ---------------------------------------------------------------------------

$totalFacture = 0.0;
$totalEncaisse = 0.0;
$totalReste = 0.0;
$nbActives = 0;
$nbDormantes = 0;

foreach ($parAgence as $a) {
    $facture = (float) $val($a, 'total_facture', 0);
    $encaisse = (float) $val($a, 'encaisse_periode', 0);

    $totalFacture += $facture;
    $totalEncaisse += $encaisse;
    $totalReste += (float) $val($a, 'reste_a_recouvrer', 0);

    // Une agence qui n'a ni saisi de colis ni etabli de facture sur la periode
    // est dormante : la distinguer evite de la lire comme une agence en echec.
    if ((int) $val($a, 'nb_colis', 0) > 0 || $facture > 0) {
        $nbActives++;
    } else {
        $nbDormantes++;
    }
}

$taux = $totalFacture > 0 ? ($totalEncaisse / $totalFacture) * 100 : 0.0;

/*
 * Un reglement est « decale par le logiciel » quand il porte exactement un jour
 * d'ecart avec sa facture ET que la facture a ete etablie apres 15h : c'est la
 * signature du report que PaiementRepository appliquait avant le 13/09/2026.
 * Un vrai retard de client, lui, n'a aucune raison de tomber toujours sur un
 * jour pile ni de ne concerner que des factures de fin d'apres-midi.
 */
$estDecalageLogiciel = static function (array $t): bool {
    if ((int) ($t['jours_ecart'] ?? 0) !== 1) {
        return false;
    }

    $heure = (string) ($t['heure_emission'] ?? '');

    return $heure !== '' && $heure >= '15:00:00';
};

$montantTardifs = 0.0;
$nbDecalesLogiciel = 0;
$montantDecalesLogiciel = 0.0;

foreach ($tardifs as $t) {
    $montant = (float) $val($t, 'montant', 0);
    $montantTardifs += $montant;

    if ($estDecalageLogiciel($t)) {
        $nbDecalesLogiciel++;
        $montantDecalesLogiciel += $montant;
    }
}

/*
 * Certaines sections d'anomalies sont des compteurs : une ligne unique portant
 * nb et montant, presente meme quand nb vaut zero. Les compter comme des
 * lignes ferait etat d'anomalies inexistantes.
 */
$estCompteur = static function (array $lignes): bool {
    if (count($lignes) !== 1) {
        return false;
    }

    $cles = array_keys($lignes[0]);
    sort($cles);

    return $cles === ['montant', 'nb'] || $cles === ['nb'];
};

/*
 * Deux sections que l'audit range sous « anomalies » n'en sont pas :
 *
 *   modes_non_reconnus est en realite la repartition de TOUS les modes de
 *   reglement, sans filtre - « especes » y figure donc systematiquement ;
 *
 *   colis_apres_15h recense les colis bascules au lendemain, ce qui est la
 *   regle de l'entreprise et non un defaut.
 *
 * Les compter comme des anomalies ferait dire au document l'inverse de ce
 * qu'il constate. Elles sont presentees a part, pour information.
 */
const SECTIONS_INFORMATIVES = ['modes_non_reconnus', 'colis_apres_15h'];

$nbAnomalies = 0;
foreach ($anomalies as $cle => $lignes) {
    if (in_array($cle, SECTIONS_INFORMATIVES, true)) {
        continue;
    }

    $lignes = $liste($lignes);

    if ($lignes === []) {
        continue;
    }

    $nbAnomalies += $estCompteur($lignes) ? (int) ($lignes[0]['nb'] ?? 0) : count($lignes);
}

// Journees dont le point de caisse n'a jamais ete soumis : de l'argent encaisse
// sans qu'aucun comptage physique ne soit venu le confirmer.
$joursSansPoint = [];
$montantSansPoint = 0.0;

foreach ($parJour as $j) {
    $statut = strtolower(trim((string) $val($j, 'statut', '')));

    if ($statut === '' || $statut === 'aucun') {
        $joursSansPoint[] = $j;
        $montantSansPoint += (float) $val($j, 'paiements_reels_xof', 0);
    }
}

$titre = 'Audit des encaissements depuis le ' . $jour($depuis);
$editeLe = date('d/m/Y H:i');

ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= $e($titre) ?></title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #0f172a; margin: 0; background: #ffffff; }

    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f172a; padding-bottom: 10px; margin-bottom: 14px; }
    .logo { font-size: 19px; font-weight: 800; letter-spacing: -0.4px; }
    .sub-logo { font-size: 10px; color: #64748b; margin-top: 2px; }
    .title { text-align: right; }
    .title-main { font-size: 14px; font-weight: 800; }
    .title-sub { font-size: 11px; color: #334155; margin-top: 3px; font-weight: 600; }

    .meta-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .meta-label { font-size: 8px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.4px; }
    .meta-value { font-size: 11px; font-weight: 700; margin-top: 2px; }

    h2.section { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0; page-break-after: avoid; }
    h3.sous { font-size: 11px; font-weight: 800; color: #334155; margin: 12px 0 4px; page-break-after: avoid; }
    p.chapo { font-size: 9px; color: #475569; margin: 0 0 8px; line-height: 1.55; }

    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .kpi-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; }
    .kpi-title { font-size: 8px; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.3px; }
    .kpi-value { font-size: 13px; font-weight: 800; margin-top: 3px; font-variant-numeric: tabular-nums; }
    .kpi-value.pos { color: #15803d; }
    .kpi-value.neg { color: #b91c1c; }
    .kpi-note { font-size: 8px; color: #64748b; margin-top: 2px; }

    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th { background: #0f172a; color: #ffffff; padding: 6px; font-weight: 700; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.2px; }
    td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
    tbody tr:nth-child(even) { background-color: #f8fafc; }
    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .muted { color: #64748b; font-size: 8px; }
    .total-row td { font-weight: 800; background: #e2e8f0; border-top: 2px solid #94a3b8; border-bottom: none; }

    .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-weight: 700; font-size: 8px; text-transform: uppercase; white-space: nowrap; }
    .badge-ok { background: #dcfce7; color: #15803d; }
    .badge-attente { background: #fef3c7; color: #b45309; }
    .badge-alerte { background: #fee2e2; color: #b91c1c; }
    .badge-neutre { background: #f1f5f9; color: #475569; }

    .encadre { border-left: 3px solid #f59e0b; background: #fff7ed; padding: 8px 10px; margin: 10px 0; font-size: 9px; color: #92400e; line-height: 1.55; }
    .encadre strong { color: #78350f; }
    .encadre.grave { border-left-color: #b91c1c; background: #fef2f2; color: #991b1b; }
    .encadre.grave strong { color: #7f1d1d; }
    .encadre.calme { border-left-color: #0369a1; background: #f0f9ff; color: #075985; }
    .encadre.calme strong { color: #0c4a6e; }

    .empty { padding: 8px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 5px; color: #64748b; font-size: 9px; text-align: center; }
    .bloc { page-break-inside: avoid; }

    .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 28px; page-break-inside: avoid; }
    .sig-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; height: 85px; }
    .sig-title { font-weight: 700; font-size: 9px; text-transform: uppercase; color: #475569; }

    .footer-note { margin-top: 16px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; line-height: 1.55; }

    .barre { text-align: right; margin-bottom: 12px; }
    .barre button { padding: 8px 16px; background: #0f172a; color: #ffffff; border: none; border-radius: 4px; font-weight: 700; cursor: pointer; font-size: 12px; }

    @media print {
        body { margin: 0; }
        .no-print { display: none; }
    }
</style>
</head>
<body>

<div class="no-print barre">
    <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
</div>

<div class="header">
    <div>
        <div class="logo">LBP</div>
        <div class="sub-logo">La Belle Poste — Direction Générale</div>
    </div>
    <div class="title">
        <div class="title-main">Audit des encaissements</div>
        <div class="title-sub">Depuis le <?= $e($jour($depuis)) ?></div>
    </div>
</div>

<div class="meta-box">
    <div>
        <div class="meta-label">Période</div>
        <div class="meta-value">Du <?= $e($jour($depuis)) ?></div>
    </div>
    <div>
        <div class="meta-label">Périmètre</div>
        <div class="meta-value"><?= $e($nbActives) ?> active(s)<?= $nbDormantes > 0 ? ', ' . $e($nbDormantes) . ' dormante(s)' : '' ?></div>
    </div>
    <div>
        <div class="meta-label">Édité le</div>
        <div class="meta-value"><?= $e($editeLe) ?></div>
    </div>
    <div>
        <div class="meta-label">Méthode</div>
        <div class="meta-value">Lecture seule</div>
    </div>
</div>

<h2 class="section">Synthèse</h2>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-title">Facturé</div>
        <div class="kpi-value"><?= $e($xof($totalFacture)) ?></div>
        <div class="kpi-note">XOF sur la période</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Encaissé</div>
        <div class="kpi-value pos"><?= $e($xof($totalEncaisse)) ?></div>
        <div class="kpi-note">XOF effectivement reçus</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Reste à recouvrer</div>
        <div class="kpi-value <?= $totalReste > 0 ? 'neg' : '' ?>"><?= $e($xof($totalReste)) ?></div>
        <div class="kpi-note">XOF encore dus</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-title">Taux de recouvrement</div>
        <div class="kpi-value <?= $taux >= 80 ? 'pos' : 'neg' ?>"><?= $e(number_format($taux, 1, ',', ' ')) ?> %</div>
        <div class="kpi-note"><?= $e(count($parEncaisseur)) ?> personne(s) ont encaissé</div>
    </div>
</div>

<?php if ($joursSansPoint !== []): ?>
    <div class="encadre grave bloc">
        <strong><?= $e(count($joursSansPoint)) ?> journée(s) sans point de caisse soumis</strong>,
        pour <?= $e($xof($montantSansPoint)) ?> XOF encaissés.
        Cet argent est entré en caisse sans qu'aucun comptage physique ne vienne le confirmer :
        rien ne permet aujourd'hui de rapprocher le solde théorique de ce qui se trouve
        réellement dans le tiroir. Ces journées sont à régulariser en priorité.
    </div>
<?php endif; ?>

<?php if ($nbDecalesLogiciel > 0): ?>
    <div class="encadre bloc">
        <strong><?= $e($nbDecalesLogiciel) ?> règlement(s) déplacés par le logiciel</strong>,
        pour <?= $e($xof($montantDecalesLogiciel)) ?> XOF.
        Jusqu'au 13/09/2026, toute facture établie ou réglée après 15h était datée du
        lendemain. Ces clients ont payé au guichet le jour même ; c'est l'enregistrement
        qui a basculé, pas l'argent. Le report est corrigé, et ces dates sont à remettre
        à leur jour réel.
    </div>
<?php endif; ?>

<h2 class="section">Situation par agence</h2>

<?php if ($parAgence === []): ?>
    <div class="empty">Aucune agence sur la période.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Agence</th>
                <th class="num">Colis</th>
                <th class="num">Factures</th>
                <th class="num">Facturé</th>
                <th class="num">Encaissé</th>
                <th class="num">Reste</th>
                <th class="num">Taux</th>
                <th class="num">Pers.</th>
                <th>Dernière activité</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($parAgence as $a):
            $facture = (float) $val($a, 'total_facture', 0);
            $encaisse = (float) $val($a, 'encaisse_periode', 0);
            $colis = (int) $val($a, 'nb_colis', 0);
            $tauxAgence = $facture > 0 ? ($encaisse / $facture) * 100 : 0.0;
            $dormante = $colis === 0 && $facture <= 0;
            ?>
            <tr>
                <td>
                    <strong><?= $e($val($a, 'agence', 'Agence inconnue')) ?></strong>
                    <?php if ($dormante): ?>
                        <br><span class="badge badge-neutre">Dormante</span>
                    <?php endif; ?>
                </td>
                <td class="num"><?= $e($colis) ?></td>
                <td class="num"><?= $e((int) $val($a, 'nb_factures', 0)) ?></td>
                <td class="num"><?= $e($xof($facture)) ?></td>
                <td class="num"><?= $e($xof($encaisse)) ?></td>
                <td class="num"><?= $e($xof($val($a, 'reste_a_recouvrer', 0))) ?></td>
                <td class="num">
                    <?php if ($facture > 0): ?>
                        <span class="badge <?= $tauxAgence >= 80 ? 'badge-ok' : ($tauxAgence > 0 ? 'badge-attente' : 'badge-alerte') ?>"><?= $e(number_format($tauxAgence, 0, ',', ' ')) ?> %</span>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="num"><?= $e((int) $val($a, 'nb_encaisseurs', 0)) ?></td>
                <td class="muted"><?= $e($instant($val($a, 'derniere_activite', ''))) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row">
                <td>Total</td>
                <td class="num"></td>
                <td class="num"></td>
                <td class="num"><?= $e($xof($totalFacture)) ?></td>
                <td class="num"><?= $e($xof($totalEncaisse)) ?></td>
                <td class="num"><?= $e($xof($totalReste)) ?></td>
                <td class="num"><?= $e(number_format($taux, 0, ',', ' ')) ?> %</td>
                <td class="num"></td>
                <td></td>
            </tr>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="section">Journée par journée</h2>
<p class="chapo">
    Les règlements reçus sont ce que la base a enregistré pour la journée. Le comptage
    physique est ce que la caissière a déclaré avoir dans son tiroir. L'écart est la
    différence entre les deux : c'est la seule colonne qui demande une explication.
    Une journée sans point soumis n'a aucun comptage à opposer au solde théorique.
</p>

<?php if ($parJour === []): ?>
    <div class="empty">Aucun mouvement sur la période.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Jour</th>
                <th>Agence</th>
                <th class="num">Règlements</th>
                <th class="num">Reçu</th>
                <th class="num">Point enregistré</th>
                <th class="num">Physique</th>
                <th class="num">Écart caisse</th>
                <th>Point de caisse</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($parJour as $j):
            $statut = strtolower(trim((string) $val($j, 'statut', '')));
            $sansPoint = $statut === '' || $statut === 'aucun';
            $ecart = (float) $val($j, 'ecart_caisse', 0);
            $physique = $val($j, 'solde_physique_declare', null);
            $explication = trim((string) $val($j, 'explication_ecart', ''));
            $ecartPoint = (float) $val($j, 'ecart_point', 0);
            ?>
            <tr>
                <td class="num"><?= $e($jour($val($j, 'jour', ''))) ?></td>
                <td><?= $e($val($j, 'agence', '—')) ?></td>
                <td class="num"><?= $e((int) $val($j, 'nb_paiements', 0)) ?></td>
                <td class="num"><?= $e($xof($val($j, 'paiements_reels_xof', 0))) ?></td>
                <td class="num">
                    <?php if ($sansPoint): ?>
                        <span class="muted">—</span>
                    <?php else: ?>
                        <?= $e($xof($val($j, 'point_enregistre', 0))) ?>
                        <?php if (abs($ecartPoint) >= 0.5): ?>
                            <div class="muted">écart <?= $e($xof($ecartPoint)) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="num">
                    <?= $sansPoint || $physique === null ? '<span class="muted">—</span>' : $e($xof($physique)) ?>
                </td>
                <td class="num">
                    <?php if ($sansPoint): ?>
                        <span class="muted">—</span>
                    <?php else: ?>
                        <span class="badge <?= abs($ecart) < 0.5 ? 'badge-ok' : 'badge-alerte' ?>"><?= $e($xof($ecart)) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($sansPoint): ?>
                        <span class="badge badge-alerte">Non soumis</span>
                    <?php else: ?>
                        <span class="badge badge-ok"><?= $e($val($j, 'statut', '')) ?></span>
                    <?php endif; ?>
                    <?php if ($explication !== ''): ?>
                        <div class="muted"><?= $e($explication) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="section">Qui a encaissé</h2>
<p class="chapo">
    Dans les agences sans caissière dédiée, l'agent qui établit la facture est aussi celui
    qui prend l'argent. Cette colonne sert à savoir, pour une journée donnée, à qui
    demander le comptage.
</p>

<?php if ($parEncaisseur === []): ?>
    <div class="empty">Aucun encaissement sur la période.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Personne</th>
                <th>Agence</th>
                <th class="num">Règlements</th>
                <th class="num">Montant</th>
                <th>Premier</th>
                <th>Dernier</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($parEncaisseur as $p): ?>
            <tr>
                <td><strong><?= $e($val($p, 'encaisseur', 'Non identifié')) ?></strong></td>
                <td><?= $e($val($p, 'agence', '—')) ?></td>
                <td class="num"><?= $e((int) $val($p, 'nb_paiements', 0)) ?></td>
                <td class="num"><?= $e($xof($val($p, 'total_xof', 0))) ?></td>
                <td class="muted"><?= $e($jour($val($p, 'premier_jour', ''))) ?></td>
                <td class="muted"><?= $e($jour($val($p, 'dernier_jour', ''))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="section">Règlements portés à une autre journée</h2>
<p class="chapo">
    Ces règlements ne portent pas la même date que leur facture. Lorsque l'écart est
    d'exactement un jour et que la facture a été établie après 15h, il ne s'agit pas d'un
    client qui a tardé à payer : c'est le logiciel qui datait du lendemain tout ce qui
    était enregistré passé 15h. Ce report est corrigé depuis le 13/09/2026.
</p>

<?php if ($tardifs === []): ?>
    <div class="empty">Aucun règlement décalé sur la période.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Facture</th>
                <th>Agence</th>
                <th class="num">Montant</th>
                <th>Établie le</th>
                <th class="num">Heure</th>
                <th>Réglée le</th>
                <th class="num">Écart</th>
                <th>Lecture</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tardifs as $t):
            $logiciel = $estDecalageLogiciel($t);
            $heure = (string) $val($t, 'heure_emission', '');
            ?>
            <tr>
                <td><strong><?= $e($val($t, 'numero_facture', '—')) ?></strong>
                    <div class="muted"><?= $e($val($t, 'encaisse_par', '')) ?></div>
                </td>
                <td><?= $e($val($t, 'agence', '—')) ?></td>
                <td class="num"><?= $e($xof($val($t, 'montant', 0))) ?></td>
                <td class="num"><?= $e($jour($val($t, 'emise_le', ''))) ?></td>
                <td class="num"><?= $heure === '' ? '—' : $e(substr($heure, 0, 5)) ?></td>
                <td class="num"><?= $e($jour($val($t, 'payee_le', ''))) ?></td>
                <td class="num"><?= $e((int) $val($t, 'jours_ecart', 0)) ?> j</td>
                <td>
                    <?php if ($logiciel): ?>
                        <span class="badge badge-attente">Décalé par le logiciel</span>
                    <?php else: ?>
                        <span class="badge badge-neutre">Règlement différé</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num"><?= $e($xof($montantTardifs)) ?></td>
                <td colspan="5"></td>
            </tr>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="section">Anomalies</h2>

<?php if ($nbAnomalies === 0 && $desync === []): ?>
    <div class="encadre calme bloc">
        <strong>Aucune anomalie détectée.</strong>
        Aucun règlement sans encaisseur, aucun règlement orphelin ou porté sur une facture
        annulée, aucun surpaiement, aucun doublon, aucun mode de règlement inconnu. Les
        compteurs des factures concordent avec la somme de leurs règlements.
    </div>
<?php else: ?>
    <?php if ($desync !== []): ?>
        <div class="encadre grave bloc">
            <strong><?= $e(count($desync)) ?> facture(s) désynchronisée(s).</strong>
            Le montant encaissé inscrit sur la facture ne correspond pas à la somme de ses
            règlements. Ce sont les règlements qui font foi : c'est le compteur de la
            facture qu'il faut recalculer.
        </div>
        <table>
            <thead>
                <tr>
                    <th>Facture</th>
                    <th>Agence</th>
                    <th class="num">Compteur facture</th>
                    <th class="num">Somme des règlements</th>
                    <th class="num">Écart</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($desync, 0, 60) as $d): ?>
                <tr>
                    <td><strong><?= $e($val($d, 'numero_facture', '—')) ?></strong></td>
                    <td><?= $e($val($d, 'agence', '—')) ?></td>
                    <td class="num"><?= $e($xof($val($d, 'compteur_facture', 0))) ?></td>
                    <td class="num"><?= $e($xof($val($d, 'somme_paiements', 0))) ?></td>
                    <td class="num"><?= $e($xof($val($d, 'ecart', 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($desync) > 60): ?>
            <div class="muted">… et <?= $e(count($desync) - 60) ?> autre(s), voir le rapport texte.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    $libelles = [
        'paiements_sans_encaisseur' => "Règlements sans encaisseur identifié",
        'paiements_orphelins' => "Règlements rattachés à aucune facture",
        'paiements_sur_facture_annulee' => "Règlements portés sur une facture annulée",
        'surpaiements' => "Règlements dépassant le montant de la facture",
        'doublons_probables' => "Doublons probables",
    ];

    foreach ($libelles as $cle => $libelle):
        $lignes = $liste($anomalies[$cle] ?? []);

        if ($lignes === []) {
            continue;
        }

        // Un compteur a zero n'est pas une anomalie : ne rien afficher.
        if ($estCompteur($lignes)) {
            $nb = (int) ($lignes[0]['nb'] ?? 0);

            if ($nb === 0) {
                continue;
            }
            ?>
            <div class="bloc">
                <h3 class="sous"><?= $e($libelle) ?></h3>
                <div class="encadre grave">
                    <strong><?= $e($nb) ?></strong> ligne(s)
                    <?php if (isset($lignes[0]['montant'])): ?>
                        pour <strong><?= $e($xof($lignes[0]['montant'])) ?> XOF</strong>
                    <?php endif; ?>.
                </div>
            </div>
            <?php
            continue;
        }

        $colonnes = array_keys($lignes[0]);
        ?>
        <div class="bloc">
            <h3 class="sous"><?= $e($libelle) ?> — <?= $e(count($lignes)) ?></h3>
            <table>
                <thead>
                    <tr>
                    <?php foreach ($colonnes as $colonne): ?>
                        <th><?= $e(ucfirst(str_replace('_', ' ', (string) $colonne))) ?></th>
                    <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($lignes, 0, 40) as $l): ?>
                    <tr>
                    <?php foreach ($colonnes as $colonne):
                        $cellule = $l[$colonne] ?? '';
                        $estMontant = is_numeric($cellule) && str_contains((string) $colonne, 'montant');
                        ?>
                        <td class="<?= is_numeric($cellule) ? 'num' : '' ?>">
                            <?= $e($estMontant ? $xof($cellule) : (string) $cellule) ?>
                        </td>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($lignes) > 40): ?>
                <div class="muted">… et <?= $e(count($lignes) - 40) ?> autre(s), voir le rapport texte.</div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2 class="section">Pour information</h2>

<?php
$modes = $liste($anomalies['modes_non_reconnus'] ?? []);
$colisApres15h = $liste($anomalies['colis_apres_15h'] ?? []);
$totalModes = 0.0;
foreach ($modes as $m) {
    $totalModes += (float) $val($m, 'montant', 0);
}
?>

<h3 class="sous">Répartition des modes de règlement</h3>

<?php if ($modes === []): ?>
    <div class="empty">Aucun règlement sur la période.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Mode</th>
                <th class="num">Règlements</th>
                <th class="num">Montant</th>
                <th class="num">Part</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($modes as $m):
            $montant = (float) $val($m, 'montant', 0);
            $part = $totalModes > 0 ? ($montant / $totalModes) * 100 : 0.0;
            ?>
            <tr>
                <td><strong><?= $e(ucfirst((string) $val($m, 'mode_brut', '—'))) ?></strong></td>
                <td class="num"><?= $e((int) $val($m, 'nb', 0)) ?></td>
                <td class="num"><?= $e($xof($montant)) ?></td>
                <td class="num"><?= $e(number_format($part, 1, ',', ' ')) ?> %</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h3 class="sous">Colis saisis après 15h</h3>
<p class="chapo">
    Un colis déposé après 15h part avec le chargement du lendemain : il est donc
    enregistré au lendemain. C'est la règle de l'entreprise, et le seul report que
    le logiciel applique encore. L'argent, lui, reste au jour où il entre en caisse.
</p>

<?php if ($colisApres15h === []): ?>
    <div class="empty">Aucun colis saisi après 15h sur la période.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Jour de saisie</th>
                <th>Agence</th>
                <th class="num">Colis reportés</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($colisApres15h as $c): ?>
            <tr>
                <td class="num"><?= $e($jour($val($c, 'jour', ''))) ?></td>
                <td><?= $e($val($c, 'agence', '—')) ?></td>
                <td class="num"><?= $e((int) $val($c, 'nb_colis_apres_15h', 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="signatures">
    <div class="sig-box"><div class="sig-title">Caissière</div></div>
    <div class="sig-box"><div class="sig-title">Chef d'agence</div></div>
    <div class="sig-box"><div class="sig-title">Direction</div></div>
</div>

<div class="footer-note">
    Document établi par lecture seule de la base de production, sans aucune modification.
    Les montants sont exprimés en francs CFA (XOF) ; les factures libellées en euros sont
    comptées à part et n'entrent pas dans ces totaux.
    Édité le <?= $e($editeLe) ?>.
</div>

</body>
</html>
<?php

$html = (string) ob_get_clean();

if (@file_put_contents($destination, $html) === false) {
    exit("Impossible d'ecrire dans : {$destination}\n");
}

echo 'Rapport mis en page : ' . $destination . PHP_EOL;
echo 'Taille : ' . number_format((float) strlen($html), 0, ',', ' ') . ' octets' . PHP_EOL;
echo PHP_EOL;
echo 'Pour obtenir le PDF : ouvrir ce fichier dans un navigateur, puis Imprimer' . PHP_EOL;
echo 'et choisir « Enregistrer au format PDF ». Format A4 portrait.' . PHP_EOL;

if (str_contains(str_replace('\\', '/', $destination), 'public_html')) {
    echo PHP_EOL;
    echo 'ATTENTION : ce fichier est sous public_html. Le .htaccess sert directement' . PHP_EOL;
    echo 'les fichiers existants, donc il serait telechargeable par n\'importe qui.' . PHP_EOL;
    echo 'Le deplacer dans un dossier prive :' . PHP_EOL;
    echo '  mkdir -p ~/rapports && chmod 700 ~/rapports' . PHP_EOL;
}
