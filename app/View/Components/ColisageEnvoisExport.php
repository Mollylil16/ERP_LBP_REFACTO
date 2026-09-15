<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Colisage\DossierEnvoiRegles as Regles;

/**
 * Exports des dossiers d'envoi : historique en PDF et en Excel, fiche d'un
 * dossier à agrafer sur sa pochette.
 *
 * Aucune bibliothèque PDF ou Excel n'est installée : le PDF est une page
 * imprimable (Imprimer, puis Enregistrer en PDF), l'Excel un tableau HTML que
 * le tableur ouvre directement, comme les autres exports de l'ERP. Les nombres
 * y portent leur valeur brute (x:num) pour que les sommes fonctionnent sans
 * retouche, quelle que soit la langue du tableur.
 */
final class ColisageEnvoisExport
{
    // ------------------------------------------------------------------
    // Historique en PDF
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function historiquePdf(array $p): string
    {
        $t = $p['totaux'];
        $lignes = '';

        foreach ($p['dossiers'] as $d) {
            $s = $d['synthese'];
            $colis = self::texte($d['nb_colis_declare'] ?? null);
            if (($s['ecart_colis'] ?? null) !== null && (int) $s['ecart_colis'] !== 0) {
                $colis .= ' (' . (int) $s['colis_pointes'] . ' pointés)';
            }

            $lignes .= '<tr>'
                . self::td((string) $d['numero'], 'mono')
                . self::td(Regles::MODES[(string) $d['mode_transport']] ?? (string) $d['mode_transport'])
                . self::td(ColisageEnvois::date($d['date_reference'] ?? null))
                . self::td(self::texte($d['transporteur'] ?? null))
                . self::td(self::texte($d['numero_document'] ?? null), 'mono')
                . self::td($colis, 'num')
                . self::td(($d['poids_brut_kg'] ?? null) !== null ? Regles::nombre((float) $d['poids_brut_kg'], 1) : '—', 'num')
                . self::tdPoste($s, 'TRANSIT_DEPART')
                . self::tdPoste($s, 'TRANSIT_ARRIVEE')
                . self::tdPoste($s, 'LIVRAISON_DEPART')
                . self::tdPoste($s, 'LIVRAISON_ARRIVEE')
                . self::tdPoste($s, 'FRET')
                . self::td($s['emballages_texte'] !== '' ? (string) $s['emballages_texte'] : '—')
                . self::td((float) $s['cout_retenu_xof'] > 0 ? Regles::nombre((float) $s['cout_retenu_xof']) : '—', 'num')
                . self::td(ColisageEnvois::signe((float) $s['ecart_facture_xof']), 'num' . ($s['ecart_facture_depasse'] ? ' alerte' : ''))
                . self::td((int) $s['pieces_presentes'] . '/' . (int) $s['pieces_attendues'], (int) $s['pieces_presentes'] < (int) $s['pieces_attendues'] ? 'alerte' : '')
                . self::td(Regles::STATUTS[(string) $d['statut']] ?? (string) $d['statut'])
                . self::td(self::texte($d['responsable'] ?? null))
                . '</tr>';
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="18" class="vide">Aucun dossier ne correspond à ces filtres.</td></tr>';
        }

        $postes = $t['postes'];
        $pied = '<tr>'
            . '<td colspan="5">' . (int) $t['dossiers'] . ' dossier(s)</td>'
            . self::td(Regles::nombre((float) $t['colis']), 'num')
            . self::td(Regles::nombre((float) $t['poids'], 1), 'num')
            . self::tdTotalPoste($postes['TRANSIT_DEPART'])
            . self::tdTotalPoste($postes['TRANSIT_ARRIVEE'])
            . self::tdTotalPoste($postes['LIVRAISON_DEPART'])
            . self::tdTotalPoste($postes['LIVRAISON_ARRIVEE'])
            . self::tdTotalPoste($postes['FRET'])
            . '<td></td>'
            . self::td(Regles::nombre((float) $t['cout_retenu']), 'num')
            . self::td(ColisageEnvois::signe((float) $t['ecart_facture']), 'num')
            . '<td colspan="3"></td>'
            . '</tr>';

        $entetes = ['Dossier', 'Mode', 'Départ', 'Transporteur', 'Document', 'Colis', 'Poids kg', 'Transit. départ', 'Transit. dest.',
            'Livr. départ', 'Livr. arrivée', 'Fret', 'Emballages', 'Coût XOF', 'Écart fact. XOF', 'Pièces', 'Statut', 'Responsable'];

        $titre = 'Historique des envois du ' . ColisageEnvois::date($p['filtres']['du']) . ' au ' . ColisageEnvois::date($p['filtres']['au']);

        return self::document(
            $titre,
            'landscape',
            '<header class="entete"><div><p class="sur-titre">LBP · Dossiers d\'envoi</p><h1>Historique des envois</h1></div>'
                . '<div class="meta">' . self::lignesMeta($p) . '</div></header>'
                . '<table><thead><tr>' . implode('', array_map(static fn (string $e): string => '<th>' . View::e($e) . '</th>', $entetes)) . '</tr></thead>'
                . '<tbody>' . $lignes . '</tbody><tfoot>' . $pied . '</tfoot></table>'
                . '<p class="note">Montants des postes : prestataire, montant prévu, puis montant facturé. Totaux des postes en XOF, au taux EUR → XOF figé de chaque dossier.</p>'
                . self::signature()
        );
    }

    // ------------------------------------------------------------------
    // Historique en Excel
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function historiqueExcel(array $p): string
    {
        $entetes = [
            'Dossier', 'Statut', 'Mode', 'Responsable', 'Agence de départ', "Agence d'arrivée", 'Destination',
            'Départ prévu', 'Départ effectif', 'Arrivée', 'Livraison', 'Transporteur', 'Type de document', 'Document',
            'Transitaire émetteur', 'Document principal', 'Tranches', 'Colis (document)', 'Colis pointés', 'Écart colis',
            'Poids brut kg', 'Poids taxable kg', 'Volume m3', 'Emballages',
        ];
        foreach (Regles::POSTES as $libelle) {
            array_push($entetes, $libelle . ' : prestataire', $libelle . ' : prévu', $libelle . ' : devise', $libelle . ' : facturé', $libelle . ' : devise facture', $libelle . ' : n° facture', $libelle . ' : écart XOF');
        }
        array_push(
            $entetes,
            'Autres frais prévus XOF', 'Autres frais facturés XOF', 'Coût prévu XOF', 'Coût retenu XOF', 'Écart factures XOF',
            'Taux EUR XOF', 'Pièces jointes', 'Pièces attendues', 'Pièces manquantes', 'Soumis le', 'Validé le', 'Validé par'
        );

        $lignes = '';
        foreach ($p['dossiers'] as $d) {
            $s = $d['synthese'];
            $taux = (float) $s['taux'];

            $tranches = [];
            foreach (($d['tranches'] ?? []) as $tranche) {
                $tranches[] = trim((Regles::LIBELLES_TRANCHE[(string) $tranche['type']] ?? '') . ' ' . ($tranche['reference'] ?? '')
                    . (!empty($tranche['date_depart']) ? ' ' . ColisageEnvois::date($tranche['date_depart']) : '')
                    . (($tranche['nb_colis'] ?? null) !== null ? ' ' . (int) $tranche['nb_colis'] . ' colis' : ''));
            }

            $cellules = self::xText((string) $d['numero'])
                . self::xText(Regles::STATUTS[(string) $d['statut']] ?? (string) $d['statut'])
                . self::xText(Regles::MODES[(string) $d['mode_transport']] ?? (string) $d['mode_transport'])
                . self::xText((string) ($d['responsable'] ?? ''))
                . self::xText((string) ($d['agence_depart'] ?? ''))
                . self::xText((string) ($d['agence_arrivee'] ?? ''))
                . self::xText((string) ($d['destination'] ?? ''))
                . self::xDate($d['date_depart_prevue'] ?? null)
                . self::xDate($d['date_depart_effective'] ?? null)
                . self::xDate($d['date_arrivee'] ?? null)
                . self::xDate($d['date_livraison'] ?? null)
                . self::xText((string) ($d['transporteur'] ?? ''))
                . self::xText(Regles::DOCUMENTS_DU_MODE[(string) $d['mode_transport']][(string) ($d['type_document'] ?? '')] ?? '')
                . self::xText((string) ($d['numero_document'] ?? ''))
                . self::xText((string) ($d['emetteur_document'] ?? ''))
                . self::xText((string) ($d['document_principal'] ?? ''))
                . self::xText(implode(' ; ', $tranches))
                . self::xNum($d['nb_colis_declare'] ?? null, 0)
                . self::xNum($s['colis_pointes'], 0)
                . self::xNum($s['ecart_colis'], 0)
                . self::xNum($d['poids_brut_kg'] ?? null, 1)
                . self::xNum($d['poids_taxable_kg'] ?? null, 1)
                . self::xNum($d['volume_m3'] ?? null, 2)
                . self::xText((string) $s['emballages_texte']);

            foreach (array_keys(Regles::POSTES) as $poste) {
                $ligne = $s['frais_par_poste'][$poste] ?? [];
                $ecart = $ligne !== [] ? Regles::ecartFacture($ligne, $taux) : null;
                $cellules .= self::xText((string) ($ligne['prestataire'] ?? $ligne['prestataire_libre'] ?? ''))
                    . self::xNum($ligne['montant_prevu'] ?? null, 2)
                    . self::xText(($ligne['montant_prevu'] ?? null) !== null ? (string) ($ligne['devise'] ?? 'XOF') : (!empty($ligne['sans_frais']) ? 'sans frais' : ''))
                    . self::xNum($ligne['montant_facture'] ?? null, 2)
                    . self::xText(($ligne['montant_facture'] ?? null) !== null ? (string) ($ligne['devise_facture'] ?? $ligne['devise'] ?? 'XOF') : '')
                    . self::xText((string) ($ligne['numero_facture'] ?? ''))
                    . self::xNum($ecart['montant_xof'] ?? null, 2);
            }

            $autresPrevus = 0.0;
            $autresFactures = 0.0;
            foreach (($d['frais'] ?? []) as $ligne) {
                if (($ligne['poste'] ?? '') !== Regles::POSTE_AUTRE) {
                    continue;
                }
                $autresPrevus += (float) Regles::enXof(isset($ligne['montant_prevu']) ? (float) $ligne['montant_prevu'] : null, (string) ($ligne['devise'] ?? 'XOF'), $taux);
                $autresFactures += (float) Regles::enXof(isset($ligne['montant_facture']) ? (float) $ligne['montant_facture'] : null, (string) ($ligne['devise_facture'] ?? $ligne['devise'] ?? 'XOF'), $taux);
            }

            $cellules .= self::xNum($autresPrevus, 2)
                . self::xNum($autresFactures, 2)
                . self::xNum($s['cout_prevu_xof'], 2)
                . self::xNum($s['cout_retenu_xof'], 2)
                . self::xNum($s['ecart_facture_xof'], 2)
                . self::xNum($taux, 6)
                . self::xNum($s['pieces_presentes'], 0)
                . self::xNum($s['pieces_attendues'], 0)
                . self::xText(implode(' ; ', $s['pieces_manquantes']))
                . self::xDate($d['soumis_le'] ?? null)
                . self::xDate($d['valide_le'] ?? null)
                . self::xText((string) ($d['valide_par'] ?? ''));

            $lignes .= '<tr>' . $cellules . '</tr>';
        }

        $meta = '';
        foreach (($p['libelles_filtres'] ?? []) as $libelle) {
            $meta .= '<tr><td colspan="8">' . View::e((string) $libelle) . '</td></tr>';
        }

        return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">'
            . '<head><meta charset="UTF-8"><style>'
            . 'td{mso-number-format:"\@";vertical-align:top}'
            . '.n0{mso-number-format:"0"}.n1{mso-number-format:"#,##0.0"}.n2{mso-number-format:"#,##0.00"}.n6{mso-number-format:"0.000000"}'
            . '.d{mso-number-format:"dd\/mm\/yyyy"}th{background:#e2e8f0;font-weight:bold}'
            . '</style></head><body><table border="1">'
            . '<tr><td colspan="8"><b>Historique des envois LBP</b></td></tr>'
            . $meta
            . '<tr><td colspan="8">Édité le ' . View::e(ColisageEnvois::date($p['edite_le'] ?? null, 'd/m/Y à H:i')) . ' par ' . View::e((string) ($p['edite_par'] ?? '')) . '</td></tr>'
            . '<tr></tr>'
            . '<tr>' . implode('', array_map(static fn (string $e): string => '<th>' . View::e($e) . '</th>', $entetes)) . '</tr>'
            . $lignes
            . '</table></body></html>';
    }

    // ------------------------------------------------------------------
    // Fiche d'un dossier
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function fichePdf(array $p): string
    {
        $d = $p['dossier'];
        $s = $p['synthese'];
        $mode = (string) $d['mode_transport'];
        $taux = (float) $s['taux'];

        $infos = [
            ['Statut', Regles::STATUTS[(string) $d['statut']] ?? (string) $d['statut']],
            ['Mode', Regles::MODES[$mode] ?? $mode],
            ['Responsable', self::texte($d['responsable'] ?? null)],
            ['Agence de départ', self::texte($d['agence_depart'] ?? null)],
            ["Arrivée", self::texte($d['agence_arrivee'] ?? $d['destination'] ?? null)],
            [Regles::libelleTransporteur($mode), self::texte($d['transporteur'] ?? null)],
            [Regles::DOCUMENTS_DU_MODE[$mode][(string) ($d['type_document'] ?? '')] ?? Regles::libelleDocument($mode), self::texte($d['numero_document'] ?? null)],
            ['Document principal', self::texte($d['document_principal'] ?? null)],
            ['Transitaire émetteur', self::texte($d['emetteur_document'] ?? null)],
            ['Départ du pointage', self::texte($d['expedition_reference'] ?? null)],
            ['Départ prévu / effectif', ColisageEnvois::date($d['date_depart_prevue'] ?? null) . ' / ' . ColisageEnvois::date($d['date_depart_effective'] ?? null)],
            ['Arrivée / livraison', ColisageEnvois::date($d['date_arrivee'] ?? null) . ' / ' . ColisageEnvois::date($d['date_livraison'] ?? null)],
            ['Colis document / pointés', self::texte($d['nb_colis_declare'] ?? null) . ' / ' . self::texte($s['colis_pointes'])],
            ['Poids brut / taxable', self::kg($d['poids_brut_kg'] ?? null) . ' / ' . self::kg($d['poids_taxable_kg'] ?? null)],
            ['Emballages', $s['emballages_texte'] !== '' ? (string) $s['emballages_texte'] : '—'],
            ["Commentaire d'écart", self::texte($d['commentaire_ecart'] ?? null)],
        ];

        $grille = '<dl class="grille">';
        foreach ($infos as [$libelle, $valeur]) {
            $grille .= '<div><dt>' . View::e($libelle) . '</dt><dd>' . View::e($valeur) . '</dd></div>';
        }
        $grille .= '</dl>';

        $tranches = '';
        foreach (($p['tranches'] ?? []) as $t) {
            $tranches .= '<tr>'
                . self::td((Regles::LIBELLES_TRANCHE[(string) $t['type']] ?? '') . ' ' . (int) $t['rang'])
                . self::td(self::texte($t['reference'] ?? null), 'mono')
                . self::td(ColisageEnvois::date($t['date_depart'] ?? null))
                . self::td(ColisageEnvois::date($t['date_arrivee'] ?? null))
                . self::td(self::texte($t['nb_colis'] ?? null), 'num')
                . self::td(self::kg($t['poids_kg'] ?? null), 'num')
                . '</tr>';
        }
        $blocTranches = $tranches === '' ? '' : '<h2>Tranches</h2><table><thead><tr><th>Tranche</th><th>Référence</th><th>Départ</th><th>Arrivée</th><th>Colis</th><th>Poids</th></tr></thead><tbody>' . $tranches . '</tbody></table>';

        $frais = '';
        $parPoste = [];
        $autres = [];
        foreach (($p['frais'] ?? []) as $ligne) {
            if (($ligne['poste'] ?? '') === Regles::POSTE_AUTRE) {
                $autres[] = $ligne;
            } else {
                $parPoste[(string) $ligne['poste']] ??= $ligne;
            }
        }
        $lignesFrais = [];
        foreach (Regles::POSTES as $poste => $libelle) {
            $lignesFrais[] = [$libelle, $parPoste[$poste] ?? []];
        }
        foreach ($autres as $ligne) {
            $lignesFrais[] = [(string) ($ligne['libelle'] ?? 'Autre frais'), $ligne];
        }
        foreach ($lignesFrais as [$libelle, $f]) {
            $ecart = $f !== [] ? Regles::ecartFacture($f, $taux) : null;
            $frais .= '<tr>'
                . self::td($libelle)
                . self::td(self::texte($f['prestataire'] ?? $f['prestataire_libre'] ?? null))
                . self::td(($f['montant_prevu'] ?? null) !== null ? ColisageEnvois::montantBrut((float) $f['montant_prevu'], (string) ($f['devise'] ?? 'XOF')) : (!empty($f['sans_frais']) ? 'Sans frais' : '—'), 'num')
                . self::td(($f['montant_facture'] ?? null) !== null ? ColisageEnvois::montantBrut((float) $f['montant_facture'], (string) ($f['devise_facture'] ?? $f['devise'] ?? 'XOF')) : '—', 'num')
                . self::td(self::texte($f['numero_facture'] ?? null))
                . self::td($ecart !== null ? ColisageEnvois::signe($ecart['montant_xof']) . ' XOF' : '—', 'num' . ($ecart !== null && $ecart['depasse'] ? ' alerte' : ''))
                . '</tr>';
        }

        $pieces = '';
        $presents = array_map('strval', array_column($p['documents'] ?? [], 'type_document'));
        foreach (Regles::piecesAttendues($p['frais'] ?? []) as $type) {
            $pieces .= '<li>' . (in_array($type, $presents, true) ? '[jointe] ' : '[MANQUANTE] ') . View::e(Regles::PIECES[$type]) . '</li>';
        }
        foreach (($p['documents'] ?? []) as $doc) {
            $pieces .= '<li class="fichier">' . View::e((string) $doc['nom_fichier']) . ' — ' . View::e(ColisageEnvois::date($doc['uploaded_at'] ?? null, 'd/m/Y')) . '</li>';
        }

        $circuit = [];
        if (!empty($d['soumis_le'])) {
            $circuit[] = 'Soumis le ' . ColisageEnvois::date($d['soumis_le'], 'd/m/Y à H:i');
        }
        if (!empty($d['valide_le'])) {
            $circuit[] = 'Validé le ' . ColisageEnvois::date($d['valide_le'], 'd/m/Y à H:i') . ' par ' . (string) ($d['valide_par'] ?? '—');
        }
        if (!empty($d['motif_renvoi']) && $d['statut'] === 'A_CORRIGER') {
            $circuit[] = 'Renvoyé pour correction : ' . (string) $d['motif_renvoi'];
        }

        return self::document(
            'Dossier ' . (string) $d['numero'],
            'portrait',
            '<header class="entete"><div><p class="sur-titre">LBP · Dossier d\'envoi</p><h1 class="mono">' . View::e((string) $d['numero']) . '</h1></div>'
                . '<div class="meta">Édité le ' . View::e(date('d/m/Y à H:i')) . '<br>par ' . View::e((string) ($p['edite_par'] ?? '')) . '</div></header>'
                . $grille
                . $blocTranches
                . '<h2>Frais</h2><table><thead><tr><th>Poste</th><th>Prestataire</th><th>Prévu</th><th>Facturé</th><th>N° facture</th><th>Écart</th></tr></thead><tbody>' . $frais . '</tbody>'
                . '<tfoot><tr><td colspan="2">Total en XOF (taux ' . View::e(Regles::nombre($taux, 3)) . ')</td>'
                . self::td(Regles::nombre((float) $s['cout_prevu_xof']), 'num') . self::td(Regles::nombre((float) $s['cout_facture_xof']), 'num')
                . self::td('Retenu : ' . Regles::nombre((float) $s['cout_retenu_xof'])) . self::td(ColisageEnvois::signe((float) $s['ecart_facture_xof']), 'num')
                . '</tr></tfoot></table>'
                . '<h2>Pièces</h2><ul class="pieces">' . $pieces . '</ul>'
                . ($circuit !== [] ? '<h2>Circuit</h2><p>' . implode('<br>', array_map(static fn (string $c): string => View::e($c), $circuit)) . '</p>' : '')
                . self::signature()
        );
    }

    // ------------------------------------------------------------------

    private static function document(string $titre, string $orientation, string $corps): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . View::e($titre) . '</title><style>'
            . '@page{size:A4 ' . $orientation . ';margin:10mm;@bottom-right{content:"Page " counter(page) " / " counter(pages);font-size:8pt;color:#475569}}'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:8.5pt;color:#0f172a;margin:0;padding:14px;background:#fff}'
            . '.barre{display:flex;justify-content:flex-end;margin-bottom:10px}.barre button{font:600 10pt Arial,sans-serif;padding:8px 14px;border:1px solid #0f172a;background:#0f172a;color:#fff;border-radius:6px;cursor:pointer}'
            . '@media print{.barre{display:none}body{padding:0}}'
            . '.entete{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:10px}'
            . '.sur-titre{margin:0;font-size:7.5pt;letter-spacing:.08em;text-transform:uppercase;color:#475569}h1{margin:2px 0 0;font-size:15pt}'
            . 'h2{font-size:10pt;margin:14px 0 6px;border-bottom:1px solid #cbd5e1;padding-bottom:3px}'
            . '.meta{font-size:8pt;color:#334155;text-align:right;line-height:1.45}'
            . 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:3px 4px;vertical-align:top;text-align:left}'
            . 'th{background:#e2e8f0;font-size:7.5pt}thead{display:table-header-group}tr{page-break-inside:avoid}'
            . 'tfoot td{font-weight:bold;border-top:2px solid #0f172a;background:#f8fafc}'
            . '.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.mono{font-family:Consolas,monospace}'
            . '.alerte{color:#b91c1c;font-weight:bold}.vide{text-align:center;padding:14px;color:#64748b}.petit{display:block;color:#475569}'
            . '.note{font-size:7.5pt;color:#475569;margin:6px 0 0}'
            . '.grille{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 14px;margin:0}.grille dt{font-size:7pt;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.grille dd{margin:1px 0 0;font-size:9pt}'
            . '.pieces{margin:0;padding-left:16px}.pieces .fichier{color:#475569;list-style:circle}'
            . '.signature{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:22px}.signature div{border-top:1px solid #0f172a;padding-top:4px;min-height:36px;font-size:8pt}'
            . '</style></head><body>'
            . '<div class="barre"><button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>'
            . $corps
            . '</body></html>';
    }

    /** @param array<string, mixed> $p */
    private static function lignesMeta(array $p): string
    {
        $lignes = array_map(static fn (string $l): string => View::e($l), array_map('strval', $p['libelles_filtres'] ?? []));
        $lignes[] = 'Édité le ' . View::e(ColisageEnvois::date($p['edite_le'] ?? null, 'd/m/Y à H:i')) . ' par ' . View::e((string) ($p['edite_par'] ?? ''));

        return implode('<br>', $lignes);
    }

    private static function signature(): string
    {
        return '<section class="signature"><div>Vérifié par</div><div>Date</div><div>Signature</div></section>';
    }

    private static function td(string $texte, string $classe = ''): string
    {
        return '<td' . ($classe !== '' ? ' class="' . View::e($classe) . '"' : '') . '>' . View::e($texte) . '</td>';
    }

    /** @param array<string, mixed> $synthese */
    private static function tdPoste(array $synthese, string $poste): string
    {
        $textes = ColisageEnvois::textesPoste($synthese, $poste);

        if ($textes['prestataire'] === '' && $textes['prevu'] === '' && $textes['facture'] === '') {
            return '<td>—</td>';
        }

        return '<td>' . View::e($textes['prestataire'] !== '' ? $textes['prestataire'] : '—')
            . '<span class="petit">' . View::e($textes['prevu'] !== '' ? $textes['prevu'] : '—') . '</span>'
            . ($textes['facture'] !== '' ? '<span class="petit">facturé ' . View::e($textes['facture']) . '</span>' : '')
            . '</td>';
    }

    /** @param array{prevu:float, facture:float} $poste */
    private static function tdTotalPoste(array $poste): string
    {
        return '<td class="num">' . View::e(Regles::nombre($poste['prevu']))
            . ($poste['facture'] > 0 ? '<span class="petit">facturé ' . View::e(Regles::nombre($poste['facture'])) . '</span>' : '')
            . '</td>';
    }

    private static function texte(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '—' : $texte;
    }

    private static function kg(mixed $valeur): string
    {
        return $valeur === null || $valeur === '' ? '—' : Regles::nombre((float) $valeur, 1) . ' kg';
    }

    private static function xText(string $texte): string
    {
        return '<td>' . View::e($texte) . '</td>';
    }

    private static function xNum(mixed $valeur, int $decimales): string
    {
        if ($valeur === null || $valeur === '') {
            return '<td></td>';
        }

        $brut = number_format((float) $valeur, $decimales, '.', '');

        return '<td class="n' . $decimales . '" x:num="' . $brut . '">' . $brut . '</td>';
    }

    private static function xDate(mixed $valeur): string
    {
        $texte = ColisageEnvois::date($valeur);

        return $texte === '—' ? '<td></td>' : '<td class="d">' . View::e($texte) . '</td>';
    }
}
