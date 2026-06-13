# Contributing Guide

Ce document définit les règles de développement du projet .

Tous les développeurs doivent respecter ces conventions afin de maintenir une architecture propre, cohérente et maintenable.

---

# Architecture générale

Le projet suit une architecture PHP native structurée.

Flux principal :

```text
Route
→ Controller
→ Service
→ Repository
→ Database


# Structure des dossiers

app/
├── Controllers/
├── Database/
├── Helpers/
├── Middleware/
├── Models/
├── Repositories/
└── Services/

# Règles importantes

Controllers

Les contrôleurs :

- reçoivent les requêtes HTTP ;
- valident les entrées simples ;
- appellent les services ;
- retournent des vues ou des réponses JSON.

Un contrôleur ne doit pas :

- contenir de requêtes SQL ;
- contenir une logique métier lourde ;
- générer du HTML complexe.


# Services

Les services contiennent la logique métier.

Exemples :

- enregistrer un utilisateur
- desactiver le compte d'un utilisateur

Toute logique métier importante doit être placée dans un service.

# Repositories

Les repositories sont responsables des accès à la base de données.

Toutes les requêtes SQL doivent être centralisées ici.

# Interdictions :

- SQL dans les contrôleurs ;
- SQL dans les vues ;
- SQL dans les services.

# Models

Chaque entité importante doit avoir un model.

Exemples :

- User
- UserGroup

Le model représente uniquement les données métier.

# Views

Les vues contiennent uniquement :

- HTML ;
- affichage ;
- petites conditions d’affichage simples.

Interdictions :

- SQL ;
- logique métier ;
- traitements complexes.

# CSS

Le CSS doit rester séparé du HTML.

Tous les styles doivent être placés dans :

public/assets/css/

Interdictions :

- CSS inline ;
- gros blocs <style> dans les vues.

# JavaScript

Le JavaScript doit rester séparé du HTML.

Tous les scripts doivent être placés dans :

public/assets/js/

Interdictions :

- gros scripts inline ;
- logique JavaScript directement dans les vues.
- Sécurité

# Règles obligatoires :

utiliser PDO avec requêtes préparées ;
protéger les formulaires sensibles avec CSRF ;
échapper les données affichées ;
journaliser les actions sensibles ;
vérifier les permissions avant tout accès critique.

# Conventions Git

Branches

- Main

main

Contient uniquement le code stable de production.

- Develop

develop

Contient le code stable de développement.

Features

- feature/*

Exemples :

feature/authentication
feature/auth-api
Fixes
fix/*

# Exemples :

fix/router-normalization
fix/session-timeout

Refactors

- refactor/*

Exemples :

refactor/router
refactor/database-layer


Convention des commits

- Les messages doivent être explicites.

Exemples :

Add authentication controllers
Create device repository layer
Implement CSRF protection
Refactor routing system
Fix asset path generation

Interdictions :

update
test
fix
modification
Documentation

Chaque fonctionnalité importante doit être documentée.

Documentation prévue :

docs/
├── API.md
├── DATABASE.md
├── SECURITY.md
├── ARCHITECTURE.md
└── ANDROID-INTEGRATION.md


Commentaires de code

Les commentaires doivent :

- être utiles ;
- expliquer le pourquoi ;
- rester lisibles ;
- éviter les commentaires inutiles.

Exemple recommandé :

/**
 * Vérifie qu’un utilisateur possède bien les infos demandé.
 *
 * Cette méthode est utilisée avant tout accès aux données
 * de localisation afin d’éviter les accès non autorisés.
 */


Objectif qualité

Le projet doit rester :

- maintenable ;
- sécurisé ;
- lisible ;
- modulaire ;
- facilement extensible vers une architecture SaaS.

---

# 3. Commit

```bash
git add .
git commit -m "Add project contribution and architecture standards"
git push -u origin feature/project-standards 

