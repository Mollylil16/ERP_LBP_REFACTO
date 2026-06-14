# Modules transit/import-export

Ce lot ajoute les dashboards métiers suivants sans supprimer les modules existants : CRM, Tickets, Site internet, Transit Douane, Tracking Colis, Facturation, Entrepôts, Flotte / Transport, Portefeuille Clients, Agents & Correspondants Internationaux, Centre de Pilotage DG.

## Architecture

- `app/Models/BusinessDashboardModule.php` : modèle de présentation d’un dashboard métier.
- `app/Services/ModuleDashboardService.php` : catalogue central des modules, couleurs, icônes, KPI et actions rapides.
- `app/Controllers/BusinessModuleController.php` : contrôleur unique propre pour exposer chaque dashboard.
- `views/modules/dashboard.php` : vue dashboard commune respectant `finea-ui.css`.
- `routes/web.php` : chaque module expose `/module` et `/module/dashboard`.

## Site public

Le site public reste séparé de l’ERP :

- `/site`
- `/site/tracking`
- `/site/devis`
- `/site/contact`

Le backoffice du site reste dans l’ERP :

- `/site-admin/dashboard`

## Identité visuelle

Chaque module possède une icône SVG et une couleur distinctive dans :

- `app/Helpers/ModuleIcon.php`
- `views/selection_portail/index.php`
- `public/assets/css/app.css`
- `public/assets/css/finea-ui.css`
