# LBP Frontend - La Belle Porte

Application frontend React pour la gestion de colis de **La Belle Porte (LBP)**.

## 🚀 Technologies utilisées

- **React 18** - Bibliothèque UI
- **TypeScript** - Typage statique
- **Vite** - Build tool moderne et rapide
- **React Router v6** - Navigation
- **Ant Design** - Composants UI
- **Zustand** - Gestion d'état
- **TanStack Query** - Gestion des requêtes API
- **Axios** - Client HTTP
- **React Hook Form** + **Zod** - Gestion des formulaires

## 📁 Structure du projet

```
lbp-frontend/
├── src/
│   ├── assets/          # Images, fonts, etc.
│   ├── components/      # Composants réutilisables
│   │   ├── common/      # Composants communs (ProtectedRoute, WithPermission)
│   │   └── layout/      # Layouts (MainLayout, Sidebar, Header)
│   ├── contexts/        # Contextes React (Auth, Permissions)
│   ├── hooks/           # Custom hooks
│   ├── pages/           # Pages de l'application
│   │   ├── admin/       # Pages admin
│   │   │   ├── colis/   # Pages gestion colis
│   │   │   ├── clients/ # Pages gestion clients
│   │   │   └── factures/# Pages gestion factures
│   │   └── public/      # Pages publiques (Login, Track)
│   ├── services/        # Services API
│   ├── types/           # Types TypeScript
│   ├── utils/           # Fonctions utilitaires
│   ├── App.tsx          # Composant racine avec routes
│   ├── main.tsx         # Point d'entrée
│   └── index.css        # Styles globaux
├── index.html
├── package.json
├── tsconfig.json
├── vite.config.ts
└── README.md
```

## 🛠️ Installation

1. **Installer les dépendances**
```bash
cd lbp-frontend
npm install
```

2. **Configurer les variables d'environnement**
```bash
cp .env.example .env
# Éditer .env avec vos paramètres
```

3. **Lancer le serveur de développement**
```bash
npm run dev
```

L'application sera accessible sur `http://localhost:3000`

## 📝 Scripts disponibles

- `npm run dev` - Lance le serveur de développement
- `npm run build` - Build de production
- `npm run preview` - Prévisualise le build de production
- `npm run lint` - Vérifie le code avec ESLint
- `npm run verify:permissions` - Vérifie l’alignement des codes `@RequirePermission` (backend) avec `PERMISSIONS` (front)
- **Mise en production** : voir **`DEPLOIEMENT.md`** à la racine du dépôt (migrations, seed, variables, recette).

## 🎨 Fonctionnalités principales

### ✅ Implémenté

- ✅ Structure de base du projet
- ✅ Configuration TypeScript + Vite
- ✅ Routing avec React Router
- ✅ Authentification (AuthContext)
- ✅ Système de permissions (PermissionsContext)
- ✅ Layout principal avec sidebar responsive
- ✅ Pages publiques (Login, Suivi Colis)
- ✅ Page Dashboard avec widgets statistiques
- ✅ Protection des routes (ProtectedRoute)
- ✅ Composant de gestion des permissions (WithPermission)
- ✅ Services API (auth.service, api.service)
- ✅ Types TypeScript complets

### 🚧 À développer

- ⏳ Pages complètes de gestion Colis (CRUD)
- ⏳ Pages complètes de gestion Clients
- ⏳ Pages complètes de gestion Factures
- ⏳ Graphiques et statistiques avancées
- ⏳ Système de notifications
- ⏳ Export PDF/Excel
- ⏳ Recherche et filtres avancés
- ⏳ Tests unitaires et E2E

## 🔐 Système d'authentification

L'authentification est gérée via `AuthContext` qui :
- Stocke le token JWT dans localStorage
- Intercepte les requêtes API pour ajouter le token
- Gère la déconnexion automatique en cas de token expiré
- Rafraîchit automatiquement les données utilisateur

## 🎯 Système de permissions

Le système de permissions permet de :
- Contrôler l'accès aux fonctionnalités selon le rôle
- Masquer/afficher des éléments dans l'UI
- Protéger les routes selon les permissions

Exemple d'utilisation :
```tsx
<WithPermission permission="colis.create">
  <Button>Créer un colis</Button>
</WithPermission>
```

## 🔌 Configuration API

Le service API est configuré dans `src/services/api.service.ts` et utilise Axios avec :
- Intercepteurs pour ajouter automatiquement le token
- Gestion des erreurs 401 (redirection vers login)
- Configuration de base URL via variable d'environnement

## 📦 Déploiement

### Build de production

```bash
npm run build
```

Les fichiers seront générés dans le dossier `dist/`

### Déploiement sur cPanel

1. Build le projet : `npm run build`
2. Uploader le contenu de `dist/` vers votre hébergement cPanel
3. Configurer les redirections si nécessaire (SPA)

## 🔄 Prochaines étapes

1. Connecter le backend NestJS (API)
2. Implémenter les formulaires complets avec validation
3. Ajouter les graphiques et statistiques
4. Optimiser les performances
5. Ajouter les tests

## 📚 Documentation

**API / droits utilisateur** : les permissions viennent de la base (`lbp_permissions`, `lbp_role_permissions`). Les migrations et le **`npm run seed`** du dossier `backend/` ne partent **pas** tout seuls au démarrage de l’API — voir la section dédiée dans [backend/README.md](backend/README.md).

Pour plus d'informations sur les technologies utilisées :
- [React](https://react.dev)
- [TypeScript](https://www.typescriptlang.org)
- [Vite](https://vitejs.dev)
- [Ant Design](https://ant.design)
- [React Router](https://reactrouter.com)
