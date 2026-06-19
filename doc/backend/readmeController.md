# Guide des contrôleurs

Les contrôleurs reçoivent les requêtes web et orchestrent les actions de l’application.

## Rôle d’un contrôleur

- récupérer les données envoyées par le formulaire
- appeler un service ou un repository
- préparer la réponse HTML ou une redirection

## Structure courante

- BaseController.php : logique commune
- AuthController.php : gestion de la connexion et de la déconnexion
- dashboardController.php : affichage du portail

## Comment créer une nouvelle page

1. ajouter la route dans routes/web.php
2. créer ou modifier un contrôleur dans app/Controllers/
3. appeler la vue dans views/
4. tester dans le navigateur

Pour un module complet, ne pas recopier dans chaque contrôleur le nom du
module, ses assets et sa navigation. Créer un contrôleur de base propre au
module, sur le modèle de `RhBaseController`.

Guide complet :
[`doc/architecture/creer-un-module.md`](../architecture/creer-un-module.md).

## Bonnes pratiques

- ne pas écrire de requêtes SQL directement dans le contrôleur
- garder les contrôleurs courts et lisibles
- déléguer la logique métier aux services
