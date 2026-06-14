<?php

namespace App\Services;

final class ModuleDashboardService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function modules(): array
    {
        return [
            'finance' => [
                'slug' => 'finance',
                'label' => 'Finance',
                'code' => 'FIN',
                'iconKey' => 'finance',
                'accent' => '#2563eb',
                'accent2' => '#1d2b57',
                'gradient' => 'linear-gradient(135deg, #1d2b57, #2563eb)',
                'description' => 'Pilotage financier, facturation, caisse, règlements et suivi des dépenses par dossier de transit.',
                'kpis' => [
                    ['label' => 'Décaissements', 'value' => '0', 'meta' => 'Socle prêt pour les demandes à valider'],
                    ['label' => 'Factures', 'value' => '0', 'meta' => 'Espace prévu pour les pièces clients'],
                    ['label' => 'Règlements', 'value' => '0', 'meta' => 'Suivi des paiements à brancher'],
                    ['label' => 'Alertes', 'value' => '0', 'meta' => 'Anomalies financières à surveiller'],
                ],
                'actions' => [
                    ['label' => 'Nouvelle dépense', 'hint' => 'Préparer une demande de décaissement', 'url' => '/finance/dashboard'],
                    ['label' => 'Facturation', 'hint' => 'Suivre les factures et avoirs', 'url' => '/finance/dashboard'],
                    ['label' => 'Trésorerie', 'hint' => 'Consulter les flux et prévisions', 'url' => '/finance/dashboard'],
                ],
            ],
            'colisage' => [
                'slug' => 'colisage',
                'label' => 'Colisage',
                'code' => 'COL',
                'iconKey' => 'colisage',
                'accent' => '#f97316',
                'accent2' => '#fabd02',
                'gradient' => 'linear-gradient(135deg, #fabd02, #f97316)',
                'description' => 'Packing-list, colis, volumes, poids, conteneurs et contrôle documentaire des marchandises.',
                'kpis' => [
                    ['label' => 'Dossiers colisage', 'value' => '0', 'meta' => 'Dossiers en préparation'],
                    ['label' => 'Conteneurs', 'value' => '0', 'meta' => 'Unités à suivre'],
                    ['label' => 'Documents', 'value' => '0', 'meta' => 'Pièces à contrôler'],
                    ['label' => 'Anomalies', 'value' => '0', 'meta' => 'Écarts poids/volume à traiter'],
                ],
                'actions' => [
                    ['label' => 'Créer packing-list', 'hint' => 'Préparer la liste de colisage', 'url' => '/colisage/dashboard'],
                    ['label' => 'Contrôle documentaire', 'hint' => 'Vérifier BL, factures et références', 'url' => '/colisage/dashboard'],
                    ['label' => 'Suivi conteneurs', 'hint' => 'Centraliser volumes et poids', 'url' => '/colisage/dashboard'],
                ],
            ],
            'logistique' => [
                'slug' => 'logistique',
                'label' => 'Logistique',
                'code' => 'LOG',
                'iconKey' => 'logistique',
                'accent' => '#059669',
                'accent2' => '#22c55e',
                'gradient' => 'linear-gradient(135deg, #059669, #22c55e)',
                'description' => 'Enlèvements, livraisons, transporteurs, véhicules et mouvements terrain des marchandises.',
                'kpis' => [
                    ['label' => 'Mouvements', 'value' => '0', 'meta' => 'Enlèvements et livraisons'],
                    ['label' => 'Transporteurs', 'value' => '0', 'meta' => 'Prestataires actifs'],
                    ['label' => 'Véhicules', 'value' => '0', 'meta' => 'Flotte et affectations'],
                    ['label' => 'Incidents', 'value' => '0', 'meta' => 'Événements terrain ouverts'],
                ],
                'actions' => [
                    ['label' => 'Planifier un enlèvement', 'hint' => 'Organiser le transport terrain', 'url' => '/logistique/dashboard'],
                    ['label' => 'Suivi livraison', 'hint' => 'Consulter les mouvements en cours', 'url' => '/logistique/dashboard'],
                    ['label' => 'Transporteurs', 'hint' => 'Gérer les partenaires logistiques', 'url' => '/logistique/dashboard'],
                ],
            ],
            'employee' => [
                'slug' => 'espace-employe',
                'label' => 'Espace employé',
                'code' => 'EMP',
                'iconKey' => 'employee',
                'accent' => '#0ea5e9',
                'accent2' => '#0284c7',
                'gradient' => 'linear-gradient(135deg, #0284c7, #0ea5e9)',
                'description' => 'Portail personnel pour demandes RH, documents, pointage, notifications et suivi administratif.',
                'kpis' => [
                    ['label' => 'Demandes', 'value' => '0', 'meta' => 'Demandes administratives ouvertes'],
                    ['label' => 'Documents', 'value' => '0', 'meta' => 'Pièces personnelles disponibles'],
                    ['label' => 'Notifications', 'value' => '0', 'meta' => 'Messages à consulter'],
                    ['label' => 'Présence', 'value' => '0%', 'meta' => 'Indicateur personnel mensuel'],
                ],
                'actions' => [
                    ['label' => 'Mes demandes', 'hint' => 'Absence, attestation, congé, avance', 'url' => '/espace-employe/dashboard'],
                    ['label' => 'Mes documents', 'hint' => 'Consulter et compléter mon dossier', 'url' => '/espace-employe/dashboard'],
                    ['label' => 'Mon pointage', 'hint' => 'Voir mes présences et retards', 'url' => '/espace-employe/dashboard'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $slug): array
    {
        $modules = $this->modules();
        if (!isset($modules[$slug])) {
            throw new \InvalidArgumentException('Module inconnu: ' . $slug);
        }

        $module = $modules[$slug];
        $module['navigation'] = $this->navigation($module);
        $module['workflow'] = [
            ['title' => 'Référentiels', 'text' => 'Paramètres métier, statuts et catégories branchables sans logique SQL dans les vues.'],
            ['title' => 'Opérations', 'text' => 'Formulaires métier, pièces jointes et contrôles seront ajoutés dans les prochains lots.'],
            ['title' => 'Reporting', 'text' => 'Les indicateurs sont déjà isolés pour évoluer vers des requêtes repositories propres.'],
        ];

        return $module;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<int, array<string, mixed>>
     */
    private function navigation(array $module): array
    {
        $base = '/' . $module['slug'];
        return [
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'operations', 'label' => 'Opérations', 'icon' => 'OP', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'documents', 'label' => 'Documents', 'icon' => 'DOC', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'reporting', 'label' => 'Reporting', 'icon' => 'RP', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'settings', 'label' => 'Paramétrage', 'icon' => 'PR', 'url' => $base . '/dashboard', 'available' => true],
        ];
    }
}
