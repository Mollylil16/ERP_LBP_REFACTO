# Guide général backend

## Architecture officielle

Controllers :
- Reçoivent les requêtes
- Valident les entrées
- Appellent les services

Services :
- Contiennent la logique métier

Repositories :
- Accès base de données

Models :
- Objets métier

Views :
- Affichage uniquement

Le guide détaillé, avec le trajet complet d'une requête et la création d'un
module étape par étape, se trouve dans
[`doc/architecture/creer-un-module.md`](../architecture/creer-un-module.md).

## Design System

Tous les nouveaux modules doivent utiliser :

- finea-ui.css
- couleurs métiers
- icônes métiers
- dashboards harmonisés

## Modules stratégiques

Finance
RH
Colisage
Logistique
CRM
Tickets
Transit Douane
Tracking Colis
Facturation
Entrepôts
Flotte
Portefeuille Clients
Agents & Correspondants
Centre DG
Site Internet

## Site public

Le site public est indépendant de l'ERP mais administré depuis celui-ci via Site Internet.
