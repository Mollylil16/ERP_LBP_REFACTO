# Module Santé & Tests ERP LBP

Ce module ajoute une interface d’administration moderne pour lancer les contrôles qualité de l’application.

## Route

- `GET /admin/system-tests` : interface visuelle
- `POST /admin/system-tests/run` : test complet
- `POST /admin/system-tests/run/{module}` : test ciblé module
- `GET /admin/system-tests/latest` : derniers résultats JSON

## Contrôles inclus

- Connexion PDO MySQL
- Syntaxe PHP `app/`, `routes/`, `tests/`
- PHPUnit complet
- Smoke tests existants
- Contrôle par module : tables, requêtes `COUNT(*)`, routes et vues principales

## Sécurité

L’accès passe par `AdminMiddleware` et les actions POST sont protégées par CSRF.
Aucune commande libre n’est exécutée : toutes les commandes sont codées en whitelist dans `SystemTestService`.

## Table créée

`system_test_runs` conserve l’historique des exécutions : scope, module, statut, score et payload JSON.
