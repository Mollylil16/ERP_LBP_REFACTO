# ERP LBP — Vague 1 de tests unitaires

Cette vague ajoute les premiers vrais tests unitaires/repository sans déplacer le code applicatif.

## Principe

- `app/Services/*` et `app/Repositories/*` restent le code de l'application.
- `tests/Unit/*` contient les tests PHPUnit.
- Les tests repository utilisent SQLite en mémoire quand c'est possible.
- Les tests service utilisent des mocks PHPUnit pour isoler la logique métier.

## Fichiers ajoutés

```text
tests/Support/DatabaseTestCase.php
tests/Unit/Services/AuthServiceTest.php
tests/Unit/Services/RhPersonnelServiceTest.php
tests/Unit/Services/AdminServiceTest.php
tests/Unit/Repositories/UserRepositoryTest.php
tests/Unit/Repositories/PermissionRepositoryTest.php
```

## Fichier modifié

```text
tests/bootstrap.php
```

Ajout d'un autoloader `Tests\` pour charger automatiquement `tests/Support/*`.

## Commande

```powershell
.\vendor\bin\phpunit
```

## Règle à suivre pour une nouvelle fonctionnalité

Pour une fonctionnalité `XxxService`, créer :

```text
tests/Unit/Services/XxxServiceTest.php
```

Pour un repository `XxxRepository`, créer :

```text
tests/Unit/Repositories/XxxRepositoryTest.php
```

Pour un parcours complet HTTP ou contrôleur :

```text
tests/Feature/<Module>/<Scenario>Test.php
```
