---
title: "États par agence & point de la journée"
project: "LBP"
version: "1.0"
---

## Objectif

Ce document explique **comment chaque agence** (et chaque profil) **consulte** :

- le **point de la journée** (entrées / sorties / solde) côté **Caisse**
- les **états** (mouvements, sessions, rapport grandes lignes)
- les **points journaliers exploitation** (brouillon/soumis/validé/rejeté) et l’onglet **Points soumis**

Le principe général est : **tout est calculé “par caisse”** (via `id_caisse`) et/ou **filtré “par agence”** (via l’agence de l’utilisateur), avec des exceptions pour les profils “globaux”.

---

## Définitions (vocabulaire)

- **Caisse** : registre d’opérations (APPRO, ENTRÉES, DÉCAISSEMENT…) rattaché à une **agence**.
- **Point de la journée (Caisse)** : agrégat du jour (00:00 → 23:59) pour **une caisse donnée**.
- **Caisse principale (Siège / Hub)** : caisse centralisée “où tout est versé”. Dans l’implémentation, elle est détectée par un nom contenant **“principal”** (sinon repli sur la 1ère caisse par `id`).
- **Points journaliers (Exploitation)** : entité distincte de la caisse, avec statuts (BROUILLON / SOUMIS / VALIDE / REJETE).

---

## Écrans concernés (Front)

| Domaine | Écran | Finalité |
|---|---|---|
| Caisse | **Suivi caisse** (`/caisse/suivi`) | Poste de vente : session, point du jour, encaissement, mouvements, sessions, rapport. |
| Exploitation | **Points journaliers** (`/exploitation/points-journaliers`) | Suivi/validation des points journaliers (SOUMIS → VALIDE/REJETE). |
| Caisse (Siège) | **Onglet “Points soumis”** (dans Suivi caisse) | Validation rapide des points **SOUMIS** (filtrés par agence de la caisse sélectionnée). |

---

## Matrice d’accès (Rôles → ce qu’ils voient / font)

> Les permissions RBAC restent la base (ex. `caisse.view`, `caisse.operations`, `rapports.view`, etc.).  
> Ce tableau décrit la **logique fonctionnelle** implémentée.

| Profil | Liste des caisses | Opérations (sessions/mouvements/encaissements) | Point du jour (caisse) | Points journaliers (exploitation) |
|---|---|---|---|---|
| **Chef d’agence** | **Uniquement sa caisse d’agence** | **Sur sa caisse** (si `caisse.operations`) | Sur sa caisse | Selon perms exploitation (soumettre/valider…) |
| **Caissière / Caissier (Siège)** | **Toutes les caisses** | **Uniquement caisse principale (hub)** | Par défaut hub ; consultation possible des autres caisses | Peut valider “Points soumis” si `exploitation.points_journaliers.validate` |
| **Admin / Directeur / Super admin** | **Toutes les caisses** | Sur toutes les caisses | Toute caisse | Selon perms exploitation + accès global |

---

## Endpoints (Back) – Caisse : point de la journée & états

### 1) Liste des caisses

- **Endpoint** : `GET /caisse/caisses`
- **Paramètres** : aucun
- **Retour utile** (simplifié) :
  - `id`, `libelle`, `solde_actuel`, `id_agence`, `seuil_alerte`
  - `peut_operer` : **false** pour la caissière sur une caisse d’agence (consultation seule)

**Filtrage métier** :

- Chef d’agence / profils agence → uniquement les caisses de **leur agence**
- Caissière (siège) → toutes les caisses, mais `peut_operer=true` seulement sur le **hub**
- Admin/Directeur/Super admin → toutes les caisses, `peut_operer=true`

### 2) Point de la journée (Caisse)

- **Endpoint** : `GET /caisse/point`
- **Paramètres** :
  - `id_caisse` (recommandé)
  - `date` (optionnel, sinon aujourd’hui)
- **Calcul** :
  - agrège les mouvements sur la journée **pour la caisse** `id_caisse`

**Remarque** : dans l’écran **Suivi caisse**, l’appel est fait avec `id_caisse` = caisse sélectionnée.

### 3) États “mouvements”

- **Endpoint** : `GET /caisse/mouvements`
- **Paramètres usuels** :
  - `id_caisse`
  - `type` (APPRO / DECAISSEMENT / ENTREE_*)
  - `date_debut` / `date_fin`
- **Retour** :
  - mouvements + champs workflow (statut, justificatif, motif rejet…)

**Filtrage métier** :

- Profils agence : si `id_caisse` n’est pas fourni, le back filtre par **agence utilisateur**
- Profils globaux + caissière siège : pas de filtre agence (accès à la liste), mais la caissière est **bloquée** sur les opérations hors hub (garde côté API)

### 4) Sessions

- **Session active** : `GET /caisse/sessions/active?id_caisse=...`
- **Historique** : `GET /caisse/sessions/history?id_caisse=...&limit=...`

### 5) Rapport “Grandes lignes”

- **Endpoint** : `GET /caisse/rapport-grandes-lignes`
- **Paramètres** :
  - `id_caisse`
  - `date_debut`
  - `date_fin`
- **Résultat** : totaux par période (appro, décaissements, entrées…)

---

## Endpoints (Back) – Exploitation : points journaliers

### 1) Liste points journaliers

- **Endpoint** : `GET /points-journaliers`
- **Paramètres** :
  - `statut` (BROUILLON / SOUMIS / VALIDE / REJETE)
  - `agence_id` (optionnel)
  - `date` (optionnel)

**Accès** :

- Si l’utilisateur n’a pas les droits de validation globaux, l’API **restreint** à son agence.

### 2) Valider / rejeter

- `PATCH /points-journaliers/:id/valider`
- `PATCH /points-journaliers/:id/rejeter` (avec `motif`)

---

## Flux “Points soumis” (dans Suivi caisse)

1) L’utilisateur ouvre **Suivi caisse** et choisit une caisse.
2) L’onglet **Points soumis** apparaît si permission : `exploitation.points_journaliers.validate`.
3) La liste charge :
   - `GET /points-journaliers?statut=SOUMIS&agence_id=<id_agence_de_la_caisse>`
4) Actions :
   - Valider → `PATCH /points-journaliers/:id/valider`
   - Rejeter → `PATCH /points-journaliers/:id/rejeter` + motif

---

## “Zéro 403” et `rapports.view` (Dashboard/Analytics)

Règle : **aucun widget / analytics / alertes automatiques** ne doit déclencher de requêtes si l’utilisateur n’a pas `rapports.view`.

Implémentation :

- Sans `rapports.view`, les requêtes dashboard (stats, activities, agences perf, IA…) sont **désactivées**.
- Un bandeau “Dashboard limité” est affiché.

---

## Export PDF (recommandé)

### Option 1 — depuis l’éditeur (le plus simple)

1) Ouvrir ce fichier `docs/ETATS_AGENCES_POINTS_JOURNEE.md`
2) Utiliser l’aperçu Markdown
3) Imprimer → “Enregistrer en PDF”

### Option 2 — via navigateur

1) Ouvrir le rendu Markdown dans un navigateur
2) Imprimer → “Enregistrer en PDF”

