# Interface du module Administration

## Organisation

Chaque écran est une vue PHP dédiée dans `views/admin` :

- `dashboard.php` : indicateurs et référentiel des entités ;
- `users/index.php` : liste filtrable et paginée ;
- `users/form.php` : sélection du personnel RH, création et modification ;
- `users/show.php` : profil et synthèse des droits ;
- `permissions/edit.php` : édition CRUD par utilisateur ;
- `permissions/matrix.php` : comparaison globale.

L’administration utilise encore `views/admin/_navigation.php` comme catalogue
historique de liens. Le rendu HTML n’y est toutefois pas construit : il est
centralisé dans `Navigation::module()` via `views/layouts/module.php`.

Pour un nouveau module, suivre la convention moderne documentée dans
`doc/architecture/creer-un-module.md` : classe de navigation dédiée,
contrôleur de base du module et aucun fragment `_navigation.php` supplémentaire.

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

La matrice affiche les tables sous forme de libellés métier, par exemple
`rh_employees` devient « Personnel » et `rh_employee_history` devient
« Historique RH ». Les noms techniques restent réservés au backend.

## Responsive

Les tableaux sont placés dans `finea-table-wrap` pour conserver un défilement
horizontal sur petit écran. La matrice garde la colonne utilisateur fixe afin
de rester lisible lorsque de nouvelles entités sont ajoutées.
