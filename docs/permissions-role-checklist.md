# Checklist permissions / rôles LBP

## Source de vérité

| Couche | Fichier / mécanisme |
|--------|---------------------|
| Codes DB (domaine) | `backend/src/database/seeders/permissions.seeder.ts` |
| Matrice rôle → permissions DB | `backend/src/database/seeders/role-permissions.seeder.ts` |
| Mapping DB → codes **app** (JWT / guards) | `backend/src/common/permission-code-map.ts` + `ensureDashboardPermissions` dans `roles.service.ts` |
| Menu + routes front | `src/constants/routeAccess.ts`, `SidebarMenu.tsx`, `routes/index.tsx` |
| Contexte front | `PermissionsContext` + `/auth/permissions` |

## Vérifier qu’aucun rôle n’a une liste vide

Si `getAppPermissionCodesForRole` retourne `null` ou un mapping vide → l’utilisateur reçoit `[]` → **403** sur la plupart des routes.

**À faire après déploiement :**

1. Lancer les migrations (dont `AddParametresApplicationPermission`).
2. Ré-appliquer les seeds permissions / rôle-permissions si besoin :  
   `npm run seed` (ou script projet équivalent qui appelle `seedRolePermissions`).

**Requête SQL rapide (rôles sans aucune permission) :**

```sql
SELECT r.code, r.nom
FROM lbp_roles r
LEFT JOIN lbp_role_permissions rp ON rp.role_id = r.id
WHERE rp.id IS NULL;
```

## Carte synthétique : route ↔ permission (app)

| Zone | Permission(s) `ProtectedRoute` |
|------|--------------------------------|
| `/dashboard` | `dashboard.view` |
| Colis groupage / autres / carte / rapports colis | `colis.groupage.*` / `colis.autres-envois.*` / `rapports.view` |
| `/expeditions` | lecture colis (any) |
| `/clients` | `clients.read` |
| `/litiges` | `litiges.view` |
| `/callcenter/inbox` | `callcenter.inbox` |
| `/factures` | `factures.read` |
| `/paiements` | `paiements.read` |
| `/caisse/*` | `caisse.view` |
| `/statistiques/*` | `rapports.view` |
| `/settings` (général société) | `config.view` |
| `/settings/tarifs` | `config.update` |
| `/settings/agences` | `config.update` |
| `/users` | `users.read` |
| `/profile` | *(authentifié seulement)* |
| `GET /agences`, `GET /agences/:id` | `agences.read` **ou** `config.view` |

## Rôles seedés (`lbp_roles`)

`DIRECTEUR`, `MANAGER`, `SUPERVISEUR_REGIONAL`, `AGENT_EXPLOITATION`, `AGENT_GROUPAGE`, `CAISSIER`, `CAISSIER_GROUPAGE`, `AGENT_SUIVI`.

- **DIRECTEUR** : `*` (toutes les permissions app).
- **ADMIN** (utilisateur) : traité comme accès complet côté `auth.service` + `PermissionsContext` (aligné JWT).

## Changement récent important

- **`structures.agences.read`** → code app **`agences.read`** (liste agences / sélecteurs), **plus** `config.view`.
- **`structures.parametres_application.read`** → **`config.view`** (menu Paramètres > Général).
- **MANAGER** reçoit `structures.parametres_application.read` dans la matrice seed + migration d’appoint.

## Pages avec `WithPermission` (à garder alignées avec `PERMISSIONS`)

- `DashboardPage`, `StatsCards`, `SuiviCaissePage`, `PaiementsListPage`, `PaiementList`, `FactureList`, `ColisList`, `ClientList`, `LitigeDetailPage`, `UsersListPage` (imports).

Auditer toute nouvelle page avec les mêmes constantes `PERMISSIONS.*` plutôt que des chaînes magiques.
