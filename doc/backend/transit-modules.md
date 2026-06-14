# Modules transit/import-export

## Vue d'ensemble

L'ERP LBP Transit est désormais organisé autour d'une architecture modulaire MVC complète.

Chaque module dispose de :

- Route dédiée `/module/dashboard`
- Contrôleur
- Service
- Modèles
- Vues
- Couleur métier unique
- Icône dédiée
- Dashboard FINEA UI
- ACL compatible
- Intégration portail de sélection

## Modules disponibles

### Finance
Couleur : Bleu Finance

### RH
Couleur : Indigo RH

### Colisage
Couleur : Orange Colisage

### Logistique
Couleur : Vert Logistique

### Administration
Couleur : Gris foncé Administration

### CRM
Couleur : Fuchsia
Fonctions :
- Prospects
- Opportunités
- Relances
- Historique client
- Pipeline commercial

### Tickets
Couleur : Rouge
Fonctions :
- Tickets interservices
- Tickets interpersonnels
- SLA
- Relances
- Suivi lecture

### Site Internet
Couleur : Turquoise
Fonctions :
- Gestion du contenu
- Actualités
- Bannières
- SEO
- Agences
- Tracking public

### Transit Douane
Couleur : Ambre
Fonctions :
- Déclarations
- Dossiers douaniers
- Documents import/export

### Tracking Colis
Couleur : Cyan
Fonctions :
- Suivi colis
- Historique positions
- Notifications client

### Facturation
Couleur : Violet
Fonctions :
- Factures
- Avoirs
- Encaissements

### Entrepôts
Couleur : Marron
Fonctions :
- Stocks
- Emplacements
- Inventaires

### Flotte / Transport
Couleur : Vert pétrole
Fonctions :
- Véhicules
- Chauffeurs
- Missions

### Portefeuille Clients
Couleur : Rose saumon
Fonctions :
- Comptes clients
- Encours
- Historique

### Agents & Correspondants
Couleur : Bleu ciel
Fonctions :
- Réseau international
- Transitaires partenaires

### Centre de Pilotage DG
Couleur : Noir Or
Fonctions :
- KPI globaux
- Décisions
- Alertes
- Prévisions

## Site public

Routes :

- /site
- /site/tracking
- /site/devis
- /site/contact
- /site/agences

## Site public nouvelle génération

Inspiré des standards des grands acteurs logistiques.

Fonctionnalités :

- Hero dynamique
- Demande de devis
- Tracking
- Services
- Agences
- Contact
- Actualités
- Statistiques

## Locator agences

Page :

- /site/agences

Fonctionnalités :

- Carte interactive
- Marqueur par agence
- Recherche
- Filtrage pays
- Coordonnées
- Horaires

Les agences proviennent à terme du paramétrage RH.

## RH Multi-sites

Ajouts :

- Table des sites
- Affectation employé -> site
- Gestion multi-pays
- Gestion multi-agences

Chaque employé est rattaché à :

- service_id
- site_id
- manager_id

## Dossier du personnel

Pièces jointes :

- Photo
- Pièce d'identité
- Diplômes
- Contrats
- Extraits de naissance enfants

Le dossier peut être complété à tout moment.

## Composants UI standardisés

Tous les modules utilisent :

- finea-ui.css
- finea-shell
- finea-container
- finea-page-header
- finea-kpi-grid
- finea-section-card

## Architecture technique

app/
├── Controllers
├── Services
├── Repositories
├── Models
├── Middleware

routes/
views/
assets/

Aucune logique SQL dans les contrôleurs.
