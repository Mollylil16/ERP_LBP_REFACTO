<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use DateTimeImmutable;

/**
 * Rapports d'activite par agence, au jour et au mois.
 *
 * Les deux vues partagent la meme structure, et le controleur rend le meme
 * fichier avec un drapeau. Le composant expose donc deux entrees distinctes :
 * l'ecran journalier et l'ecran mensuel ne montrent pas les memes colonnes et
 * n'ont pas les memes exports.
 */
final class ColisageRapports
{
    // ==================================================================
    // Rapport journalier
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $sites
     * @param array<int, array<string, mixed>> $rapportColis
     * @param array<int, array<string, mixed>> $creditsMap
     * @param array<string, mixed> $totaux
     */
    public static function journalierPage(
        string $date,
        ?int $agenceId,
        array $sites,
        array $rapportColis,
        array $creditsMap,
        array $totaux,
        bool $peutExporterExcel
    ): string {
        $parametres = self::parametres(['date' => $date, 'agence_id' => $agenceId]);

        $actions = Ui::button('Vue mensuelle', [
            'href' => 'colisage/rapports/mensuel?' . self::parametres([
                'mois' => substr($date, 0, 7),
                'agence_id' => $agenceId,
            ]),
            'variant' => 'ghost',
        ])
            . Ui::button('Export PDF', ['href' => 'colisage/rapports/export-pdf?' . $parametres, 'variant' => 'secondary']);

        if ($peutExporterExcel) {
            $actions .= Ui::button('Export Excel', [
                'href' => 'colisage/rapports/export-csv?' . $parametres,
                'variant' => 'success',
            ]);
        }

        $entete = Ui::pageHeader(
            'Rapport journalier par agence',
            'Journée du ' . self::jour($date) . '.',
            ['eyebrow' => 'Colisage • Reporting', 'class' => 'rh-hero-white', 'actions' => $actions]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . ($totaux !== [] ? Dashboard::kpis(self::kpisJournee($totaux)) : '')
            . '<div style="margin-bottom:1.5rem;">' . self::filtres($sites, $agenceId, $date, null) . '</div>'
            . Ui::section(
                'Détail par agence',
                self::tableAgences($rapportColis, $creditsMap, $totaux),
                self::jour($date)
            )
            . self::navigationDates($date, $agenceId)
            . '</div></div>';
    }

    /**
     * @param array<string, mixed> $totaux
     * @return array<int, array<string, mixed>>
     */
    private static function kpisJournee(array $totaux): array
    {
        $horsDelai = (int) ($totaux['nb_hors_delai'] ?? 0);
        $nonRegle = (float) ($totaux['credits_non_regle_xof'] ?? 0);

        return [
            [
                'label' => 'Colis reçus',
                'value' => number_format((int) ($totaux['nb_colis'] ?? 0), 0, ',', ' '),
                'meta' => self::kg((float) ($totaux['poids_total'] ?? 0)) . ' au total',
            ],
            [
                // Les deux devises restent separees : les additionner donnerait
                // un chiffre faux.
                'label' => 'Chiffre d\'affaires XOF',
                'value' => self::xof((float) ($totaux['ca_xof'] ?? 0)),
                'meta' => 'Colis facturés en francs CFA',
            ],
            [
                'label' => 'Chiffre d\'affaires EUR',
                'value' => self::eur((float) ($totaux['ca_eur'] ?? 0)),
                'meta' => 'Colis facturés en euros',
            ],
            [
                'label' => 'Colis hors délai',
                'value' => number_format($horsDelai, 0, ',', ' '),
                'meta' => 'Au-delà de la date limite de retrait',
                'tone' => $horsDelai > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Crédits non réglés',
                'value' => self::xof($nonRegle),
                'meta' => 'Encours inter-agences',
                'tone' => $nonRegle > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Crédits réglés ce jour',
                'value' => self::xof((float) ($totaux['credits_regle_xof_jour'] ?? 0)),
                'meta' => 'Compensations encaissées',
                'tone' => 'success',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rapportColis
     * @param array<int, array<string, mixed>> $creditsMap
     * @param array<string, mixed> $totaux
     */
    private static function tableAgences(array $rapportColis, array $creditsMap, array $totaux): string
    {
        $lignes = [];
        foreach ($rapportColis as $ligne) {
            $credits = $creditsMap[(int) $ligne['agence_id']] ?? [];
            $nonRegle = (float) ($credits['credits_non_regle_xof'] ?? 0);
            $regleJour = (float) ($credits['credits_regle_xof_jour'] ?? 0);
            $horsDelai = (int) $ligne['nb_hors_delai'];
            $nbNonRegle = (int) ($credits['nb_credits_non_regle'] ?? 0);
            $caEur = (float) $ligne['ca_eur'];

            $lignes[] = [
                '<strong>' . View::e((string) $ligne['agence_name']) . '</strong>',
                Ui::badge(number_format((int) $ligne['nb_colis'], 0, ',', ' '), 'info'),
                View::e(number_format((int) $ligne['nb_retires'], 0, ',', ' ')),
                ModuleTable::montant((float) $ligne['poids_total'], 'kg', 1),
                ModuleTable::montant((float) $ligne['ca_xof']),
                $caEur > 0 ? ModuleTable::montant($caEur, 'EUR', 2) : '<small>—</small>',
                $horsDelai > 0
                    ? Ui::badge((string) $horsDelai, 'danger')
                    : Ui::badge('0', 'success'),
                $nonRegle > 0
                    ? ModuleTable::montant($nonRegle)
                        . ($nbNonRegle > 0 ? '<br><small>' . $nbNonRegle . ' crédit(s)</small>' : '')
                    : Ui::badge('0', 'success'),
                $regleJour > 0 ? ModuleTable::montant($regleJour) : '<small>—</small>',
            ];
        }

        // Ligne de totaux, ajoutee au corps du tableau : ModuleTable ne rend pas
        // de pied, et un total qui disparait au defilement ne sert a personne.
        if ($totaux !== [] && $lignes !== []) {
            $lignes[] = [
                '<strong>TOTAUX</strong>',
                '<strong>' . View::e(number_format((int) $totaux['nb_colis'], 0, ',', ' ')) . '</strong>',
                '<small>—</small>',
                '<strong>' . ModuleTable::montant((float) $totaux['poids_total'], 'kg', 1) . '</strong>',
                '<strong>' . ModuleTable::montant((float) $totaux['ca_xof']) . '</strong>',
                '<strong>' . ModuleTable::montant((float) $totaux['ca_eur'], 'EUR', 2) . '</strong>',
                '<strong>' . View::e(number_format((int) $totaux['nb_hors_delai'], 0, ',', ' ')) . '</strong>',
                '<strong>' . ModuleTable::montant((float) $totaux['credits_non_regle_xof']) . '</strong>',
                '<strong>' . ModuleTable::montant((float) $totaux['credits_regle_xof_jour']) . '</strong>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Agence'],
                ['label' => 'Colis reçus', 'align' => 'center'],
                ['label' => 'Retirés', 'align' => 'right'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'CA XOF', 'align' => 'right'],
                ['label' => 'CA EUR', 'align' => 'right'],
                ['label' => 'Hors délai', 'align' => 'center'],
                ['label' => 'Crédits non réglés', 'align' => 'right'],
                ['label' => 'Crédits réglés', 'align' => 'right'],
            ],
            $lignes,
            'Aucune activité',
            'Aucun colis n\'a été enregistré ce jour-là sur ce périmètre.'
        );
    }

    private static function navigationDates(string $date, ?int $agenceId): string
    {
        $jour = new DateTimeImmutable($date);
        $hier = $jour->modify('-1 day')->format('Y-m-d');
        $demain = $jour->modify('+1 day')->format('Y-m-d');

        $liens = Ui::button(
            self::court($hier),
            ['href' => 'colisage/rapports?' . self::parametres(['date' => $hier, 'agence_id' => $agenceId]), 'variant' => 'secondary']
        )
            . Ui::button(
                'Aujourd\'hui',
                ['href' => 'colisage/rapports?' . self::parametres(['date' => date('Y-m-d'), 'agence_id' => $agenceId]), 'variant' => 'primary']
            );

        // Pas de lien vers demain : le rapport d'une journee a venir est vide.
        if ($demain <= date('Y-m-d')) {
            $liens .= Ui::button(
                self::court($demain),
                ['href' => 'colisage/rapports?' . self::parametres(['date' => $demain, 'agence_id' => $agenceId]), 'variant' => 'secondary']
            );
        }

        return '<div class="rapport-navigation">' . $liens . '</div>'
            . '<style>.rapport-navigation{display:flex;gap:.75rem;justify-content:center;'
            . 'flex-wrap:wrap;margin-top:1.5rem;}</style>';
    }

    // ==================================================================
    // Rapport mensuel
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $sites
     * @param array<int, array<string, mixed>> $journaliers
     */
    public static function mensuelPage(
        string $mois,
        ?int $agenceId,
        array $sites,
        array $journaliers
    ): string {
        $entete = Ui::pageHeader(
            'Rapport mensuel par agence',
            'Mois de ' . self::mois($mois . '-01') . '.',
            [
                'eyebrow' => 'Colisage • Reporting',
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Vue journalière', [
                    'href' => 'colisage/rapports?' . self::parametres([
                        'date' => date('Y-m-d'),
                        'agence_id' => $agenceId,
                    ]),
                    'variant' => 'ghost',
                ]),
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . ($journaliers !== [] ? Dashboard::kpis(self::kpisMois($journaliers)) : '')
            . '<div style="margin-bottom:1.5rem;">' . self::filtres($sites, $agenceId, null, $mois) . '</div>'
            . Ui::section(
                'Points journaliers',
                self::tableJournaliers($journaliers),
                self::mois($mois . '-01')
            )
            . '</div></div>';
    }

    /**
     * @param array<int, array<string, mixed>> $journaliers
     * @return array<int, array<string, mixed>>
     */
    private static function kpisMois(array $journaliers): array
    {
        $colis = array_sum(array_map(static fn(array $j): int => (int) $j['nb_colis'], $journaliers));
        $poids = array_sum(array_map(static fn(array $j): float => (float) $j['poids'], $journaliers));
        $xof = array_sum(array_map(static fn(array $j): float => (float) $j['ca_xof'], $journaliers));
        $eur = array_sum(array_map(static fn(array $j): float => (float) $j['ca_eur'], $journaliers));
        $jours = count(array_unique(array_map(static fn(array $j): string => (string) $j['jour'], $journaliers)));

        return [
            [
                'label' => 'Colis du mois',
                'value' => number_format($colis, 0, ',', ' '),
                'meta' => $jours . ' journée(s) d\'activité',
            ],
            [
                'label' => 'Poids total',
                'value' => self::kg($poids),
                'meta' => 'Cumul sur le mois',
            ],
            [
                'label' => 'Chiffre d\'affaires XOF',
                'value' => self::xof($xof),
                'meta' => $jours > 0 ? 'Soit ' . self::xof($xof / $jours) . ' par jour actif' : '—',
            ],
            [
                'label' => 'Chiffre d\'affaires EUR',
                'value' => self::eur($eur),
                'meta' => 'Colis facturés en euros',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $journaliers
     */
    private static function tableJournaliers(array $journaliers): string
    {
        $lignes = [];
        $jourPrecedent = '';

        foreach ($journaliers as $point) {
            $jour = (string) $point['jour'];
            $nouveau = $jour !== $jourPrecedent;
            $jourPrecedent = $jour;

            $lignes[] = [
                // Le jour n'est ecrit qu'une fois par groupe : les agences d'une
                // meme journee se lisent alors comme un bloc.
                $nouveau ? '<strong>' . View::e(self::jour($jour)) . '</strong>' : '',
                View::e((string) $point['agence_name']),
                View::e(number_format((int) $point['nb_colis'], 0, ',', ' ')),
                ModuleTable::montant((float) $point['poids'], 'kg', 1),
                ModuleTable::montant((float) $point['ca_xof']),
                (float) $point['ca_eur'] > 0
                    ? ModuleTable::montant((float) $point['ca_eur'], 'EUR', 2)
                    : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Jour'],
                ['label' => 'Agence'],
                ['label' => 'Colis', 'align' => 'center'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'CA XOF', 'align' => 'right'],
                ['label' => 'CA EUR', 'align' => 'right'],
            ],
            $lignes,
            'Aucune activité ce mois-ci',
            'Aucun colis n\'a été enregistré sur ce mois et ce périmètre.'
        );
    }

    // ==================================================================
    // Briques communes
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $sites
     */
    private static function filtres(array $sites, ?int $agenceId, ?string $date, ?string $mois): string
    {
        $options = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($sites as $site) {
            $options[] = ['value' => (string) $site['id'], 'label' => (string) $site['name']];
        }

        $action = $mois !== null ? 'colisage/rapports/mensuel' : 'colisage/rapports';

        $periode = $mois !== null
            ? Form::input('mois', ['label' => 'Mois', 'type' => 'month', 'value' => $mois])
            : Form::input('date', ['label' => 'Date', 'type' => 'date', 'value' => (string) $date]);

        return Ui::section(
            'Période',
            '<form method="get" action="' . View::url($action) . '">'
                . '<div class="rh-form-grid" style="gap:1rem;">'
                . $periode
                . Form::select('agence_id', $options, (string) ($agenceId ?? ''), ['label' => 'Agence'])
                . '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
                . Ui::button('Afficher', ['variant' => 'primary', 'type' => 'submit'])
                . '</div></div></form>'
        );
    }

    /**
     * @param array<string, mixed> $parametres
     */
    private static function parametres(array $parametres): string
    {
        return http_build_query(array_filter(
            $parametres,
            static fn(mixed $valeur): bool => $valeur !== null && $valeur !== ''
        ));
    }

    private static function jour(string $date): string
    {
        $horodatage = strtotime($date);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }

    private static function court(string $date): string
    {
        $horodatage = strtotime($date);

        return $horodatage !== false ? date('d/m', $horodatage) : '—';
    }

    /**
     * Mois en toutes lettres, en francais.
     *
     * date('F') renvoie toujours l'anglais : le rapport mensuel affichait
     * « September 2026 » dans une interface francaise.
     */
    private static function mois(string $date): string
    {
        $horodatage = strtotime($date);
        if ($horodatage === false) {
            return '—';
        }

        $noms = View::monthNames();

        return ($noms[(int) date('n', $horodatage)] ?? '') . ' ' . date('Y', $horodatage);
    }

    private static function xof(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ') . ' XOF';
    }

    private static function eur(float $valeur): string
    {
        return number_format($valeur, 2, ',', ' ') . ' EUR';
    }

    private static function kg(float $valeur): string
    {
        return number_format($valeur, 1, ',', ' ') . ' kg';
    }
}
