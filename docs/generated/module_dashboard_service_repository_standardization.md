# Standardisation Dashboard Service / Repository

## Objectif
Chaque dashboard métier possède désormais sa chaîne MVC complète :

`Route → DashboardController → DashboardService → DashboardRepository → View + Components`

## Socle ajouté
- `app/Services/ModuleDashboardContract.php`
- `app/Services/AbstractModuleDashboardService.php`
- `app/Repositories/ModuleDashboardRepository.php`

## Modules standardisés
- Finance
- Colisage
- Logistique
- CRM
- Tickets
- Site Admin
- Transit Douane
- Tracking Colis
- Facturation
- Entrepôts
- Flotte / Transport
- Portefeuille Clients
- Agents & Correspondants
- Centre de Pilotage DG

## Règle d'évolution
Les repositories dédiés sont volontairement simples au départ. Ils isolent le point d'accès aux données du module et pourront ensuite remplacer les indicateurs statiques par des requêtes SQL propres au métier sans toucher aux controllers ni aux vues.

## Modules déjà conformes
- Administration : `AdminDashboardController → AdminService → repositories Admin`
- RH : `RhDashboardController → RhDashboardService → RhDashboardRepository`
- Espace employé : `EmployeePortalController → EmployeePortalService → EmployeePortalRepository`
