# Architecture du module Site Internet

Le Site Internet comporte deux surfaces :

- le backoffice privé `/site-admin` ;
- le site public `/site`.

## Backoffice

`SiteAdminBaseController` centralise le contexte du module et
`SiteAdminNavigation` définit la navigation. La vue reçoit
`App\View\Pages\SiteAdmin\DashboardPage`.

Le partial `views/site_admin/_navigation.php` a été supprimé.

## Site public

Toutes les pages publiques reçoivent `App\View\Pages\Site\SitePage` dans
`$page`. Cet objet prépare :

- la référence de tracking sélectionnée ;
- les pays disponibles ;
- les coordonnées visuelles des marqueurs ;
- les données communes agences, services, actualités et statistiques.

`App\View\Components\Site` rend les fragments réutilisables, notamment les
icônes, statistiques et cartes de services.

Les vues publiques ne reconstruisent plus de `ViewBag` et n’utilisent plus de
variables libres.
