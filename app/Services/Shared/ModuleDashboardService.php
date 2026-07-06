<?php

namespace App\Services\Shared;

use App\Models\BusinessDashboardModule;

final class ModuleDashboardService
{
    /**
     * Catalogue central des dashboards métiers.
     *
     * @return array<string, array<string, mixed>>
     */
    public function modules(): array
    {

        return [
            'finance' => [
                'slug' => 'finance', 'label' => 'Finance', 'code' => 'FIN', 'iconKey' => 'finance',
                'accent' => '#2563eb', 'accent2' => '#1d2b57', 'gradient' => 'linear-gradient(135deg, #1d2b57, #2563eb)',
                'description' => 'Pilotage financier, facturation, caisse, règlements et suivi des dépenses par dossier de transit.',
                'kpis' => [
                    ['label' => 'Décaissements', 'value' => '0', 'meta' => 'Socle prêt pour les demandes à valider'],
                    ['label' => 'Factures', 'value' => '0', 'meta' => 'Espace prévu pour les pièces clients'],
                    ['label' => 'Règlements', 'value' => '0', 'meta' => 'Suivi des paiements à brancher'],
                    ['label' => 'Alertes', 'value' => '0', 'meta' => 'Anomalies financières à surveiller'],
                ],
                'actions' => [
                    ['label' => 'Nouvelle dépense', 'hint' => 'Préparer une demande de décaissement', 'url' => '/finance/depenses'],
                    ['label' => 'Facturation', 'hint' => 'Suivre les factures et règlements', 'url' => '/finance/factures'],
                    ['label' => 'Trésorerie / Comptabilité', 'hint' => 'Consulter le grand livre', 'url' => '/finance/comptabilite'],
                ],
            ],
            'colisage' => [
                'slug' => 'colisage', 'label' => 'Colisage', 'code' => 'COL', 'iconKey' => 'colisage',
                'accent' => '#f97316', 'accent2' => '#ca8a04', 'gradient' => 'linear-gradient(135deg, #ca8a04, #f97316)',
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
                'slug' => 'logistique', 'label' => 'Logistique', 'code' => 'LOG', 'iconKey' => 'logistique',
                'accent' => '#22c55e', 'accent2' => '#047857', 'gradient' => 'linear-gradient(135deg, #047857, #22c55e)',
                'description' => 'Enlèvements, livraisons, transporteurs, véhicules et mouvements terrain des marchandises.',
                'kpis' => [
                    ['label' => 'Mouvements', 'value' => '0', 'meta' => 'Enlèvements et livraisons'],
                    ['label' => 'Transporteurs', 'value' => '0', 'meta' => 'Prestataires actifs'],
                    ['label' => 'Véhicules', 'value' => '0', 'meta' => 'Flotte et affectations'],
                    ['label' => 'Incidents', 'value' => '0', 'meta' => 'Événements terrain ouverts'],
                ],
                'actions' => [
                    ['label' => 'Enregistrer un colis', 'hint' => 'Saisie fiche de colisage', 'url' => '/colisage/parcels/nouveau'],
                    ['label' => 'Suivi de livraison', 'hint' => 'Consulter les colis actifs', 'url' => '/colisage/parcels'],
                    ['label' => 'Suivi GPS', 'hint' => 'Mettre à jour les coordonnées logistiques', 'url' => '/colisage/exploitation/tracking'],
                ],
            ],
            'employee' => [
                'slug' => 'espace-employe', 'label' => 'Espace employé', 'code' => 'EMP', 'iconKey' => 'employee',
                'accent' => '#0ea5e9', 'accent2' => '#0369a1', 'gradient' => 'linear-gradient(135deg, #0369a1, #0ea5e9)',
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
            'crm' => [
                'slug' => 'crm', 'label' => 'CRM', 'code' => 'CRM', 'iconKey' => 'crm',
                'accent' => '#ec4899', 'accent2' => '#9d174d', 'gradient' => 'linear-gradient(135deg, #9d174d, #ec4899)',
                'description' => 'Clients, prospects, relances, opportunités commerciales et suivi relationnel import-export.',
                'kpis' => [
                    ['label' => 'Clients', 'value' => '0', 'meta' => 'Clients et prospects enregistrés'],
                    ['label' => 'Relances', 'value' => '0', 'meta' => 'Actions commerciales à suivre'],
                    ['label' => 'Opportunités', 'value' => '0', 'meta' => 'Dossiers commerciaux actifs'],
                    ['label' => 'Interactions', 'value' => '0', 'meta' => 'Historique relation client'],
                ],
                'actions' => [
                    ['label' => 'Nouveau client', 'hint' => 'Créer une fiche client/prospect', 'url' => '/crm/dashboard'],
                    ['label' => 'Pipeline', 'hint' => 'Suivre les opportunités et relances', 'url' => '/crm/dashboard'],
                    ['label' => 'Interactions', 'hint' => 'Historique appels, mails et visites', 'url' => '/crm/dashboard'],
                ],
            ],
            'tickets' => [
                'slug' => 'tickets', 'label' => 'Tickets', 'code' => 'TIC', 'iconKey' => 'tickets',
                'accent' => '#ef4444', 'accent2' => '#991b1b', 'gradient' => 'linear-gradient(135deg, #991b1b, #ef4444)',
                'description' => 'Demandes interpersonnelles, interactions interservices, SLA, relances et traçabilité interne.',
                'kpis' => [
                    ['label' => 'Tickets ouverts', 'value' => '0', 'meta' => 'Demandes interservices en cours'],
                    ['label' => 'Urgents', 'value' => '0', 'meta' => 'Priorités à traiter'],
                    ['label' => 'En retard', 'value' => '0', 'meta' => 'SLA dépassés'],
                    ['label' => 'Messages', 'value' => '0', 'meta' => 'Interactions internes'],
                ],
                'actions' => [
                    ['label' => 'Nouveau ticket', 'hint' => 'Créer une demande vers un service ou un collègue', 'url' => '/tickets/dashboard'],
                    ['label' => 'Mes tickets', 'hint' => 'Suivre les demandes émises et reçues', 'url' => '/tickets/dashboard'],
                    ['label' => 'SLA & relances', 'hint' => 'Contrôler les délais et escalades', 'url' => '/tickets/dashboard'],
                ],
            ],
            'site-admin' => [
                'slug' => 'site-admin', 'label' => 'Site internet', 'code' => 'WEB', 'iconKey' => 'website',
                'accent' => '#14b8a6', 'accent2' => '#0f766e', 'gradient' => 'linear-gradient(135deg, #0f766e, #14b8a6)',
                'description' => 'Pilotage du site vitrine transit, contenus, services, demandes clients et suivi colis public.',
                'kpis' => [
                    ['label' => 'Pages publiées', 'value' => '0', 'meta' => 'Contenus visibles sur le site'],
                    ['label' => 'Services', 'value' => '0', 'meta' => 'Offres import-export mises en avant'],
                    ['label' => 'Leads web', 'value' => '0', 'meta' => 'Demandes reçues via le site'],
                    ['label' => 'Suivis colis', 'value' => '0', 'meta' => 'Consultations tracking enregistrées'],
                ],
                'actions' => [
                    ['label' => 'Gérer les pages', 'hint' => 'Accueil, services, agences, FAQ', 'url' => '/site-admin/dashboard'],
                    ['label' => 'Demandes web', 'hint' => 'Traiter devis, tracking et contacts', 'url' => '/site-admin/dashboard'],
                    ['label' => 'Voir le site', 'hint' => 'Ouvrir la partie publique', 'url' => '/site'],
                ],
            ],
            'transit-douane' => [
                'slug' => 'transit-douane', 'label' => 'Transit Douane', 'code' => 'TDO', 'iconKey' => 'customs',
                'accent' => '#7c3aed', 'accent2' => '#4c1d95', 'gradient' => 'linear-gradient(135deg, #4c1d95, #7c3aed)',
                'description' => 'Déclarations, formalités douanières, documents réglementaires, mainlevées et conformité import-export.',
                'kpis' => [
                    ['label' => 'Dossiers douane', 'value' => '0', 'meta' => 'Dossiers ouverts ou à qualifier'],
                    ['label' => 'Déclarations', 'value' => '0', 'meta' => 'Déclarations à suivre'],
                    ['label' => 'Mainlevées', 'value' => '0', 'meta' => 'Mainlevées attendues'],
                    ['label' => 'Alertes conformité', 'value' => '0', 'meta' => 'Contrôles documentaires à traiter'],
                ],
                'actions' => [
                    ['label' => 'Nouveau dossier douane', 'hint' => 'Initier un dossier de transit réglementaire', 'url' => '/transit-douane/dashboard'],
                    ['label' => 'Documents douaniers', 'hint' => 'Contrôler les pièces obligatoires', 'url' => '/transit-douane/dashboard'],
                    ['label' => 'Mainlevées', 'hint' => 'Suivre les statuts de libération', 'url' => '/transit-douane/dashboard'],
                ],
            ],
            'tracking-colis' => [
                'slug' => 'tracking-colis', 'label' => 'Tracking Colis', 'code' => 'TRK', 'iconKey' => 'tracking',
                'accent' => '#06b6d4', 'accent2' => '#155e75', 'gradient' => 'linear-gradient(135deg, #155e75, #06b6d4)',
                'description' => 'Suivi interne et public des colis, statuts, événements, notifications clients et preuves de livraison.',
                'kpis' => [
                    ['label' => 'Colis suivis', 'value' => '0', 'meta' => 'Références actives'],
                    ['label' => 'En transit', 'value' => '0', 'meta' => 'Colis en mouvement'],
                    ['label' => 'Livrés', 'value' => '0', 'meta' => 'Preuves de livraison attendues'],
                    ['label' => 'Demandes web', 'value' => '0', 'meta' => 'Recherches faites sur le site public'],
                ],
                'actions' => [
                    ['label' => 'Rechercher un colis', 'hint' => 'Consulter une référence de suivi', 'url' => '/tracking-colis/dashboard'],
                    ['label' => 'Ajouter événement', 'hint' => 'Mettre à jour un statut opérationnel', 'url' => '/tracking-colis/dashboard'],
                    ['label' => 'Notifications client', 'hint' => 'Préparer les alertes automatiques', 'url' => '/tracking-colis/dashboard'],
                ],
            ],
            'facturation' => [
                'slug' => 'facturation', 'label' => 'Facturation', 'code' => 'FAC', 'iconKey' => 'billing',
                'accent' => '#16a34a', 'accent2' => '#14532d', 'gradient' => 'linear-gradient(135deg, #14532d, #16a34a)',
                'description' => 'Factures clients, proformas, avoirs, règlements, taxes, frais transit et rentabilité par dossier.',
                'kpis' => [
                    ['label' => 'Proformas', 'value' => '0', 'meta' => 'Devis à convertir'],
                    ['label' => 'Factures', 'value' => '0', 'meta' => 'Pièces émises'],
                    ['label' => 'Impayés', 'value' => '0', 'meta' => 'Relances financières'],
                    ['label' => 'CA estimé', 'value' => '0', 'meta' => 'Indicateur à brancher'],
                ],
                'actions' => [
                    ['label' => 'Nouvelle facture', 'hint' => 'Créer une facture', 'url' => '/finance/factures/nouveau'],
                    ['label' => 'Factures & Règlements', 'hint' => 'Suivre les règlements clients', 'url' => '/finance/factures'],
                    ['label' => 'Points de caisse', 'hint' => 'Faire mon point de caisse', 'url' => '/finance/clotures'],
                ],
            ],
            'entrepots' => [
                'slug' => 'entrepots', 'label' => 'Entrepôts', 'code' => 'ENT', 'iconKey' => 'warehouse',
                'accent' => '#a16207', 'accent2' => '#713f12', 'gradient' => 'linear-gradient(135deg, #713f12, #a16207)',
                'description' => 'Gestion des magasins, emplacements, entrées/sorties, inventaires, stockage temporaire et anomalies.',
                'kpis' => [
                    ['label' => 'Entrepôts', 'value' => '0', 'meta' => 'Sites de stockage paramétrés'],
                    ['label' => 'Entrées', 'value' => '0', 'meta' => 'Marchandises réceptionnées'],
                    ['label' => 'Sorties', 'value' => '0', 'meta' => 'Livraisons préparées'],
                    ['label' => 'Anomalies', 'value' => '0', 'meta' => 'Écarts ou litiges de stock'],
                ],
                'actions' => [
                    ['label' => 'Réception marchandise', 'hint' => 'Créer une entrée entrepôt', 'url' => '/entrepots/dashboard'],
                    ['label' => 'Inventaire', 'hint' => 'Contrôler stocks et emplacements', 'url' => '/entrepots/dashboard'],
                    ['label' => 'Préparer sortie', 'hint' => 'Organiser la livraison depuis stock', 'url' => '/entrepots/dashboard'],
                ],
            ],
            'flotte-transport' => [
                'slug' => 'flotte-transport', 'label' => 'Flotte / Transport', 'code' => 'FLT', 'iconKey' => 'fleet',
                'accent' => '#ea580c', 'accent2' => '#7c2d12', 'gradient' => 'linear-gradient(135deg, #7c2d12, #ea580c)',
                'description' => 'Véhicules, chauffeurs, missions, carburant, maintenance, documents, disponibilité et coûts transport.',
                'kpis' => [
                    ['label' => 'Véhicules', 'value' => '0', 'meta' => 'Flotte disponible'],
                    ['label' => 'Missions', 'value' => '0', 'meta' => 'Courses planifiées'],
                    ['label' => 'Maintenance', 'value' => '0', 'meta' => 'Interventions à prévoir'],
                    ['label' => 'Carburant', 'value' => '0', 'meta' => 'Suivi consommation'],
                ],
                'actions' => [
                    ['label' => 'Planifier mission', 'hint' => 'Affecter véhicule et chauffeur', 'url' => '/flotte-transport/dashboard'],
                    ['label' => 'Maintenance', 'hint' => 'Suivre visites et réparations', 'url' => '/flotte-transport/dashboard'],
                    ['label' => 'Documents véhicules', 'hint' => 'Contrôler assurances et visites', 'url' => '/flotte-transport/dashboard'],
                ],
            ],
            'portefeuille-clients' => [
                'slug' => 'portefeuille-clients', 'label' => 'Portefeuille Clients', 'code' => 'PCL', 'iconKey' => 'portfolio',
                'accent' => '#84cc16', 'accent2' => '#365314', 'gradient' => 'linear-gradient(135deg, #365314, #84cc16)',
                'description' => 'Segmentation clients, valeur portefeuille, contrats, fidélisation, comptes stratégiques et risques commerciaux.',
                'kpis' => [
                    ['label' => 'Comptes actifs', 'value' => '0', 'meta' => 'Clients suivis'],
                    ['label' => 'VIP', 'value' => '0', 'meta' => 'Comptes stratégiques'],
                    ['label' => 'À relancer', 'value' => '0', 'meta' => 'Clients sans activité récente'],
                    ['label' => 'Risque', 'value' => '0', 'meta' => 'Comptes à surveiller'],
                ],
                'actions' => [
                    ['label' => 'Segmenter portefeuille', 'hint' => 'Classer les comptes par valeur', 'url' => '/portefeuille-clients/dashboard'],
                    ['label' => 'Contrats clients', 'hint' => 'Suivre engagements et conditions', 'url' => '/portefeuille-clients/dashboard'],
                    ['label' => 'Relances stratégiques', 'hint' => 'Préparer les actions de fidélisation', 'url' => '/portefeuille-clients/dashboard'],
                ],
            ],
            'agents-correspondants' => [
                'slug' => 'agents-correspondants', 'label' => 'Agents & Correspondants', 'code' => 'AGC', 'iconKey' => 'agents',
                'accent' => '#6366f1', 'accent2' => '#312e81', 'gradient' => 'linear-gradient(135deg, #312e81, #6366f1)',
                'description' => 'Réseau d’agents, correspondants internationaux, partenaires, zones couvertes et performance opérationnelle.',
                'kpis' => [
                    ['label' => 'Agents', 'value' => '0', 'meta' => 'Correspondants actifs'],
                    ['label' => 'Pays couverts', 'value' => '0', 'meta' => 'Zones internationales'],
                    ['label' => 'Dossiers confiés', 'value' => '0', 'meta' => 'Opérations externalisées'],
                    ['label' => 'Évaluations', 'value' => '0', 'meta' => 'Qualité partenaire'],
                ],
                'actions' => [
                    ['label' => 'Nouvel agent', 'hint' => 'Créer un correspondant international', 'url' => '/agents-correspondants/dashboard'],
                    ['label' => 'Zones couvertes', 'hint' => 'Paramétrer pays, ports et contacts', 'url' => '/agents-correspondants/dashboard'],
                    ['label' => 'Performance', 'hint' => 'Comparer délais et qualité de service', 'url' => '/agents-correspondants/dashboard'],
                ],
            ],
            'pilotage-dg' => [
                'slug' => 'pilotage-dg', 'label' => 'Centre de Pilotage DG', 'code' => 'DG', 'iconKey' => 'pilotage',
                'accent' => '#eab308', 'accent2' => '#854d0e', 'gradient' => 'linear-gradient(135deg, #854d0e, #eab308)',
                'description' => 'Vue exécutive transverse : activité, finance, opérations, risques, performances sites et décisions prioritaires.',
                'kpis' => [
                    ['label' => 'Modules suivis', 'value' => '0', 'meta' => 'Indicateurs transverses'],
                    ['label' => 'Alertes DG', 'value' => '0', 'meta' => 'Points critiques à arbitrer'],
                    ['label' => 'Sites', 'value' => '0', 'meta' => 'Agences et points de vente'],
                    ['label' => 'Décisions', 'value' => '0', 'meta' => 'Actions à prioriser'],
                ],
                'actions' => [
                    ['label' => 'Synthèse exécutive', 'hint' => 'Afficher les KPI prioritaires', 'url' => '/pilotage-dg/dashboard'],
                    ['label' => 'Alertes critiques', 'hint' => 'Suivre les points bloquants', 'url' => '/pilotage-dg/dashboard'],
                    ['label' => 'Performance sites', 'hint' => 'Comparer les agences et pays', 'url' => '/pilotage-dg/dashboard'],
                ],
            ],
        ];

    }

    /**
     * Modules exposés sur selection_portail, hors RH/Admin qui gardent leurs règles ACL dédiées.
     *
     * @return array<int, array<string, string>>
     */
    public function portalModules(): array
    {
        $modules = [];
        foreach ($this->modules() as $key => $module) {
            $modules[] = [
                'key' => $key,
                'label' => (string) $module['label'],
                'code' => (string) $module['code'],
                'icon' => (string) $module['iconKey'],
                'description' => (string) $module['description'],
                'url' => '/' . (string) $module['slug'] . '/dashboard',
                'class' => 'module-' . (($key === 'site-admin') ? 'website' : (string) $module['slug']),
                'status' => $key === 'site-admin' ? 'Public + backoffice' : 'Dashboard actif',
                'keywords' => strtolower((string) $module['label'] . ' ' . (string) $module['code'] . ' ' . (string) $module['description']),
            ];
        }

        return $modules;
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

        $module = BusinessDashboardModule::fromArray($modules[$slug])->toArray();
        $module['navigation'] = $this->navigation($module);
        $module['workflow'] = [
            ['title' => 'Models', 'text' => 'Les objets métier du module sont isolés dans app/Models pour garder les vues sans logique métier.'],
            ['title' => 'Controllers', 'text' => 'Le contrôleur ne fait que protéger la route, appeler le service et transmettre les données à la vue.'],
            ['title' => 'Services / Repositories', 'text' => 'La logique applicative et les futures requêtes SQL sont préparées pour évoluer sans régression.'],
        ];
        $module['showWorkflow'] = false;

        return $module;
    }

    /**
     * @param array<string, mixed> $module
     * @return array<int, array<string, mixed>>
     */
    private function navigation(array $module): array
    {
        if ($module['slug'] === 'finance') {
            return [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => '/finance/dashboard', 'available' => true],
                ['key' => 'factures', 'label' => 'Factures Clients', 'icon' => 'FAC', 'url' => '/finance/factures', 'available' => true],
                ['key' => 'clotures', 'label' => 'Points de Caisse', 'icon' => 'CLT', 'url' => '/finance/clotures', 'available' => true],
                ['key' => 'depenses', 'label' => 'Dépenses Prestataires', 'icon' => 'DEP', 'url' => '/finance/depenses', 'available' => true],
                ['key' => 'comptabilite', 'label' => 'Comptabilité', 'icon' => 'CPT', 'url' => '/finance/comptabilite', 'available' => true],
            ];
        }

        if ($module['slug'] === 'facturation') {
            return [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => '/facturation/dashboard', 'available' => true],
                ['key' => 'factures', 'label' => 'Factures Clients', 'icon' => 'FAC', 'url' => '/finance/factures', 'available' => true],
                ['key' => 'clotures', 'label' => 'Points de Caisse', 'icon' => 'CLT', 'url' => '/finance/clotures', 'available' => true],
            ];
        }

        if ($module['slug'] === 'logistique') {
            return [
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => '/logistique/dashboard', 'available' => true],
                ['key' => 'parcels', 'label' => 'Gestion des Colis', 'icon' => 'CL', 'url' => '/colisage/parcels', 'available' => true],
                ['key' => 'groupage', 'label' => 'Groupage & Expéditions', 'icon' => 'GP', 'url' => '/colisage/groupage', 'available' => true],
                ['key' => 'tracking', 'label' => 'Suivi GPS', 'icon' => 'GPS', 'url' => '/colisage/exploitation/tracking', 'available' => true],
                ['key' => 'exploitation_fournitures', 'label' => 'Fournitures bureau', 'icon' => 'FT', 'url' => '/colisage/exploitation/fournitures', 'available' => \App\Helpers\Auth::can(\App\Security\PermissionEntityRegistry::EXPLOITATION_FOURNITURES)],
            ];
        }

        $base = '/' . $module['slug'];
        $nav = [
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'operations', 'label' => 'Opérations', 'icon' => 'OP', 'url' => $base . '/dashboard', 'available' => true],
        ];

        $nav = array_merge($nav, [
            ['key' => 'documents', 'label' => 'Documents', 'icon' => 'DOC', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'reporting', 'label' => 'Reporting', 'icon' => 'RP', 'url' => $base . '/dashboard', 'available' => true],
            ['key' => 'settings', 'label' => 'Paramétrage', 'icon' => 'PR', 'url' => $base . '/dashboard', 'available' => true],
        ]);

        return $nav;
    }
}
