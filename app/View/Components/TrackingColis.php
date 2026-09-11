<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class TrackingColis
{
    /**
     * @param array<string, mixed> $donnees
     */
    public static function suiviPage(array $donnees, string $agenceLabel): string
    {
        $fenetre = (int) $donnees['fenetre'];

        $entete = Ui::pageHeader(
            'Tracking Colis',
            'Tout ce qui est arrivé à un colis, quelle que soit l\'équipe qui l\'a enregistré, '
                . 'et les recherches du site public restées sans réponse.',
            [
                'eyebrow' => 'TRK • ' . $agenceLabel,
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Recherche Call Center', ['href' => 'call-center/recherche-colis', 'variant' => 'secondary'])
                    . Ui::button('Suivi GPS', ['href' => 'colisage/exploitation/tracking', 'variant' => 'ghost']),
            ]
        );

        $fiche = is_array($donnees['fiche'] ?? null)
            ? self::bloc(self::fiche((array) $donnees['fiche']))
            : '';

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis((array) $donnees['kpis'])
            . self::bloc(self::recherche((string) $donnees['recherche']))
            . $fiche
            . self::bloc(Ui::section(
                'Recherches sans réponse sur le site public',
                self::tableEchecs((array) $donnees['echecs']),
                $fenetre . ' derniers jours'
            ))
            . self::bloc(Ui::section(
                'Répartition des colis',
                self::tableStatuts((array) $donnees['statuts'])
            ))
            . Ui::section(
                'Colis en cours',
                self::tableColis((array) $donnees['colis']),
                count((array) $donnees['colis']) . ' envoi(s)'
            )
            . '</div></div>';
    }

    private static function bloc(string $contenu): string
    {
        return '<div style="margin-bottom:1.5rem;">' . $contenu . '</div>';
    }

    private static function recherche(string $valeur): string
    {
        return Ui::section(
            'Rechercher un colis',
            '<form method="get" action="' . View::url('tracking-colis/dashboard') . '">'
                . '<div class="rh-form-grid" style="gap:1rem; align-items:end;">'
                . Form::input('q', [
                    'label' => 'Référence, expéditeur, destinataire ou téléphone',
                    'value' => $valeur,
                    'placeholder' => 'Saisissez la référence exacte pour ouvrir la fiche complète',
                ])
                . '<div class="finea-field">' . Ui::button('Rechercher', ['variant' => 'primary', 'type' => 'submit']) . '</div>'
                . '</div></form>'
        );
    }

    /**
     * @param array<string, mixed> $fiche
     */
    private static function fiche(array $fiche): string
    {
        $colis = (array) $fiche['colis'];
        $evenements = (array) $fiche['evenements'];

        $identite = ModuleTable::render(
            [['label' => 'Information'], ['label' => 'Valeur']],
            [
                ['Expéditeur', View::e((string) ($colis['expediteur'] ?? '—'))
                    . ($colis['expediteur_tel'] ? ' — ' . View::e((string) $colis['expediteur_tel']) : '')],
                ['Destinataire', View::e((string) ($colis['destinataire'] ?? '—'))
                    . ($colis['destinataire_tel'] ? ' — ' . View::e((string) $colis['destinataire_tel']) : '')],
                ['Trajet', View::e((string) ($colis['agence_depart'] ?? '—') . ' → ' . (string) ($colis['agence_arrivee'] ?? '—'))],
                ['Contenu', View::e($colis['nombre_colis'] . ' colis, ' . $colis['poids_total'] . ' kg')],
                ['Statut', Ui::badge((string) $colis['statut'], 'info')],
                ['Pris en charge le', View::e(self::date((string) $colis['created_at'], true))],
                ['Retiré par', $colis['recup_nom']
                    ? View::e((string) $colis['recup_nom'] . ' le ' . self::date((string) $colis['recup_date_heure'], true))
                    : '<small>Pas encore retiré</small>'],
            ]
        );

        return Ui::section(
            'Colis ' . (string) $colis['numero_tracking'],
            '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem;">'
                . '<div>' . $identite . '</div>'
                . '<div>' . self::timeline($evenements) . '</div>'
                . '</div>',
            count($evenements) . ' événement(s) enregistré(s)'
        );
    }

    /** @param array<int, array<string, mixed>> $evenements */
    private static function timeline(array $evenements): string
    {
        if ($evenements === []) {
            return Ui::emptyState(
                'Aucun événement',
                'Ce colis n\'a aucun point de suivi : ni passage en rayon, ni position GPS.'
            );
        }

        $lignes = [];
        foreach ($evenements as $e) {
            $position = $e['latitude'] !== null && $e['longitude'] !== null
                ? '<br><small>' . View::e($e['latitude'] . ', ' . $e['longitude']) . '</small>'
                : '';

            $lignes[] = [
                View::e(self::date((string) $e['survenu_le'], true)),
                Ui::badge((string) $e['source'], (string) $e['source'] === 'GPS' ? 'info' : 'neutral'),
                '<strong>' . View::e((string) $e['libelle']) . '</strong>' . $position
                    . ($e['detail'] ? '<br><small>' . View::e((string) $e['detail']) . '</small>' : ''),
                View::e((string) ($e['auteur'] ?? '—')),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Quand'],
                ['label' => 'Source', 'align' => 'center'],
                ['label' => 'Événement'],
                ['label' => 'Par'],
            ],
            $lignes
        );
    }

    /** @param array<int, array<string, mixed>> $echecs */
    private static function tableEchecs(array $echecs): string
    {
        $lignes = [];
        foreach ($echecs as $e) {
            $existe = (int) $e['existe_aujourdhui'] === 1;

            $lignes[] = [
                '<strong>' . View::e((string) $e['reference']) . '</strong>',
                View::e((string) $e['nb_tentatives']),
                View::e((string) $e['nb_visiteurs']),
                View::e(self::date((string) $e['derniere_tentative'], true)),
                $existe
                    // La référence existe désormais : la recherche a eu lieu
                    // avant l'enregistrement du colis, ce n'est pas une erreur.
                    ? Ui::badge('Enregistré depuis', 'success')
                    : Ui::badge('Introuvable', 'danger'),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Référence cherchée'],
                ['label' => 'Tentatives', 'align' => 'center'],
                ['label' => 'Visiteurs', 'align' => 'center'],
                ['label' => 'Dernière tentative'],
                ['label' => 'Aujourd\'hui', 'align' => 'center'],
            ],
            $lignes,
            'Aucune recherche infructueuse',
            'Toutes les recherches du site public ont abouti sur la période.'
        );
    }

    /** @param array<int, array<string, mixed>> $statuts */
    private static function tableStatuts(array $statuts): string
    {
        $lignes = [];
        foreach ($statuts as $s) {
            $lignes[] = [
                Ui::badge((string) $s['statut'], match ((string) $s['statut']) {
                    'LIVRÉ', 'RETIRÉ' => 'success',
                    'EN_TRANSIT' => 'info',
                    default => 'neutral',
                }),
                View::e((string) $s['nb_envois']),
                View::e((string) $s['nb_colis']),
                ModuleTable::montant((float) $s['poids_kg'], 'kg'),
                View::e(round((float) $s['age_moyen_jours']) . ' j'),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Statut', 'align' => 'center'],
                ['label' => 'Envois', 'align' => 'center'],
                ['label' => 'Colis', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Âge moyen', 'align' => 'center'],
            ],
            $lignes,
            'Aucun colis',
            'Aucun colis n\'est enregistré sur ce périmètre.'
        );
    }

    /** @param array<int, array<string, mixed>> $colis */
    private static function tableColis(array $colis): string
    {
        $lignes = [];
        foreach ($colis as $c) {
            $derniere = $c['a_trace']
                ? '<strong>' . View::e((string) $c['derniere_etape']) . '</strong>'
                    . '<br><small>' . View::e(self::date((string) $c['derniere_etape_le'], true)) . '</small>'
                : Ui::badge('Aucune trace', 'warning');

            $lignes[] = [
                '<a href="' . View::url('tracking-colis/dashboard?q=' . urlencode((string) $c['numero_tracking'])) . '">'
                    . '<strong>' . View::e((string) $c['numero_tracking']) . '</strong></a>',
                View::e((string) ($c['destinataire'] ?? '—'))
                    . ($c['destinataire_tel'] ? '<br><small>' . View::e((string) $c['destinataire_tel']) . '</small>' : ''),
                View::e((string) ($c['agence_depart'] ?? '—') . ' → ' . (string) ($c['agence_arrivee'] ?? '—')),
                Ui::badge((string) $c['statut'], 'info'),
                $derniere,
                Ui::badge(
                    (int) $c['jours_depuis_prise_en_charge'] . ' j',
                    $c['immobilise'] ? 'danger' : 'neutral'
                ),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Référence'],
                ['label' => 'Destinataire'],
                ['label' => 'Trajet'],
                ['label' => 'Statut', 'align' => 'center'],
                ['label' => 'Dernier événement'],
                ['label' => 'Ancienneté', 'align' => 'center'],
            ],
            $lignes,
            'Aucun colis en cours',
            'Aucun colis actif ne correspond à ce périmètre ou à cette recherche.'
        );
    }

    private static function date(string $valeur, bool $avecHeure = false): string
    {
        if ($valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date($avecHeure ? 'd/m/Y H:i' : 'd/m/Y', $horodatage) : '—';
    }
}
