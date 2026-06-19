# Interface du module Administration

## Organisation

Chaque écran est une vue PHP dédiée dans `views/admin` :

- `dashboard.php` : indicateurs et référentiel des entités ;
- `users/index.php` : liste filtrable et paginée ;
- `users/form.php` : sélection du personnel RH, création et modification ;
- `users/show.php` : profil et synthèse des droits ;
- `permissions/edit.php` : édition CRUD par utilisateur ;
- `permissions/matrix.php` : comparaison globale ;
- `system_tests/index.php` : santé technique et exécution des contrôles.

Toutes les vues reçoivent exclusivement un Page Object dans `$page`. La
préparation des dates, liens, pagination, profils RH et résumés de permissions
se trouve dans `app/View/Pages/Admin`.

## Navigation

La source unique des liens est
`app/View/Navigation/AdminNavigation.php`.

`AdminBaseController::adminView()` transmet cette navigation et les assets au
layout partagé. `views/layouts/module.php` effectue le rendu avec
`Navigation::module()`.

Il n’existe plus de `views/admin/_navigation.php`.

## Composants

Les vues assemblent les composants suivants :

- `Admin` : tableaux utilisateurs, profils RH, permissions, matrice et santé ;
- `Dashboard` : KPI du tableau de bord ;
- `Form` : champs, listes et cases à cocher ;
- `Ui` : entêtes, sections, boutons et états vides, sans wrapper Admin ;
- `Navigation` : menu latéral via le layout.

Les vues ne préparent pas de tableaux métier et ne vérifient pas directement
les permissions.

## Design

Le module charge d'abord `finea-ui.css`, puis `admin.css`. Il réutilise les
composants FINEA :

- `finea-page-header`
- `finea-kpi-card`
- `finea-section-card`
- `finea-table`
- `finea-action-btn`
- `finea-field`

Le shell partagé avec le module RH (`module-layout`, menu latéral, topbar,
profil connecté et responsive mobile) est défini dans `finea-ui.css`.
`admin.css` ne contient que les adaptations propres à l'administration. Les
couleurs principales restent le bleu marine LBP et l'accent or.

## JavaScript

`admin.js` gère uniquement les interactions d'interface :

- confirmation avant activation ou désactivation ;
- prévisualisation des données du profil RH sélectionné ;
- sélection rapide "lecture seule" ;
- retrait de toutes les permissions ;
- attribution rapide de tous les droits ;
- activation automatique de la lecture lorsqu'un droit d'écriture est coché.

La sécurité et la validation restent toujours exécutées côté serveur.

La page Santé & Tests charge en plus `system-tests.css` et `system-tests.js`
depuis `AdminSystemTestController`, sans insérer manuellement des balises
`<link>` ou `<script>` dans la vue.

La matrice affiche les tables sous forme de libellés métier, par exemple
`rh_employees` devient « Personnel » et `rh_employee_history` devient
« Historique RH ». Les noms techniques restent réservés au backend.

## Responsive

Les tableaux sont placés dans `finea-table-wrap` pour conserver un défilement
horizontal sur petit écran. La matrice garde la colonne utilisateur fixe afin
de rester lisible lorsque de nouvelles entités sont ajoutées.
