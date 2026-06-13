# Guide des modèles

Les modèles représentent les données principales de l’application.

## Qu’est-ce qu’un modèle ?

Un modèle contient la définition d’un objet métier et sert de base pour les échanges avec la base de données.

## Exemple dans le projet

- User.php représente un utilisateur
- Database.php gère la connexion à la base

## Comment lire un modèle

1. repérer la table correspondante
2. vérifier les propriétés de l’objet
3. voir les méthodes utiles (création, lecture, mise à jour)

## Ajouter un nouveau modèle

1. créer le fichier dans app/Models/
2. définir les propriétés principales
3. l’utiliser depuis un service ou un repository
4. tester avec une page simple

## Astuce

Un modèle doit rester simple : il ne doit pas contenir toute la logique métier.
