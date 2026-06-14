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
