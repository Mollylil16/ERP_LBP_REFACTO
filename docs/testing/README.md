# Guide de tests ERP LBP

## Emplacement du fichier XML

Le fichier `phpunit.xml` doit être placé à la racine exacte du projet :

```text
C:\wamp\www\ERP_LBP_REFACTO\phpunit.xml
```

Il doit être au même niveau que :

```text
composer.json
bootstrap/
app/
routes/
tests/
public/
```

## Installation PHPUnit conseillée

Pour PHP 8.3 :

```powershell
composer require --dev phpunit/phpunit:^12.5 --with-all-dependencies
```

Si Composer échoue sous Windows avec `Could not delete ...`, fermer VS Code, arrêter WAMP, désactiver temporairement l'indexation/antivirus sur le dossier projet, puis relancer :

```powershell
composer clear-cache
Remove-Item -Recurse -Force .\vendor, .\composer.lock
composer require --dev phpunit/phpunit:^12.5 --with-all-dependencies
```

## Scripts Composer recommandés

Ajouter dans `composer.json` :

```json
{
  "scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite Unit",
    "test:feature": "phpunit --testsuite Feature",
    "smoke:admin": "php tests/Smoke/smoke_admin.php",
    "smoke:visibility": "php tests/Smoke/smoke_visibility.php"
  }
}
```
