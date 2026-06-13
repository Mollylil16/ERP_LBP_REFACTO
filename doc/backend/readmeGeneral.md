# Guide général backend

Ce document décrit la logique générale du backend de l’application.

## 1. Structure de base

- app/Controllers : reçoit les requêtes HTTP et prépare la réponse
- app/Services : contient la logique métier
- app/Repositories : accès aux données et requêtes SQL
- app/Models : représentation des objets métier
- app/Middleware : contrôle d’accès et sécurité

## 2. Comment démarrer

1. lancer Apache et MySQL
2. ouvrir l’URL locale du projet
3. vérifier que bootstrap/app.php initialise la session et la base
4. utiliser le compte admin/admin pour tester

## 3. Flux d’une page simple

1. la route est définie dans routes/web.php
2. le contrôleur est appelé par le routeur
3. le service vérifie les règles métier
4. la vue est rendue dans views/

## 4. Bonnes habitudes

- ne pas écrire la logique SQL dans les contrôleurs
- utiliser les services pour la logique métier
- utiliser les repositories pour les interactions avec la base
- garder les vues simples et lisibles
