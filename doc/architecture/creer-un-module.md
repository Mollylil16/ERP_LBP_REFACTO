# Créer un module MVC de A à Z

Ce guide décrit la convention officielle du projet pour créer un module métier
sans mélanger navigation, HTML, logique métier et accès à la base de données.
Le module RH constitue l’exemple le plus complet.

## 1. Comprendre le trajet d’une requête

Lorsqu’un utilisateur ouvre `/rh/personnel`, le trajet est le suivant :

```text
Navigateur
  -> public/index.php
  -> routes/web.php
  -> routes/rh.php
  -> RhPersonnelController::index()
  -> RhPersonnelService::list()
  -> RhPersonnelRepository
  -> PersonnelIndexPage
  -> views/rh/personnel/index.php
  -> views/layouts/module.php
  -> Navigation::module()
  -> HTML envoyé au navigateur
```

Chaque couche a une responsabilité précise :

| Couche | Dossier | Responsabilité |
|---|---|---|
| Route | `routes/` | Associer une URL et une méthode HTTP à un contrôleur |
| Contrôleur | `app/Controllers/` | Protéger la requête et orchestrer le cas d’usage |
| Service | `app/Services/` | Appliquer les règles métier |
| Repository | `app/Repositories/` | Lire et écrire dans la base de données |
| Model | `app/Models/` | Représenter une donnée ou un objet métier |
| Page Object | `app/View/Pages/` | Préparer exactement les données attendues par une page |
| Composant | `app/View/Components/` | Produire un fragment HTML réutilisable |
| Vue | `views/` | Composer la page, sans SQL ni règle métier |
| Layout | `views/layouts/` | Produire le cadre HTML commun du module |
| CSS / JS | `public/assets/` | Présentation et interactions navigateur |

## 2. Choisir les noms du module

Avant de coder, définir :

- le nom métier, par exemple `Stocks` ;
- le slug URL, par exemple `stocks` ;
- le code court, par exemple `STK` ;
- la clé d’icône ;
- les écrans attendus ;
- les opérations nécessitant une permission ;
- les tables nécessaires.

Convention de nommage pour l’exemple :

```text
app/Controllers/Stocks/StocksDashboardController.php
app/Services/Stocks/StocksDashboardService.php
app/Repositories/Stocks/StocksDashboardRepository.php
app/View/Navigation/StocksNavigation.php
app/View/Pages/Stocks/DashboardPage.php
views/stocks/dashboard.php
routes/stocks.php
public/assets/css/stocks.css
public/assets/js/stocks.js
```

## 3. Déclarer les routes

Créer `routes/stocks.php` :

```php
<?php

declare(strict_types=1);

use App\Controllers\Stocks\StocksDashboardController;
use App\Controllers\Stocks\StocksItemController;
use App\Router;

/** @var Router $router */

$router->group('/stocks', function (Router $router): void {
    $router->get('/', [StocksDashboardController::class, 'index']);
    $router->get('/dashboard', [StocksDashboardController::class, 'index']);

    $router->group('/articles', function (Router $router): void {
        $router->get('/', [StocksItemController::class, 'index']);
        $router->get('/nouveau', [StocksItemController::class, 'create']);
        $router->post('/', [StocksItemController::class, 'store']);
        $router->get('/{id}', [StocksItemController::class, 'show']);
    });
});
```

Puis charger ce fichier dans `routes/web.php` :

```php
require __DIR__ . '/stocks.php';
```

Règles :

- `GET` affiche une page ou une ressource ;
- `POST` modifie les données ;
- les routes d’un module restent dans leur propre fichier ;
- le nom du paramètre `{id}` doit correspondre à l’argument du contrôleur ;
- une URL affichée dans la navigation doit posséder une route réelle.

## 4. Créer la navigation du module

La navigation est une donnée de présentation, pas une vue complète. Créer
`app/View/Navigation/StocksNavigation.php` :

```php
<?php

declare(strict_types=1);

namespace App\View\Navigation;

final class StocksNavigation
{
    /** @return array<int,array<string,mixed>> */
    public static function items(): array
    {
        return [
            [
                'group' => 'Pilotage',
                'key' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'DB',
                'url' => 'stocks/dashboard',
                'available' => true,
            ],
            [
                'group' => 'Catalogue',
                'key' => 'items',
                'label' => 'Articles',
                'icon' => 'AR',
                'url' => 'stocks/articles',
                'available' => true,
            ],
        ];
    }
}
```

Chaque entrée possède :

- `group` : section visuelle du menu ;
- `key` : identifiant stable et unique ;
- `label` : texte affiché ;
- `icon` : code court affiché dans l’icône ;
- `url` : URL relative au projet ;
- `available` : désactive proprement une fonction non livrée.

La clé active est envoyée par le contrôleur avec `activeModule`. Le composant
`Navigation::module()` ajoute ensuite `aria-current="page"` au bon lien.

Ne pas créer une seconde navigation HTML dans `views/stocks/_navigation.php`.
La source de vérité doit rester la classe `StocksNavigation`; le rendu commun
reste dans `views/layouts/module.php`.

## 5. Centraliser le contexte visuel du module

Quand un module possède plusieurs contrôleurs, créer un contrôleur de base
interne au module. Le module RH utilise
`app/Controllers/Rh/RhBaseController.php`.

Exemple :

```php
abstract class StocksBaseController extends BaseController
{
    protected function stocksView(
        string $view,
        string $pageTitle,
        string $activeModule,
        array $data = [],
        array $layout = [],
    ): void {
        $this->view($view, array_replace([
            'pageTitle' => $pageTitle,
            'moduleName' => 'Stocks',
            'moduleCode' => 'STK',
            'activeModule' => $activeModule,
            'moduleNavigation' => StocksNavigation::items(),
            'additionalStyles' => ['css/finea-ui.css', 'css/stocks.css'],
            'additionalScripts' => ['js/stocks.js'],
        ], $layout, $data));
    }
}
```

Cette classe centralise uniquement :

- le nom et le code du module ;
- les assets communs ;
- la navigation ;
- les données nécessaires au layout.

Elle ne contient ni SQL, ni validation métier, ni traitement de formulaire.

## 6. Créer le repository

Le repository contient les requêtes SQL. Exemple :

```php
final class StocksItemRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, reference, name, quantity
             FROM stock_items
             ORDER BY name'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

Règles :

- utiliser des requêtes préparées pour les valeurs utilisateur ;
- ne jamais mettre de SQL dans une vue ou un contrôleur ;
- retourner des données prévisibles ;
- garder les transactions cohérentes dans le repository ou le service selon le
  cas d’usage.

## 7. Créer le service

Le service porte les règles métier :

```php
final class StocksItemService
{
    public function __construct(private StocksItemRepository $repository)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return $this->repository->all();
    }
}
```

Le service doit notamment gérer :

- validation métier ;
- calculs ;
- coordination de plusieurs repositories ;
- transactions métier ;
- exceptions compréhensibles par le contrôleur.

Il ne produit pas de HTML et ne connaît pas le layout.

## 8. Créer un Page Object

Une page complexe reçoit un objet dédié plutôt qu’un grand tableau implicite :

```php
final class ItemIndexPage
{
    /** @param array<int,array<string,mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly bool $canCreate,
    ) {
    }
}
```

Le Page Object peut :

- normaliser les données ;
- préparer les actions visibles selon les permissions ;
- préparer pagination, colonnes et options de formulaire ;
- formater des valeurs destinées à l’affichage.

Il ne doit pas exécuter de SQL.

## 9. Créer le contrôleur

```php
final class StocksItemController extends StocksBaseController
{
    private StocksItemService $service;

    public function __construct()
    {
        $this->service = new StocksItemService(
            new StocksItemRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $this->stocksView(
            'stocks/items/index',
            'Articles en stock',
            'items',
            [
                'page' => new ItemIndexPage(
                    $this->service->list(),
                    true,
                ),
            ]
        );
    }
}
```

Ordre recommandé dans une action :

1. vérifier authentification ou permission ;
2. lire les paramètres HTTP nécessaires ;
3. appeler le service ;
4. construire le Page Object ;
5. rendre une vue ou rediriger.

Pour un formulaire `POST` :

1. vérifier la permission ;
2. vérifier le jeton CSRF ;
3. appeler le service ;
4. enregistrer un message flash ;
5. rediriger après succès ;
6. rediriger avec une erreur métier en cas d’échec attendu.

## 10. Créer la vue

`views/stocks/items/index.php` :

```php
<?php

use App\View\Components\Ui;
use App\View\Pages\Stocks\ItemIndexPage;

/** @var ItemIndexPage $page */

ob_start();
?>
<div class="finea-shell">
    <div class="finea-container">
        <?= Ui::pageHeader(
            'Articles en stock',
            'Consulter les références et les quantités disponibles.',
            [
                'eyebrow' => 'Stocks',
                'actions' => $page->canCreate
                    ? [Ui::button('Nouvel article', [
                        'href' => 'stocks/articles/nouveau',
                        'variant' => 'accent',
                    ])]
                    : [],
            ]
        ) ?>

        <!-- Composition de composants et HTML spécifique à la page -->
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/module.php';
```

Une vue peut :

- lire les propriétés du Page Object ;
- choisir quels blocs afficher ;
- appeler des composants ;
- produire le HTML propre à cette page.

Une vue ne doit pas :

- ouvrir une connexion à la base ;
- exécuter une requête SQL ;
- vérifier directement des permissions complexes ;
- construire la navigation du module ;
- transformer un gros résultat brut ;
- effectuer une écriture métier.

## 11. Comprendre composants et layouts

Un composant produit un fragment réutilisable :

```php
Ui::button(...)
Ui::pageHeader(...)
Form::input(...)
Navigation::module(...)
Dashboard::kpis(...)
```

Un layout produit le document commun complet :

```text
views/layouts/module.php
  -> <html>, <head>, CSS
  -> barre latérale
  -> Navigation::module(...)
  -> topbar
  -> messages flash
  -> contenu de la vue dans $content
  -> JavaScript
```

Le layout n’a pas à connaître le module RH, Stocks ou Finance. Il reçoit un
contrat de données commun :

```text
pageTitle
moduleName
moduleCode
activeModule
moduleNavigation
moduleTheme
additionalStyles
additionalScripts
content
```

## 12. Ajouter un composant

Créer un composant lorsque le même fragment :

- apparaît sur plusieurs pages ;
- possède des règles d’échappement ou d’accessibilité ;
- accepte plusieurs variantes ;
- doit évoluer partout de la même façon.

Ne pas créer un composant pour masquer quelques lignes utilisées une seule
fois. Un composant trop spécifique rend la lecture plus difficile.

Tous les textes et attributs venant des données doivent être échappés avec
`View::e()` ou par un composant qui effectue déjà cet échappement.

## 13. Ajouter CSS et JavaScript

Les styles communs restent dans :

```text
public/assets/css/app.css
public/assets/css/components.css
public/assets/css/finea-ui.css
```

Les styles du module restent dans :

```text
public/assets/css/stocks.css
```

Même règle pour JavaScript :

```text
public/assets/js/components.js
public/assets/js/stocks.js
```

Le JavaScript gère l’interface. Les permissions, validations et écritures
restent garanties côté serveur.

## 14. Ajouter le module au portail

Le module doit être ajouté au catalogue utilisé par le portail de sélection.
Dans l’architecture actuelle, les modules génériques sont déclarés dans
`ModuleDashboardService`; les modules avec règles spécifiques, comme RH, sont
complétés dans `SelectionPortailController`.

Vérifier :

- libellé et code ;
- URL d’entrée ;
- icône ;
- description et mots-clés ;
- règle de visibilité ;
- permission minimale d’accès.

## 15. Ajouter permissions et sécurité

Pour une opération sensible :

- déclarer l’entité ou l’opération dans les registres de sécurité ;
- utiliser `PermissionMiddleware` avant l’action ;
- vérifier le CSRF pour toute écriture ;
- contrôler les fichiers envoyés ;
- ne jamais se fier à un bouton masqué côté navigateur ;
- filtrer les données selon les habilitations.

Le bouton et la route répondent à deux besoins différents :

- masquer le bouton améliore l’interface ;
- protéger la route garantit la sécurité.

## 16. Ajouter la base de données

Dans ce projet, les évolutions de schéma sont pilotées par
`app/Database/MigrationRunner.php`.

Avant d’ajouter une table :

- choisir des noms cohérents ;
- définir clés primaires et étrangères ;
- prévoir index, contraintes et valeurs par défaut ;
- décider du comportement `ON DELETE` ;
- rendre la migration réexécutable sans casser une base existante.

Éviter de lancer des modifications manuelles non documentées directement dans
phpMyAdmin.

## 17. Tester

Contrôles minimum :

```powershell
php -l app/Controllers/Stocks/StocksItemController.php
php -l app/Services/Stocks/StocksItemService.php
php -l app/Repositories/Stocks/StocksItemRepository.php
php -l views/stocks/items/index.php
composer test
```

Ajouter des tests ciblés pour :

- règles métier du service ;
- validation de navigation ;
- préparation du Page Object ;
- route avec paramètre ;
- échappement HTML du composant ;
- permission et CSRF d’une écriture sensible.

Test manuel :

1. ouvrir le module depuis le portail ;
2. cliquer chaque lien de navigation ;
3. vérifier le lien actif ;
4. vérifier mobile et bureau ;
5. tester utilisateur autorisé et non autorisé ;
6. tester résultat vide, erreur et succès ;
7. vérifier les messages flash ;
8. vérifier les formulaires et fichiers ;
9. vérifier qu’aucune URL ne termine en erreur 404.

## 18. Checklist finale

- [ ] le fichier de routes est chargé par `routes/web.php` ;
- [ ] chaque URL de navigation possède une route ;
- [ ] les clés de navigation sont uniques ;
- [ ] le contrôleur étend le contrôleur de base du module ;
- [ ] les contrôleurs ne contiennent pas de SQL ;
- [ ] la logique métier est dans un service ;
- [ ] la base est accédée par un repository ;
- [ ] les pages complexes utilisent un Page Object ;
- [ ] la vue utilise `views/layouts/module.php` ;
- [ ] la navigation est rendue par `Navigation::module()` ;
- [ ] les formulaires utilisent `Form` et un jeton CSRF ;
- [ ] les actions sensibles vérifient les permissions côté serveur ;
- [ ] les textes dynamiques sont échappés ;
- [ ] CSS et JS spécifiques sont chargés une seule fois ;
- [ ] le module est visible dans le portail ;
- [ ] la migration est réexécutable ;
- [ ] les tests et vérifications syntaxiques passent.

## 19. Erreurs fréquentes

### Dupliquer la navigation dans chaque vue

Conséquence : un lien est corrigé à plusieurs endroits et les menus divergent.

Solution : une classe de navigation, un composant de rendu, un layout.

### Passer vingt variables libres à la vue

Conséquence : contrat implicite et erreurs de variables manquantes.

Solution : utiliser un Page Object nommé.

### Mettre une requête SQL dans le contrôleur

Conséquence : code difficile à tester et règles métier mélangées.

Solution : repository pour SQL, service pour métier.

### Considérer le JavaScript comme une sécurité

Conséquence : une requête HTTP manuelle contourne l’interface.

Solution : permission, validation et CSRF côté serveur.

### Créer un composant pour chaque balise

Conséquence : la vue devient une suite d’appels abstraits difficile à lire.

Solution : extraire seulement les blocs réellement réutilisables ou complexes.
