<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\Services\Colisage\PointageColisService;
use App\Support\HeureAgence;

/**
 * Rapport de réception : à l'écran, en PDF et en Excel.
 *
 * Pour chaque départ : colis attendus, reçus (dont endommagés), non reçus, en
 * attente, manquants, et le taux de réception ; puis le détail des colis —
 * numéro de suivi, client, état, commentaire, qui a pointé et quand. Les
 * heures sont celles de l'agence d'arrivée, avec celle de l'agence d'envoi
 * entre parenthèses quand elles diffèrent.
 *
 * Comme les exports des départs (ColisageEnvoisExport) : le PDF est une page
 * imprimable, l'Excel un tableau HTML que le tableur ouvre directement, les
 * nombres en valeur brute (x:num) pour que les sommes fonctionnent quelle que
 * soit la langue du tableur.
 */
final class ColisageReceptionRapport
{
    // ------------------------------------------------------------------
    // Écran
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function page(array $p): string
    {
        $f = $p['filtres'];
        $requete = self::requete($f);

        $html = ColisagePointage::styles() . self::stylesPage() . Ui::pageHeader(
            'Rapport de réception',
            self::libellePeriode($f) . ' · état au ' . HeureAgence::date($p['edite_le'] ?? null, (string) $p['fuseau']) . ' (heure ' . self::deLaVille((string) $p['fuseau']) . ')',
            [
                'eyebrow' => 'Pointage des colis',
                'class' => 'rh-hero-white',
                'actions' => [
                    Ui::button('PDF', ['href' => 'colisage/rapport-reception/pdf?' . $requete, 'variant' => 'secondary', 'target' => '_blank']),
                    Ui::button('Excel', ['href' => 'colisage/rapport-reception/excel?' . $requete, 'variant' => 'secondary']),
                    Ui::button('Réception des colis', ['href' => 'colisage/reception', 'variant' => 'secondary']),
                    Ui::button('Suivi des départs', ['href' => 'colisage/suivi-departs', 'variant' => 'secondary']),
                ],
            ]
        );

        if (!empty($p['sans_agence'])) {
            return $html . Ui::emptyState('Aucune agence de rattachement', "Votre compte n'est rattaché à aucune agence : demandez à l'administration de le compléter.");
        }

        $html .= self::formulaire($p);

        $t = $p['totaux'];
        $html .= '<div class="lbp-pointage-kpis">'
            . ColisagePointage::kpi('Départs', (int) $t['departs'])
            . ColisagePointage::kpi('Colis attendus', (int) $t['attendus'])
            . ColisagePointage::kpi('Reçus', (int) $t['recus'])
            . ColisagePointage::kpi('dont endommagés', (int) $t['endommages'], (int) $t['endommages'] > 0)
            . ColisagePointage::kpi('Non reçus', (int) $t['non_recus'], (int) $t['non_recus'] > 0)
            . ColisagePointage::kpi('En attente', (int) $t['en_attente'])
            . ColisagePointage::kpi('Taux de réception', self::pourcent((float) $t['taux']))
            . '</div>';

        $lignes = [];
        foreach ($p['departs'] as $d) {
            [$libelle, $ton] = ColisagePointage::ETATS[(string) $d['etat']] ?? ['—', 'neutral'];
            $lignes[] = [
                ColisagePointage::lienDepart($d),
                View::e(HeureAgence::double($d['date_depart'] ?? null, (string) $d['fuseau_depart'], (string) $d['fuseau_arrivee'])),
                View::e((string) ($d['agence_depart'] ?? '—')) . ' → ' . View::e((string) ($d['agence_arrivee'] ?? '—')),
                (string) (int) $d['envoyes'],
                (string) (int) $d['recus'],
                (string) (int) $d['endommages'],
                (int) $d['non_recus'] > 0 ? '<strong class="lbp-alerte">' . (int) $d['non_recus'] . '</strong>' : '0',
                (string) (int) $d['en_attente'],
                ColisagePointage::jaugeTaux((float) $d['taux'], (int) $d['manquants'] > 0),
                Ui::badge($libelle, $ton),
                $f['depart'] === (int) $d['id']
                    ? ''
                    : '<a href="' . View::e(View::url('colisage/rapport-reception?' . self::requete(['depart' => (int) $d['id']] + $f))) . '">Détail des colis</a>',
            ];
        }

        $html .= Ui::section('Départs', ModuleTable::render([
            ['label' => 'Départ'], ['label' => 'Parti le'], ['label' => 'Trajet'], ['label' => 'Attendus', 'align' => 'right'],
            ['label' => 'Reçus', 'align' => 'right'], ['label' => 'dont endommagés', 'align' => 'right'], ['label' => 'Non reçus', 'align' => 'right'],
            ['label' => 'En attente', 'align' => 'right'], ['label' => 'Taux de réception'], ['label' => 'État'], ['label' => ''],
        ], $lignes, 'Aucun départ sur la période', 'Un départ entre dans le rapport le jour où il part, et chaque jour où l’un de ses colis est pointé à l’arrivée.'));

        foreach ($p['departs'] as $d) {
            if (!isset($d['colis'])) {
                continue;
            }
            $html .= self::detailEcran($d);
        }

        return $html;
    }

    /** @param array<string, mixed> $d */
    private static function detailEcran(array $d): string
    {
        $lignes = [];
        foreach ($d['colis'] as $c) {
            [$etat, $ton] = ColisagePointage::ETATS_COLIS[(string) $c['etat']] ?? ['—', 'neutral'];
            $lignes[] = [
                '<strong>' . View::e((string) $c['numero_tracking']) . '</strong>',
                View::e(self::client($c)),
                Ui::badge($etat, $ton),
                View::e(self::texte($c['commentaire_reception'] ?? null)),
                View::e(self::pointePar($c)),
                View::e(self::pointeLe($c, $d)),
            ];
        }

        return Ui::section(
            'Colis du départ ' . (string) $d['reference'],
            ModuleTable::render([
                ['label' => 'N° de suivi'], ['label' => 'Client'], ['label' => 'État'], ['label' => 'Commentaire'],
                ['label' => 'Pointé par'], ['label' => 'Pointé le'],
            ], $lignes, 'Aucun colis dans ce départ'),
            (string) ($d['agence_depart'] ?? '—') . ' → ' . (string) ($d['agence_arrivee'] ?? '—')
        );
    }

    /** @param array<string, mixed> $p */
    private static function formulaire(array $p): string
    {
        $f = $p['filtres'];

        $periodes = '';
        foreach (PointageColisService::PERIODES as $cle => $libelle) {
            $periodes .= '<option value="' . View::e($cle) . '"' . ($f['periode'] === $cle ? ' selected' : '') . '>' . View::e($libelle) . '</option>';
        }

        $agences = static function (string $nom, string $libelle, ?int $choisie) use ($p): string {
            $options = '<option value="">Toutes</option>';
            foreach ($p['agences'] ?? [] as $a) {
                $options .= '<option value="' . (int) $a['id'] . '"' . ($choisie === (int) $a['id'] ? ' selected' : '') . '>' . View::e((string) $a['name']) . '</option>';
            }

            return '<label>' . View::e($libelle) . ' <select name="' . View::e($nom) . '">' . $options . '</select></label>';
        };

        $departs = '<option value="">Tous les départs de la période</option>';
        $connu = false;
        foreach ($p['options_departs'] ?? [] as $o) {
            $connu = $connu || $f['depart'] === (int) $o['id'];
            $departs .= '<option value="' . (int) $o['id'] . '"' . ($f['depart'] === (int) $o['id'] ? ' selected' : '') . '>' . View::e((string) $o['libelle']) . '</option>';
        }
        // Un départ ouvert par lien, hors de la période, reste choisi.
        if (!$connu && $f['depart'] !== null && ($p['departs'][0] ?? null) !== null) {
            $d = $p['departs'][0];
            $departs .= '<option value="' . (int) $d['id'] . '" selected>' . View::e((string) $d['reference'] . ' · ' . ($d['agence_depart'] ?? '—') . ' → ' . ($d['agence_arrivee'] ?? '—')) . '</option>';
        }

        return '<form method="get" action="' . View::e(View::url('colisage/rapport-reception')) . '" class="lbp-pointage-filtre lbp-rapport-filtre">'
            . '<label>Période <select name="periode" data-role="periode">' . $periodes . '</select></label>'
            . '<label data-pour="jour semaine">Jour <input type="date" name="date" value="' . View::e($f['date']) . '"></label>'
            . '<label data-pour="periode">Du <input type="date" name="du" value="' . View::e($f['du']) . '"></label>'
            . '<label data-pour="periode">Au <input type="date" name="au" value="' . View::e($f['au']) . '"></label>'
            . (!empty($p['peut_choisir']) ? $agences('agence_envoi', "Agence d'envoi", $f['agence_envoi']) : '')
            . $agences('agence_arrivee', "Agence d'arrivée", $f['agence_arrivee'])
            . '<label class="lbp-rapport-depart">Départ <select name="depart">' . $departs . '</select></label>'
            . Ui::button('Afficher', ['type' => 'submit', 'variant' => 'primary'])
            . '</form>'
            . '<script>(function () {'
            . 'var f = document.querySelector(".lbp-rapport-filtre"); if (!f) { return; }'
            . 'var p = f.querySelector("[data-role=periode]");'
            . 'function voir() { f.querySelectorAll("[data-pour]").forEach(function (l) { l.hidden = l.dataset.pour.split(" ").indexOf(p.value) === -1; }); }'
            . 'p.addEventListener("change", voir); voir();'
            . '})();</script>';
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function pdf(array $p): string
    {
        $t = $p['totaux'];

        $resume = '';
        foreach ($p['departs'] as $d) {
            [$libelle] = ColisagePointage::ETATS[(string) $d['etat']] ?? ['—'];
            $resume .= '<tr>'
                . self::td((string) $d['reference'], 'mono')
                . self::td(HeureAgence::double($d['date_depart'] ?? null, (string) $d['fuseau_depart'], (string) $d['fuseau_arrivee']))
                . self::td((string) ($d['agence_depart'] ?? '—') . ' → ' . (string) ($d['agence_arrivee'] ?? '—'))
                . self::td(self::nombre((int) $d['envoyes']), 'num')
                . self::td(self::nombre((int) $d['recus']), 'num')
                . self::td(self::nombre((int) $d['endommages']), 'num')
                . self::td(self::nombre((int) $d['non_recus']), 'num' . ((int) $d['non_recus'] > 0 ? ' alerte' : ''))
                . self::td(self::nombre((int) $d['en_attente']), 'num')
                . self::td(self::pourcent((float) $d['taux']), 'num')
                . self::td($libelle)
                . '</tr>';
        }
        if ($resume === '') {
            $resume = '<tr><td colspan="10" class="vide">Aucun départ sur la période.</td></tr>';
        }

        $pied = '<td colspan="3">' . (int) $t['departs'] . ' départ(s)</td>'
            . self::td(self::nombre((int) $t['attendus']), 'num') . self::td(self::nombre((int) $t['recus']), 'num')
            . self::td(self::nombre((int) $t['endommages']), 'num') . self::td(self::nombre((int) $t['non_recus']), 'num')
            . self::td(self::nombre((int) $t['en_attente']), 'num') . self::td(self::pourcent((float) $t['taux']), 'num') . '<td></td>';

        $details = '';
        foreach ($p['departs'] as $d) {
            $lignes = '';
            foreach ($d['colis'] ?? [] as $c) {
                [$etat] = ColisagePointage::ETATS_COLIS[(string) $c['etat']] ?? ['—'];
                $lignes .= '<tr>'
                    . self::td((string) $c['numero_tracking'], 'mono')
                    . self::td(self::client($c))
                    . self::td($etat, in_array((string) $c['etat'], ['NON_RECU', 'MANQUANT'], true) ? 'alerte' : '')
                    . self::td(self::texte($c['commentaire_reception'] ?? null))
                    . self::td(self::pointePar($c))
                    . self::td(self::pointeLe($c, $d))
                    . '</tr>';
            }
            if ($lignes === '') {
                $lignes = '<tr><td colspan="6" class="vide">Aucun colis dans ce départ.</td></tr>';
            }

            $details .= '<h2 class="saut">Départ <span class="mono">' . View::e((string) $d['reference']) . '</span> · '
                . View::e((string) ($d['agence_depart'] ?? '—') . ' → ' . (string) ($d['agence_arrivee'] ?? '—'))
                . ' · ' . View::e(self::nombre((int) $d['recus']) . ' reçus sur ' . self::nombre((int) $d['envoyes']) . ' (' . self::pourcent((float) $d['taux']) . ')') . '</h2>'
                . '<table><thead><tr><th>N° de suivi</th><th>Client</th><th>État</th><th>Commentaire</th><th>Pointé par</th><th>Pointé le</th></tr></thead>'
                . '<tbody>' . $lignes . '</tbody></table>';
        }

        return self::document(
            (string) $p['nom_fichier'],
            '<header class="entete"><div><p class="sur-titre">LBP · Réception des colis</p><h1>Rapport de réception</h1>'
                . '<p class="petit">' . View::e(self::libellePeriode($p['filtres'])) . '</p></div>'
                . '<div class="meta">' . self::lignesMeta($p) . '</div></header>'
                . '<table><thead><tr><th>Départ</th><th>Parti le</th><th>Trajet</th><th>Attendus</th><th>Reçus</th><th>dont endommagés</th>'
                . '<th>Non reçus</th><th>En attente</th><th>Taux</th><th>État</th></tr></thead>'
                . '<tbody>' . $resume . '</tbody><tfoot><tr>' . $pied . '</tr></tfoot></table>'
                . '<p class="note">Taux de réception : colis reçus (endommagés compris) sur colis attendus, arrondi vers le bas. '
                . 'Un colis non reçu est manquant tout de suite ; un colis en attente l’est 24 h après l’arrivée du départ.</p>'
                . $details
        );
    }

    // ------------------------------------------------------------------
    // Excel
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $p */
    public static function excel(array $p): string
    {
        $entetesResume = ['Départ', 'Parti le', "Agence d'envoi", "Agence d'arrivée", 'Attendus', 'Reçus', 'dont endommagés', 'Non reçus',
            'En attente', 'Manquants', 'Taux de réception %', 'État'];
        $resume = '';
        foreach ($p['departs'] as $d) {
            [$libelle] = ColisagePointage::ETATS[(string) $d['etat']] ?? ['—'];
            $resume .= '<tr>'
                . self::xText((string) $d['reference'])
                . self::xText(HeureAgence::double($d['date_depart'] ?? null, (string) $d['fuseau_depart'], (string) $d['fuseau_arrivee']))
                . self::xText((string) ($d['agence_depart'] ?? ''))
                . self::xText((string) ($d['agence_arrivee'] ?? ''))
                . self::xNum($d['envoyes'], 0) . self::xNum($d['recus'], 0) . self::xNum($d['endommages'], 0)
                . self::xNum($d['non_recus'], 0) . self::xNum($d['en_attente'], 0) . self::xNum($d['manquants'], 0)
                . self::xNum($d['taux'], 1)
                . self::xText($libelle)
                . '</tr>';
        }
        $t = $p['totaux'];
        $resume .= '<tr><td colspan="4"><b>Total : ' . (int) $t['departs'] . ' départ(s)</b></td>'
            . self::xNum($t['attendus'], 0) . self::xNum($t['recus'], 0) . self::xNum($t['endommages'], 0)
            . self::xNum($t['non_recus'], 0) . self::xNum($t['en_attente'], 0) . self::xNum($t['manquants'], 0)
            . self::xNum($t['taux'], 1) . '<td></td></tr>';

        $entetesDetail = ['Départ', "Agence d'envoi", "Agence d'arrivée", 'N° de suivi', 'Expéditeur', 'Destinataire', 'État', 'Commentaire',
            'Pointé par', "Pointé le (heure de l'agence d'arrivée)", "Pointé le (heure d'Abidjan)"];
        $detail = '';
        foreach ($p['departs'] as $d) {
            foreach ($d['colis'] ?? [] as $c) {
                [$etat] = ColisagePointage::ETATS_COLIS[(string) $c['etat']] ?? ['—'];
                $pointe = self::aEtePointe($c);
                $detail .= '<tr>'
                    . self::xText((string) $d['reference'])
                    . self::xText((string) ($d['agence_depart'] ?? ''))
                    . self::xText((string) ($d['agence_arrivee'] ?? ''))
                    . self::xText((string) $c['numero_tracking'])
                    . self::xText((string) ($c['expediteur'] ?? ''))
                    . self::xText((string) ($c['destinataire'] ?? ''))
                    . self::xText($etat)
                    . self::xText((string) ($c['commentaire_reception'] ?? ''))
                    . self::xText($pointe ? (string) ($c['pointe_par'] ?? '') : '')
                    . self::xText($pointe ? HeureAgence::date($c['pointe_le'], (string) $d['fuseau_arrivee']) : '')
                    . self::xText($pointe ? HeureAgence::date($c['pointe_le'], HeureAgence::SERVEUR) : '')
                    . '</tr>';
            }
        }

        $entetes = static fn (array $e): string => '<tr>' . implode('', array_map(static fn (string $x): string => '<th>' . View::e($x) . '</th>', $e)) . '</tr>';
        $meta = '';
        foreach (self::lignesMetaTexte($p) as $ligne) {
            $meta .= '<tr><td colspan="8">' . View::e($ligne) . '</td></tr>';
        }

        return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">'
            . '<head><meta charset="UTF-8"><title>' . View::e((string) $p['nom_fichier']) . '</title><style>'
            . 'td{mso-number-format:"\@";vertical-align:top}'
            . '.n0{mso-number-format:"0"}.n1{mso-number-format:"0.0"}'
            . 'th{background:#e2e8f0;font-weight:bold}'
            . '</style></head><body><table border="1">'
            . '<tr><td colspan="8"><b>Rapport de réception LBP</b></td></tr>'
            . $meta
            . '<tr></tr>'
            . $entetes($entetesResume) . $resume
            . '<tr></tr><tr><td colspan="8"><b>Détail des colis</b></td></tr>'
            . $entetes($entetesDetail) . $detail
            . '</table></body></html>';
    }

    // ------------------------------------------------------------------

    /** @param array<string, mixed> $f */
    public static function libellePeriode(array $f): string
    {
        $jour = static fn (string $d): string => date('d/m/Y', (int) strtotime($d));

        return match ($f['periode'] ?? 'jour') {
            'semaine' => 'Semaine du ' . $jour($f['du']) . ' au ' . $jour($f['au']),
            'periode' => $f['du'] === $f['au'] ? 'Le ' . $jour($f['du']) : 'Du ' . $jour($f['du']) . ' au ' . $jour($f['au']),
            default => 'Le ' . $jour($f['du']),
        };
    }

    /** @param array<string, mixed> $f */
    private static function requete(array $f): string
    {
        $q = ['periode' => $f['periode'] ?? 'jour'];
        if (($f['periode'] ?? 'jour') === 'periode') {
            $q['du'] = $f['du'];
            $q['au'] = $f['au'];
        } else {
            $q['date'] = $f['date'];
        }
        foreach (['agence_envoi', 'agence_arrivee', 'depart'] as $cle) {
            if (!empty($f[$cle])) {
                $q[$cle] = (int) $f[$cle];
            }
        }

        return http_build_query($q);
    }

    /** @param array<string, mixed> $p */
    private static function lignesMeta(array $p): string
    {
        return implode('<br>', array_map(static fn (string $l): string => View::e($l), self::lignesMetaTexte($p)));
    }

    /**
     * @param array<string, mixed> $p
     * @return array<int, string>
     */
    private static function lignesMetaTexte(array $p): array
    {
        $lignes = [self::libellePeriode($p['filtres'])];
        foreach ($p['libelles_filtres'] ?? [] as $l) {
            $lignes[] = (string) $l;
        }
        $lignes[] = 'Heures de ' . self::ville((string) $p['fuseau']) . ' · état au ' . HeureAgence::date($p['edite_le'] ?? null, (string) $p['fuseau']);
        $lignes[] = 'Édité par ' . (string) ($p['edite_par'] ?? '');

        return $lignes;
    }

    private static function ville(string $fuseau): string
    {
        return HeureAgence::ville($fuseau);
    }

    /** « d'Abidjan », « de Paris ». */
    private static function deLaVille(string $fuseau): string
    {
        $ville = HeureAgence::ville($fuseau);

        return (preg_match('/^[AEIOUYÉÈH]/iu', $ville) === 1 ? 'd’' : 'de ') . $ville;
    }

    /** @param array<string, mixed> $c */
    private static function client(array $c): string
    {
        return (trim((string) ($c['expediteur'] ?? '')) ?: '—') . ' → ' . (trim((string) ($c['destinataire'] ?? '')) ?: '—');
    }

    /** @param array<string, mixed> $c */
    private static function aEtePointe(array $c): bool
    {
        return !empty($c['pointe_le']) && !in_array((string) $c['etat'], ['ATTENDU', 'MANQUANT'], true);
    }

    /** @param array<string, mixed> $c */
    private static function pointePar(array $c): string
    {
        return self::aEtePointe($c) ? self::texte($c['pointe_par'] ?? null) : '—';
    }

    /**
     * @param array<string, mixed> $c
     * @param array<string, mixed> $d
     */
    private static function pointeLe(array $c, array $d): string
    {
        return self::aEtePointe($c) ? HeureAgence::double($c['pointe_le'], (string) $d['fuseau_arrivee'], (string) $d['fuseau_depart']) : '—';
    }

    private static function texte(mixed $valeur): string
    {
        $texte = trim((string) ($valeur ?? ''));

        return $texte === '' ? '—' : $texte;
    }

    private static function nombre(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }

    private static function pourcent(float $taux): string
    {
        return number_format($taux, 1, ',', '') . ' %';
    }

    private static function td(string $texte, string $classe = ''): string
    {
        return '<td' . ($classe !== '' ? ' class="' . View::e($classe) . '"' : '') . '>' . View::e($texte) . '</td>';
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

    private static function document(string $titre, string $corps): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . View::e($titre) . '</title><style>'
            . '@page{size:A4 landscape;margin:10mm;@bottom-right{content:"Page " counter(page) " / " counter(pages);font-size:8pt;color:#475569}}'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:8.5pt;color:#0f172a;margin:0;padding:14px;background:#fff}'
            . '.barre{display:flex;justify-content:flex-end;margin-bottom:10px}.barre button{font:700 10pt Arial,sans-serif;padding:8px 14px;border:0;background:#2563eb;color:#fff;border-radius:8px;cursor:pointer}'
            . '@media print{.barre{display:none}body{padding:0}}'
            . '.entete{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:10px}'
            . '.sur-titre{margin:0;font-size:7.5pt;letter-spacing:.08em;text-transform:uppercase;color:#475569}h1{margin:2px 0 0;font-size:15pt}'
            . 'h2{font-size:10pt;margin:16px 0 6px;border-bottom:1px solid #cbd5e1;padding-bottom:3px}'
            . '.meta{font-size:8pt;color:#334155;text-align:right;line-height:1.45}'
            . 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:3px 4px;vertical-align:top;text-align:left}'
            . 'th{background:#e2e8f0;font-size:7.5pt}thead{display:table-header-group}tr{page-break-inside:avoid}'
            . 'tfoot td{font-weight:bold;border-top:2px solid #0f172a;background:#f8fafc}'
            . '.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.mono{font-family:Consolas,monospace}'
            . '.alerte{color:#b91c1c;font-weight:bold}.vide{text-align:center;padding:14px;color:#64748b}.petit{display:block;color:#475569;margin:2px 0 0}'
            . '.note{font-size:7.5pt;color:#475569;margin:6px 0 0}'
            . '</style></head><body>'
            . '<div class="barre"><button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>'
            . $corps
            . '</body></html>';
    }

    private static function stylesPage(): string
    {
        return '<style>.lbp-rapport-filtre .lbp-rapport-depart{flex:1 1 16rem}.lbp-rapport-filtre .lbp-rapport-depart select{width:100%}'
            . '.lbp-rapport-filtre label[hidden]{display:none}</style>';
    }
}
