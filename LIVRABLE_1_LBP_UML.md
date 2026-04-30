# Modélisation UML du Système LBP (La Belle Porte)
## Examen UML — Master Hybride IGL

---

## 1. Description du domaine

**LBP (La Belle Porte)** est un système d'information logistique de gestion de colis. Il couvre l'ensemble du cycle de vie des envois : réception en agence, suivi, facturation, paiements, et livraison au destinataire. Le système est structuré en modules : gestion des colis, clients, factures, paiements, caisse, call center, litiges, supervision, et gestion des utilisateurs avec contrôle d'accès basé sur les rôles (RBAC).

**Stack technique :** Frontend React 18 + TypeScript (Vite), Backend NestJS + TypeORM, base PostgreSQL, authentification JWT, notifications temps réel via Socket.io.

---

## 2. Acteurs du système

| Acteur | Rôle |
|--------|------|
| **Utilisateur** | Agent ou employé de l'agence. Accède au système après authentification avec identifiants fournis (username + mot de passe temporaire à la création). Crée des colis, gère les clients, traite les paiements selon ses permissions. |
| **Admin** | Administrateur système. Gère les comptes utilisateurs, assigne les rôles et permissions (RBAC), supervise l'activité globale et génère des rapports analytiques. Accès complet. |
| **Livreur** | Agent de livraison terrain. Consulte ses colis à livrer, suit leur état en temps réel, et confirme les livraisons effectuées dans le système. |

---

## 3. Interactions entre les acteurs et le système

### Utilisateur ↔ Système
- Reçoit un **nom d'utilisateur** et un **mot de passe temporaire** (créés par l’Admin)
- S'authentifie via `/auth/login` (JWT + refresh token)
- Lors de la première connexion : change le mot de passe temporaire (obligatoire) et sélectionne son agence si nécessaire
- Crée, recherche et consulte des colis
- Gère les fiches clients
- Émet et consulte des factures, enregistre des paiements

### Admin ↔ Système
- S'authentifie avec accès étendu
- Crée, modifie et désactive des comptes utilisateurs
- Génère / envoie un **mot de passe temporaire** aux utilisateurs (selon l’organisation : WhatsApp/SMS)
- Assigne des rôles et permissions via l'interface RBAC
- Consulte le tableau de bord et génère des rapports (PDF/Excel)
- Supervise les modules exploitation, litiges, caisse

### Livreur ↔ Système
- S'authentifie avec accès restreint aux fonctions de livraison
- Recherche un colis par numéro de tracking
- Suit l'état et la localisation (carte Leaflet)
- Confirme une livraison (mise à jour du statut + notification Socket.io)
- Signale une tentative échouée ou un refus

---

## 4. Tableaux de description des cas d'utilisation

---

### UC1 — Première connexion (mot de passe temporaire + sélection agence)

| Champ | Contenu |
|-------|---------|
| **Nom** | Première connexion (mot de passe temporaire + sélection agence) |
| **Acteur principal** | Utilisateur |
| **Préconditions** | Un compte existe déjà (créé par l’Admin). L’utilisateur a reçu son **username** et un **mot de passe temporaire**. |
| **Scénario nominal** | 1. L’utilisateur accède à la page de connexion. 2. Il saisit username + mot de passe temporaire. 3. Le système authentifie et ouvre une session (JWT). 4. Le système détecte que l’utilisateur doit changer son mot de passe et redirige vers l’écran “Changer le mot de passe”. 5. L’utilisateur saisit l’ancien mot de passe (temporaire) + nouveau mot de passe + confirmation. 6. Le système enregistre le nouveau mot de passe (bcrypt) et valide la mise à jour. 7. Si l’utilisateur doit être rattaché à une agence et n’en a pas, le système redirige vers “Sélectionner agence”. 8. L’utilisateur sélectionne son agence, le système l’associe au compte. 9. L’utilisateur accède à sa page d’accueil. |
| **Cas d'erreur** | - Identifiants temporaires incorrects → refus (401). - Nouveau mot de passe faible / confirmation différente → message d’erreur. - Action non autorisée / agence requise → 403 Forbidden. - Erreur serveur → message générique. |
| **Postconditions** | L’utilisateur est authentifié, possède un **mot de passe définitif** et, si applicable, une **agence sélectionnée**. |

---

### UC2 — Se connecter

| Champ | Contenu |
|-------|---------|
| **Nom** | Se connecter |
| **Acteur principal** | Utilisateur, Admin, Livreur |
| **Préconditions** | L'acteur possède un compte actif. |
| **Scénario nominal** | 1. L'acteur accède à la page de connexion. 2. Il saisit **username** et mot de passe. 3. Il soumet. 4. Le système vérifie les credentials (bcrypt.compare). 5. Le système génère un access token JWT et un refresh token. 6. Le frontend stocke les tokens et les permissions. 7. Le système redirige vers la page d'accueil selon le rôle de l'acteur (ou vers “Changer mot de passe” / “Sélectionner agence” si requis). |
| **Cas d'erreur** | - Credentials incorrects → "Nom d'utilisateur ou mot de passe incorrect". - Compte désactivé → accès refusé. - Token expiré → refresh automatique ou redirection vers login. |
| **Postconditions** | L'acteur est authentifié. Un JWT valide est stocké côté client. La page d'accueil correspond au rôle. |

---

### UC3 — Gérer les utilisateurs

| Champ | Contenu |
|-------|---------|
| **Nom** | Gérer les utilisateurs |
| **Acteur principal** | Admin |
| **Préconditions** | L'Admin est connecté et possède la permission `users.manage`. |
| **Scénario nominal** | 1. L'Admin accède au module de gestion des utilisateurs. 2. Le système affiche la liste des utilisateurs. 3. L'Admin choisit une action : créer, modifier, désactiver ou assigner un rôle. 4. Pour une création : il remplit le formulaire et soumet. 5. Le système crée le compte avec le rôle assigné et génère un **mot de passe temporaire** (à changer à la première connexion). 6. L’Admin transmet les identifiants à l’utilisateur (canal interne : WhatsApp/SMS selon organisation). 7. La liste est mise à jour et une confirmation s'affiche. |
| **Cas d'erreur** | - Email déjà utilisé → erreur 409. - Tentative de suppression de son propre compte → refus. - Permission insuffisante → 403 Forbidden. |
| **Postconditions** | L'utilisateur ciblé est créé / modifié / désactivé. En cas de création, un **mot de passe temporaire** est disponible pour la première connexion. Les changements sont persistés en base PostgreSQL. |

---

### UC4 — Générer un rapport

| Champ | Contenu |
|-------|---------|
| **Nom** | Générer un rapport |
| **Acteur principal** | Admin |
| **Préconditions** | L'Admin est connecté et possède la permission `dashboard.view`. Des données existent sur la période choisie. |
| **Scénario nominal** | 1. L'Admin accède au tableau de bord. 2. Il sélectionne le type de rapport (colis, factures, paiements) et la période. 3. Il lance la génération. 4. Le backend agrège les données depuis PostgreSQL. 5. Le rapport est généré en PDF (PDFKit), Excel (ExcelJS) ou affiché sous forme de graphiques (Recharts). 6. L'Admin télécharge ou consulte le rapport à l'écran. |
| **Cas d'erreur** | - Aucune donnée pour la période → message informatif. - Erreur de génération PDF → message d'erreur + log Sentry. |
| **Postconditions** | Un rapport est disponible en téléchargement ou affiché à l'écran. |

---

### UC5 — Suivre une livraison

| Champ | Contenu |
|-------|---------|
| **Nom** | Suivre une livraison |
| **Acteur principal** | Livreur |
| **Préconditions** | Le Livreur est connecté et possède la permission `livraisons.read`. Il connaît le numéro de tracking. |
| **Scénario nominal** | 1. Le Livreur accède au module de livraison. 2. Il saisit ou scanne le numéro de tracking. 3. Le système recherche le colis en base. 4. Il affiche les détails : destinataire, adresse, statut, historique. 5. Optionnellement, le Livreur affiche la localisation sur la carte Leaflet. |
| **Cas d'erreur** | - Numéro de tracking inconnu → "Colis introuvable". - Colis d'une autre agence → accès refusé (403). - Erreur réseau → message d'erreur. |
| **Postconditions** | Le Livreur dispose des informations nécessaires pour effectuer ou programmer la livraison. |

---

### UC6 — Confirmer une livraison

| Champ | Contenu |
|-------|---------|
| **Nom** | Confirmer une livraison |
| **Acteur principal** | Livreur |
| **Préconditions** | Le Livreur est connecté. Le colis est en statut "En cours de livraison" (suite à UC5). |
| **Scénario nominal** | 1. Le Livreur sélectionne le colis à confirmer. 2. Il vérifie l'identité du destinataire. 3. Il remet le colis et confirme dans le système. 4. Le backend met le statut à "Livré" en base. 5. Le système envoie une notification temps réel au client via Socket.io. 6. Si paiement à la livraison, la caisse est mise à jour automatiquement. |
| **Cas d'erreur** | - Destinataire absent → statut "Tentative de livraison échouée". - Refus du colis → statut "Refusé" + documentation du motif. - Erreur réseau → sauvegarde locale + synchronisation différée. |
| **Postconditions** | Statut du colis = "Livré". Notification envoyée. Caisse mise à jour si applicable. |

---

## 5. Description du diagramme de classes

Le système LBP repose sur les entités principales suivantes :

| Classe | Attributs clés | Responsabilité |
|--------|---------------|----------------|
| **User** | id, username, nom, prenom, password, isActive, mustChangePassword, agenceSelected | Représente tout acteur du système. Authentification par username. À la première connexion, l’utilisateur doit changer son mot de passe temporaire et sélectionner son agence si nécessaire. |
| **Role** | id, name, description | Groupe de permissions. Ex: admin, agent, livreur. |
| **Permission** | id, name, key | Permission atomique. Ex: `users.manage`, `colis.create`. |
| **Colis** | id, numeroTracking, statut, poids, dateCreation | Entité centrale : un envoi physique. |
| **Client** | id, nom, prenom, telephone, adresse | Expéditeur ou destinataire d'un colis. |
| **Facture** | id, numero, montant, statut, dateEmission | Document financier lié à un ou plusieurs colis. |
| **Paiement** | id, montant, modePaiement, date, reference | Enregistrement d'un règlement. |
| **Agence** | id, nom, adresse, telephone | Point de collecte/distribution. Les utilisateurs et colis peuvent être rattachés à une agence. |

**Relations clés :**
- `User` → `Role` : many-to-one (un rôle principal par compte)
- `Role` ↔ `Permission` : many-to-many (table `lbp_role_permissions`)
- `Colis` → `Client` : many-to-one
- `Client` → `Facture` : one-to-many
- `Facture` → `Paiement` : one-to-many
- `Colis` → `Agence` : many-to-one

---

## 6. Description du diagramme de déploiement

Le système LBP est déployé selon une architecture 3-tiers :

| Nœud | Rôle | Artéfacts déployés |
|------|------|--------------------|
| **Navigateur Client** | Point d'accès utilisateur | Application React (SPA) via HTTPS:443 |
| **Serveur Web / CDN** | Hébergement frontend | Bundle React (Vite build), assets statiques |
| **Serveur API (NestJS)** | Logique métier | API REST (port 3001), WebSocket Server (Socket.io) |
| **Serveur PostgreSQL** | Persistance des données | Base `lbp_db` (port 5432) |

**Communications :**
- Navigateur ↔ Serveur API : HTTPS / WSS (WebSocket sécurisé)
- Serveur API ↔ PostgreSQL : TCP:5432 (réseau interne)
- Frontend proxy (développement) : `/api/*` → `http://localhost:3001`

---

## 7. Index des diagrammes (fichier LBP_Diagrammes_UML.drawio)

| Page | Nom | Type |
|------|-----|------|
| 1 | Vue Globale — Cas d'utilisation | Diagramme de cas d'utilisation (global) |
| 2 | UC1 — Première connexion (mdp temporaire + agence) — CdU | Diagramme de cas d'utilisation |
| 3 | UC1 — Première connexion (mdp temporaire + agence) — Séquence | Diagramme de séquence |
| 4 | UC1 — Première connexion (mdp temporaire + agence) — Activité | Diagramme d'activité |
| 5 | UC2 — Se connecter — CdU | Diagramme de cas d'utilisation |
| 6 | UC2 — Se connecter — Séquence | Diagramme de séquence |
| 7 | UC2 — Se connecter — Activité | Diagramme d'activité |
| 8 | UC3 — Gérer utilisateurs — CdU | Diagramme de cas d'utilisation |
| 9 | UC3 — Gérer utilisateurs — Séquence | Diagramme de séquence |
| 10 | UC3 — Gérer utilisateurs — Activité | Diagramme d'activité |
| 11 | UC4 — Générer rapport — CdU | Diagramme de cas d'utilisation |
| 12 | UC4 — Générer rapport — Séquence | Diagramme de séquence |
| 13 | UC4 — Générer rapport — Activité | Diagramme d'activité |
| 14 | UC5 — Suivre livraison — CdU | Diagramme de cas d'utilisation |
| 15 | UC5 — Suivre livraison — Séquence | Diagramme de séquence |
| 16 | UC5 — Suivre livraison — Activité | Diagramme d'activité |
| 17 | UC6 — Confirmer livraison — CdU | Diagramme de cas d'utilisation |
| 18 | UC6 — Confirmer livraison — Séquence | Diagramme de séquence |
| 19 | UC6 — Confirmer livraison — Activité | Diagramme d'activité |
| 20 | Diagramme de classes | Diagramme de classes (système complet) |
| 21 | Diagramme de déploiement | Diagramme de déploiement (système complet) |
