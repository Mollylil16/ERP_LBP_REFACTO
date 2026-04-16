# Plan d’amélioration — Sécurité · Permissions · Expérience utilisateur

**Logiciel** : LBP (gestion transport & logistique)  
**Stack cible** : NestJS · React · TypeScript · PostgreSQL · TypeORM  
**Version document** : **1.1 — Consolidé et clos pour le périmètre LBP**  
**Statut** : référence produit + technique ; les cases « À faire » sont le backlog restant.

---

## 0. Objectif et périmètre

Ce document remplace la version initiale (exemples Prisma / Redis / UUID) par une **spécification alignée sur le dépôt actuel** : tables `lbp_*`, guards NestJS existants, front React avec `PermissionsContext` et `ProtectedRoute`.

**Priorités historiques (conservées)** :

| ID  | Thème                         | Rôle                           |
|-----|-------------------------------|---------------------------------|
| P1  | Permissions unifiées          | Source de vérité + API + front  |
| P2  | Routes protégées              | Garde back + route front        |
| P3  | UX par rôle                   | Accueils et menus contextuels   |
| P4  | Audit & onboarding            | Traçabilité métier + guidage    |

---

## 1. Source unique de vérité pour les permissions

### 1.1 Problème visé

Éviter les écarts entre « ce que l’UI affiche » et « ce que l’API autorise » (menus visibles mais 403, ou l’inverse).

### 1.2 Architecture réelle LBP (TypeORM)

La vérité métier pour les droits applicatifs est portée par :

- **`lbp_permissions`** — catalogue des permissions (codes stockés en base, alignés avec les codes « app »).
- **`lbp_roles`** — rôles utilisateurs.
- **`lbp_role_permissions`** — liaison rôle ↔ permission.
- **Mapping** : les codes en base peuvent être mappés vers les codes consommés par le front (service rôles / permissions — ex. `permission-code-map` ou équivalent selon migrations).

Les utilisateurs référencent un rôle (`lbp_users` → rôle).

**Ce n’est pas** le schéma exemple du document v1.0 (UUID / Prisma) : **ne pas** recopier tel quel les migrations de l’ancienne annexe ; toute évolution de schéma passe par les entités TypeORM et migrations du projet.

### 1.3 API

| Endpoint | Garde | Rôle |
|----------|--------|------|
| `POST /auth/login` | Public | Retourne entre autres un tableau **`permissions`** (codes app) pour hydrater le client au login. |
| `GET /auth/permissions` | JWT | Recharge les permissions à jour (ex. après changement de rôle) — implémenté dans `auth.controller.ts`. |

**Fichiers de référence (backend)** : `backend/src/auth/auth.service.ts` (`getPermissionsForUser`), `backend/src/auth/auth.controller.ts`, `backend/src/roles/roles.service.ts`.

### 1.4 Frontend

- **Chargement** : `authService.getPermissions()` → `GET /auth/permissions`, cache `localStorage` (`lbp_permissions`) dans `PermissionsContext`.
- **Vérification** : `usePermissions().hasPermission` / `hasAnyPermission` ; routes via `ProtectedRoute` et `ROUTE_ACCESS` (`src/constants/routeAccess.ts`).
- **Constantes** : `src/constants/permissions.ts` — liste des **codes typés** pour le TS et les tests ; la **décision d’accès** pour un rôle donné reste en **base** (sauf règles exceptionnelles documentées ci‑dessous).

### 1.5 Exceptions actuelles (à assumer ou à retirer explicitement)

Dans `getPermissionsForUser`, certains profils reçoivent **`['*']`** sans lecture de la matrice (ex. directeur / admin / `code_acces` legacy).  
**Décision produit** :

- **Option A (stricte)** : supprimer ce raccourci et tout modéliser en `lbp_role_permissions` (y compris admin).  
- **Option B (pragmatique)** : garder le raccourci mais **le documenter** et **logger** quand un utilisateur non couvert par ce raccourci a `permissions: []`.

### 1.6 Cache Redis (document v1.0)

**Non requis** pour clôturer P1 sur LBP. **Optionnel (P1-bis)** : cache Redis avec TTL si la charge ou la latence sur `getPermissionsForUser` l’exigent ; invalider au changement de rôle / permissions.

### 1.7 Critères de succès P1 (révisés pour LBP)

| Critère v1.0 original | Critère applicable LBP |
|------------------------|-------------------------|
| 0 constante hardcodée | **Cible** : une seule liste « canonique » des codes (idéalement générée ou vérifiée contre `lbp_permissions`) ; le fichier TS peut rester comme **référent de typage** tant qu’il ne crée pas de second jeu de vérité pour les **rôles**. |
| Test auto si constantes réapparaissent | **À faire** : règle ESLint / test qui interdit duplications dangereuses ou script CI qui compare seeds ↔ `PERMISSIONS`. |
| DB = seule source pour les rôles | **Partiel** tant que `['*']` existe pour certains comptes. |

**Statut global P1** : **partiellement fait** — socle DB + API + front OK ; durcissement et tests de non-régression **à faire**.

---

## 2. Sécurité : protection cohérente front + backend

### 2.1 Principe

Le menu masqué améliore l’UX **mais ne suffit pas** : toute action sensible doit échouer côté API si la permission manque.

### 2.2 Backend

- **`JwtAuthGuard`** + **`PermissionsGuard`** + décorateur **`@RequirePermission(...)`** sur les contrôleurs sensibles (colis, factures, litiges, call center, caisse, etc.).
- **Fichiers** : `backend/src/auth/guards/permissions.guard.ts`, `backend/src/auth/decorators/permissions.decorator.ts`.

### 2.3 Frontend

- **`ProtectedRoute`** avec `requiredPermission` ou liste de permissions.
- Page **`ForbiddenPage`** pour les refus d’accès UI.

### 2.4 Critères de succès P2

- Couverture : **toutes** les routes API mutantes ou lisant des données sensibles ont un garde explicite (revue de code + checklist module par module).
- **Tests** : au moins un e2e ou test d’intégration « JWT sans permission → 403 » par module critique.

**Statut global P2** : **largement avancé** — à valider par **audit de couverture** et tests automatisés.

---

## 3. Matrice officielle rôle × permission

### 3.1 Nomenclature LBP (réelle)

Les codes utilisés dans le code et en base suivent le style **`module.action`** ou **`module.sous-module.action`** , par exemple :

- Colis : `colis.groupage.read`, `colis.autres-envois.create`, …
- Factures : `factures.read`, `factures.validate`, …
- Litiges : `litiges.view`, `litiges.create`, `litiges.manage`, `litiges.admin`
- Call center : `callcenter.inbox`
- etc.

Voir **`src/constants/permissions.ts`** pour la liste structurée côté front et les seeds / migrations côté back pour la **matrice en base**.

### 3.2 Tableau générique du document initial (expeditions.*, manifestes.*)

À traiter comme **exemple métier** : en LBP, « expéditions / manifestes » se rapprochent des modules **colis** et **expéditions** (routes et permissions réelles du projet). Toute nouvelle ligne de matrice doit :

1. Exister en **`lbp_permissions`** (ou créée par seed).  
2. Être reliée aux rôles dans **`lbp_role_permissions`**.  
3. Être référencée dans le front si besoin de typage (`PERMISSIONS`).

### 3.3 Seeds et déploiement

- Après migration : exécuter les **seeds** de permissions / rôles (scripts documentés dans `backend/package.json` et README backend).
- **Checklist** si un utilisateur « normal » a des menus vides :
  1. Vérifier le **code rôle** de l’utilisateur.
  2. Vérifier les lignes **`lbp_role_permissions`** pour ce rôle.
  3. Vérifier que les codes app ressortis par l’API sont bien ceux attendus par le front (`lbp_permissions` + mapping).

**Statut** : matrice **vivante** — à maintenir à chaque nouveau module (voir section 8).

---

## 4. Expérience par rôle (dashboards contextuels)

### 4.1 Cible produit

- **Agent exploitation / groupage** : file de travail (colis à traiter), actions rapides, stats du jour.
- **Caissier** : bannière session, encaissement, clôture si permission dédiée.
- **Superviseur régional** : multi-agences, alertes.
- **Manager** : files de validation (factures, litiges, exceptions).

### 4.2 Implémentation LBP (route `/dashboard`)

- Une seule page **`DashboardPage`** : la **persona** est dérivée de **`user.role.code`** (`resolveDashboardPersona` dans `src/pages/admin/dashboard/resolveDashboardPersona.ts`).
- Les **raccourcis** sont dans **`DashboardQuickActions`** : chaque bouton est enveloppé par **`WithPermission`** — affichage **dynamique** selon les permissions API (pas seulement le rôle).
- Les **données** (cartes stats, graphiques, point caisse, activités, grille agences, IA) viennent de **React Query** + **`dashboardService` / `agencesService`** avec **rafraîchissement périodique** ; les requêtes **graphiques** et **trafic** ne partent **que si** la persona affiche les graphiques (`showCharts`, ex. caissier = pas d’appel).

### 4.3 Ce que voit chaque persona (résumé)

| Code(s) rôle | Persona | Titre / ton | Raccourcis typiques (si droits) | Blocs données |
|---------------|---------|-------------|----------------------------------|---------------|
| **DIRECTEUR**, **ADMIN** | `direction` | Tableau de bord stratégique | Utilisateurs, Paramètres, Statistiques, Litiges | Tout : stats, perf multi-agences (si `dashboard.caisse`), point caisse, **graphiques**, activités, classement agences, **panneau IA** |
| **MANAGER**, **SUPERVISEUR_REGIONAL** | `manager` | Espace manager | Litiges, Factures, Rapports, Clients | Comme direction **sans** panneau IA ; perf agences + graphiques + reste |
| **CAISSIER** | `caissier` | Caisse du jour | Suivi caisse, Paiements, Factures | Stats cartes, **point caisse en haut**, activités ; **pas** de graphiques, **pas** de perf multi-agences / IA / widget classement |
| **AGENT_EXPLOITATION**, **AGENT_GROUPAGE** | `agent` | Activité colis | Groupage, Autres envois, Expéditions, Carte | Stats, graphiques, point caisse (si droit), activités ; pas perf multi-agences ni IA |
| **AGENT_SUIVI** | `suivi` | Suivi & relation client | Litiges, Boîte messages, Suivi public | Stats, graphiques, point caisse (si droit), activités |
| **Autre** / rôle inconnu | `default` | Tableau de bord générique | Groupage, Caisse, Factures (selon droits) | Comme agent côté graphiques |

Le bouton **« Suivi public colis »** (persona suivi) mène vers **`/track`** : **pas** de garde `WithPermission` (écran public métier).

### 4.4 Statique vs dynamique / interactif

- **Statique (config produit)** : libellés titre/sous-titre par persona ; **liste** des raccourcis proposés par persona ; règles `showCharts` / `showAgenciesPerf` / `showAiPanel`.
- **Dynamique** : contenu des **StatsCards**, **graphiques**, **PointCaisse**, **RecentActivities**, grilles **agences**, **recommandations IA** = données **API** ; **visibilité** des boutons = **permissions** réelles ; **interactions** existantes inchangées (clics navigation, actions du panneau IA vers des routes, etc.).

### 4.5 Critères de succès P3

- Un composant d’accueil **ou** route `/dashboard` qui adapte l’expérience selon `user.role.code` (fait).
- Validation métier : **au moins un utilisateur par persona** valide le contenu (à faire côté équipe).

**Statut global P3** : **livré (MVP)** — voir §4.2–4.4.

---

## 5. Cohérence des menus avec les droits réels

### 5.1 Règle

Chaque entrée de menu visible doit correspondre à **au moins une** permission que l’utilisateur possède ; sinon l’utilisateur ne doit pas voir le lien (ou est redirigé vers 403 si URL directe).

### 5.2 Implémentation LBP

- **`SidebarMenu.tsx`** : items conditionnés par `hasPermission(ROUTE_ACCESS.*)`.
- **`ROUTE_ACCESS`** : centralise la permission minimale par zone (`src/constants/routeAccess.ts`).

### 5.3 Amélioration possible (alignement doc v1.0)

- Fichier **`NAV_CONFIG`** unique (tableau d’objets `{ path, label, permission }`) générant menu + aide à la revue de cohérence avec le routeur.

**Statut** : **fonctionnel** ; consolidation config **optionnelle**.

---

## 6. Onboarding et messages d’erreur clairs

### 6.1 Cible

- Résumé « ce que permet mon rôle » (sans inventer de droits non présents en base).
- Tutoriel première connexion **optionnel** par persona.
- Page 403 avec message compréhensible (et idéalement libellé de la permission manquante si disponible).

### 6.2 État LBP

- 403 : **partiellement** couvert (`ForbiddenPage`).
- **Tutoriel** : **livré** — `OnboardingTourProvider` + **Tour** Ant Design (`AppOnboardingTour.tsx`) : 6 étapes (accueil, menu, zone centrale, notifications, menu utilisateur, **RoleSummary**). Lancement automatique une fois par utilisateur (`localStorage` `lbp_onboarding_tour_v1_<id>`) après chargement des permissions ; relance via menu utilisateur **Visite guidée**.
- **RoleSummary** : composant `RoleSummary.tsx` — rôle affiché + liste des **codes permission** réels (groupés par préfixe) ou message si `*` / liste vide ; variantes `compact` (tutoriel) et `full` (page **Mon profil**).

**Statut** : **MVP onboarding + résumé accès livrés** ; affinages UX possibles (thème sombre du panneau Tour, étapes conditionnelles par persona).

---

## 7. Audit et traçabilité

### 7.1 Cible document v1.0

Journal métier avec **action nommée**, **entité**, **avant/après**, utilisateur, IP, user-agent.

### 7.2 État LBP

- Table **`audit_logs`** + **`AuditInterceptor`** : journalisation **HTTP** générique (utile mais pas équivalent à « 100 % actions sensibles avec old/new métier »).
- **`lbp_caisse_audit_logs`** : exemple de log **métier** côté caisse.

### 7.3 Critères de succès P4 (révisés)

- Liste **explicite** des actions à tracer (litige validé, facture validée, session caisse fermée, etc.).
- **`AuditService`** métier (ou extension de l’intercepteur) appelé depuis les **services** concernés.
- Option UI : mention « action tracée » sur boutons critiques.

**Statut** : **partiel → renforcé (P4 MVP)** — **`BusinessAuditService`** (`audit/business-audit.service.ts`) écrit dans **`audit_logs`** (async, sans bloquer la transaction) pour : **`litige.updated`**, **`facture.validated`** / **`facture.cancelled`**, **`caisse.session_closed`** (en complément de `lbp_caisse_audit_logs`). Modules **`AuditModule`**, imports dans **Litiges**, **Factures**, **Caisse**.

**Alignement schéma** : entité **`AuditLog`** mappe explicitement les colonnes SQL **`user_id`**, **`entity`**, **`entity_id`**, **`details`** (jsonb), **`ip_address`**, **`user_agent`**, **`duration`**, **`status`**, **`created_at`**. Migration **`1743310000000-AlignAuditLogsSchema`** (idempotente) : renommages `entity_type` → `entity`, `changes` → **`details`** (jsonb, contenu legacy encapsulé si besoin), types **`user_id`** / **`entity_id`** en **varchar**, ajout **`duration`** / **`status`**, prise en charge d’anciennes colonnes **camelCase** issues d’un sync.

**UI « action tracée »** : composant **`TracedActionButton`** (info-bulle + bouton) sur **validation facture** et **clôture caisse** ; texte explicite dans le modal de validation et d’alerte dans le modal de clôture.

---

## 8. Nouveaux modules — nomenclature

Tout nouveau module doit :

1. Définir les codes **`module.action`**.
2. Les insérer en base (**seed / migration de données**) avant déploiement.
3. Ajouter les guards sur les contrôleurs Nest.
4. Ajouter les entrées de menu + `ROUTE_ACCESS` + constantes TS si besoin.

**Synchronisation auto au bootstrap** (`syncPermissions(ALL_PERMISSIONS)`) : **non implémentée** — reste une **amélioration** pour éviter les oublis ; peut s’appuyer sur un tableau unique partagé seed + Nest + front.

**Exemples déjà présents** : litiges (`litiges.*`), call center (`callcenter.inbox`).

---

## 9. Plan d’implémentation priorisé (backlog fermé pour Cursor / équipe)

Ordre recommandé **pour terminer** le plan au sens « critères ci-dessus remplis » :

| Étape | Livrable | Dépend de |
|-------|----------|-----------|
| 9.1 | Audit de couverture **PermissionsGuard** sur tous les endpoints sensibles + correctifs | P2 |
| 9.2 | Tests automatisés **403** (ou e2e) sur un jeu représentatif de routes | P2 |
| 9.3 | Décision produit sur **`['*']`** + doc + éventuellement retrait | P1 — **doc** : `DEPLOIEMENT.md` §7 + ci-dessous §9.3 |
| 9.4 | Script ou test **seed ↔ codes** `PERMISSIONS` / `lbp_permissions` | P1 — **livré** : `npm run verify:permissions` + test `permissions.consistency.test.ts` (guards ↔ `PERMISSIONS`) |
| 9.5 | **Dispatcher dashboard** par rôle (MVP : 2–3 personas) | P3 |
| 9.6 | **Audit métier** sur 3 flux : litige, facture, caisse (minimum) | P4 |
| 9.7 | **Onboarding** léger (1 écran « vos accès » basé sur permissions réelles) | P4 |
| 9.8 | (Option) Cache Redis pour `getPermissionsForUser` | P1-bis |

### 9.3 Décision wildcard `*` (login)

- **`['*']` n’est pas stocké en base** pour les profils concernés : il est **attribué en code** dans `AuthService.getPermissionsForUser` lorsque le rôle utilisateur (entité `User.role`, enum string) est **`DIRECTEUR`** ou **`ADMIN`**, ou que **`code_acces === 2`** (héritage STTINTER « accès total »).
- **Recommandation prod** : ne pas créer de permission littérale `*` dans `lbp_permissions` pour des rôles métier ; garder une matrice explicite `lbp_role_permissions` pour tous les autres codes rôle.
- **Pour aller plus loin** (option) : supprimer progressivement la dépendance à `code_acces === 2` pour le wildcard au profit d’un rôle technique en base + matrice complète — à planifier côté métier.

### 9.4 Cohérence des codes app (front ↔ guards Nest)

- **`npm run verify:permissions`** (racine du dépôt) : chaque chaîne `'…'` avec un point présente sur une ligne contenant `RequirePermission` dans `backend/src/**/*.ts` doit exister dans l’objet **`PERMISSIONS`** de `src/constants/permissions.ts`.
- **Test Jest** `src/constants/permissions.consistency.test.ts` : tout `ROUTE_ACCESS` est déclaré dans `PERMISSIONS`.
- Le mapping **DB domaine** → **codes app** reste couvert par **`permission-code-map.spec.ts`** ; toute nouvelle permission **domaine** en seed doit mettre à jour **`permission-code-map.ts`** pour que le front reçoive les bons codes au login.

### 9.0 Journal d’avancement (code)

Réalisé dans le dépôt (suite audit P2 / §9.1) :

- **Tracking GPS** : les `GET /tracking/live`, `/tracking/:ref_colis/last` et `/tracking/:ref_colis/history` exigent **JWT** + **`PermissionsGuard`** + au moins une des permissions **`colis.groupage.read`** ou **`colis.autres-envois.read`** (aligné carte colis). **`POST /tracking/update`** reste public **avec clé traceur** (`api_key` / `x-api-key`). Le **WebSocket** namespace `/tracking` exige un **JWT valide** (`handshake.auth.token` ou en-tête `Authorization`).
- **Frontend** : `ColisMapView` transmet le Bearer sur le `fetch` initial et le token dans `socket.io` (`auth: { token }`).
- **`AuthModule`** : exporte **`JwtModule`** pour les modules qui injectent `JwtService` (ex. gateway tracking).
- **Utilisateurs** : `UsersController` sous **`JwtAuthGuard` + `PermissionsGuard`** ; `@RequirePermission` sur liste / stats / CRUD / reset MDP / MDP en clair / envoi MDP temporaire (`users.read`, `users.create`, `users.update`, `users.delete` selon le cas). **`GET /users/:id`** pour un **tiers** : contrôle inchangé **`assertAdminOrDG`** (DIRECTEUR ou ADMIN uniquement) ; consultation **de son propre** profil, **changement de MDP** et **sélection d’agence** sans permission dédiée.

**9.2 (tests 403 / JWT)** : `test/access-control.e2e-spec.ts` couvre notamment `GET /clients` sans token (401), `GET /colis` sans droit colis (403), `GET /tracking/live` (401 / 403 / 200 selon permissions), `GET /users` sans `users.read` (403), `POST /tracking/update` sans clé traceur (401).

**P3 (dashboard)** : `src/pages/admin/dashboard/resolveDashboardPersona.ts`, `DashboardQuickActions.tsx`, adaptation de `DashboardPage.tsx` (titres, raccourcis, charge API ciblée).

**P4 (audit métier)** : `backend/src/audit/` (`AuditModule`, `BusinessAuditService`) — événements listés ci-dessus §7.2.

**À poursuivre** : extension **audit métier** à d’autres mutations, revue des endpoints publics, raffinement tutoriel (persona / thème sombre).

**Clôture « prêt prod » (checklist outillage)** : guide **`DEPLOIEMENT.md`**, **`npm run verify:permissions`**, tests **`permissions.consistency`**, doc wildcard §9.3. **Lot précédent** : migration **`AlignAuditLogsSchema`**, onboarding **Tour**, **`RoleSummary`**, **`TracedActionButton`**.

---

## 10. Checklist opérationnelle (recette / prod)

À coller dans la procédure de déploiement :

1. Migrations TypeORM appliquées (inclure **`AlignAuditLogsSchema1743310000000`** avant de déployer un build backend qui écrit dans **`audit_logs`** sur une base créée avec l’ancienne migration `entity_type` / `changes`).  
2. Seeds permissions / rôles exécutés sans erreur.  
3. Connexion test avec **rôle métier** : au moins une page par module attendu.  
4. Si menus vides : contrôler **`lbp_role_permissions`** et logs `[LBP_PERMISSIONS]` côté API.  
5. Vérifier qu’aucun utilisateur métier ne dépend uniquement d’un **CODEACCES** legacy non mappé.

---

## 11. Historique de version du document

| Version | Contenu |
|---------|---------|
| 1.0 | Spécification initiale (exemples Prisma, Redis, UUID, matrice générique). |
| **1.1** | **Clôture du document** pour LBP : stack réelle, statuts, critères révisés, backlog section 9, checklist section 10. |

---

*Fin du document — référence unique pour le périmètre Sécurité / Permissions / UX LBP.*
