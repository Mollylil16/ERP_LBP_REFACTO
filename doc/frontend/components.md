# Composants frontend ERP LBP

Les vues doivent recevoir `/** @var \App\Support\ViewBag $viewData */` puis lire les donnees avec `$viewData->string()`, `$viewData->array()`, `$viewData->int()`, `$viewData->bool()` ou `$viewData->get()`.

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
- Utiliser `ViewBag` au lieu de dépendre de variables extraites.
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
