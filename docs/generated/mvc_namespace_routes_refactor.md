# Standardisation namespaces, routes et dashboards MVC

## Réorganisation appliquée

### Controllers
- `app/Controllers/Admin`
- `app/Controllers/Auth`
- `app/Controllers/Core`
- `app/Controllers/Employee`
- `app/Controllers/Modules`
- `app/Controllers/Portal`
- `app/Controllers/Rh`
- `app/Controllers/Site`

`BaseController` reste volontairement dans `app/Controllers` comme classe socle commune.

### Services
- `app/Services/Admin`
- `app/Services/Auth`
- `app/Services/Employee`
- `app/Services/Modules`
- `app/Services/Rh`
- `app/Services/Support`

### Repositories
- `app/Repositories/Admin`
- `app/Repositories/Employee`
- `app/Repositories/Modules`
- `app/Repositories/Rh`

## Dashboards métiers

Chaque module métier transit dispose maintenant d'une chaîne MVC dédiée :

`Route dédiée → DashboardController dédié → DashboardService dédié → DashboardRepository dédié → vue dédiée`

Modules concernés :

- Finance
- Colisage
- Logistique
- CRM
- Tickets
- Site admin
- Transit Douane
- Tracking Colis
- Facturation
- Entrepôts
- Flotte / Transport
- Portefeuille Clients
- Agents & Correspondants
- Pilotage DG

## Routes séparées par domaine

- `routes/public.php` : accueil + site public.
- `routes/auth.php` : authentification.
- `routes/portal.php` : portail + dashboard principal.
- `routes/modules.php` : dashboards métiers transit.
- `routes/employee.php` : espace employé.
- `routes/rh.php` : module RH.
- `routes/admin.php` : administration.
- `routes/web.php` : point d'entrée unique qui charge les fichiers de routes par domaine.

## Components

- `Dashboard::businessModuleDashboard()` centralise le rendu des dashboards métiers.
- Les vues dédiées des modules n'ont plus de gros HTML métier : elles délèguent le rendu au component.

## Compatibilité

Les URLs existantes sont conservées :

- `/finance` et `/finance/dashboard`
- `/colisage` et `/colisage/dashboard`
- `/logistique` et `/logistique/dashboard`
- etc.

## Vérifications effectuées

- `php -l` OK sur `app/`, `routes/`, `views/`, `tests/`.
- Chargement de `routes/web.php` OK.
- Vérification statique des actions de routes OK : classes et méthodes de controllers trouvées.
- PHPUnit non lancé dans l'environnement Linux de génération car les extensions PHP `dom`, `mbstring`, `xml`, `xmlwriter` sont absentes.
