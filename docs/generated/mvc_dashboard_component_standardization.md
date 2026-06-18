# Standardisation MVC des dashboards métiers

## Objectif

Supprimer la dépendance des modules métiers au dashboard générique `views/modules/dashboard.php` et aligner les modules sur le pattern MVC déjà utilisé par Admin/RH :

- un `DashboardController` dédié par module ;
- une vue dédiée `views/<module>/dashboard.php` ;
- des routes `/module` et `/module/dashboard` pointant vers le contrôleur du module ;
- un rendu basé sur les components, pas sur du HTML dupliqué.

## Controllers créés

- `FinanceDashboardController`
- `ColisageDashboardController`
- `LogistiqueDashboardController`
- `CrmDashboardController`
- `TicketsDashboardController`
- `SiteAdminDashboardController`
- `TransitDouaneDashboardController`
- `TrackingColisDashboardController`
- `FacturationDashboardController`
- `EntrepotsDashboardController`
- `FlotteTransportDashboardController`
- `PortefeuilleClientsDashboardController`
- `AgentsCorrespondantsDashboardController`
- `PilotageDgDashboardController`

Chaque contrôleur hérite de `BaseController`, protège la route avec `AuthMiddleware::check()`, appelle `ModuleDashboardService`, puis rend sa vue dédiée.

## Vues dédiées créées

- `views/finance/dashboard.php`
- `views/colisage/dashboard.php`
- `views/logistique/dashboard.php`
- `views/crm/dashboard.php`
- `views/tickets/dashboard.php`
- `views/site_admin/dashboard.php`
- `views/transit_douane/dashboard.php`
- `views/tracking_colis/dashboard.php`
- `views/facturation/dashboard.php`
- `views/entrepots/dashboard.php`
- `views/flotte_transport/dashboard.php`
- `views/portefeuille_clients/dashboard.php`
- `views/agents_correspondants/dashboard.php`
- `views/pilotage_dg/dashboard.php`

## Components ajoutés / améliorés

- `Dashboard::businessModuleDashboard()` centralise le rendu des dashboards métiers.
- `Ui::pageHeader()` reste le point d’entrée unique pour les headers avec `badge`, `icon`, `actions`, `style`, `eyebrow_class`.
- Les dashboards métiers utilisent `Dashboard::kpis()`, `Dashboard::moduleOperations()`, `Dashboard::moduleIdentity()` et `Dashboard::moduleWorkflowSection()`.

## Routes mises à jour

Les routes métier ne pointent plus vers `BusinessModuleController` pour le rendu principal. Elles pointent maintenant vers les controllers dédiés.

## Compatibilité

`BusinessModuleController`, `ModuleDashboardController`, `CrmController`, `TicketController` et `WebsiteController::dashboard()` ont été conservés en compatibilité, mais ne rendent plus `views/modules/dashboard.php`.

## Vérifications

- `php -l` OK sur `app/`, `routes/`, `views/`.
- PHPUnit non exécutable dans l’environnement Linux fourni : extensions PHP `dom`, `mbstring`, `xml`, `xmlwriter` absentes.
