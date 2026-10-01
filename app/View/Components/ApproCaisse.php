<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Finance\ApproCaisseService as Service;

/**
 * Finance > Appro Caisse : l'argent remis à une agence.
 *
 * Trois lectures sur le même écran. La caissière principale saisit et suit ce
 * qu'elle a remis ; le comptable voit en tête ce qui attend sa validation ;
 * l'agence lit ce qu'elle a reçu, et à quelle date cet argent entre dans son
 * point de caisse — sans quoi elle compterait le soir un argent qu'elle ne
 * saurait pas expliquer.
 */
final class ApproCaisse
{
    private const ICONES = [
        'filtrer' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"></path>',
        'reinitialiser' => '<path d="M3 2v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L3 8"></path>',
        'ajouter' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
    ];

    /** @var array<string, string> */
    private const TONS = ['validee' => 'success', 'rejetee' => 'danger', 'en_attente' => 'warning'];

    /** @param array<string, mixed> $p */
    public static function page(array $p): string
    {
        $f = $p['filtres'];

        $html = Ui::pageHeader(
            'Appro Caisse',
            "L'argent remis à une agence. Saisi par la caissière principale, validé par le comptable — et compté dans le point de caisse de l'agence au jour de la remise.",
            ['eyebrow' => 'Finance', 'class' => 'rh-hero-white']
        );

        $html .= self::kpis($p) . self::filtres($p);

        if (!empty($p['peutSaisir'])) {
            $html .= Ui::section(
                'Nouvel approvisionnement',
                self::formulaire($p),
                "Il n'entre dans la caisse de l'agence qu'une fois validé par le comptable."
            );
        } else {
            // Un écran sans formulaire paraît amputé. Il dit donc à qui revient
            // la saisie, plutôt que de laisser croire à une panne.
            $html .= self::rappelDesRoles($p);
        }

        $html .= Ui::section(
            'Approvisionnements du ' . self::date($f['du']) . ' au ' . self::date($f['au']),
            self::tableau($p),
            'La date de remise est celle où l\'argent entre en caisse, pas celle de la saisie.'
        );

        return self::styles() . '<div class="finea-shell lbp-appro"><div class="finea-container">' . $html . '</div></div>';
    }

    /** @param array<string, mixed> $p */
    private static function kpis(array $p): string
    {
        $t = $p['totaux'];

        return '<div class="lbp-appro-kpis">'
            . self::kpi('Validé sur la période', self::montant((float) $t['valide']) . ' F', (int) $t['nombre'] . ' approvisionnement(s)', false, true)
            . self::kpi('En attente de validation', self::montant((float) $t['en_attente']) . ' F', (int) $t['a_valider'] . ' à valider', (int) $t['a_valider'] > 0)
            . self::kpi('Rejeté', self::montant((float) $t['rejete']) . ' F', 'Non compté en caisse')
            . '</div>';
    }

    private static function kpi(string $libelle, string $valeur, string $detail = '', bool $alerte = false, bool $sombre = false): string
    {
        return '<div class="lbp-appro-kpi' . ($alerte ? ' is-alerte' : '') . ($sombre ? ' is-sombre' : '') . '">'
            . '<span class="lbp-appro-kpi-libelle">' . View::e($libelle) . '</span>'
            . '<strong>' . View::e($valeur) . '</strong>'
            . ($detail !== '' ? '<span class="lbp-appro-kpi-detail">' . View::e($detail) . '</span>' : '')
            . '</div>';
    }

    /** @param array<string, mixed> $p */
    private static function filtres(array $p): string
    {
        $f = $p['filtres'];
        $action = View::e(View::url('finance/appro-caisse'));

        $statuts = [['value' => '', 'label' => 'Tous les états']];
        foreach (Service::STATUTS as $code => $libelle) {
            $statuts[] = ['value' => $code, 'label' => $libelle];
        }

        return '<form method="get" action="' . $action . '" class="rh-personnel-filters lbp-appro-filtres">'
            . '<div class="lbp-appro-grille">'
            . Form::input('du', ['label' => 'Du', 'type' => 'date', 'value' => (string) $f['du'], 'id' => 'appro-du'])
            . Form::input('au', ['label' => 'Au', 'type' => 'date', 'value' => (string) $f['au'], 'id' => 'appro-au'])
            . Form::select('agence_id', self::optionsAgences($p, 'Toutes les agences'), (string) ($f['agence_id'] ?: ''), ['label' => 'Agence', 'id' => 'appro-agence'])
            . Form::select('statut', $statuts, (string) $f['statut'], ['label' => 'État', 'id' => 'appro-statut'])
            . '</div>'
            . '<div class="rh-personnel-filter-actions">'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">' . self::icone('filtrer') . 'Afficher</button>'
            . '<a href="' . $action . '" class="rh-filter-btn rh-filter-btn--reset">' . self::icone('reinitialiser') . 'Ce mois-ci</a>'
            . '</div></form>';
    }

    /**
     * Pourquoi cet écran n'offre pas le formulaire de saisie.
     *
     * L'appro se saisit d'une main et se valide d'une autre : qui n'a ni l'une
     * ni l'autre consulte. Le dire évite de chercher un bouton qui n'existe
     * pas, et de croire l'écran incomplet.
     *
     * @param array<string, mixed> $p
     */
    private static function rappelDesRoles(array $p): string
    {
        $texte = !empty($p['peutValider'])
            ? "Vous validez ici les approvisionnements saisis par la caissière principale. "
                . "Un appro validé entre dans la caisse de l'agence au jour de sa remise."
            : "Vous consultez les approvisionnements. La saisie revient à la caissière principale, "
                . "la validation au comptable — et un appro validé entre dans la caisse de l'agence "
                . "au jour de sa remise, ce que votre comptage du soir doit retrouver.";

        return '<p class="lbp-appro-rappel">' . View::e($texte) . '</p>';
    }

    /** @param array<string, mixed> $p */
    private static function formulaire(array $p): string
    {
        $sources = [];
        foreach (Service::SOURCES as $code => $libelle) {
            $sources[] = ['value' => $code, 'label' => $libelle];
        }

        $devises = [];
        foreach (Service::DEVISES as $code => $libelle) {
            $devises[] = ['value' => $code, 'label' => $libelle];
        }

        return '<form method="post" action="' . View::e(View::url('finance/appro-caisse/enregistrer')) . '" class="lbp-appro-formulaire">'
            . Csrf::field()
            . self::filtresCaches($p)
            . '<div class="lbp-appro-grille">'
            . Form::select('agence_id', self::optionsAgences($p, 'Choisir une agence'), '', ['label' => 'Agence approvisionnée', 'id' => 'appro-f-agence'])
            . Form::input('montant', ['label' => 'Montant remis', 'value' => '', 'id' => 'appro-f-montant', 'inputmode' => 'numeric', 'placeholder' => '250 000'])
            . Form::select('devise', $devises, 'XOF', ['label' => 'Monnaie', 'id' => 'appro-f-devise'])
            . Form::input('date_effet', ['label' => 'Date de remise', 'type' => 'date', 'value' => date('Y-m-d'), 'id' => 'appro-f-date'])
            . Form::select('source', $sources, 'SIEGE', ['label' => 'Provenance', 'id' => 'appro-f-source'])
            . '</div>'
            . Form::input('motif', ['label' => 'Motif — ce que l\'agence lira au comptage', 'value' => '', 'id' => 'appro-f-motif', 'placeholder' => 'Fonds de caisse pour la semaine'])
            . '<div class="lbp-appro-actions">'
            . '<span class="lbp-appro-note">L\'appro n\'entre dans la caisse qu\'après validation du comptable.</span>'
            . '<button type="submit" class="rh-filter-btn rh-filter-btn--primary">' . self::icone('ajouter') . 'Enregistrer l\'approvisionnement</button>'
            . '</div></form>';
    }

    /** @param array<string, mixed> $p */
    private static function tableau(array $p): string
    {
        if ($p['lignes'] === []) {
            return Ui::emptyState(
                'Aucun approvisionnement sur cette période',
                'Élargissez les dates, ou changez le filtre d\'état.'
            );
        }

        $corps = '';
        foreach ($p['lignes'] as $ligne) {
            $corps .= self::ligne($ligne, $p);
        }

        return '<div class="lbp-appro-table-enveloppe"><table class="finea-table lbp-appro-table">'
            . '<thead><tr>'
            . '<th>N°</th><th>Date de remise</th><th>Agence</th><th class="lbp-appro-droite">Montant</th>'
            . '<th>Provenance</th><th>Motif</th><th>État</th><th>Saisi / validé par</th>'
            . (!empty($p['peutValider']) ? '<th>Décision</th>' : '')
            . '</tr></thead><tbody>' . $corps . '</tbody></table></div>';
    }

    /**
     * @param array<string, mixed> $l
     * @param array<string, mixed> $p
     */
    private static function ligne(array $l, array $p): string
    {
        $statut = (string) $l['statut'];
        $classe = 'lbp-appro-ligne' . ($statut === 'en_attente' ? ' is-attente' : ($statut === 'rejetee' ? ' is-rejete' : ''));

        $signatures = View::e((string) ($l['demandeur'] ?? '—'))
            . self::sous($l['validateur'] ? 'validé par ' . (string) $l['validateur'] : '');

        $motif = trim((string) ($l['motif'] ?? ''));
        $rejet = trim((string) ($l['motif_rejet'] ?? ''));

        return '<tr class="' . $classe . '">'
            . '<td class="lbp-appro-mono">' . View::e((string) $l['numero']) . '</td>'
            . '<td class="lbp-appro-mono">' . View::e(self::date((string) $l['date_effet'])) . '</td>'
            . '<td>' . View::e((string) ($l['agence'] ?? '—')) . '</td>'
            . '<td class="lbp-appro-droite lbp-appro-mono"><strong>' . View::e(self::montant((float) $l['montant'])) . '</strong>'
            . self::sous(Service::DEVISES[(string) ($l['devise'] ?? 'XOF')] ?? (string) $l['devise']) . '</td>'
            . '<td>' . View::e(Service::SOURCES[(string) $l['source']] ?? (string) $l['source']) . '</td>'
            . '<td><span class="lbp-appro-motif">' . View::e($motif) . '</span>'
            . ($rejet !== '' ? self::sous('Rejet : ' . $rejet) : '') . '</td>'
            . '<td>' . Ui::badge(Service::STATUTS[$statut] ?? $statut, self::TONS[$statut] ?? 'neutral') . '</td>'
            . '<td>' . $signatures . '</td>'
            . (!empty($p['peutValider']) ? '<td>' . self::decision($l, $p) . '</td>' : '')
            . '</tr>';
    }

    /**
     * @param array<string, mixed> $l
     * @param array<string, mixed> $p
     */
    private static function decision(array $l, array $p): string
    {
        if ((string) $l['statut'] !== 'en_attente') {
            return '<span class="lbp-appro-vide">—</span>';
        }

        $id = (int) $l['id'];
        $caches = Csrf::field() . self::filtresCaches($p);

        return '<div class="lbp-appro-decision">'
            . '<form method="post" action="' . View::e(View::url('finance/appro-caisse/' . $id . '/valider')) . '">'
            . $caches
            . '<button type="submit" class="lbp-appro-valider">Valider</button>'
            . '</form>'
            . '<form method="post" action="' . View::e(View::url('finance/appro-caisse/' . $id . '/rejeter')) . '" class="lbp-appro-rejet">'
            . $caches
            . '<input class="finea-input" type="text" name="motif_rejet" placeholder="Motif du rejet" required>'
            . '<button type="submit" class="lbp-appro-rejeter">Rejeter</button>'
            . '</form></div>';
    }

    /** @param array<string, mixed> $p */
    private static function filtresCaches(array $p): string
    {
        $html = '';
        foreach (['du', 'au', 'agence_id', 'statut'] as $champ) {
            $valeur = (string) ($p['filtres'][$champ] ?? '');
            if ($valeur !== '' && $valeur !== '0') {
                $html .= '<input type="hidden" name="f_' . $champ . '" value="' . View::e($valeur) . '">';
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $p
     * @return array<int, array{value:string, label:string}>
     */
    private static function optionsAgences(array $p, string $premier): array
    {
        $options = [['value' => '', 'label' => $premier]];
        foreach ($p['agences'] as $agence) {
            $options[] = ['value' => (string) $agence['id'], 'label' => (string) $agence['name']];
        }

        return $options;
    }

    // ------------------------------------------------------------------

    private static function sous(string $texte): string
    {
        return $texte === '' ? '' : '<span class="lbp-appro-sous">' . View::e($texte) . '</span>';
    }

    private static function montant(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }

    private static function date(string $date): string
    {
        $objet = date_create($date);

        return $objet === false ? $date : $objet->format('d/m/Y');
    }

    private static function icone(string $nom): string
    {
        return '<svg class="lbp-appro-icone" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . (self::ICONES[$nom] ?? '') . '</svg>';
    }

    private static function styles(): string
    {
        return '<style>'
            . '.lbp-appro-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0}'
            . '.lbp-appro-kpi{background:#fff;border:1px solid #e3e6ea;border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px}'
            . '.lbp-appro-kpi strong{font-size:26px;font-weight:700;letter-spacing:-.02em;line-height:1.1}'
            . '.lbp-appro-kpi-libelle{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472}'
            . '.lbp-appro-kpi-detail{font-size:12px;color:#5b6472}'
            . '.lbp-appro-kpi.is-alerte strong{color:#b54708}'
            . '.lbp-appro-kpi.is-sombre{background:#0f172a;border-color:#0f172a}'
            . '.lbp-appro-kpi.is-sombre strong{color:#fff}'
            . '.lbp-appro-kpi.is-sombre .lbp-appro-kpi-libelle,.lbp-appro-kpi.is-sombre .lbp-appro-kpi-detail{color:#94a3b8}'
            . '.lbp-appro-grille{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}'
            . '.lbp-appro-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:12px;flex-wrap:wrap}'
            . '.lbp-appro-note{font-size:12px;color:#5b6472}'
            . '.lbp-appro-rappel{margin:0 0 18px;padding:12px 16px;border:1px solid #dbe3ef;border-left:4px solid #1e3a5f;border-radius:10px;background:#f8fafc;color:#334155;font-size:13px;line-height:1.55}'
            . '.lbp-appro-table-enveloppe{overflow-x:auto}'
            . '.lbp-appro-table{width:100%;font-size:13px}'
            . '.lbp-appro-table th{white-space:nowrap}'
            . '.lbp-appro-droite{text-align:right}'
            . '.lbp-appro-mono{font-family:Consolas,"SF Mono",monospace;font-variant-numeric:tabular-nums;white-space:nowrap}'
            . '.lbp-appro-sous{display:block;font-size:11px;color:#8b94a1}'
            . '.lbp-appro-motif{display:block;max-width:34ch;color:#475569}'
            . '.lbp-appro-vide{color:#8b94a1}'
            . '.lbp-appro-ligne.is-attente{background:#fffbf5;box-shadow:inset 3px 0 0 #b54708}'
            . '.lbp-appro-ligne.is-rejete td{color:#94a3b8}'
            . '.lbp-appro-decision{display:flex;flex-direction:column;gap:6px;min-width:190px}'
            . '.lbp-appro-rejet{display:flex;gap:6px}'
            . '.lbp-appro-rejet input{flex:1 1 90px;min-width:0;font-size:12px}'
            . '.lbp-appro-valider,.lbp-appro-rejeter{border:0;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer}'
            . '.lbp-appro-valider{background:#027a48;color:#fff}'
            . '.lbp-appro-rejeter{background:#fff;color:#b42318;border:1px solid #fecdca}'
            . '@media (max-width:850px){.lbp-appro-motif{max-width:none}}'
            . '</style>';
    }
}
