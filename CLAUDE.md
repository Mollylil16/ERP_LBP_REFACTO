# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**LBP (La Belle Porte)** — a full-stack monorepo for parcel/package management: clients, invoicing, payments, cash register, call center, disputes, expedition tracking, and grouper supplier management.

- **Frontend**: React 18 + TypeScript + Vite (`/` root)
- **Backend**: NestJS + TypeORM + PostgreSQL (`/backend/`)

---

## Commands

### Frontend (root directory)

```bash
npm run dev              # Dev server on port 5173
npm run build            # Production build (runs permission parity check first)
npm run lint             # ESLint (max 200 warnings)
npm run lint:fix         # ESLint auto-fix
npm run test             # Jest tests
npm run test:watch       # Jest watch mode
npm run test:coverage    # Coverage report
npm run storybook        # Storybook on port 6006
npm run verify:permissions  # Check permission parity between frontend/backend
```

### Backend (`cd backend`)

```bash
npm run start:dev        # Dev server with hot reload (port 3001)
npm run start:debug      # Debug mode
npm run build            # Compile TypeScript
npm run lint             # ESLint
npm run test             # Unit tests
npm run test:e2e         # End-to-end tests
npm run test:cov         # Coverage report
npm run migration:run    # Apply pending TypeORM migrations
npm run migration:show   # List pending migrations
npm run seed             # Seed permissions, roles, and users into database
```

### New module setup order (critical)

```bash
# 1. Create and apply migration
npm run migration:run
# 2. Run seeders AFTER migration
npm run seed
```

---

## Architecture

### Frontend (`src/`)

| Directory | Purpose |
|-----------|---------|
| `pages/` | Route-level pages (lazy-loaded), organized by domain: `admin/`, `groupeurs/`, `expeditions/`, `auth/`, `public/` |
| `components/` | Reusable UI components per domain (`colis/`, `caisse/`, `litiges/`, `layout/`, `common/`) |
| `services/` | Axios-based API clients per feature (`api.service.ts`, `colis.service.ts`, etc.) |
| `contexts/` | React contexts: `AuthContext`, `PermissionsContext`, `ThemeContext`, `NotificationsContext` |
| `hooks/` | Custom hooks: `useAuth`, `usePermissions`, etc. |
| `constants/` | `PERMISSIONS` (RBAC keys), `ROUTE_ACCESS` (route guards), application config |
| `routes/` | Central route config with lazy loading and role-based landing page routing |
| `types/` | TypeScript interfaces shared across the app |
| `i18n/` | French localization via i18next |

**API proxy**: In dev, `vite.config.ts` proxies `/api/*` → `http://localhost:3000`.

**State management**: TanStack React Query for server state; Zustand for simple client state; React Context for auth/permissions/theme.

### Backend (`backend/src/`)

Domain modules: `auth/`, `users/`, `roles/`, `permissions/`, `colis/`, `clients/`, `factures/`, `paiements/`, `caisse/`, `exploitation/`, `callcenter/`, `litiges/`, `groupeurs/`, `supervision/`, `prestataires/`, `fournitures-bureau/`, `agences/`, `dashboard/`, `notifications/`, `alerts/`.

Database assets live in `database/`: `entities/`, `migrations/`, `seeders/`, `seeds/`, `sql/`.

Common utilities (guards, filters, interceptors, decorators) are in `common/`.

Swagger docs available at `http://localhost:3001/api/docs` in dev.

---

## Key Patterns

### RBAC / Permissions System

This is the most critical cross-cutting concern in the project.

- **Backend**: Use `@RequirePermission('permission.key')` decorator on controller methods.
- **Frontend**: Add the permission key to `src/constants/permissions.ts` `PERMISSIONS` object. Gate components with `usePermissions()` or the `<PermissionsContext>`.
- **Parity check**: `npm run verify:permissions` (also runs on `npm run build`) ensures every `@RequirePermission` key has a matching `PERMISSIONS` constant. CI fails if they diverge.
- **Database**: Permissions are seeded into `lbp_permissions` and `lbp_role_permissions` tables — run `npm run seed` after any new permission is added.

### Route access control

`ROUTE_ACCESS` in `src/constants/` maps route paths to required permissions. The `ProtectedRoute` component enforces this. The landing page after login is dynamically chosen based on role and permissions.

### Real-time notifications

Socket.io is used for push notifications. The backend `notifications/` module broadcasts events; the frontend `NotificationsContext` listens and displays them.

### Database (PostgreSQL)

- Migrations and seeds do **not** run automatically on startup — always run them manually in order: migrations first, then seeds.
- Connection config is in `backend/.env` (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`).
- Production DB: `labelleporte.cloud`; local dev: `127.0.0.1:5432`, database `lbp_db`, user `lbp_ci`.

### Environment variables

Frontend: `VITE_API_URL` (set in `.env`).

Backend (`.env`): `DB_*`, `JWT_SECRET`, `JWT_EXPIRES_IN` (1h), `REFRESH_TOKEN_TTL_DAYS` (7), `PORT` (3001), `CORS_ORIGINS`, `FORCE_HTTPS`, Sentry DSN, call center and caisse hub config.

---

## Important Constraints

- **Migrations are manual**: Never assume the database schema is up to date — run `npm run migration:run` after pulling changes that include new migrations.
- **Seeds must follow migrations**: Running `npm run seed` before migrations will fail or seed incomplete data.
- **Permission parity is enforced at build time**: Adding a backend permission without its frontend constant (or vice versa) breaks `npm run build`.
- **Role/permission misconfiguration causes silent UI failures**: Missing permissions result in empty menus and 403 errors, not visible error messages.
- **French localization**: All user-facing strings go through i18next; locale files are in `src/i18n/`. Day.js locale is set to `fr`.
