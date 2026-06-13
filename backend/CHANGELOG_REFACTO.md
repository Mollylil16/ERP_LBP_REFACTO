# CHANGELOG_REFACTO

## [Unreleased]

### Ajouté

- `backend/core/Config.php` : gestion centralisée des configurations.
- `backend/core/Request.php` : encapsulation et filtrage des données entrantes (`GET`, `POST`, `JSON`).
- `backend/core/Response.php` : réponses JSON centralisées, avec `success` et `error`.
- `backend/core/Session.php` : wrapper de session sécurisé et centralisé.
- `backend/core/JWT.php` : gestion JWT sécurisée et réutilisable.
- `backend/core/Auth.php` : gestion de l'authentification et des permissions côté backend.
- `backend/core/Database.php` : wrapper d'accès à la base de données.
- `backend/core/Exceptions/HttpException.php` : exception HTTP standardisée.
- `backend/routes/api.php` et `backend/routes/web.php` : structure de routes backend modulaire.
- `backend/database/migrations/*` : fichiers SQL d'initialisation pour les modules Colissage, Logistique, Finance (001, 002, 003).
- **Module RH** : Fichiers `rh_controller.php`, `rh_service.php`, `rh_repository.php`, `routes.php` respectant la stricte séparation des responsabilités.
- **Module Colissage** : Fichiers `colis_controller.php`, `client_controller.php`, leurs Services et Repositories dédiés, et `routes.php`.
- **Module Finance** : Fichiers `facture_controller.php`, `paiement_controller.php`, leurs Services et Repositories dédiés, et `routes.php`.
- **Module Logistique** : Fichiers `expedition_controller.php`, `expedition_service.php`, `expedition_repository.php`, et `routes.php`.
- **Module Administration** : Fichiers `dashboard_controller.php`, `dashboard_service.php`, `dashboard_repository.php` (agrégation statistiques), et `routes.php`.

- **Module Supervision** : Fichiers `supervision_controller.php`, `supervision_service.php`, `supervision_repository.php`, et `routes.php` (Audit réseau).
- **Module Clients (CRM)** : Fichiers `clients_controller.php`, `clients_service.php`, `clients_repository.php`, et `routes.php`.
- **Module Tarifs** : Fichiers `tarifs_controller.php`, `tarifs_service.php`, `tarifs_repository.php`, et `routes.php` (Calcul dynamique).
- **Module Litiges (SAV)** : Fichiers `litiges_controller.php`, `litiges_service.php`, `litiges_repository.php`, et `routes.php` (Déclaration pertes/casses).
- **Module Uploads** : Fichier `uploads_controller.php`, et `routes.php` (Téléchargement sécurisé).

### Modifié

- `backend/bootstrap/app.php` : autoloader étendu pour `app/`, `core/` et `modules/` ; support de fichiers `snake_case.php`. Interception des exceptions globales.
- `backend/app/Controllers/BaseController.php` : passage à `Request`/`Response` centralisées, méthode `authenticate()`, `checkPermission()` et séparation des responsabilités.
- `backend/app/Helpers/Session.php`, `backend/app/Helpers/Response.php`, `backend/app/Helpers/JWT.php`, `backend/app/Helpers/Auth.php` : wrappers vers `App\Core` pour éviter les duplications fonctionnelles.

### Corrigé

- `backend/public/index.php` : routes backend déclarées via `backend/routes` pour éviter les erreurs de chargement.
- **Faille Critique (Crash Serveur)** : Résolue par la création de `backend/core/ExceptionHandler.php` qui retourne systématiquement du JSON propre.
- **Faille Critique (Injections/Data)** : Résolue par la création de `backend/core/Validator.php` qui standardise la validation des requêtes HTTP.

### Notes

- L'architecture MVC est complétée pour les dix modules (RH, Colissage, Finance, Logistique, Administration, Supervision, Clients, Tarifs, Litiges, Uploads).
- Utilisation stricte de PDO et requêtes préparées dans tous les Repositories.
- La logique métier est entièrement isolée dans les `Services`.
