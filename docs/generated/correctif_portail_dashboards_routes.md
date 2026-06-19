# Correctif portail / dashboards / routes

## Cause corrigée

`BaseController::view()` fusionnait les valeurs par défaut avec l’opérateur `+` dans le mauvais sens :

```php
$data = ViewBag::defaults() + $data;
```

En PHP, l’opérateur `+` conserve les clés du tableau de gauche. Les vraies données envoyées par les contrôleurs (`modules`, `additionalStyles`, `moduleName`, `moduleTheme`, etc.) étaient donc ignorées dès qu’une clé existait dans les valeurs par défaut.

La fusion est remplacée par :

```php
$data = array_replace(ViewBag::defaults(), $data);
```

Les valeurs par défaut restent disponibles, mais les contrôleurs reprennent la priorité.

## Routes

Les routes peuvent rester séparées. Le fichier `routes/web.php` est le manifeste principal : il inclut les fichiers de routes métier.

Ajout effectué :

```php
$router->get('/portail', [SelectionPortailController::class, 'index']);
```

`/selection_portail` reste la route canonique existante.
