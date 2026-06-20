# Tests navigateur avec Symfony Panther

Panther complète les tests PHPUnit et les smoke tests existants. Il pilote un vrai navigateur Chrome pour vérifier les pages, les redirections, les formulaires et le rendu final. Il ne remplace ni les tests unitaires ni les tests de fumée.

## Installation

Depuis la racine du projet :

```bash
composer install
vendor/bin/bdi detect drivers
```

La seconde commande installe dans `drivers/` une version de ChromeDriver compatible avec le navigateur présent sur la machine. Google Chrome ou Chromium doit être installé.

Les dépendances de développement utilisées sont :

- `symfony/panther` pour le pilotage du navigateur ;
- `dbrekelmans/bdi` pour détecter et installer ChromeDriver ;
- `phpunit/phpunit`, déjà utilisé par le projet.

## Lancer les tests

La suite navigateur est volontairement séparée :

```bash
vendor/bin/phpunit -c phpunit.browser.xml
```

Panther démarre automatiquement l’application sur `http://127.0.0.1:8000`, lance Chrome, exécute les tests puis arrête les processus.

Les suites existantes restent inchangées :

```bash
vendor/bin/phpunit
composer test
```

## Mode headless

Le mode headless est le mode par défaut. Chrome s’exécute sans fenêtre :

```bash
vendor/bin/phpunit -c phpunit.browser.xml
```

## Mode visible

Pour observer le navigateur pendant un diagnostic :

### PowerShell

```powershell
$env:PANTHER_NO_HEADLESS = '1'
vendor/bin/phpunit -c phpunit.browser.xml
Remove-Item Env:PANTHER_NO_HEADLESS
```

### Bash

```bash
PANTHER_NO_HEADLESS=1 vendor/bin/phpunit -c phpunit.browser.xml
```

## Compte administrateur

Les tests authentifiés utilisent par défaut le compte initial du projet :

- identifiant : `admin` ;
- mot de passe : `admin`.

Une autre configuration peut être fournie sans modifier les tests :

```powershell
$env:PANTHER_ADMIN_IDENTIFIER = 'admin@erp-lbp.local'
$env:PANTHER_ADMIN_PASSWORD = 'mot-de-passe-local'
vendor/bin/phpunit -c phpunit.browser.xml
```

Ne jamais enregistrer de véritables secrets dans le dépôt. GitHub Actions utilisera plus tard des secrets ou un jeu de données de test dédié.

## Ajouter un nouveau test

1. Créer une classe dans `tests/Browser/`.
2. Étendre `Tests\Panther\PantherTestCase`.
3. Obtenir le navigateur avec `$this->browser()`.
4. Charger une route avec `$client->request('GET', '/route')`.
5. Vérifier le résultat avec des sélecteurs stables et `assertPageHasNoInternalServerError()`.

Exemple :

```php
<?php

namespace Tests\Browser;

use Tests\Panther\PantherTestCase;

final class ExampleTest extends PantherTestCase
{
    public function testPageIsAvailable(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/login');

        self::assertCount(1, $crawler->filter('form.auth-form'));
        $this->assertPageHasNoInternalServerError($client);
    }
}
```

## Bonnes pratiques

- Un test doit pouvoir être exécuté seul et dans n’importe quel ordre.
- Utiliser des attributs stables (`name`, `data-*`, rôles ou classes de composant), pas une longue chaîne CSS dépendante de la mise en page.
- Éviter les identifiants numériques codés en dur.
- Préférer les données initiales garanties par le seeder ou des variables d’environnement.
- Vérifier le comportement observable, pas l’implémentation interne d’un contrôleur.
- Garder les scénarios courts : une intention métier principale par test.
- Ne pas ajouter les tests Panther au `phpunit.xml` principal.
- En cas d’échec visuel, relancer temporairement avec `PANTHER_NO_HEADLESS=1`.

## Préparation à GitHub Actions

Le fichier `phpunit.browser.xml` est autonome afin qu’un futur job CI puisse :

1. installer PHP 8.3, Composer et Chrome ;
2. exécuter `composer install` ;
3. exécuter `vendor/bin/bdi detect drivers` ;
4. préparer la base de données de test ;
5. lancer `vendor/bin/phpunit -c phpunit.browser.xml`.

Les workflows GitHub Actions actuels ne sont pas modifiés par cette intégration.
