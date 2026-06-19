# Architecture du module Administration

## Flux d’une page

Exemple pour `/admin/users` :

```text
routes/admin.php
  -> AdminUserController::index()
  -> AdminService::listUsers()
  -> UserIndexPage
  -> views/admin/users/index.php
  -> views/layouts/module.php
  -> Navigation::module()
```

## Contrat du module

Tous les contrôleurs Admin étendent `AdminBaseController` et appellent :

```php
$this->adminView(
    'admin/users/index',
    'Utilisateurs',
    'users',
    ['page' => new UserIndexPage($data)]
);
```

`AdminBaseController` fournit automatiquement :

- `moduleName` ;
- `moduleCode` ;
- `activeModule` ;
- `moduleNavigation` ;
- les feuilles de style ;
- les scripts JavaScript.

## Navigation

`AdminNavigation::items()` est l’unique catalogue des liens :

```text
dashboard
users
permissions
tests
```

La vue ne charge aucun partial de navigation. Le layout commun rend les liens
avec `Navigation::module()`.

## Page Objects

| Classe | Vue |
|---|---|
| `DashboardPage` | `admin/dashboard.php` |
| `UserIndexPage` | `admin/users/index.php` |
| `UserFormPage` | `admin/users/form.php` |
| `UserShowPage` | `admin/users/show.php` |
| `PermissionEditPage` | `admin/permissions/edit.php` |
| `PermissionMatrixPage` | `admin/permissions/matrix.php` |
| `SystemTestsPage` | `admin/system_tests/index.php` |

La vue reçoit uniquement :

```php
/** @var UserIndexPage $page */
```

Les dates, liens de pagination, options RH et résumés de permissions sont
préparés avant le rendu.

## Composants

`App\View\Components\Admin` contient les fragments propres au module :

- liste des entités sécurisées ;
- tableau des utilisateurs ;
- pagination ;
- profil RH ;
- grille CRUD ;
- résumé des permissions ;
- matrice globale ;
- interface Santé & Tests.

Les éléments déjà génériques ne sont pas redéfinis dans `Admin`. Par exemple,
les vues appellent directement `Ui::pageHeader()` ; créer
`Admin::pageHeader()` ne ferait que dupliquer l’API commune.

Les composants génériques restent dans :

- `Ui` pour les sections, boutons et entêtes ;
- `Form` pour les contrôles de formulaire ;
- `Dashboard` pour les KPI ;
- `Navigation` pour le menu latéral.

## Ajouter une page Admin

1. Déclarer la route dans `routes/admin.php`.
2. Ajouter l’action dans un contrôleur Admin.
3. Créer un Page Object dans `app/View/Pages/Admin`.
4. Appeler `adminView()` avec une clé de navigation existante.
5. Créer une vue mince qui utilise `$page` et les composants.
6. Ajouter un lien dans `AdminNavigation` uniquement si la page doit apparaître
   dans le menu.
7. Ajouter les tests du Page Object et du composant.

## Règles

- aucune vue Admin ne construit la navigation ;
- aucune vue Admin ne crée son propre `ViewBag` ;
- aucune vue Admin ne prépare pagination ou permissions ;
- aucun contrôleur Admin ne répète le contexte du layout ;
- aucun SQL dans les contrôleurs, Page Objects, composants ou vues ;
- toute écriture vérifie le CSRF et les droits administrateur côté serveur ;
- les assets supplémentaires sont déclarés par le contrôleur, jamais avec des
  balises manuelles dans la vue.
