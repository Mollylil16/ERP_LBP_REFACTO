# Santé & Tests ERP LBP — V3 Page Health Checks

Cette version ajoute une couche de contrôle HTTP par module.

## Ajouts

- Correction du contrôle `php -l` sous Apache/WAMP : le service utilise désormais `PHP_BINDIR/php.exe` au lieu de `PHP_BINARY` quand l'interface tourne dans Apache.
- Ajout du contrôle `Pages HTTP • Module`.
- Chaque module peut déclarer des `pages`.
- Le test complet vérifie maintenant :
  - connexion BDD ;
  - syntaxe PHP ;
  - PHPUnit ;
  - smoke tests ;
  - BDD module ;
  - pages HTTP module.

## Détection

Une page est marquée en erreur si son HTML contient notamment :

- `Fatal error`
- `Parse error`
- `Warning:`
- `Uncaught`
- `SQLSTATE`
- `PDOException`
- `Undefined variable`
- `Undefined array key`
- `Page introuvable`

## Fichier modifié

- `app/Services/SystemTestService.php`

Après extraction :

```powershell
composer dump-autoload
php tests\Smoke\smoke_admin.php
php tests\Smoke\smoke_visibility.php
.\vendor\bin\phpunit
```

Puis relancer le test complet depuis `/admin/system-tests`.
