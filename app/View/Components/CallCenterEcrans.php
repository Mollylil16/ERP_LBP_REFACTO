<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

/**
 * Ecrans du Call Center.
 *
 * Le composant CallCenter existant porte la recherche de colis ; celui-ci
 * accueille les ecrans de pilotage et de suivi, pour ne pas laisser grossir
 * indefiniment un seul fichier.
 *
 * Tous les liens passent par View::url. Les vues d'origine ecrivaient des
 * chemins absolus - href="/call-center/appels" - qui pointaient a cote des que
 * l'ERP n'est pas servi a la racine du domaine, ce qui est le cas en local.
 */
final class CallCenterEcrans
{
    /** Couleurs de statut d'appel, communes a tous les ecrans du module. */
    private const TONS_APPEL = [
        'traite' => 'success',
        'en_cours' => 'warning',
        'a_rappeler' => 'info',
    ];

    /** Couleurs de gravite d'un litige, de la plus grave a la plus benigne. */
    private const TONS_GRAVITE = [
        'critique' => 'danger',
        'elevee' => 'warning',
        'moyenne' => 'warning',
        'faible' => 'success',
    ];

    private const TONS_LITIGE = [
        'resolu' => 'success',
        'nouveau' => 'danger',
        'en_cours' => 'warning',
        'annule' => 'neutral',
    ];

    // ==================================================================
    // Tableau de bord
    // ==================================================================

    /**
     * @param array<string, mixed> $kpis
     * @param array<int, array<string, mixed>> $recentAppels
     * @param array<int, array<string, mixed>> $recentLitiges
     */
    public static function tableauDeBordPage(
        array $kpis,
        array $recentAppels,
        array $recentLitiges,
        bool $peutGerer
    ): string {
        $actions = Ui::button('Vue rayons temps réel', ['href' => 'call-center/rayons', 'variant' => 'secondary']);
        if ($peutGerer) {
            $actions .= Ui::button('Enregistrer un appel', ['href' => 'call-center/appels', 'variant' => 'primary'])
                . Ui::button('Ouvrir un litige', ['href' => 'call-center/litiges', 'variant' => 'danger']);
        }

        $entete = Ui::pageHeader(
            'Tableau de bord Call Center',
            'Appels clients, réclamations et assistance en temps réel.',
            ['eyebrow' => 'CAL • Support client', 'class' => 'rh-hero-white', 'actions' => $actions]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis(self::kpis($kpis))
            . '<div class="cc-deux-colonnes">'
            . Ui::section(
                'Derniers appels',
                self::tableAppelsRecents($recentAppels),
                'Voir le journal complet'
            )
            . Ui::section(
                'Litiges récents',
                self::tableLitigesRecents($recentLitiges),
                'Voir tous les litiges'
            )
            . '</div>'
            . self::styles()
            . '</div></div>';
    }

    /**
     * @param array<string, mixed> $kpis
     * @return array<int, array<string, mixed>>
     */
    private static function kpis(array $kpis): array
    {
        return [
            [
                'label' => 'Total appels',
                'value' => (string) ($kpis['total_appels'] ?? 0),
                'meta' => 'Depuis l\'ouverture du journal',
                'href' => 'call-center/appels',
            ],
            [
                'label' => 'Appels aujourd\'hui',
                'value' => (string) ($kpis['appels_aujourdhui'] ?? 0),
                'meta' => 'Sur la journée en cours',
                'href' => 'call-center/appels',
            ],
            [
                'label' => 'Satisfaction moyenne',
                'value' => ($kpis['avg_satisfaction'] ?? '0') . ' / 5',
                'meta' => 'Note laissée par les clients',
            ],
            [
                'label' => 'Litiges ouverts',
                'value' => (string) ($kpis['open_litiges'] ?? 0),
                'meta' => 'Réclamations en cours',
                'tone' => 'danger',
                'href' => 'call-center/litiges',
            ],
            [
                'label' => 'Nouveaux litiges',
                'value' => (string) ($kpis['new_litiges'] ?? 0),
                'meta' => 'Pas encore pris en charge',
                'href' => 'call-center/litiges',
            ],
            [
                'label' => 'Taux de résolution',
                'value' => ($kpis['resolution_rate'] ?? '0') . ' %',
                'meta' => 'Litiges résolus sur litiges ouverts',
                'tone' => 'success',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $appels */
    private static function tableAppelsRecents(array $appels): string
    {
        $lignes = [];
        foreach ($appels as $appel) {
            $statut = (string) ($appel['statut'] ?? '');

            $lignes[] = [
                '<strong>' . View::e((string) ($appel['client_name'] ?? '—')) . '</strong>',
                View::e((string) ($appel['type_appel'] ?? '—')),
                View::e((string) ($appel['agent_name'] ?? '—')),
                $statut !== '' ? Ui::badge($statut, self::TONS_APPEL[$statut] ?? 'neutral') : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Client'],
                ['label' => 'Type'],
                ['label' => 'Agent'],
                ['label' => 'Statut', 'align' => 'center'],
            ],
            $lignes,
            'Aucun appel',
            'Aucun appel n\'a encore été consigné dans le journal.'
        );
    }

    /** @param array<int, array<string, mixed>> $litiges */
    private static function tableLitigesRecents(array $litiges): string
    {
        $lignes = [];
        foreach ($litiges as $litige) {
            $gravite = (string) ($litige['gravite'] ?? '');
            $statut = (string) ($litige['statut'] ?? '');

            $lignes[] = [
                '<strong>' . View::e((string) ($litige['client_name'] ?? '—')) . '</strong>',
                View::e((string) ($litige['type_litige'] ?? '—')),
                $gravite !== '' ? Ui::badge($gravite, self::TONS_GRAVITE[$gravite] ?? 'neutral') : '<small>—</small>',
                $statut !== '' ? Ui::badge($statut, self::TONS_LITIGE[$statut] ?? 'neutral') : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Client'],
                ['label' => 'Type'],
                ['label' => 'Gravité', 'align' => 'center'],
                ['label' => 'Statut', 'align' => 'center'],
            ],
            $lignes,
            'Aucun litige',
            'Aucune réclamation n\'a encore été ouverte.'
        );
    }

    // ==================================================================
    // Vue rayons en temps reel
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $sites
     * @param array<int, array<string, mixed>> $rayons
     * @param array<int, array<int, array<string, mixed>>> $colisParRayon
     */
    public static function rayonsPage(
        array $sites,
        array $rayons,
        array $colisParRayon,
        ?int $agenceId,
        int $colisHorsDelai,
        string $dernierRefresh
    ): string {
        $sousTitre = 'Dernière mise à jour : ' . $dernierRefresh
            . ' — la page se rafraîchit seule toutes les 60 secondes.';

        $badges = Ui::badge('Rafraîchissement 60 s', 'warning');
        if ($colisHorsDelai > 0) {
            $badges .= ' ' . Ui::badge($colisHorsDelai . ' colis hors délai', 'danger');
        }

        $entete = Ui::pageHeader(
            'Vue rayons — temps réel',
            $sousTitre,
            [
                'eyebrow' => 'CAL • Occupation des rayons',
                'class' => 'rh-hero-white',
                'badge' => $badges,
                'actions' => Ui::button('Rechercher un colis', ['href' => 'call-center/recherche-colis', 'variant' => 'secondary']),
            ]
        );

        $corps = $rayons === []
            ? Ui::section(
                'Rayons',
                Ui::emptyState(
                    'Aucun rayon configuré' . ($agenceId ? ' pour cette agence' : ''),
                    'Les rayons se paramètrent dans Logistique › Gestion des rayons.'
                ) . '<div style="text-align:center;">'
                    . Ui::button('Configurer les rayons', ['href' => 'logistique/rayons', 'variant' => 'secondary'])
                    . '</div>'
            )
            : self::grillesParAgence($rayons, $colisParRayon);

        return '<meta http-equiv="refresh" content="60">'
            . '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . '<div style="margin-bottom:1.5rem;">' . self::filtreAgence($sites, $agenceId) . '</div>'
            . $corps
            . '<p class="cc-note">Vue en lecture seule, rafraîchie automatiquement.</p>'
            . self::styles()
            . '</div></div>';
    }

    /**
     * @param array<int, array<string, mixed>> $sites
     */
    private static function filtreAgence(array $sites, ?int $agenceId): string
    {
        $options = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($sites as $site) {
            $options[] = ['value' => (string) $site['id'], 'label' => (string) $site['name']];
        }

        return Ui::section(
            'Périmètre',
            '<form method="get" action="' . View::url('call-center/rayons') . '">'
                . '<div class="rh-form-grid" style="gap:1rem; align-items:end;">'
                . Form::select('agence_id', $options, (string) ($agenceId ?? ''), ['label' => 'Agence'])
                . '<div class="finea-field">' . Ui::button('Afficher', ['variant' => 'primary', 'type' => 'submit']) . '</div>'
                . '</div></form>'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rayons
     * @param array<int, array<int, array<string, mixed>>> $colisParRayon
     */
    private static function grillesParAgence(array $rayons, array $colisParRayon): string
    {
        $parAgence = [];
        foreach ($rayons as $rayon) {
            $cle = (string) ($rayon['agence_nom'] ?? ('Agence ' . ($rayon['agence_id'] ?? '?')));
            $parAgence[$cle][] = $rayon;
        }

        $html = '';
        foreach ($parAgence as $agence => $liste) {
            $cartes = '';
            foreach ($liste as $rayon) {
                $cartes .= self::carteRayon($rayon, $colisParRayon[(int) $rayon['id']] ?? []);
            }

            $html .= Ui::section(
                $agence,
                '<div class="cc-rayons">' . $cartes . '</div>',
                count($liste) . ' rayon' . (count($liste) > 1 ? 's' : '')
            );
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $rayon
     * @param array<int, array<string, mixed>> $colis
     */
    private static function carteRayon(array $rayon, array $colis): string
    {
        $occupee = (int) ($rayon['capacite_occupee'] ?? 0);
        $max = (int) ($rayon['capacite_max'] ?? 0);
        $taux = $max > 0 ? min(100, (int) round($occupee / $max * 100)) : 0;

        $enRetard = count(array_filter(
            $colis,
            static fn(array $c): bool => (int) ($c['jours_retard'] ?? 0) > 0
        ));

        // Un rayon en maintenance n'est pas juge sur son remplissage.
        if ((string) ($rayon['statut'] ?? '') === 'MAINTENANCE') {
            [$etat, $ton] = ['Maintenance', 'neutral'];
        } else {
            [$etat, $ton] = match (true) {
                $taux >= 90 => ['Plein', 'danger'],
                $taux >= 70 => ['Chargé', 'warning'],
                $taux >= 40 => ['Actif', 'warning'],
                default => ['Disponible', 'success'],
            };
        }

        $lignes = '';
        foreach (array_slice($colis, 0, 8) as $unColis) {
            $lignes .= self::ligneColis($unColis);
        }

        if ($colis === []) {
            $lignes = '<p class="cc-rayon-vide">Rayon vide</p>';
        } elseif (count($colis) > 8) {
            $lignes .= '<p class="cc-rayon-reste">+ ' . (count($colis) - 8) . ' autre(s) colis</p>';
        }

        return '<article class="cc-rayon">'
            . '<header>'
            . '<div><strong>' . View::e((string) $rayon['code_rayon']) . '</strong>'
            . '<small>' . View::e((string) $rayon['nom_rayon']) . '</small></div>'
            . Ui::badge($etat, $ton)
            . '</header>'
            . '<div class="cc-rayon-jauge">'
            . '<div class="cc-rayon-chiffres"><span>' . $occupee . ' / ' . $max . ' colis</span></div>'
            . ModuleTable::jauge($taux, $ton)
            . ($enRetard > 0 ? '<p class="cc-rayon-retard">' . $enRetard . ' colis hors délai</p>' : '')
            . '</div>'
            . '<div class="cc-rayon-colis">' . $lignes . '</div>'
            . '</article>';
    }

    /**
     * @param array<string, mixed> $colis
     */
    private static function ligneColis(array $colis): string
    {
        $retard = (int) ($colis['jours_retard'] ?? 0);
        $limite = (string) ($colis['date_limite_retrait'] ?? '');

        if ($retard > 0) {
            $marqueur = Ui::badge('+' . $retard . ' j', 'danger');
        } elseif ($limite !== '' && strtotime($limite) !== false) {
            $marqueur = '<small>Limite : ' . View::e(date('d/m', (int) strtotime($limite))) . '</small>';
        } else {
            $marqueur = '';
        }

        $telephone = trim((string) ($colis['destinataire_phone'] ?? ''));

        return '<div class="cc-colis' . ($retard > 0 ? ' is-retard' : '') . '">'
            . '<div>'
            . '<code>' . View::e((string) $colis['numero_tracking']) . '</code>'
            . '<small>' . View::e((string) ($colis['destinataire_nom'] ?? '—'))
            . ($telephone !== '' ? ' · ' . View::e($telephone) : '') . '</small>'
            . '</div>'
            . '<div>' . $marqueur . '</div>'
            . '</div>';
    }

    // ==================================================================
    // Journal des appels
    // ==================================================================

    private const TYPES_APPEL = [
        'information' => 'Information',
        'reclamation' => 'Réclamation',
        'suivi_colis' => 'Suivi colis',
        'autre' => 'Autre',
    ];

    private const LIBELLES_APPEL = [
        'traite' => 'Traité',
        'en_cours' => 'En cours',
        'a_rappeler' => 'À rappeler',
    ];

    /**
     * @param array<int, array<string, mixed>> $appels
     * @param array<int, array<string, mixed>> $clients
     */
    public static function journalAppelsPage(
        array $appels,
        array $clients,
        string $dateDebut,
        string $dateFin,
        string $typeFiltre,
        bool $peutGerer,
        bool $peutSupprimer
    ): string {
        $entete = Ui::pageHeader(
            'Journal des appels',
            count($appels) . ' appel(s) sur la période retenue.',
            [
                'eyebrow' => 'CAL • Relation client',
                'class' => 'rh-hero-white',
                'actions' => $peutGerer
                    ? Ui::button('Enregistrer un appel', [
                        'href' => 'call-center/appels#nouvel-appel',
                        'variant' => 'primary',
                    ])
                    : '',
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . '<div style="margin-bottom:1.5rem;">' . self::filtresAppels($dateDebut, $dateFin, $typeFiltre) . '</div>'
            . ($peutGerer ? '<div style="margin-bottom:1.5rem;">' . self::formulaireAppel($clients) . '</div>' : '')
            . Ui::section('Appels enregistrés', self::tableAppels($appels, $peutSupprimer))
            . self::styles()
            . '</div></div>';
    }

    private static function filtresAppels(string $dateDebut, string $dateFin, string $typeFiltre): string
    {
        $types = [['value' => '', 'label' => 'Tous les types']];
        foreach (self::TYPES_APPEL as $code => $libelle) {
            $types[] = ['value' => $code, 'label' => $libelle];
        }

        return Ui::section(
            'Filtres',
            '<form method="get" action="' . View::url('call-center/appels') . '">'
                . '<div class="rh-form-grid" style="gap:1rem;">'
                . Form::input('date_debut', ['label' => 'Date de début', 'type' => 'date', 'value' => $dateDebut])
                . Form::input('date_fin', ['label' => 'Date de fin', 'type' => 'date', 'value' => $dateFin])
                . Form::select('type_appel', $types, $typeFiltre, ['label' => 'Type d\'appel'])
                . '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
                . Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit'])
                . Ui::button('Réinitialiser', ['href' => 'call-center/appels', 'variant' => 'secondary'])
                . '</div></div></form>'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     */
    private static function formulaireAppel(array $clients): string
    {
        $optionsClients = [['value' => '', 'label' => 'Sélectionner un client']];
        foreach ($clients as $client) {
            $optionsClients[] = ['value' => (string) $client['id'], 'label' => (string) $client['name']];
        }

        $types = [];
        foreach (self::TYPES_APPEL as $code => $libelle) {
            $types[] = ['value' => $code, 'label' => $libelle];
        }

        $statuts = [];
        foreach (self::LIBELLES_APPEL as $code => $libelle) {
            $statuts[] = ['value' => $code, 'label' => $libelle];
        }

        // La note est facultative : tous les appels ne donnent pas lieu a un
        // ressenti exprime par le client.
        $notes = [['value' => '', 'label' => 'Non renseignée']];
        for ($i = 1; $i <= 5; $i++) {
            $notes[] = ['value' => (string) $i, 'label' => $i . ' / 5'];
        }

        return Ui::section(
            'Enregistrer un appel',
            '<form method="post" action="' . View::url('call-center/appels/enregistrer') . '">'
                . \App\Helpers\Csrf::field()
                . '<div class="rh-form-grid" style="gap:1.25rem;">'
                . Form::select('client_id', $optionsClients, '', ['label' => 'Client', 'required' => true])
                . Form::input('numero_tracking', [
                    'label' => 'N° de suivi',
                    'placeholder' => 'LB-CI-0726-001',
                    'hint' => 'Facultatif : renseignez-le si l\'appel porte sur un colis.',
                ])
                . Form::select('type_appel', $types, 'information', ['label' => 'Type d\'appel', 'required' => true])
                . Form::select('statut', $statuts, 'traite', ['label' => 'Statut', 'required' => true])
                . Form::select('satisfaction_score', $notes, '', ['label' => 'Satisfaction'])
                . '</div>'
                . Form::textarea('description', [
                    'label' => 'Description',
                    'rows' => 3,
                    'required' => true,
                    'placeholder' => 'Résumé de l\'appel et suite donnée',
                ])
                . '<div style="text-align:right; margin-top:1rem;">'
                . Ui::button('Enregistrer l\'appel', ['variant' => 'accent', 'type' => 'submit'])
                . '</div></form>',
            '',
            ['id' => 'nouvel-appel']
        );
    }

    /** @param array<int, array<string, mixed>> $appels */
    private static function tableAppels(array $appels, bool $peutSupprimer): string
    {
        $colonnes = [
            ['label' => 'N°', 'align' => 'center'],
            ['label' => 'Client'],
            ['label' => 'Type'],
            ['label' => 'Suivi'],
            ['label' => 'Agent'],
            ['label' => 'Satisfaction', 'align' => 'center'],
            ['label' => 'Statut', 'align' => 'center'],
            ['label' => 'Date'],
        ];

        if ($peutSupprimer) {
            $colonnes[] = ['label' => 'Actions', 'align' => 'center'];
        }

        $lignes = [];
        foreach ($appels as $appel) {
            $type = (string) ($appel['type_appel'] ?? '');
            $statut = (string) ($appel['statut'] ?? '');
            $suivi = trim((string) ($appel['numero_tracking'] ?? ''));

            $ligne = [
                '<small>#' . (int) $appel['id'] . '</small>',
                '<strong>' . View::e((string) ($appel['client_name'] ?? '—')) . '</strong>',
                View::e(self::TYPES_APPEL[$type] ?? $type),
                $suivi !== '' ? '<code>' . View::e($suivi) . '</code>' : '<small>—</small>',
                View::e((string) ($appel['agent_name'] ?? '—')),
                self::etoiles(isset($appel['satisfaction_score']) ? (int) $appel['satisfaction_score'] : null),
                Ui::badge(self::LIBELLES_APPEL[$statut] ?? $statut, self::TONS_APPEL[$statut] ?? 'neutral'),
                View::e(self::dateHeure((string) ($appel['created_at'] ?? ''))),
            ];

            if ($peutSupprimer) {
                $ligne[] = Ui::deleteForm(
                    'call-center/appels/' . (int) $appel['id'] . '/supprimer',
                    'Supprimer définitivement cet appel ? Cette action est irréversible.'
                );
            }

            $lignes[] = $ligne;
        }

        return ModuleTable::render(
            $colonnes,
            $lignes,
            'Aucun appel',
            'Aucun appel ne correspond à la période et aux filtres retenus.'
        );
    }

    private static function dateHeure(string $valeur): string
    {
        if ($valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y H:i', $horodatage) : '—';
    }

    /**
     * Note de satisfaction sur cinq, en etoiles dessinees.
     *
     * Les etoiles etaient ecrites avec les caracteres U+2605 et U+2606. La
     * suppression des emoji les a retires, et la colonne satisfaction du journal
     * des appels s'affichait vide quelle que soit la note.
     */
    public static function etoiles(?int $note): string
    {
        if ($note === null || $note < 1) {
            return '<small>—</small>';
        }

        $note = min(5, $note);
        $html = '<span class="cc-etoiles" role="img" aria-label="' . View::e($note . ' sur 5') . '">';

        for ($i = 1; $i <= 5; $i++) {
            $pleine = $i <= $note;
            $html .= '<svg viewBox="0 0 24 24" width="14" height="14"'
                . ' fill="' . ($pleine ? 'currentColor' : 'none') . '"'
                . ' stroke="currentColor" stroke-width="1.8"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                . '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77'
                . ' 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
        }

        return $html . '</span>';
    }

    // ==================================================================
    // Styles communs
    // ==================================================================

    private static function styles(): string
    {
        return '<style>'
            . '.cc-deux-colonnes{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1.5rem;}'
            . '.cc-note{margin-top:1rem;font-size:.8rem;color:#94a3b8;text-align:center;}'
            . '.cc-rayons{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;}'
            . '.cc-rayon{border:1px solid #e2e8f0;border-radius:.75rem;overflow:hidden;background:var(--finea-surface,#fff);}'
            . '.cc-rayon header{display:flex;justify-content:space-between;align-items:center;gap:.75rem;'
            . 'padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;}'
            . '.cc-rayon header small{display:block;font-size:.8rem;color:#64748b;}'
            . '.cc-rayon-jauge{padding:.75rem 1.25rem;border-bottom:1px solid #f1f5f9;}'
            . '.cc-rayon-chiffres{font-size:.8rem;color:#64748b;margin-bottom:.35rem;}'
            . '.cc-rayon-retard{margin:.4rem 0 0;font-size:.75rem;color:#dc2626;font-weight:600;}'
            . '.cc-rayon-colis{max-height:220px;overflow-y:auto;}'
            . '.cc-colis{display:flex;justify-content:space-between;align-items:center;gap:.5rem;'
            . 'padding:.6rem 1.25rem;border-bottom:1px solid #f8fafc;}'
            . '.cc-colis.is-retard{background:#fef2f2;}'
            . '.cc-colis code{display:block;font-size:.78rem;color:#0369a1;font-weight:600;}'
            . '.cc-colis small{display:block;font-size:.75rem;color:#64748b;}'
            . '.cc-rayon-vide,.cc-rayon-reste{margin:0;padding:1rem 1.25rem;text-align:center;'
            . 'font-size:.8rem;color:#94a3b8;}'
            . '.cc-rayon-reste{background:#f8fafc;}'
            . '.cc-etoiles{display:inline-flex;gap:2px;color:#f97316;vertical-align:-2px;}'
            . '</style>';
    }
}
