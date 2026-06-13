# Backend Refactor - lbp_projet

## Architecture

Le backend est maintenant structuré en modules et en utilitaires réutilisables.

- `backend/app/` : code applicatif existant, classes de base et configuration du framework.
- `backend/core/` : utilitaires partagés et services transverses.
- `backend/modules/` : modules métier ciblés.
- `backend/routes/` : routes centrales du backend.
- `backend/public/` : point d'entrée HTTP.

## Modules

Chaque module possède sa propre organisation :

- `Controllers/` : controllers exposant des endpoints.
- `Services/` : logique métier.
- `Repositories/` : accès aux données.
- `routes.php` : routes exposées par le module.

Modules créés :

- `colissage`
- `rh`
- `logistique`
- `administration`
- `finance`

## Utilitaires centraux

- `core/Config.php`
- `core/Request.php`
- `core/Response.php`
- `core/Session.php`
- `core/JWT.php`
- `core/Auth.php`
- `core/Database.php`
- `core/Exceptions/HttpException.php`

## Points importants

- Toutes les entrées sont désormais filtrées via `App\Core\Request`.
- Les réponses JSON passent par `App\Core\Response`.
- L’authentification JWT est centralisée dans `App\Core\JWT`.
- L’autoloader PSR-4 gère `app/`, `core/` et `modules/`.

## À faire ensuite

- migrer les endpoints métier actuels vers les modules.
- implémenter les repositories et services pour chaque module sur la base des tables existantes.
- ajouter des tests unitaires / de route.
