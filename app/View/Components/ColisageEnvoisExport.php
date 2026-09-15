<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Colisage\DossierEnvoiRegles as Regles;

/**
 * Exports des départs : historique en PDF et en Excel, fiche d'un départ.
 *
 * Mêmes colonnes que l'écran, dans l'ordre des dix colonnes LBP, suivies des
 * montants facturés et des écarts. Les chiffres de la saisie des colis n'y
 * figurent que pour le Directeur général.
 *
 * Aucune bibliothèque PDF ou Excel n'est installée : le PDF est une page
 * imprimable, l'Excel un tableau HTML que le tableur ouvre directement. Les
 * nombres y portent leur valeur brute (x:num) pour que les sommes fonctionnent
 * quelle que soit la langue du tableur.
 */
final class ColisageEnvoisExport
{
    // ------------------------------------------------------------------
    // Historique en PDF
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function historiquePdf(array $p): string
    {
        $voitSaisie = !empty($p['voit_saisie']);
        $t = $p['totaux'];

        $entetes = ['N° de départ', 'Agent'];
        foreach (Regles::COLONNES as $numero => $libelle) {
            $entetes[] = $numero . '. ' . ($numero === 3 ? 'Document' : $libelle);
        }
        array_push($entetes, 'Facturé XOF', 'Écart factures');
        if ($voitSaisie) {
            array_push($entetes, 'Saisie colis', 'Saisie kg', 'Écart saisie');
        }
        $entetes[] = 'Statut';

        $lignes = '';
        foreach ($p['dossiers'] as $d) {
            $s = $d['synthese'];
            $lignes .= '<tr>'
                . self::td((string) $d['numero'], 'mono')
                . self::td(self::texte($d['responsable'] ?? null))
                . self::td(ColisageEnvois::date($d['date_depart_effective'] ?? null))
                . self::td(self::texte($d['transporteur'] ?? null))
                . self::td(self::texte($d['numero_document'] ?? null), 'mono')
                . self::td(Regles::nombre((float) ($d['nb_colis_declare'] ?? 0)), 'num')
                . self::td(Regles::nombre((float) ($d['poids_brut_kg'] ?? 0), 1), 'num');

            foreach (array_keys(Regles::POSTES) as $poste) {
                $lignes .= self::tdPoste($s, $poste);
            }

            $lignes .= self::td($s['emballages_texte'] !== '' ? (string) $s['emballages_texte'] : '—')
                . self::td((float) $s['cout_facture_xof'] > 0 ? Regles::nombre((float) $s['cout_facture_xof']) : '—', 'num')
                . self::td(ColisageEnvois::signe((float) $s['ecart_facture_xof']), 'num' . ($s['ecart_facture_depasse'] ? ' alerte' : ''));

            if ($voitSaisie) {
                $ecart = $s['ecart_saisie'];
                $lignes .= self::td(Regles::nombre((float) ($d['colis_erp'] ?? 0)), 'num')
                    . self::td(Regles::nombre((float) ($d['poids_erp_kg'] ?? 0), 1), 'num')
                    . self::td($ecart === null ? '—' : ColisageEnvois::signe((float) $ecart['poids'], 1) . ' kg', 'num' . ($ecart !== null && $ecart['depasse'] ? ' alerte' : ''));
            }

            $lignes .= self::td(Regles::STATUTS[(string) $d['statut']] ?? (string) $d['statut']) . '</tr>';
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="' . count($entetes) . '" class="vide">Aucun départ ne correspond à ces filtres.</td></tr>';
        }

        $pied = '<td colspan="5">' . (int) $t['dossiers'] . ' départ(s)</td>'
            . self::td(Regles::nombre((float) $t['colis']), 'num')
            . self::td(Regles::nombre((float) $t['poids'], 1), 'num');
        foreach (array_keys(Regles::POSTES) as $poste) {
            $pied .= self::td(Regles::nombre((float) $t['postes'][$poste]), 'num');
        }
        $pied .= '<td></td>'
            . self::td(Regles::nombre((float) $t['cout_facture']), 'num')
            . self::td(ColisageEnvois::signe((float) $t['ecart_facture']), 'num');
        if ($voitSaisie) {
            $pied .= self::td(Regles::nombre((float) $t['colis_erp']), 'num')
                . self::td(Regles::nombre((float) $t['poids_erp'], 1), 'num')
                . self::td(ColisageEnvois::signe(round((float) $t['poids'] - (float) $t['poids_erp'], 1), 1) . ' kg', 'num');
        }
        $pied .= '<td></td>';

        return self::document(
            'Historique des envois du ' . ColisageEnvois::date($p['filtres']['du']) . ' au ' . ColisageEnvois::date($p['filtres']['au']),
            'landscape',
            '<header class="entete"><div><p class="sur-titre">LBP · Envois</p><h1>Historique des envois</h1></div>'
                . '<div class="meta">' . self::lignesMeta($p) . '</div></header>'
                . '<table><thead><tr>' . implode('', array_map(static fn (string $e): string => '<th>' . View::e($e) . '</th>', $entetes)) . '</tr></thead>'
                . '<tbody>' . $lignes . '</tbody><tfoot><tr>' . $pied . '</tr></tfoot></table>'
                . '<p class="note">Colonnes 6 à 9 : montant prévu, prestataire, puis montant facturé. Totaux des frais en XOF, au taux figé de chaque départ.</p>'
                . self::signature()
        );
    }

    // ------------------------------------------------------------------
    // Historique en Excel
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function historiqueExcel(array $p): string
    {
        $voitSaisie = !empty($p['voit_saisie']);

        $entetes = ['N° de départ', 'Statut', 'Mode', 'Agent export', 'Agence de départ', 'Destination',
            '1. Date de départ', '2. Compagnie', '3. Type de document', '3. Numéro', '4. Nombre de colis', '5. Poids total kg'];
        foreach (Regles::POSTES as $poste => $libelle) {
            $n = Regles::COLONNE_DU_POSTE[$poste] . '. ' . $libelle;
            array_push($entetes, $n . ' : prestataire', $n . ' : montant prévu', $n . ' : devise', $n . ' : facturé', $n . ' : n° facture', $n . ' : écart XOF');
        }
        array_push($entetes, '10. Emballages', 'Frais prévus XOF', 'Facturé XOF', 'Écart factures XOF', 'Taux EUR XOF',
            'Pièces jointes', 'Pièces attendues', 'Soumis le', 'Validé le', 'Validé par', 'Commentaire du DG');
        if ($voitSaisie) {
            array_push($entetes, 'Saisie : colis', 'Saisie : poids kg', 'Écart colis', 'Écart poids kg', 'Écart poids %');
        }

        $lignes = '';
        foreach ($p['dossiers'] as $d) {
            $s = $d['synthese'];
            $taux = (float) $s['taux'];
            $mode = (string) $d['mode_transport'];

            $cellules = self::xText((string) $d['numero'])
                . self::xText(Regles::STATUTS[(string) $d['statut']] ?? (string) $d['statut'])
                . self::xText(Regles::MODES[$mode] ?? $mode)
                . self::xText((string) ($d['responsable'] ?? ''))
                . self::xText((string) ($d['agence_depart'] ?? ''))
                . self::xText((string) ($d['agence_arrivee'] ?? ''))
                . self::xDate($d['date_depart_effective'] ?? null)
                . self::xText((string) ($d['transporteur'] ?? ''))
                . self::xText(Regles::DOCUMENT_DU_MODE[$mode] ?? '')
                . self::xText((string) ($d['numero_document'] ?? ''))
                . self::xNum($d['nb_colis_declare'] ?? null, 0)
                . self::xNum($d['poids_brut_kg'] ?? null, 1);

            foreach (array_keys(Regles::POSTES) as $poste) {
                $ligne = $s['frais_par_poste'][$poste] ?? [];
                $ecart = $ligne !== [] ? Regles::ecartFacture($ligne, $taux) : null;
                $cellules .= self::xText(trim((string) ($ligne['prestataire'] ?? $ligne['prestataire_libre'] ?? '')))
                    . self::xNum($ligne['montant_prevu'] ?? null, 2)
                    . self::xText(($ligne['montant_prevu'] ?? null) !== null ? (string) ($ligne['devise'] ?? 'XOF') : '')
                    . self::xNum($ligne['montant_facture'] ?? null, 2)
                    . self::xText((string) ($ligne['numero_facture'] ?? ''))
                    . self::xNum($ecart['montant_xof'] ?? null, 2);
            }

            $cellules .= self::xText((string) $s['emballages_texte'])
                . self::xNum($s['cout_prevu_xof'], 2)
                . self::xNum($s['cout_facture_xof'], 2)
                . self::xNum($s['ecart_facture_xof'], 2)
                . self::xNum($taux, 6)
                . self::xNum($s['pieces_presentes'], 0)
                . self::xNum($s['pieces_attendues'], 0)
                . self::xDate($d['soumis_le'] ?? null)
                . self::xDate($d['valide_le'] ?? null)
                . self::xText((string) ($d['valide_par'] ?? ''))
                . self::xText((string) ($d['commentaire_dg'] ?? ''));

            if ($voitSaisie) {
                $ecart = $s['ecart_saisie'];
                $cellules .= self::xNum($d['colis_erp'] ?? null, 0)
                    . self::xNum($d['poids_erp_kg'] ?? null, 1)
                    . self::xNum($ecart['colis'] ?? null, 0)
                    . self::xNum($ecart['poids'] ?? null, 1)
                    . self::xNum($ecart['pourcent_poids'] ?? null, 1);
            }

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
    // Fiche d'un départ
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function fichePdf(array $p): string
    {
        $d = $p['dossier'];
        $s = $p['synthese'];
        $mode = (string) ($d['mode_transport'] ?? 'AERIEN');
        $taux = (float) $s['taux'];

        $colonnes = [
            1 => [Regles::COLONNES[1], ColisageEnvois::date($d['date_depart_effective'] ?? null)],
            2 => [Regles::COLONNES[2], self::texte($d['transporteur'] ?? null)],
            3 => [Regles::DOCUMENT_DU_MODE[$mode] ?? Regles::COLONNES[3], self::texte($d['numero_document'] ?? null)],
            4 => [Regles::COLONNES[4], Regles::nombre((float) ($d['nb_colis_declare'] ?? 0))],
            5 => [Regles::COLONNES[5], Regles::nombre((float) ($d['poids_brut_kg'] ?? 0), 1) . ' kg'],
        ];
        foreach (Regles::POSTES as $poste => $libelle) {
            $textes = ColisageEnvois::textesPoste($s, $poste);
            $colonnes[Regles::COLONNE_DU_POSTE[$poste]] = [$libelle, trim(($textes['prestataire'] !== '' ? $textes['prestataire'] . ' · ' : '') . ($textes['prevu'] !== '' ? $textes['prevu'] : '—'))];
        }
        $colonnes[10] = [Regles::COLONNES[10], $s['emballages_texte'] !== '' ? (string) $s['emballages_texte'] : '—'];

        $grille = '<dl class="grille">';
        foreach ($colonnes as $numero => [$libelle, $valeur]) {
            $grille .= '<div><dt>' . $numero . '. ' . View::e($libelle) . '</dt><dd>' . View::e($valeur) . '</dd></div>';
        }
        $grille .= '</dl>';

        $frais = '';
        foreach (Regles::POSTES as $poste => $libelle) {
            $f = $s['frais_par_poste'][$poste] ?? [];
            $ecart = $f !== [] ? Regles::ecartFacture($f, $taux) : null;
            $frais .= '<tr>'
                . self::td(Regles::COLONNE_DU_POSTE[$poste] . '. ' . $libelle)
                . self::td(self::texte($f['prestataire'] ?? $f['prestataire_libre'] ?? null))
                . self::td(($f['montant_prevu'] ?? null) !== null ? ColisageEnvois::montantBrut((float) $f['montant_prevu'], (string) ($f['devise'] ?? 'XOF')) : '—', 'num')
                . self::td(($f['montant_facture'] ?? null) !== null ? ColisageEnvois::montantBrut((float) $f['montant_facture'], (string) ($f['devise_facture'] ?? $f['devise'] ?? 'XOF')) : '—', 'num')
                . self::td(self::texte($f['numero_facture'] ?? null))
                . self::td($ecart !== null ? ColisageEnvois::signe($ecart['montant_xof']) . ' XOF' : '—', 'num' . ($ecart !== null && $ecart['depasse'] ? ' alerte' : ''))
                . '</tr>';
        }

        $controle = '';
        $ecart = $s['ecart_saisie'] ?? null;
        if ($ecart !== null) {
            $controle = '<h2>Contrôle : document de la compagnie et saisie des colis</h2>'
                . '<table><thead><tr><th></th><th>Document</th><th>Saisie</th><th>Écart</th></tr></thead><tbody>'
                . '<tr>' . self::td('Nombre de colis') . self::td(Regles::nombre((float) ($d['nb_colis_declare'] ?? 0)), 'num')
                . self::td(Regles::nombre((float) ($d['colis_erp'] ?? 0)), 'num') . self::td(ColisageEnvois::signe((float) $ecart['colis']), 'num' . ($ecart['depasse'] ? ' alerte' : '')) . '</tr>'
                . '<tr>' . self::td('Poids total') . self::td(Regles::nombre((float) ($d['poids_brut_kg'] ?? 0), 1) . ' kg', 'num')
                . self::td(Regles::nombre((float) ($d['poids_erp_kg'] ?? 0), 1) . ' kg', 'num') . self::td(ColisageEnvois::signe((float) $ecart['poids'], 1) . ' kg', 'num' . ($ecart['depasse'] ? ' alerte' : '')) . '</tr>'
                . '</tbody></table>';

            $agents = '';
            foreach ($p['agents_saisie'] ?? [] as $agent) {
                $agents .= '<tr>' . self::td((string) $agent['agent']) . self::td((string) (int) $agent['enregistrements'], 'num')
                    . self::td(Regles::nombre((float) $agent['colis']), 'num') . self::td(Regles::nombre((float) $agent['poids'], 1) . ' kg', 'num') . '</tr>';
            }
            if ($agents !== '') {
                $controle .= '<table class="espace"><thead><tr><th>Agent de saisie</th><th>Enregistrements</th><th>Colis</th><th>Poids</th></tr></thead><tbody>' . $agents . '</tbody></table>';
            }
        }

        $pieces = '';
        $presents = array_map('strval', array_column($p['documents'] ?? [], 'type_document'));
        foreach (Regles::piecesAttendues($p['frais'] ?? []) as $type) {
            $pieces .= '<li>' . (in_array($type, $presents, true) ? '[jointe] ' : '[À JOINDRE] ') . View::e(Regles::PIECES[$type]) . '</li>';
        }
        foreach (($p['documents'] ?? []) as $doc) {
            $pieces .= '<li class="fichier">' . View::e((string) $doc['nom_fichier']) . ' — ' . View::e(ColisageEnvois::date($doc['uploaded_at'] ?? null, 'd/m/Y')) . '</li>';
        }

        $circuit = ['Statut : ' . (Regles::STATUTS[(string) $d['statut']] ?? (string) $d['statut'])];
        if (!empty($d['soumis_le'])) {
            $circuit[] = 'Soumis le ' . ColisageEnvois::date($d['soumis_le'], 'd/m/Y à H:i');
        }
        if (!empty($d['valide_le'])) {
            $circuit[] = 'Validé le ' . ColisageEnvois::date($d['valide_le'], 'd/m/Y à H:i') . ' par ' . (string) ($d['valide_par'] ?? '—');
        }
        if (!empty($d['commentaire_dg'])) {
            $circuit[] = 'Commentaire du DG : ' . (string) $d['commentaire_dg'];
        }

        return self::document(
            'Départ ' . (string) $d['numero'],
            'portrait',
            '<header class="entete"><div><p class="sur-titre">LBP · Départ</p><h1 class="mono">' . View::e((string) $d['numero']) . '</h1>'
                . '<p class="petit">' . View::e((Regles::MODES[$mode] ?? $mode) . ' · ' . ($d['agence_depart'] ?? '—') . ' → ' . ($d['agence_arrivee'] ?? '—') . ' · agent export : ' . ($d['responsable'] ?? '—')) . '</p></div>'
                . '<div class="meta">Édité le ' . View::e(date('d/m/Y à H:i')) . '<br>par ' . View::e((string) ($p['edite_par'] ?? '')) . '</div></header>'
                . '<h2>Les dix colonnes</h2>' . $grille
                . '<h2>Frais</h2><table><thead><tr><th>Colonne</th><th>Prestataire</th><th>Prévu</th><th>Facturé</th><th>N° facture</th><th>Écart</th></tr></thead><tbody>' . $frais . '</tbody>'
                . '<tfoot><tr><td colspan="2">Total en XOF (taux ' . View::e(Regles::nombre($taux, 3)) . ')</td>'
                . self::td(Regles::nombre((float) $s['cout_prevu_xof']), 'num') . self::td(Regles::nombre((float) $s['cout_facture_xof']), 'num')
                . '<td></td>' . self::td(ColisageEnvois::signe((float) $s['ecart_facture_xof']), 'num')
                . '</tr></tfoot></table>'
                . $controle
                . '<h2>Pièces</h2><ul class="pieces">' . $pieces . '</ul>'
                . '<h2>Circuit</h2><p>' . implode('<br>', array_map(static fn (string $c): string => View::e($c), $circuit)) . '</p>'
                . self::signature()
        );
    }

    // ------------------------------------------------------------------

    private static function document(string $titre, string $orientation, string $corps): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . View::e($titre) . '</title><style>'
            . '@page{size:A4 ' . $orientation . ';margin:10mm;@bottom-right{content:"Page " counter(page) " / " counter(pages);font-size:8pt;color:#475569}}'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:8.5pt;color:#0f172a;margin:0;padding:14px;background:#fff}'
            . '.barre{display:flex;justify-content:flex-end;margin-bottom:10px}.barre button{font:700 10pt Arial,sans-serif;padding:8px 14px;border:0;background:#2563eb;color:#fff;border-radius:8px;cursor:pointer}'
            . '@media print{.barre{display:none}body{padding:0}}'
            . '.entete{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:10px}'
            . '.sur-titre{margin:0;font-size:7.5pt;letter-spacing:.08em;text-transform:uppercase;color:#475569}h1{margin:2px 0 0;font-size:15pt}'
            . 'h2{font-size:10pt;margin:14px 0 6px;border-bottom:1px solid #cbd5e1;padding-bottom:3px}'
            . '.meta{font-size:8pt;color:#334155;text-align:right;line-height:1.45}'
            . 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:3px 4px;vertical-align:top;text-align:left}'
            . 'th{background:#e2e8f0;font-size:7.5pt}thead{display:table-header-group}tr{page-break-inside:avoid}'
            . 'tfoot td{font-weight:bold;border-top:2px solid #0f172a;background:#f8fafc}'
            . '.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.mono{font-family:Consolas,monospace}'
            . '.alerte{color:#b91c1c;font-weight:bold}.vide{text-align:center;padding:14px;color:#64748b}.petit{display:block;color:#475569;margin:2px 0 0}'
            . '.note{font-size:7.5pt;color:#475569;margin:6px 0 0}.espace{margin-top:6px}'
            . '.grille{display:grid;grid-template-columns:repeat(2,1fr);gap:6px 14px;margin:0}.grille dt{font-size:7pt;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.grille dd{margin:1px 0 0;font-size:9.5pt;font-weight:bold}'
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

        if ($textes['prestataire'] === '' && $textes['prevu'] === '') {
            return '<td class="num">—</td>';
        }

        return '<td class="num">' . View::e($textes['prevu'] !== '' ? $textes['prevu'] : '—')
            . '<span class="petit">' . View::e($textes['prestataire'] !== '' ? $textes['prestataire'] : '—') . '</span>'
            . ($textes['facture'] !== '' ? '<span class="petit">facturé ' . View::e($textes['facture']) . '</span>' : '')
            . '</td>';
    }

    private static function texte(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '—' : $texte;
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
