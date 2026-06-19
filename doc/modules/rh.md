# Architecture du module Ressources humaines

## Décision d’architecture

Le module RH utilise une navigation centralisée en trois parties :

```text
RhNavigation::items()
        |
        v
RhBaseController::rhView()
        |
        v
views/layouts/module.php
        |
        v
Navigation::module()
```

Il ne faut pas ajouter `views/rh/_navigation.php`.

La navigation est déjà un composant de vue : `Navigation::module()` produit le
HTML dans le layout partagé. `RhNavigation` ne produit pas de HTML ; cette
classe décrit seulement les groupes et les liens.

Cette séparation évite :

- de répéter le menu dans toutes les vues RH ;
- de mélanger la configuration des liens et leur balisage HTML ;
- de transmettre manuellement les mêmes assets depuis chaque contrôleur ;
- d’avoir plusieurs sources de vérité.

## Fichiers responsables

| Fichier | Rôle |
|---|---|
| `routes/rh.php` | URLs et actions HTTP du module |
| `app/Controllers/Rh/RhBaseController.php` | Contexte commun du layout RH |
| `app/View/Navigation/RhNavigation.php` | Définition unique des liens |
| `app/View/Components/Navigation.php` | Validation et rendu HTML du menu |
| `views/layouts/module.php` | Sidebar, topbar, flash, assets et contenu |
| `app/View/Components/Rh.php` | Fragments visuels propres aux écrans RH |
| `app/View/Pages/Rh/` | Contrats de données des pages |
| `views/rh/` | Composition HTML de chaque écran |
| `public/assets/css/rh.css` | Styles propres au module |
| `public/assets/js/rh.js` | Interactions propres au module |

## Comment une page RH reçoit la navigation

Exemple pour la liste du personnel :

1. `/rh/personnel` correspond à `RhPersonnelController::index()`.
2. Le contrôleur appelle le service RH.
3. Il construit `PersonnelIndexPage`.
4. Il appelle `rhView(..., 'personnel', ...)`.
5. `RhBaseController` ajoute automatiquement `RhNavigation::items()`.
6. La vue construit son contenu dans `$content`.
7. La vue charge `views/layouts/module.php`.
8. Le layout appelle `Navigation::module($moduleNavigation, $activeModule)`.
9. Le lien dont la clé vaut `personnel` devient actif.

## Choisir `activeModule`

La valeur doit être une clé existante dans `RhNavigation::items()` :

| Écran | Clé active |
|---|---|
| Dashboard | `dashboard` |
| Liste, fiche, création ou modification d’un salarié | `personnel` |
| Registre et formulaire de mutation | `mutations` |
| Entrées et sorties | `sorties` |
| Pointage | `attendance` |
| Paie | `payroll` |
| Paramétrage | `settings` |
| Sections du cycle de vie | clé de la section, par exemple `contracts` |

Une sous-page garde normalement la clé de son domaine. Le formulaire de
modification d’un salarié active donc `personnel`, pas une nouvelle entrée
`employee-edit`.

## Ajouter une entrée au menu RH

1. Ajouter la route dans `routes/rh.php`.
2. Créer l’action de contrôleur et la vue.
3. Ajouter une entrée dans `RhNavigation::items()`.
4. Utiliser une nouvelle `key` unique.
5. Passer cette clé comme troisième argument de `rhView()`.
6. Ajouter un test ou étendre les contrôles existants.
7. Cliquer le lien et vérifier `aria-current="page"`.

Exemple :

```php
[
    'group' => 'Administration RH',
    'key' => 'absences',
    'label' => 'Absences',
    'icon' => 'AB',
    'url' => 'rh/absences',
    'available' => true,
],
```

## Ajouter une page RH

Exemple d’action :

```php
public function absences(): void
{
    AuthMiddleware::check();

    $this->rhView(
        'rh/absences/index',
        'Gestion des absences',
        'absences',
        ['page' => new AbsenceIndexPage(/* données préparées */)]
    );
}
```

La vue ne reçoit pas manuellement `moduleName`, `moduleCode`,
`moduleNavigation`, `additionalStyles` ou `additionalScripts`. Ces données sont
déjà fournies par `RhBaseController`.

## Différence entre layout et composant RH

`views/layouts/module.php` appartient à tous les modules. Il gère le cadre de
l’application.

`App\View\Components\Rh` contient les fragments propres aux pages RH :

- entête RH ;
- avertissement de données restreintes ;
- cartes ;
- formulaires d’opération ;
- tableaux ;
- pagination ;
- dossier personnel ;
- historique et documents.

Une fonctionnalité générique doit aller dans `Ui`, `Form`, `Dashboard`,
`Navigation`, `Tabs` ou un autre composant partagé. Une fonctionnalité
strictement RH peut aller dans `Rh`.

## Pourquoi ne pas tout transformer en composant

Une vue reste utile pour montrer la structure réelle d’un écran. Extraire tout
le HTML produirait des composants très spécialisés, difficiles à comprendre et
peu réutilisables.

La règle pratique :

- structure propre à une page : vue ;
- fragment réutilisé par plusieurs pages RH : composant `Rh` ;
- fragment réutilisé par plusieurs modules : composant partagé ;
- cadre complet de page : layout ;
- données et liens du menu : classe de navigation.

## Contrôleurs RH

| Contrôleur | Responsabilité |
|---|---|
| `RhDashboardController` | Indicateurs et modes du dashboard |
| `RhPersonnelController` | Dossiers, mutations, sorties et historique |
| `RhLifecycleController` | Contrats, missions, évaluations, formations et workflows |
| `RhSettingsController` | Catalogues et paramètres RH |
| `RhModuleController` | Pages transitoires encore non spécialisées |

`RhModuleController` doit rester temporaire. Dès qu’une fonction comme le
pointage ou la paie reçoit une vraie logique métier, créer un contrôleur,
service, repository, Page Object et dossier de vues dédiés.

## Points de vigilance

- La route `/rh/contrats` affiche actuellement le cycle de vie avec sa section
  par défaut. Préférer les liens explicites avec
  `/rh/cycle-vie?section=contracts`.
- Les permissions doivent être vérifiées dans le contrôleur, même si une action
  est masquée dans la vue.
- `available => false` signale une fonction non livrée, mais ne remplace pas une
  permission.
- Les vues RH doivent toujours finir par charger
  `views/layouts/module.php`.
- Les données de page complexes doivent passer par `app/View/Pages/Rh`.
