# Architecture du module Site Internet

Le Site Internet comporte deux surfaces :

- le backoffice privé `/site-admin` ;
- le site public `/site`.

## Backoffice

`SiteAdminBaseController` centralise le contexte du module et
`SiteAdminNavigation` définit la navigation. Les vues reçoivent un Page Object :

- `DashboardPage` pour le pilotage ;
- `ConfigurationPage` pour le branding, le carrousel et la marketplace.

La page `/site-admin/configuration` permet de modifier :

- le nom, le monogramme ou l’URL du logo ;
- la signature et le bandeau d’annonce ;
- la typographie ;
- les quatre couleurs principales avec `Form::colorPalette()` ;
- les slides, images, boutons et ordre du carrousel ;
- les offres de la marketplace, leurs prix, badges et disponibilité.

Le partial `views/site_admin/_navigation.php` a été supprimé.

## Site public

Toutes les pages publiques reçoivent `App\View\Pages\Site\SitePage` dans
`$page`. Cet objet prépare :

- la référence de tracking sélectionnée ;
- les pays disponibles ;
- les coordonnées visuelles des marqueurs ;
- les données communes agences, services, actualités et statistiques.
- le branding provenant de `website_branding` ;
- les slides actifs ;
- les produits actifs ;
- les discussions communautaires.

`App\View\Components\Site` rend les fragments réutilisables : carrousel,
tracking rapide, statistiques, services, produits, discussions et héros des
pages internes. `SiteAdmin` rend les formulaires du backoffice.

Les vues publiques ne reconstruisent plus de `ViewBag` et n’utilisent plus de
variables libres.

## Surfaces publiques

- `/site` : accueil pleine largeur ;
- `/site/tracking` : suivi d’expédition ;
- `/site/shop` : marketplace logistique ;
- `/site/agences` : réseau et localisateur ;
- `/site/forum` : communauté import-export ;
- `/site/devis` et `/site/contact` : acquisition commerciale.

La marketplace actuelle fonctionne comme un catalogue assisté avec panier
local. Les comptes publics, paiements, commandes et messages du forum pourront
être ajoutés ensuite sans modifier le contrat principal de `SitePage`.
