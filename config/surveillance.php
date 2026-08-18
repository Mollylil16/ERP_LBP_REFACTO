<?php

/**
 * Configuration du module de surveillance & intégrité anti-fraude.
 *
 * Ces valeurs sont des defaults utilisés quand lbp_regles_config ne contient
 * pas encore de paramètres JSON pour une règle donnée. La BDD a toujours
 * priorité sur ce fichier (les seuils sont modifiables en live par le DG).
 */

return [
    // ──────────────────────────────────────────────────────────
    // Heures de bureau (pour la règle SUPPRESSION_HORS_HORAIRES)
    // ──────────────────────────────────────────────────────────
    'heures_bureau' => [
        'debut' => '08:00',
        'fin'   => '18:00',
        // 1=Lundi … 7=Dimanche (ISO-8601)
        'jours_ouvres' => [1, 2, 3, 4, 5],
    ],

    // ──────────────────────────────────────────────────────────
    // Seuils par règle (defaults, overridés par lbp_regles_config.parametres_json)
    // ──────────────────────────────────────────────────────────
    'seuils' => [
        'SOUS_DECLARATION_COLIS' => [
            'ratio_minimum_pourcent' => 50, // Valeur/poids < 50% de la moyenne → alerte
        ],
        'MODIF_POST_VALIDATION' => [
            'statuts_proteges' => ['validé', 'clôturé', 'payee', 'soldee', 'cloturee'],
        ],
        'ECART_ENCAISSEMENT_COMPTA' => [
            'delai_heures' => 24, // Paiement encaissé mais absent de la comptabilité au-delà de X heures
        ],
        'CUMUL_ROLES_TRANSACTION' => [
            // Nombre minimum de rôles distincts (créer, valider, encaisser) par le même user
            'min_roles_cumules' => 2,
        ],
        'SUPPRESSION_HORS_HORAIRES' => [
            // Utilise heures_bureau ci-dessus
        ],
        'ECART_PESEE_RECURRENT' => [
            'occurrences_par_mois' => 3,     // >= 3 écarts/mois → alerte
            'ecart_poids_pourcent' => 15,     // Écart > 15% du poids réel = écart significatif
        ],
        'ACCES_SURVEILLANCE_NON_AUTORISE' => [
            'max_tentatives_avant_blocage' => 5, // Après 5 tentatives, recommandation de blocage
        ],
    ],

    // ──────────────────────────────────────────────────────────
    // Formule de scoring (pondérations des pénalités)
    // ──────────────────────────────────────────────────────────
    'scoring' => [
        'score_initial'       => 100.00,
        'penalite_moyen'      => 5.0,
        'penalite_grave'      => 20.0,
        'penalite_tres_grave' => 50.0,
        // Les alertes « justifiées » par le DG ne comptent pas dans le score
        'statuts_comptabilises' => ['nouvelle', 'en_cours', 'confirmee'],
    ],

    // ──────────────────────────────────────────────────────────
    // Export
    // ──────────────────────────────────────────────────────────
    'export' => [
        'titre_rapport' => 'Rapport Mensuel d\'Intégrité — La Belle Porte',
        'sous_titre'    => 'Document confidentiel — Réservé à la Direction Générale',
    ],
];
