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

    // ==================================================================
    // Reclamations et litiges
    // ==================================================================

    private const TYPES_LITIGE = [
        'colis_perdu' => 'Colis perdu',
        'colis_endommage' => 'Colis endommagé',
        'retard' => 'Retard de livraison',
        'facturation' => 'Problème de facturation',
        'autre' => 'Autre',
    ];

    private const LIBELLES_GRAVITE = [
        'faible' => 'Faible',
        'moyenne' => 'Moyenne',
        'elevee' => 'Élevée',
        'critique' => 'Critique',
    ];

    private const LIBELLES_LITIGE = [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours',
        'resolu' => 'Résolu',
        'annule' => 'Annulé',
    ];

    /** Statuts qui designent un litige encore a traiter. */
    private const LITIGES_OUVERTS = ['nouveau', 'en_cours'];

    /**
     * @param array<int, array<string, mixed>> $litiges
     * @param array<int, array<string, mixed>> $clients
     * @param array<int, array<string, mixed>> $colis
     * @param array<string, mixed>|null $aTraiter litige dont on ouvre la resolution
     */
    public static function litigesPage(
        array $litiges,
        array $clients,
        array $colis,
        string $statutFiltre,
        string $graviteFiltre,
        bool $peutGerer,
        bool $peutSupprimer,
        ?array $aTraiter = null
    ): string {
        $ouverts = count(array_filter(
            $litiges,
            static fn(array $l): bool => in_array((string) $l['statut'], self::LITIGES_OUVERTS, true)
        ));

        $entete = Ui::pageHeader(
            'Réclamations & litiges',
            count($litiges) . ' litige(s) sur ce filtre, dont ' . $ouverts . ' encore ouvert(s).',
            [
                'eyebrow' => 'CAL • Qualité de service',
                'class' => 'rh-hero-white',
                'actions' => $peutGerer
                    ? Ui::button('Ouvrir un litige', ['href' => 'call-center/litiges#nouveau-litige', 'variant' => 'danger'])
                    : '',
            ]
        );

        $resolution = '';
        if ($peutGerer && $aTraiter !== null) {
            $resolution = '<div style="margin-bottom:1.5rem;">' . self::formulaireResolution($aTraiter) . '</div>';
        }

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . '<div style="margin-bottom:1.5rem;">' . self::filtresLitiges($statutFiltre, $graviteFiltre) . '</div>'
            . $resolution
            . ($peutGerer ? '<div style="margin-bottom:1.5rem;">' . self::formulaireLitige($clients, $colis) . '</div>' : '')
            . Ui::section('Litiges enregistrés', self::tableLitiges($litiges, $peutGerer, $peutSupprimer))
            . self::styles()
            . '</div></div>';
    }

    private static function filtresLitiges(string $statutFiltre, string $graviteFiltre): string
    {
        $statuts = [['value' => '', 'label' => 'Tous les statuts']];
        foreach (self::LIBELLES_LITIGE as $code => $libelle) {
            $statuts[] = ['value' => $code, 'label' => $libelle];
        }

        $gravites = [['value' => '', 'label' => 'Toutes les gravités']];
        foreach (self::LIBELLES_GRAVITE as $code => $libelle) {
            $gravites[] = ['value' => $code, 'label' => $libelle];
        }

        return Ui::section(
            'Filtres',
            '<form method="get" action="' . View::url('call-center/litiges') . '">'
                . '<div class="rh-form-grid" style="gap:1rem;">'
                . Form::select('statut', $statuts, $statutFiltre, ['label' => 'Statut'])
                . Form::select('gravite', $gravites, $graviteFiltre, ['label' => 'Gravité'])
                . '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
                . Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit'])
                . Ui::button('Réinitialiser', ['href' => 'call-center/litiges', 'variant' => 'secondary'])
                . '</div></div></form>'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     * @param array<int, array<string, mixed>> $colis
     */
    private static function formulaireLitige(array $clients, array $colis): string
    {
        $optionsClients = [['value' => '', 'label' => 'Sélectionner un client']];
        foreach ($clients as $client) {
            $optionsClients[] = ['value' => (string) $client['id'], 'label' => (string) $client['name']];
        }

        $optionsColis = [['value' => '', 'label' => 'Aucun colis en particulier']];
        foreach ($colis as $unColis) {
            $optionsColis[] = ['value' => (string) $unColis['id'], 'label' => (string) $unColis['numero_tracking']];
        }

        $types = [];
        foreach (self::TYPES_LITIGE as $code => $libelle) {
            $types[] = ['value' => $code, 'label' => $libelle];
        }

        $gravites = [];
        foreach (self::LIBELLES_GRAVITE as $code => $libelle) {
            $gravites[] = ['value' => $code, 'label' => $libelle];
        }

        return Ui::section(
            'Ouvrir un litige',
            '<form method="post" action="' . View::url('call-center/litiges/enregistrer') . '">'
                . \App\Helpers\Csrf::field()
                . '<div class="rh-form-grid" style="gap:1.25rem;">'
                . Form::select('client_id', $optionsClients, '', ['label' => 'Client', 'required' => true])
                . Form::select('colis_id', $optionsColis, '', [
                    'label' => 'Colis concerné',
                    'hint' => 'Facultatif : à renseigner si la réclamation porte sur un envoi précis.',
                ])
                . Form::select('type_litige', $types, 'autre', ['label' => 'Type de litige', 'required' => true])
                . Form::select('gravite', $gravites, 'moyenne', ['label' => 'Gravité', 'required' => true])
                . '</div>'
                . Form::textarea('description', [
                    'label' => 'Description du problème',
                    'rows' => 3,
                    'required' => true,
                    'placeholder' => 'Ce que le client rapporte, et ce qui a déjà été vérifié',
                ])
                . '<div style="text-align:right; margin-top:1rem;">'
                . Ui::button('Enregistrer le litige', ['variant' => 'danger', 'type' => 'submit'])
                . '</div></form>',
            '',
            ['id' => 'nouveau-litige']
        );
    }

    /**
     * @param array<string, mixed> $litige
     */
    private static function formulaireResolution(array $litige): string
    {
        $id = (int) $litige['id'];

        return Ui::section(
            'Traiter le litige #' . $id,
            '<p>' . View::e((string) ($litige['description'] ?? '')) . '</p>'
                . '<form method="post" action="' . View::url('call-center/litiges/' . $id . '/resoudre') . '">'
                . \App\Helpers\Csrf::field()
                . '<div class="rh-form-grid" style="gap:1.25rem;">'
                . Form::select(
                    'statut',
                    [
                        ['value' => 'resolu', 'label' => 'Résolu'],
                        ['value' => 'en_cours', 'label' => 'En cours (mise à jour)'],
                        ['value' => 'annule', 'label' => 'Annulé'],
                    ],
                    'resolu',
                    ['label' => 'Nouveau statut']
                )
                . '</div>'
                . Form::textarea('solution_apportee', [
                    'label' => 'Solution apportée',
                    'rows' => 3,
                    'required' => true,
                    'placeholder' => 'Ce qui a été fait, et ce qui a été dit au client',
                ])
                . '<div style="display:flex; justify-content:flex-end; gap:.75rem; margin-top:1rem;">'
                . Ui::button('Annuler', ['href' => 'call-center/litiges', 'variant' => 'secondary'])
                . Ui::button('Valider le traitement', ['variant' => 'success', 'type' => 'submit'])
                . '</div></form>',
            View::e((string) ($litige['client_name'] ?? 'Client inconnu'))
        );
    }

    /** @param array<int, array<string, mixed>> $litiges */
    private static function tableLitiges(array $litiges, bool $peutGerer, bool $peutSupprimer): string
    {
        $colonnes = [
            ['label' => 'N°', 'align' => 'center'],
            ['label' => 'Client'],
            ['label' => 'Type'],
            ['label' => 'Colis'],
            ['label' => 'Gravité', 'align' => 'center'],
            ['label' => 'Statut', 'align' => 'center'],
            ['label' => 'Ouvert le'],
        ];

        if ($peutGerer || $peutSupprimer) {
            $colonnes[] = ['label' => 'Actions', 'align' => 'center'];
        }

        $lignes = [];
        foreach ($litiges as $litige) {
            $id = (int) $litige['id'];
            $type = (string) ($litige['type_litige'] ?? '');
            $gravite = (string) ($litige['gravite'] ?? '');
            $statut = (string) ($litige['statut'] ?? '');
            $suivi = trim((string) ($litige['numero_tracking'] ?? ''));

            $ligne = [
                '<small>#' . $id . '</small>',
                '<strong>' . View::e((string) ($litige['client_name'] ?? '—')) . '</strong>',
                View::e(self::TYPES_LITIGE[$type] ?? $type),
                $suivi !== '' ? '<code>' . View::e($suivi) . '</code>' : '<small>—</small>',
                Ui::badge(self::LIBELLES_GRAVITE[$gravite] ?? $gravite, self::TONS_GRAVITE[$gravite] ?? 'neutral'),
                Ui::badge(self::LIBELLES_LITIGE[$statut] ?? $statut, self::TONS_LITIGE[$statut] ?? 'neutral'),
                View::e(self::dateCourte((string) ($litige['date_ouverture'] ?? ''))),
            ];

            if ($peutGerer || $peutSupprimer) {
                $actions = '';

                if ($peutGerer) {
                    $actions .= in_array($statut, self::LITIGES_OUVERTS, true)
                        ? Ui::button('Traiter', [
                            'href' => 'call-center/litiges?traiter=' . $id,
                            'variant' => 'success',
                        ])
                        : '<small>Clos le ' . View::e(self::dateCourte((string) ($litige['date_resolution'] ?? ''))) . '</small>';
                }

                if ($peutSupprimer) {
                    $actions .= Ui::deleteForm(
                        'call-center/litiges/' . $id . '/supprimer',
                        'Supprimer définitivement ce litige ? Cette action est irréversible.'
                    );
                }

                $ligne[] = '<div style="display:inline-flex; gap:6px; align-items:center;">' . $actions . '</div>';
            }

            $lignes[] = $ligne;
        }

        return ModuleTable::render(
            $colonnes,
            $lignes,
            'Aucun litige',
            'Aucune réclamation ne correspond aux filtres retenus.'
        );
    }

    private static function dateCourte(string $valeur): string
    {
        if ($valeur === '') {
            return '—';
        }

        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }

    // ==================================================================
    // Suivi et relances
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $colisList
     */
    public static function suiviPage(array $colisList, string $recherche, bool $peutGerer): string
    {
        $entete = Ui::pageHeader(
            'Suivi & relances clients',
            'Prévenir les destinataires par WhatsApp, SMS ou appel, et garder trace de chaque relance.',
            [
                'eyebrow' => 'CAL • Relances',
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Bilan des départs', ['href' => 'call-center/suivi-departs', 'variant' => 'secondary']),
            ]
        );

        $recherchePanneau = Ui::section(
            'Rechercher',
            '<form method="get" action="' . View::url('call-center/suivi') . '">'
                . '<div class="rh-form-grid" style="gap:1rem;">'
                . Form::input('q', [
                    'label' => 'Colis, client ou téléphone',
                    'value' => $recherche,
                    'placeholder' => 'LB-CI-0726-001, nom du client, numéro de téléphone',
                ])
                . '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
                . Ui::button('Rechercher', ['variant' => 'primary', 'type' => 'submit'])
                . ($recherche !== '' ? Ui::button('Effacer', ['href' => 'call-center/suivi', 'variant' => 'secondary']) : '')
                . '</div></div></form>'
        );

        return '<div class="finea-shell"><div class="finea-container"'
            . self::attributsRelance($peutGerer) . '>'
            . $entete
            . '<div style="margin-bottom:1.5rem;">' . $recherchePanneau . '</div>'
            . Ui::section('Colis suivis', self::tableSuivi($colisList, $peutGerer))
            . ($peutGerer ? self::panneauAppel() : '')
            . self::styles()
            . ($peutGerer ? self::scriptRelances() : '')
            . '</div></div>';
    }

    /**
     * Attributs que le script de relance lit sur la coque de la page.
     *
     * L'adresse du point d'enregistrement passe par View::url : ecrite en dur,
     * elle ne repondait pas sur une installation en sous-repertoire, et la
     * relance n'etait alors jamais tracee. Le jeton n'est emis que pour qui peut
     * reellement declencher une relance.
     */
    private static function attributsRelance(bool $peutGerer): string
    {
        if (!$peutGerer) {
            return '';
        }

        return ' data-cc-notifier="' . View::url('call-center/suivi/notifier') . '"'
            . ' data-cc-jeton="' . View::e(\App\Helpers\Csrf::token()) . '"';
    }

    /** @param array<int, array<string, mixed>> $colisList */
    private static function tableSuivi(array $colisList, bool $peutGerer): string
    {
        $colonnes = [
            ['label' => 'N° de suivi'],
            ['label' => 'Destinataire'],
            ['label' => 'Téléphone'],
            ['label' => 'Statut du colis', 'align' => 'center'],
            ['label' => 'Relance', 'align' => 'center'],
        ];

        if ($peutGerer) {
            $colonnes[] = ['label' => 'Prévenir le client', 'align' => 'center'];
        }

        $lignes = [];
        foreach ($colisList as $colis) {
            $ligne = [
                '<code>' . View::e((string) $colis['numero_tracking']) . '</code>',
                '<strong>' . View::e((string) ($colis['destinataire_nom'] ?? '—')) . '</strong>',
                View::e((string) ($colis['destinataire_tel'] ?? '—')),
                Ui::badge((string) $colis['statut'], 'info'),
                self::etatRelance($colis),
            ];

            if ($peutGerer) {
                $ligne[] = self::boutonsRelance($colis);
            }

            $lignes[] = $ligne;
        }

        return ModuleTable::render(
            $colonnes,
            $lignes,
            'Aucun colis',
            'Aucun colis ne correspond à cette recherche.'
        );
    }

    /** @param array<string, mixed> $colis */
    private static function etatRelance(array $colis): string
    {
        $type = trim((string) ($colis['type_notification'] ?? ''));

        if ($type === '') {
            return Ui::badge('Non notifié', 'danger');
        }

        $details = ['Le ' . self::dateHeure((string) ($colis['notification_date'] ?? ''))];

        $agent = trim((string) ($colis['agent_name'] ?? ''));
        if ($agent !== '') {
            $details[] = 'par ' . $agent;
        }

        if ($type === 'appel') {
            $duree = (int) ($colis['duree_appel'] ?? 0);
            $details[] = sprintf('durée %02d:%02d', intdiv($duree, 60), $duree % 60);
        }

        $note = trim((string) ($colis['notification_desc'] ?? ''));
        if ($note !== '') {
            $details[] = $note;
        }

        return Ui::badge('Notifié par ' . $type, 'success')
            . '<br><small>' . View::e(implode(' — ', $details)) . '</small>';
    }

    /**
     * Boutons de relance.
     *
     * Les donnees du colis voyagent par des attributs data, jamais interpolees
     * dans un gestionnaire onclick : un nom contenant une apostrophe - N'Dri,
     * N'Guessan - produisait une erreur de syntaxe JavaScript, et le bouton
     * « Appeler » ne faisait plus rien, sans le moindre message.
     *
     * @param array<string, mixed> $colis
     */
    private static function boutonsRelance(array $colis): string
    {
        $donnees = ' data-cc-colis="' . (int) $colis['id'] . '"'
            . ' data-cc-client="' . (int) ($colis['destinataire_id'] ?? 0) . '"'
            . ' data-cc-tel="' . View::e((string) ($colis['destinataire_tel'] ?? '')) . '"'
            . ' data-cc-nom="' . View::e((string) ($colis['destinataire_nom'] ?? '')) . '"'
            . ' data-cc-tracking="' . View::e((string) $colis['numero_tracking']) . '"'
            . ' data-cc-statut="' . View::e((string) $colis['statut']) . '"';

        $boutons = [
            ['whatsapp', 'WhatsApp', 'cc-whatsapp', '<path d="M20.5 3.5A11.9 11.9 0 0 0 12 0C5.4 0 0 5.4 0 12a11.9 11.9 0 0 0 1.6 6L0 24l6.2-1.6A11.9 11.9 0 0 0 12 24c6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.5-8.5z"></path>'],
            ['sms', 'SMS', 'cc-sms', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>'],
            ['appel', 'Appeler', 'cc-appel', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>'],
        ];

        $html = '<div class="cc-relances">';
        foreach ($boutons as [$action, $libelle, $classe, $trace]) {
            $html .= '<button type="button" class="cc-relance ' . $classe . '"'
                . ' data-cc-action="' . $action . '"' . $donnees
                . ' title="' . View::e($libelle) . '" aria-label="' . View::e($libelle) . '">'
                . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
                . ' stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                . $trace . '</svg></button>';
        }

        return $html . '</div>';
    }

    /**
     * Panneau d'appel : minuteur, puis compte rendu.
     *
     * Il est rendu une seule fois pour toute la page, et non une fois par colis.
     */
    private static function panneauAppel(): string
    {
        $notes = [['value' => '', 'label' => 'Non renseignée']];
        for ($i = 1; $i <= 5; $i++) {
            $notes[] = ['value' => (string) $i, 'label' => $i . ' / 5'];
        }

        return '<div id="cc-appel" class="cc-panneau" hidden role="dialog" aria-modal="true"'
            . ' aria-labelledby="cc-appel-titre">'
            . '<div class="cc-panneau-boite">'
            . '<h3 id="cc-appel-titre">Appel en cours</h3>'
            . '<p id="cc-appel-tel"></p>'
            . '<p class="cc-chrono" id="cc-appel-chrono" role="timer" aria-live="off">00:00</p>'
            . '<div id="cc-appel-encours">'
            . Ui::button('Terminer l\'appel', ['variant' => 'danger', 'type' => 'button', 'id' => 'cc-appel-fin'])
            . '</div>'
            . '<div id="cc-appel-compte-rendu" hidden>'
            . Form::textarea('cc_description', [
                'label' => 'Résumé de l\'appel',
                'rows' => 3,
                'id' => 'cc-appel-description',
                'placeholder' => 'Client informé de l\'arrivée du colis',
            ])
            . Form::select('cc_satisfaction', $notes, '', ['label' => 'Satisfaction', 'id' => 'cc-appel-note'])
            . '<div class="cc-panneau-actions">'
            . Ui::button('Abandonner', ['variant' => 'secondary', 'type' => 'button', 'id' => 'cc-appel-abandon'])
            . Ui::button('Enregistrer l\'appel', ['variant' => 'accent', 'type' => 'button', 'id' => 'cc-appel-enregistrer'])
            . '</div>'
            . '</div></div></div>';
    }

    private static function scriptRelances(): string
    {
        return '<script>'
            . '(function(){'
            . 'var racine=document.querySelector("[data-cc-notifier]");if(!racine)return;'
            . 'var url=racine.dataset.ccNotifier,jeton=racine.dataset.ccJeton;'
            . 'var panneau=document.getElementById("cc-appel");'
            . 'var chrono=null,secondes=0,courant=null;'
            // Un ecran peut fournir son propre texte (bilan des departs) ; sinon
            // on compose le message court de suivi d'un colis.
            . 'function message(d){return d.ccMessage||("Bonjour, votre colis LBP "+d.ccTracking'
            . '+" est actuellement au statut : "+d.ccStatut+". Merci pour votre confiance.");}'
            . 'function numero(t){return (t||"").replace(/[^\\d+]/g,"");}'
            . 'function tracer(d,type,extra){'
            . 'var f=new FormData();f.append("_csrf_token",jeton);'
            . 'f.append("colis_id",d.ccColis);f.append("client_id",d.ccClient);'
            . 'f.append("type_notification",type);'
            . 'Object.keys(extra||{}).forEach(function(k){'
            . 'if(extra[k]!==null&&extra[k]!==undefined&&extra[k]!=="")f.append(k,extra[k]);});'
            . 'return fetch(url,{method:"POST",body:f}).then(function(r){return r.json();})'
            . '.then(function(j){return !!(j&&j.ok);}).catch(function(){return false;});'
            . '}'
            . 'function horloge(){'
            . 'var m=String(Math.floor(secondes/60)).padStart(2,"0");'
            . 'var s=String(secondes%60).padStart(2,"0");'
            . 'document.getElementById("cc-appel-chrono").textContent=m+":"+s;}'
            . 'document.addEventListener("click",function(e){'
            . 'var b=e.target.closest("[data-cc-action]");if(!b)return;'
            . 'var d=b.dataset,tel=numero(d.ccTel);'
            . 'if(!tel){alert("Aucun numéro de téléphone pour ce destinataire.");return;}'
            . 'if(d.ccAction==="appel"){'
            . 'courant=d;secondes=0;'
            . 'document.getElementById("cc-appel-titre").textContent="Appel avec "+(d.ccNom||"le destinataire");'
            . 'document.getElementById("cc-appel-tel").textContent=d.ccTel;'
            . 'document.getElementById("cc-appel-encours").hidden=false;'
            . 'document.getElementById("cc-appel-compte-rendu").hidden=true;'
            . 'panneau.hidden=false;horloge();'
            . 'chrono=setInterval(function(){secondes++;horloge();},1000);'
            . 'window.location.href="tel:"+tel;return;}'
            . 'tracer(d,d.ccAction).then(function(ok){'
            . 'if(!ok){alert("La relance n\'a pas pu être enregistrée.");return;}'
            . 'var t=encodeURIComponent(message(d));'
            . 'if(d.ccAction==="whatsapp"){'
            . 'window.open("https://api.whatsapp.com/send?phone="+encodeURIComponent(tel)+"&text="+t,"_blank","noopener");'
            . '}else{window.location.href="sms:"+encodeURIComponent(tel)+"?body="+t;}'
            . 'setTimeout(function(){location.reload();},1200);});'
            . '});'
            . 'function fermer(){if(chrono)clearInterval(chrono);chrono=null;panneau.hidden=true;}'
            . 'var fin=document.getElementById("cc-appel-fin");'
            . 'if(fin)fin.addEventListener("click",function(){'
            . 'if(chrono)clearInterval(chrono);chrono=null;'
            . 'document.getElementById("cc-appel-encours").hidden=true;'
            . 'document.getElementById("cc-appel-compte-rendu").hidden=false;'
            . 'document.getElementById("cc-appel-description").focus();});'
            . 'var abandon=document.getElementById("cc-appel-abandon");'
            . 'if(abandon)abandon.addEventListener("click",fermer);'
            . 'document.addEventListener("keydown",function(e){'
            . 'if(e.key==="Escape"&&panneau&&!panneau.hidden)fermer();});'
            . 'var valider=document.getElementById("cc-appel-enregistrer");'
            . 'if(valider)valider.addEventListener("click",function(){'
            . 'var d=document.getElementById("cc-appel-description").value.trim();'
            . 'if(!d){alert("Renseignez un résumé avant d\'enregistrer l\'appel.");return;}'
            . 'tracer(courant,"appel",{duree_appel:secondes,description:d,'
            . 'satisfaction_score:document.getElementById("cc-appel-note").value})'
            . '.then(function(ok){if(!ok){alert("L\'appel n\'a pas pu être enregistré.");return;}'
            . 'fermer();location.reload();});});'
            . '})();'
            . '</script>';
    }

    // ==================================================================
    // Bilan des departs
    // ==================================================================

    /**
     * @param array<int, array<string, mixed>> $groupes
     * @param array<int, array<string, mixed>> $sites
     */
    public static function suiviDepartsPage(
        array $groupes,
        array $sites,
        string $recherche,
        ?int $agenceId,
        bool $peutGerer,
        bool $peutExporterExcel
    ): string {
        $partiels = count(array_filter($groupes, static fn(array $g): bool => (int) $g['nb_restes'] > 0));
        $complets = count($groupes) - $partiels;

        $parametres = http_build_query(array_filter([
            'q' => $recherche,
            'agence_id' => $agenceId,
        ]));

        $actions = Ui::button('Export PDF', [
            'href' => 'call-center/suivi-departs/export-pdf' . ($parametres !== '' ? '?' . $parametres : ''),
            'variant' => 'secondary',
        ]);
        if ($peutExporterExcel) {
            $actions .= Ui::button('Export Excel', [
                'href' => 'call-center/suivi-departs/export-excel' . ($parametres !== '' ? '?' . $parametres : ''),
                'variant' => 'success',
            ]);
        }

        $entete = Ui::pageHeader(
            'Bilan des départs & colis restés',
            'Ce qui est parti et ce qui attend encore en agence, par expéditeur, avec la relance du client.',
            ['eyebrow' => 'CAL • Logistique & relances', 'class' => 'rh-hero-white', 'actions' => $actions]
        );

        $kpis = Dashboard::kpis([
            [
                'label' => 'Envois suivis',
                'value' => (string) count($groupes),
                'meta' => 'Expéditeurs concernés sur ce filtre',
            ],
            [
                'label' => 'Envois complets',
                'value' => (string) $complets,
                'meta' => 'Tous les colis sont partis',
                'tone' => 'success',
            ],
            [
                'label' => 'Envois partiels',
                'value' => (string) $partiels,
                'meta' => 'Au moins un colis est resté en agence',
                'tone' => $partiels > 0 ? 'warning' : 'neutral',
            ],
        ]);

        $corps = $groupes === []
            ? Ui::section(
                'Envois',
                Ui::emptyState('Aucun envoi', 'Aucune expédition ne correspond aux critères retenus.')
            )
            : self::listeGroupes($groupes, $peutGerer);

        return '<div class="finea-shell"><div class="finea-container"'
            . self::attributsRelance($peutGerer) . '>'
            . $entete
            . $kpis
            . '<div style="margin-bottom:1.5rem;">' . self::filtresDeparts($sites, $recherche, $agenceId) . '</div>'
            . $corps
            . ($peutGerer ? self::panneauAppel() : '')
            . self::styles()
            . ($peutGerer ? self::scriptRelances() : '')
            . '</div></div>';
    }

    /**
     * @param array<int, array<string, mixed>> $sites
     */
    private static function filtresDeparts(array $sites, string $recherche, ?int $agenceId): string
    {
        $options = [['value' => '', 'label' => 'Toutes les agences']];
        foreach ($sites as $site) {
            $options[] = ['value' => (string) $site['id'], 'label' => (string) $site['name']];
        }

        $efface = ($recherche !== '' || $agenceId !== null)
            ? Ui::button('Effacer', ['href' => 'call-center/suivi-departs', 'variant' => 'secondary'])
            : '';

        return Ui::section(
            'Filtres',
            '<form method="get" action="' . View::url('call-center/suivi-departs') . '">'
                . '<div class="rh-form-grid" style="gap:1rem;">'
                . Form::input('q', [
                    'label' => 'Client ou numéro de suivi',
                    'value' => $recherche,
                    'placeholder' => 'Yao, LB-CI, +225…',
                ])
                . Form::select('agence_id', $options, (string) ($agenceId ?? ''), ['label' => 'Agence de départ'])
                . '<div class="finea-field" style="display:flex; gap:.5rem; align-items:end;">'
                . Ui::button('Filtrer', ['variant' => 'primary', 'type' => 'submit'])
                . $efface
                . '</div></div></form>'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $groupes
     */
    private static function listeGroupes(array $groupes, bool $peutGerer): string
    {
        $html = '';
        foreach ($groupes as $groupe) {
            $html .= self::groupeDepart($groupe, $peutGerer);
        }

        return '<div class="cc-groupes">' . $html . '</div>';
    }

    /**
     * @param array<string, mixed> $groupe
     */
    private static function groupeDepart(array $groupe, bool $peutGerer): string
    {
        $restes = (int) $groupe['nb_restes'];

        $badges = Ui::badge($groupe['nb_partis'] . ' / ' . $groupe['total_colis'] . ' parti(s)', 'success');
        if ($restes > 0) {
            $badges .= ' ' . Ui::badge($restes . ' resté(s)', 'danger');
        }
        if ((int) ($groupe['nb_attente'] ?? 0) > 0) {
            $badges .= ' ' . Ui::badge($groupe['nb_attente'] . ' en attente', 'neutral');
        }

        $service = 'Service ' . (string) $groupe['type_expediteur']
            . (!empty($groupe['trajet']) ? ' (' . (string) $groupe['trajet'] . ')' : '');

        $identite = '<p class="cc-groupe-identite">'
            . 'Expéditeur : <strong>' . View::e((string) ($groupe['expediteur_phone'] ?: '—')) . '</strong>'
            . ' — Destinataire : <strong>' . View::e((string) $groupe['destinataire_name']) . '</strong>'
            . ' (' . View::e((string) ($groupe['destinataire_phone'] ?: '—')) . ')'
            . (!empty($groupe['agence_depart'])
                ? ' — Départ : <strong>' . View::e((string) $groupe['agence_depart']) . '</strong>'
                : '')
            . '</p>';

        $sousTitre = $service . ' — ' . $badges;

        return Ui::section(
            (string) $groupe['expediteur_name'],
            $identite
                . ($peutGerer ? self::relancesGroupe($groupe) : '')
                . self::tableColisGroupe($groupe),
            View::html($sousTitre),
            ['class' => $restes > 0 ? 'cc-groupe cc-groupe--partiel' : 'cc-groupe']
        );
    }

    /**
     * Message de synthese envoye au client.
     *
     * Il est compose ici, en PHP, et voyage dans un attribut de donnees. La
     * version precedente serialisait tout le groupe en JSON dans un gestionnaire
     * onclick, et reconstruisait le texte en JavaScript : le meme message etait
     * ecrit a deux endroits, et les colis du groupe etaient dupliques dans le
     * HTML a chaque bouton.
     *
     * @param array<string, mixed> $groupe
     */
    private static function messageDepart(array $groupe): string
    {
        $lignes = [
            'Bonjour ' . (string) $groupe['expediteur_name'] . ',',
            '',
            'Point d\'expédition pour vos colis (service ' . (string) $groupe['type_expediteur'] . ') :',
        ];

        if ((int) $groupe['nb_partis'] > 0) {
            $lignes[] = $groupe['nb_partis'] . ' colis sur ' . $groupe['total_colis'] . ' sont partis (en transit).';
        }

        if ((int) $groupe['nb_restes'] > 0) {
            $lignes[] = $groupe['nb_restes'] . ' colis est/sont resté(s) à l\'agence de départ :';

            foreach ((array) $groupe['colis'] as $colis) {
                if ((string) ($colis['statut_depart'] ?? '') !== 'RESTE') {
                    continue;
                }

                $motif = trim((string) ($colis['motif_reste'] ?? ''));
                $lignes[] = '  - ' . (string) $colis['numero_tracking']
                    . ($motif !== '' ? ' (motif : ' . $motif . ')' : '');
            }

            $lignes[] = 'Ces colis partiront lors de la prochaine expédition.';
        }

        $lignes[] = '';
        $lignes[] = 'Merci de votre confiance, LA BELLE PORTE LOGISTICS.';

        return implode("\n", $lignes);
    }

    /**
     * @param array<string, mixed> $groupe
     */
    private static function relancesGroupe(array $groupe): string
    {
        $colis = (array) $groupe['colis'];
        $premier = $colis[0] ?? [];

        $donnees = ' data-cc-colis="' . (int) ($premier['colis_id'] ?? 0) . '"'
            . ' data-cc-client="' . (int) ($groupe['expediteur_id'] ?? 0) . '"'
            . ' data-cc-tel="' . View::e((string) ($groupe['expediteur_phone'] ?? '')) . '"'
            . ' data-cc-nom="' . View::e((string) $groupe['expediteur_name']) . '"'
            . ' data-cc-message="' . View::e(self::messageDepart($groupe)) . '"';

        $boutons = [
            ['whatsapp', 'Synthèse par WhatsApp', 'cc-whatsapp'],
            ['sms', 'Synthèse par SMS', 'cc-sms'],
            ['appel', 'Appeler l\'expéditeur', 'cc-appel'],
        ];

        $html = '<div class="cc-relances cc-relances--groupe">';
        foreach ($boutons as [$action, $libelle, $classe]) {
            $html .= '<button type="button" class="cc-relance-large ' . $classe . '"'
                . ' data-cc-action="' . $action . '"' . $donnees
                . ' title="' . View::e($libelle) . '">'
                . View::e(match ($action) {
                    'whatsapp' => 'WhatsApp',
                    'sms' => 'SMS',
                    default => 'Appeler',
                })
                . '</button>';
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, mixed> $groupe
     */
    private static function tableColisGroupe(array $groupe): string
    {
        /** Statuts de colis qui valent depart effectif, meme sans statut_depart. */
        $partis = ['EN_TRANSIT', 'ARRIVÉ', 'LIVRÉ', 'RETIRÉ'];

        $lignes = [];
        foreach ((array) $groupe['colis'] as $colis) {
            $statutDepart = (string) ($colis['statut_depart'] ?? 'NON_SPECIFIE');
            $estParti = $statutDepart === 'PARTI' || in_array((string) $colis['statut'], $partis, true);
            $estReste = $statutDepart === 'RESTE';
            $motif = trim((string) ($colis['motif_reste'] ?? ''));

            $lignes[] = [
                '<code>' . View::e((string) $colis['numero_tracking']) . '</code>',
                View::e((string) $colis['destinataire_name']),
                ModuleTable::montant((float) $colis['poids_total'], 'kg', 1),
                Ui::badge((string) $colis['statut'], 'neutral'),
                match (true) {
                    $estParti => Ui::badge('Parti', 'success'),
                    $estReste => Ui::badge('Resté en agence', 'danger'),
                    default => Ui::badge('En attente', 'neutral'),
                },
                $estReste && $motif !== '' ? View::e($motif) : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'N° de suivi'],
                ['label' => 'Destinataire'],
                ['label' => 'Poids', 'align' => 'right'],
                ['label' => 'Statut du colis', 'align' => 'center'],
                ['label' => 'État du départ', 'align' => 'center'],
                ['label' => 'Motif si resté'],
            ],
            $lignes,
            'Aucun colis',
            'Ce groupe ne contient aucun colis.'
        );
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
            . '.cc-relances{display:inline-flex;gap:.5rem;}'
            . '.cc-relance{width:36px;height:36px;border-radius:50%;border:none;color:#fff;'
            . 'display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}'
            . '.cc-relance:focus-visible{outline:2px solid #1e293b;outline-offset:2px;}'
            . '.cc-whatsapp{background:#22c55e;}.cc-sms{background:#0ea5e9;}.cc-appel{background:#f97316;}'
            . '.cc-panneau{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:999;'
            . 'display:flex;align-items:center;justify-content:center;padding:1.5rem;}'
            . '.cc-panneau[hidden]{display:none;}'
            . '.cc-panneau-boite{background:#fff;border-radius:1rem;padding:2rem;max-width:460px;'
            . 'width:100%;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);}'
            . '.cc-panneau-boite h3{margin:0;font-size:1.2rem;}'
            . '.cc-panneau-boite>p{color:#64748b;margin:.3rem 0 1.25rem;}'
            . '.cc-chrono{font-size:2.4rem;font-weight:700;font-family:monospace;'
            . 'color:#0f172a;margin:0 0 1.5rem;font-variant-numeric:tabular-nums;}'
            . '.cc-panneau-actions{display:flex;justify-content:flex-end;gap:.75rem;margin-top:1rem;}'
            . '#cc-appel-compte-rendu{text-align:left;margin-top:1.5rem;'
            . 'border-top:1px solid #e2e8f0;padding-top:1.5rem;}'
            . '#cc-appel-compte-rendu[hidden],#cc-appel-encours[hidden]{display:none;}'
            . '.cc-groupes{display:grid;gap:1.25rem;}'
            . '.cc-groupe--partiel{border:2px solid #f97316;}'
            . '.cc-groupe-identite{font-size:.85rem;color:#475569;margin:0 0 1rem;}'
            . '.cc-relances--groupe{margin-bottom:1rem;gap:.5rem;}'
            . '.cc-relance-large{border:none;color:#fff;border-radius:.4rem;'
            . 'padding:.45rem .9rem;font-weight:600;font-size:.8rem;cursor:pointer;}'
            . '.cc-relance-large:focus-visible{outline:2px solid #1e293b;outline-offset:2px;}'
            . '</style>';
    }
}
