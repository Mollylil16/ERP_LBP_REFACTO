# Module Administration

## Objectif

Le module `/admin` centralise :

- la création des utilisateurs à partir d'un dossier RH ;
- l'activation, le blocage et la désactivation des comptes ;
- l'attribution de permissions CRUD par entité ;
- la matrice globale des habilitations.

Toutes les routes du module sont protégées par `AdminMiddleware`. Un utilisateur
doit être authentifié, actif et avoir `users.is_admin = 1`.

## Architecture

- Modèles : `User`, `PermissionEntity`
- Contrôleurs : `AdminDashboardController`, `AdminUserController`,
  `AdminPermissionController`
- Services : `AdminService`
- Sécurité : `PermissionEntityRegistry`, `PermissionAction`, `OperationPolicy`,
  `AuthorizationService`
- Repositories : `UserRepository`, `PermissionRepository`
- Middlewares : `AdminMiddleware`, `PermissionMiddleware`
- Vues : `views/admin`
- Assets : `public/assets/css/admin.css`, `public/assets/js/admin.js`

Les contrôleurs coordonnent la requête HTTP. La validation métier reste dans
`AdminService` et toutes les requêtes SQL restent dans les repositories.

## Schéma de données

### users

La colonne `is_admin` identifie les administrateurs globaux. Ces comptes ont
automatiquement tous les droits.

La colonne unique `rh_employee_id` relie le compte à son dossier RH. Tous les
nouveaux comptes doivent être créés depuis un collaborateur actif sans compte.
Le compte système historique `admin` est la seule exception tolérée.

### permission_entities

Catalogue des tables sécurisées. Le champ `code` contient le nom technique de
la table, par exemple `rh_employees`, tandis que `name` fournit le libellé
métier affiché dans la matrice.

### user_permissions

Une ligne par couple utilisateur / entité avec quatre indicateurs :

- `can_view`
- `can_create`
- `can_update`
- `can_delete`

La clé primaire composite empêche les doublons. Les contraintes de base
garantissent l'intégrité des permissions lors des opérations techniques.

## Contrôle d'accès

Pour protéger une action métier :

```php
PermissionMiddleware::check('rh_employees', 'update');
```

Les actions autorisées sont `view`, `create`, `update` et `delete`.
`Auth::can()` peut être utilisé pour adapter la navigation ou masquer une action.

Pour une opération qui touche plusieurs tables, la règle est définie une seule
fois dans `OperationPolicy` :

```php
PermissionMiddleware::checkOperation(OperationPolicy::RH_MUTATION_CREATE);
```

La même politique est utilisée par le contrôleur et la vue avec
`Auth::canOperation()`. `AuthorizationService` applique un refus par défaut :
utilisateur absent ou inactif, entité inconnue et action inconnue sont refusés.
La matrice d'un utilisateur standard est chargée en une seule requête et mise
en cache uniquement pendant la requête HTTP.

Les permissions correspondent aux tables réelles. Le filtrage est appliqué
côté service avant le rendu :

- sans `view` sur `rh_employees`, les lignes et totaux du personnel sont retirés ;
- sans `view` sur `rh_services`, les collaborateurs restent visibles mais leur
  service devient « Donnée masquée » et les listes de services sont retirées ;
- le même principe s'applique aux fonctions, statuts, motifs de sortie,
  historiques et mutations ;
- les droits d'écriture contrôlent toutes les tables réellement modifiées par
  une opération.
- les identifiants de référentiels masqués envoyés dans un POST fabriqué sont
  ignorés côté service et ne peuvent pas contourner l'interface.

Les valeurs ne sont pas chiffrées différemment pour chaque utilisateur :
le chiffrement ne remplace pas le contrôle d'accès. Elles sont retirées ou
remplacées côté serveur avant le rendu HTML. Une donnée interdite n'est donc
pas présente dans la réponse envoyée au navigateur.

## Règles de sécurité

- toutes les écritures utilisent un jeton CSRF ;
- aucun compte utilisateur ne peut être supprimé depuis l'application ;
- la désactivation coupe immédiatement les sessions et les connexions ;
- la réactivation conserve le profil RH et les permissions existantes ;
- un administrateur ne peut pas désactiver son propre compte ;
- il ne peut pas retirer son propre profil administrateur ni se désactiver ;
- les mots de passe sont hachés avec `password_hash()` ;
- les opérations de remplacement de permissions sont transactionnelles ;
- un droit d'écriture implique automatiquement le droit de lecture ;
- le cache d'autorisation est invalidé dès que la matrice est modifiée ;
- le compte `admin` historique est automatiquement promu administrateur.

## Ajouter une entité sécurisée

1. Ajouter l'entité dans `PermissionEntityRegistry`.
2. Protéger le contrôleur avec `PermissionMiddleware`.
3. Ajouter une règle dans `OperationPolicy` si plusieurs tables sont touchées.
4. Utiliser `Auth::can()` ou `Auth::canOperation()` dans la navigation.
5. Relancer le bootstrap : le seed est idempotent.

## Vérification

Le smoke test crée un dossier RH temporaire, crée son compte avec une
permission, le désactive, le réactive puis nettoie les données de test :

```bash
php tests/smoke_admin.php
php tests/smoke_visibility.php
```

Les résultats attendus sont `SMOKE_ADMIN_OK` et `SMOKE_VISIBILITY_OK`.
