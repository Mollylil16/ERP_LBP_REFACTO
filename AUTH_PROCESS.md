# Processus d’authentification et d’affichage du portail de sélection

Ce document explique, étape par étape, comment un développeur débutant peut suivre le parcours complet depuis la page de connexion jusqu’à l’affichage du portail de sélection (la page actuellement rendue par la vue dashboard/index.php, qui sert de portail ERP).

---

## 1. Point d’entrée du projet

Le projet démarre via :
- index.php (à la racine)
- public/index.php (point d’entrée HTTP)

### Ce qui se passe au démarrage
1. index.php détecte la route demandée et prépare la bonne redirection interne.
2. public/index.php charge bootstrap/app.php.
3. bootstrap/app.php initialise la session, charge les classes PHP, exécute les migrations et crée l’utilisateur administrateur par défaut si nécessaire.

### Rôle de bootstrap/app.php
- démarrage de la session
- définition de BASE_PATH
- autoload des classes App\
- création des tables si elles n’existent pas
- insertion de l’admin de secours (admin / admin)

---

## 2. Les routes utilisées

Les routes principales sont définies dans routes/web.php.

### Routes publiques
- GET / : page d’accueil
- GET /login : affichage de la page de connexion
- POST /login : soumission du formulaire de connexion
- GET /logout : déconnexion

### Route protégée
- GET /dashboard : page de portail de sélection après connexion

### Rôle du routeur
Le fichier app/Router.php compare la requête HTTP à la liste des routes et exécute le bon contrôleur.

Si aucune route ne correspond, il affiche la page 404.

---

## 3. Affichage de la page de connexion

Quand l’utilisateur accède à /login :
1. le routeur appelle AuthController::showLogin()
2. AuthController vérifie si l’utilisateur est déjà connecté via GuestMiddleware::check()
3. si tout est correct, il affiche la vue views/auth/login.php

### Ce que contient la page login
- formulaire email / mot de passe
- champ CSRF caché 
- interface moderne de connexion
- compte par défaut à utiliser pour les tests :
  - identifiant : admin
  - mot de passe : admin

### Important
Le formulaire utilise la logique de sécurité fournie par App\Helpers\Csrf.

---

## 4. Soumission du formulaire de connexion

Quand l’utilisateur clique sur “Se connecter” :
1. le navigateur envoie une requête POST à /login
2. le routeur appelle AuthController::login()

### Étapes dans AuthController::login()
1. Vérification du jeton CSRF
2. Appel de AuthService::login($data)
3. Si la connexion échoue : message d’erreur + retour sur la page login
4. Si la connexion réussit :
   - écriture de la session auth_user_id
   - message de succès
   - redirection vers /dashboard

---

## 5. Logique métier de connexion (AuthService)

Le cœur de la logique est dans app/Services/AuthService.php.

### Ce que fait AuthService::login()
1. récupère l’identifiant et le mot de passe
2. contrôle que les champs sont présents
3. appelle UserRepository pour chercher l’utilisateur
4. vérifie le mot de passe avec password_verify()
5. vérifie que le statut du compte est actif
6. retourne un résultat success/failure

### Pourquoi ce service existe
Il sépare la logique métier de la couche d’accès à la base de données.

---

## 6. Recherche de l’utilisateur dans la base (UserRepository)

La recherche des utilisateurs est centralisée dans app/Repositories/UserRepository.php.

### Ce que fait le repository
- findByEmail() : recherche par email
- findById() : recherche par identifiant
- findByIdentifier() : recherche par email ou nom complet (utile pour l’admin)
- create() : enregistrement d’un nouvel utilisateur

### Pourquoi c’est important
Le contrôleur ne parle jamais directement à la base. Il passe toujours par le repository.

---

## 7. Création de la session utilisateur

Si la connexion est valide, AuthController exécute :
- Session::set('auth_user_id', $user->id)

### Ce que cela fait
Cela garde l’utilisateur connecté pendant toute la session navigateur.

Le système récupère ensuite les infos utilisateur à partir de la session dans les vues et contrôleurs.

---

## 8. Redirection vers le tableau de bord / portail

Après la connexion réussie, AuthController redirige vers /dashboard.

### Ce qui se passe ensuite
1. le routeur appelle DashboardController::index()
2. DashboardController vérifie que l’utilisateur est bien connecté avec AuthMiddleware::check()
3. si la session est absente, l’utilisateur est renvoyé vers /login
4. si la session existe, le contrôleur récupère les informations utilisateur
5. il envoie ces données à la vue du portail

---

## 9. Affichage de la page de sélection / portail ERP

La page affichée est la vue :
- views/dashboard/index.php

Elle sert de portail de sélection. C’est la page qui donne l’impression d’un tableau de bord type Odoo / Finea avec des modules de navigation et des cartes d’accès.

### Ce que la vue affiche
- un message d’accueil personnalisée
- les indicateurs clés (opérations, documents, équipes, conformité)
- des cartes de modules (Transit, Documents, Stocks, Administration)
- un espace de présentation du portail ERP

### Mise en forme
La vue est rendue dans le layout :
- views/layouts/app.php

Le layout applique le design global de l’application et charge le fichier CSS principal.

---

## 10. Rôle du middleware d’authentification

Le middleware est dans :
- app/Middleware/AuthMiddleWare.php

### Son rôle
Il vérifie que la session contient auth_user_id.

Si la session n’existe pas :
- message d’erreur affiché
- redirection automatique vers /login

Cette vérification protège la page du dashboard.

---

## 11. Création de l’administrateur par défaut

L’utilisateur admin est créé automatiquement par le service :
- app/Services/AdminSeederService.php

### Compte par défaut
- email : admin@erp-lbp.local
- nom : Admin
- mot de passe : admin

Ce mécanisme permet aux débutants de tester l’application immédiatement sans créer un utilisateur manuellement.

---

## 12. Résumé ultra simple du flux

Voici la séquence complète en une ligne :

1. utilisateur ouvre /login
2. il saisit ses identifiants
3. AuthController appelle AuthService
4. AuthService vérifie les identifiants via UserRepository
5. si OK, la session auth_user_id est créée
6. l’utilisateur est redirigé vers /dashboard
7. DashboardController charge les infos utilisateur
8. la vue dashboard/index.php s’affiche comme portail de sélection

---

## 13. Conseils pour un développeur débutant

### Si la page de connexion ne s’ouvre pas
- vérifier que le fichier .htaccess pointe bien vers index.php
- tester l’URL locale du projet
- vérifier que la base de données est bien accessible

### Si la connexion échoue
- vérifier que l’utilisateur admin a bien été créé
- vérifier que le mot de passe est bien “admin” dans la base
- vérifier que le compte est en statut “active”

### Si la page du dashboard ne s’affiche pas
- vérifier que la session auth_user_id existe
- vérifier que AuthMiddleware::check() ne redirige pas vers /login
- vérifier que DashboardController reçoit bien les données utilisateur

### Si vous souhaitez modifier la logique
- ne touchez pas la vue directement pour la logique métier
- modifiez d’abord le service (AuthService)
- puis le contrôleur si nécessaire
- enfin la vue pour l’affichage

---

## 14. Recommandation de structure mentale

Pour comprendre ce projet, pensez toujours en 4 couches :
1. Route
2. Contrôleur
3. Service / Repository
4. Vue

C’est la base du projet et c’est ce qui rend l’application facile à maintenir.
