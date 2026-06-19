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

Les images du carrousel peuvent être téléversées directement :

- formats JPG, PNG et WEBP ;
- taille maximale de 8 Mo ;
- dimensions minimales de 1600 × 600 px ;
- dimensions recommandées de 1920 × 760 px.

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
- `/site/account` : inscription, connexion et dashboard client.

La marketplace fonctionne comme un catalogue assisté avec panier persistant
dans le navigateur. Son badge et le dashboard client utilisent le même panier.

## Comptes et messagerie

Les comptes publics sont séparés des comptes ERP dans
`website_customer_accounts`. Cette séparation évite qu’un client externe
obtienne accidentellement un accès à un module interne.

Une conversation privée est créée pour chaque client. Le client écrit depuis
son dashboard et le gestionnaire répond depuis `/site-admin/messages`.
L’interface interroge le serveur toutes les quatre secondes, sans service
payant externe.

Les messages acceptent :

- texte ;
- images JPG, PNG et WEBP ;
- vidéos MP4 et WEBM ;
- audio MP3, OGG, WEBM et M4A ;
- enregistrement direct d’une note vocale lorsque le navigateur supporte
  `MediaRecorder`.

Chaque média est limité à 20 Mo et enregistré sous `public/uploads/site`.
Les paiements et véritables commandes restent une évolution ultérieure.

## Annonces, blog et statistiques

Le gestionnaire peut créer plusieurs annonces avec badge, texte, lien, période
de visibilité et ordre d’affichage. Le bandeau public les fait défiler.

Les articles possèdent un titre, un slug, un résumé, un contenu, un auteur et
un statut de publication. Ils sont disponibles sous `/site/blog`.

Le module Analytics propriétaire enregistre :

- pages vues et boutons ou liens cliqués ;
- date et heure ;
- identifiant visiteur local ;
- IP, user-agent, langue, fuseau horaire et taille d’écran ;
- référent ;
- coordonnées géographiques uniquement lorsque le navigateur les a déjà
  autorisées.

Le tableau `/site-admin/analytics` présente les indicateurs, classements,
graphiques en barres et événements récents. Aucune bibliothèque de mesure
externe n’est utilisée.
