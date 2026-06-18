# Standardisation MVC complète des modules

## Objectif

Uniformiser les 17 modules ERP avec la même logique que RH/Admin :

- `Controller` dédié par module.
- `DashboardService` dédié par module.
- `DashboardRepository` dédié par module.
- Vue dédiée `views/<module>/dashboard.php`.
- Navigation dédiée `views/<module>/_navigation.php`.
- Routes séparées par métier dans `routes/<module>.php`.
- Dashboards rendus via les components partagés.

## Corrections critiques

- Correction de `App\Services\Rh\RhDashboardService` : ajout de l'import réel `App\Services\Support\DataVisibilityService`.
- Suppression de la dépendance aux namespaces génériques `App\Controllers\Modules`, `App\Services\Modules`, `App\Repositories\Modules`.
- Déplacement des classes métier vers leurs domaines propres : `Finance`, `Colisage`, `Logistique`, `Crm`, `Tickets`, etc.
- `routes/modules.php` est déprécié et n'est plus chargé par `routes/web.php`.
- `SystemTestService` scanne désormais `routes/*.php`, pas uniquement `routes/web.php`.
- Les tests de composants dashboard ne référencent plus `views/modules/dashboard.php`.
- Le backoffice Site internet est isolé dans `App\Controllers\SiteAdmin`, `App\Services\SiteAdmin`, `App\Repositories\SiteAdmin`.
- Ajout de `AdminDashboardService` / `AdminDashboardRepository`.
- Ajout de `EmployeeDashboardService` / `EmployeeDashboardRepository`.

## Modules avec structure dédiée

- Administration
- RH
- Espace employé
- Finance
- Colisage
- Logistique
- CRM
- Tickets
- Site internet / backoffice
- Transit Douane
- Tracking Colis
- Facturation
- Entrepôts
- Flotte / Transport
- Portefeuille Clients
- Agents & Correspondants
- Pilotage DG

## Vérifications effectuées

- `php -l` OK sur `app/`, `routes/`, `views/`, `tests/`.
- Vérification statique des imports `use App\...` OK.
- Vérification des classes critiques OK : RH, Finance, SiteAdmin, Shared, AdminDashboard, EmployeeDashboard.
- Aucune référence restante à `App\...\Modules` dans `app/`, `routes/`, `views/`, `tests/`.
