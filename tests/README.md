# Tests ERP LBP

## Structure

```text
tests/
├── Unit/       # Tests unitaires rapides, sans vraie base de données
├── Feature/    # Tests de comportement interne : routes, contrôleurs légers, flux simples
├── Smoke/      # Scripts de vérification bout-en-bout sur la vraie application locale
├── TestCase.php
└── bootstrap.php
```

## Commandes

Depuis la racine du projet :

```powershell
.\vendor\bin\phpunit
```

ou :

```powershell
composer test
```

Les smoke tests restent séparés de PHPUnit car ils touchent la vraie base et exécutent `bootstrap/app.php` :

```powershell
php tests\Smoke\smoke_admin.php
php tests\Smoke\smoke_visibility.php
```

## Règle de projet

- Unit = rapide, isolé, pas de MySQL.
- Feature = vérifie le comportement applicatif sans navigateur.
- Smoke = vérifie que l'application locale complète fonctionne réellement.
