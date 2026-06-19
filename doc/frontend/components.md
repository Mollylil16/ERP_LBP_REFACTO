# Composants frontend ERP LBP

Les vues modernisées reçoivent un objet typé `$page` construit par le contrôleur.
`ViewBag` reste uniquement une compatibilité transitoire pour les anciennes vues.

## Formulaires

Importer :

```php
use App\View\Components\Form;
use App\View\Components\Ui;
```

### Input simple

```php
<?= Form::input('full_name', 'Nom complet', $employee['full_name'] ?? '', ['required' => true]) ?>
<?= Form::input('birth_date', 'Date de naissance', $employee['birth_date'] ?? '', ['type' => 'date']) ?>
```

### Textarea

```php
<?= Form::textarea('notes', 'Observations', $notes ?? '', ['rows' => 5]) ?>
```

### Select classique

```php
<?= Form::select('status', 'Statut', $options, $selected) ?>
```

### Select-search

Le select-search affiche un seul champ. Le `<select>` natif est conservé mais masqué pour que le POST reste standard.

```php
<?= Form::selectSearch('service_id', 'Service', $services, $employee['service_id'] ?? null) ?>
```

### Multi-select avec badges

```php
<?= Form::selectSearch('module_ids', 'Modules', $modules, $selectedIds, ['multiple' => true]) ?>
```

### Dropzone

```php
<?= Form::dropzone('photo', "Photo d'identité", ['accept' => 'image/*']) ?>
```

## UI

```php
<?= Ui::pageHeader('RH', 'Personnel', 'Gestion des collaborateurs', Ui::button('Retour', 'rh/personnel', 'secondary')) ?>
<?= Ui::section('Identité', $html) ?>
<?= Ui::badge('Actif', 'success') ?>
<?= Ui::emptyState('Aucune donnée') ?>
```

## Règles projet

- Ne pas écrire de `<input>`, `<select>` ou `<textarea>` directement dans une nouvelle vue.
- Utiliser un Page Object typé `$page` au lieu de dépendre de variables extraites.
- Pour les listes de choix, fournir des tableaux `[['value' => '1', 'label' => 'Libellé']]`.
- Les assets globaux sont `public/assets/css/components.css` et `public/assets/js/components.js`.

## Navigation des modules

Toute navigation latérale passe par `Navigation::module()` dans le layout partagé.

- Chaque entrée possède une `key` unique, un `label` et une `url` si elle est disponible.
- Les entrées métier précisent un `group`.
- Les anciens modules sont regroupés automatiquement par type de menu.
- Seuls les groupes défilent ; « Retour au portail » reste toujours fixe.
- Le lien actif utilise `aria-current="page"`.
- Une classe comme `RhNavigation` décrit les liens ; elle ne produit pas de HTML.
- Le contrôleur transmet la clé active et le layout appelle le composant.
- Ne pas dupliquer le menu dans une vue `_navigation.php` pour un nouveau module.

Voir aussi :

- [Architecture du module RH](../modules/rh.md)
- [Architecture du module Administration](../modules/admin.md)
- [Créer un module MVC de A à Z](../architecture/creer-un-module.md)

## Dashboards et pages

Les KPI et actions rapides utilisent `Dashboard::kpis()` et `Dashboard::actions()`.
Les en-têtes, sections, boutons, badges et états vides utilisent `Ui`.
Tous les champs utilisent `Form`. Ces règles s’appliquent à toute nouvelle page
et à toute page existante dès qu’elle est modifiée.

Ne pas créer d’alias comme `Admin::pageHeader()` ou `Rh::pageHeader()` lorsqu’un
composant générique existe déjà. Les différences visuelles passent par les
options (`class`, attributs) et les feuilles de style du module.

## Pages d’erreur

Les pages d’erreur suivent la même séparation MVC que les pages métier :

1. `ErrorController` choisit le code HTTP et construit `ErrorPage`.
2. `ErrorPage` contient tous les textes, actions et variantes visuelles.
3. `ErrorState` produit le composant HTML partagé.
4. `views/errors/*.php` assemble uniquement le composant et le layout `guest`.

Le routeur ne doit jamais inclure directement une vue d’erreur. Il appelle le
contrôleur, ce qui garantit que la logique HTTP reste hors des templates.

`ErrorPage::forStatus()` fournit une explication française et des conseils pour
les erreurs courantes : 400, 401, 403, 404, 408, 419, 422, 429, 500, 502, 503
et 504. Les autres codes utilisent une présentation générique cohérente.
