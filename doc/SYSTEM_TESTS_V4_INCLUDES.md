# Santé & Tests V4 — contrôle des includes/partials

Cette version ajoute un contrôle `Includes / partials` par module.

Objectif : détecter les erreurs d'intégration non vues par un simple ping HTTP, par exemple :

- la navigation RH est fournie par `App\View\Navigation\RhNavigation` ;
- un partial statique réellement requis mais manquant ;
- partial requis par une vue mais absent du disque.

Le contrôle scanne les vues du module et résout les includes statiques de type :

```php
include __DIR__ . '/partial.php';
```

Si un fichier requis n'existe pas, la carte du module passe en échec et le détail indique :

- le fichier appelant ;
- l'include demandé ;
- le chemin résolu manquant.

Après installation :

```powershell
composer dump-autoload
.\vendor\bin\phpunit
php tests\Smoke\smoke_admin.php
php tests\Smoke\smoke_visibility.php
```

Puis relancer `/admin/system-tests`.
