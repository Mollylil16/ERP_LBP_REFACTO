# Déploiement production — LBP

Guide opérationnel pour publier l’API NestJS et le front Vite. Adapter les chemins et l’orchestrateur (Docker, systemd, PaaS) à votre hébergement.

## 1. Prérequis

- **Node.js** LTS (aligné avec les `engines` du projet si présents).
- **PostgreSQL** accessible depuis le serveur d’API.
- **HTTPS** pour le site (certificat valide).

## 2. Base de données

1. Créer une base dédiée et un utilisateur avec droits DDL si vous appliquez les migrations depuis la CI.
2. Variables **`DB_HOST`**, **`DB_PORT`**, **`DB_USERNAME`**, **`DB_PASSWORD`**, **`DB_DATABASE`**.
3. **`DB_SYNCHRONIZE=false`** en production.
4. Exécuter **toutes** les migrations TypeORM (dossier `backend/src/database/migrations`), y compris **`AlignAuditLogsSchema1743310000000`** si l’ancienne table `audit_logs` utilisait `entity_type` / `changes`.
5. Charger les données de référence :

```bash
cd backend
npm ci
npm run build
npm run seed
```

Sans seed (ou sans `lbp_role_permissions` pour un rôle), les comptes métier reçoivent **`permissions: []`** : menus vides côté front.

## 3. API (NestJS)

Variables typiques :

- **`JWT_SECRET`** : chaîne longue et aléatoire, unique par environnement.
- **`TRACKER_API_KEY`** : clé pour `POST /tracking/update` (traceurs) — ne pas laisser la valeur de développement.
- Toute autre clé déjà utilisée dans le projet (messagerie, etc.).

Build et démarrage :

```bash
cd backend
npm ci
npm run build
npm run start:prod
```

Vérifier health / connectivité DB selon vos routes (`/health` si configuré).

## 4. Front (Vite)

1. Copier **`.env.production`** (ou variables du CI) : URL de l’API, etc.
2. Build :

```bash
cd ..   # racine du repo front (lbp_projet)
npm ci
npm run build
```

3. Servir le répertoire **`dist/`** derrière un reverse proxy (nginx, Caddy, etc.) avec **fallback** vers `index.html` pour le routeur SPA.

## 5. Contrôles avant bascule

Depuis la **racine du dépôt** :

```bash
npm run verify:permissions
npm test -- --testPathPattern=permissions.consistency
cd backend && npx jest src/common/permission-code-map.spec.ts --no-coverage
```

Recette manuelle minimale :

- Connexion avec un **profil métier** (pas seulement admin).
- Un écran par module critique (colis, factures, caisse, litiges si activé).
- Validation facture ou clôture caisse si dans le périmètre (audit).

## 6. Sauvegardes

- Sauvegardes planifiées **PostgreSQL** (dump chiffré, rétention définie).
- Procédure de restauration testée au moins une fois sur un environnement de staging.

## 7. Politique permissions `*` (wildcard)

Le jeton de session peut contenir **`['*']`** pour **DIRECTEUR**, **ADMIN** et **`code_acces === 2`**, calculé dans `AuthService.getPermissionsForUser` : pas de ligne `*` obligatoire en base pour ces profils. Ne pas attribuer `*` manuellement dans `lbp_permissions` aux rôles courants ; préférer la matrice `lbp_role_permissions` explicite.

---

*Pour le détail sécurité / permissions / UX, voir `PLAN_SECURITE_PERMISSIONS_UX.md`.*
