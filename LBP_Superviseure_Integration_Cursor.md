# PROMPT CURSOR — Intégration rôle "Superviseure Générale" dans LBP existant

> Ce prompt est conçu pour un système **déjà en production**.
> Stack : Next.js (frontend) + NestJS (backend) + PostgreSQL (BDD).
> Les rôles existent déjà en base. L'objectif est d'**ajouter un nouveau rôle** sans casser l'existant.
> NE PAS réécrire ce qui existe. Ajouter uniquement ce qui manque.

---

## CONTEXTE SYSTÈME EXISTANT

- La gestion des rôles est en base de données (table `roles` ou équivalent)
- Des guards/middleware NestJS protègent déjà les routes par rôle
- Le frontend Next.js gère déjà l'affichage conditionnel selon le rôle de l'utilisateur connecté
- Certaines routes API retournent déjà des données multi-agences (partiellement)

---

## OBJECTIF

Ajouter le rôle **`superviseure_generale`** avec :
- Accès **lecture seule** sur toutes les agences du réseau
- Un dashboard dédié accessible uniquement à ce rôle
- 3 actions de contrôle (signaler anomalie, demander justification, annoter) — sans modifier les données opérationnelles
- Génération et soumission de rapports au directeur

---

## ÉTAPE 1 — BASE DE DONNÉES

### 1.1 Trouver la structure actuelle des rôles
```
Montre-moi le schéma de la table qui gère les rôles (probablement "roles", "user_roles" ou dans la table "users").
Cherche dans : prisma/schema.prisma, src/entities/, ou les fichiers de migration.
```

### 1.2 Insérer le nouveau rôle
Une fois la structure trouvée, générer et exécuter ce qui correspond à ton système (Prisma, TypeORM, ou SQL brut) :

**Si Prisma — dans un fichier seed ou migration :**
```typescript
await prisma.role.create({
  data: {
    name: 'superviseure_generale',
    label: 'Superviseure Générale',
    description: 'Supervision lecture seule de l\'ensemble du réseau LBP',
    niveau_acces: 'reseau', // si ce champ existe
  }
});
```

**Si TypeORM — dans un seeder :**
```typescript
const role = roleRepository.create({
  name: 'superviseure_generale',
  label: 'Superviseure Générale',
});
await roleRepository.save(role);
```

**Si SQL brut (migration) :**
```sql
INSERT INTO roles (name, label, description, created_at)
VALUES ('superviseure_generale', 'Superviseure Générale', 'Supervision réseau complet — lecture seule', NOW());
```

> ⚠️ Adapte exactement aux colonnes de ta table `roles` existante. Ne crée pas de nouvelle table.

---

## ÉTAPE 2 — BACKEND NESTJS

### 2.1 Ajouter le rôle dans l'enum/constante des rôles

Cherche dans le code un fichier comme `roles.enum.ts`, `constants/roles.ts`, ou `decorators/roles.decorator.ts`.

Ajoute simplement :
```typescript
// Dans ton enum ou objet de rôles existant — ajouter UNE ligne
export enum Role {
  // ... rôles existants ...
  SUPERVISEURE_GENERALE = 'superviseure_generale', // ← ajouter
}
```

### 2.2 Nouvelles routes API nécessaires

Crée un module `supervision` ou ajoute dans le module existant le plus approprié.

**Fichier : `src/supervision/supervision.controller.ts`**
```typescript
import { Controller, Get, Post, Body, Param, UseGuards } from '@nestjs/common';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard'; // adapte le chemin
import { RolesGuard } from '../auth/guards/roles.guard';      // adapte le chemin
import { Roles } from '../auth/decorators/roles.decorator';   // adapte le chemin
import { SupervisionService } from './supervision.service';

@Controller('supervision')
@UseGuards(JwtAuthGuard, RolesGuard)
@Roles('superviseure_generale')
export class SupervisionController {

  constructor(private readonly supervisionService: SupervisionService) {}

  // KPIs consolidés — toutes agences
  @Get('dashboard/kpis')
  getKpisConsolides() {
    return this.supervisionService.getKpisConsolides();
  }

  // Liste toutes les agences avec statut en temps réel
  @Get('agences')
  getEtatAgences() {
    return this.supervisionService.getEtatAgences();
  }

  // Détail d'une agence (transactions du jour, état caisse, agents)
  @Get('agences/:id')
  getDetailAgence(@Param('id') id: string) {
    return this.supervisionService.getDetailAgence(id);
  }

  // Transactions d'une agence sur une période
  @Get('agences/:id/transactions')
  getTransactionsAgence(@Param('id') id: string) {
    return this.supervisionService.getTransactionsAgence(id);
  }

  // Performance de tous les agents du réseau
  @Get('agents')
  getPerformanceAgents() {
    return this.supervisionService.getPerformanceAgents();
  }

  // Anomalies actives sur le réseau
  @Get('anomalies')
  getAnomalies() {
    return this.supervisionService.getAnomalies();
  }

  // Historique des rapports soumis par la superviseure
  @Get('rapports/historique')
  getHistoriqueRapports() {
    return this.supervisionService.getHistoriqueRapports();
  }

  // Soumettre un rapport au directeur
  @Post('rapports/soumettre')
  soumettreRapport(@Body() dto: SoumettreRapportDto) {
    return this.supervisionService.soumettreRapport(dto);
  }

  // ── ACTIONS DE CONTRÔLE (lecture seule, pas de modif données) ──

  // Signaler une anomalie sur une agence
  @Post('signalements')
  signalerAnomalie(@Body() dto: SignalementDto) {
    return this.supervisionService.signalerAnomalie(dto);
  }

  // Demander une justification à un agent ou chef d'agence
  @Post('justifications/demander')
  demanderJustification(@Body() dto: DemanderJustificationDto) {
    return this.supervisionService.demanderJustification(dto);
  }

  // Annoter une opération (note interne visible de la direction uniquement)
  @Post('annotations')
  annoterOperation(@Body() dto: AnnotationDto) {
    return this.supervisionService.annoterOperation(dto);
  }
}
```

**Fichier : `src/supervision/supervision.service.ts`**
```typescript
import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
// Adapte les imports selon tes entités existantes

@Injectable()
export class SupervisionService {

  // getKpisConsolides : agrège les données de toutes les agences
  // → utilise tes tables existantes (transactions, caisses, agences)
  // → NE PAS créer de nouvelles tables pour ça
  // → Exemple de requête SQL à adapter :
  //
  // SELECT
  //   COUNT(*) as total_transactions,
  //   SUM(montant) as total_encaissements,
  //   COUNT(DISTINCT agence_id) as agences_actives
  // FROM transactions
  // WHERE DATE(created_at) = CURRENT_DATE
  //   AND statut = 'valide'
  //
  // Retourner un objet { totalEncaissements, totalTransactions, agencesActives, agentsConnectes }

  async getKpisConsolides() { /* ... */ }

  // getEtatAgences : liste toutes les agences avec leur statut du jour
  // → JOIN avec ta table agences + calcul écart de caisse du jour
  // → Statut calculé : 'normal' | 'surveillance' | 'anomalie'
  async getEtatAgences() { /* ... */ }

  async getDetailAgence(id: string) { /* ... */ }
  async getTransactionsAgence(id: string) { /* ... */ }
  async getPerformanceAgents() { /* ... */ }
  async getAnomalies() { /* ... */ }
  async getHistoriqueRapports() { /* ... */ }

  // soumettreRapport : sauvegarde le rapport + notifie le directeur
  // → Insérer dans une table "rapports" (à créer si elle n'existe pas)
  // → Créer une notification pour le directeur (via ton système de notifs existant)
  async soumettreRapport(dto: any) { /* ... */ }

  // Ces 3 méthodes créent des enregistrements dans des tables de contrôle
  // → JAMAIS de UPDATE sur transactions, caisses, ou données opérationnelles
  async signalerAnomalie(dto: any) { /* ... */ }
  async demanderJustification(dto: any) { /* ... */ }
  async annoterOperation(dto: any) { /* ... */ }
}
```

### 2.3 DTOs à créer

```typescript
// src/supervision/dto/signalement.dto.ts
export class SignalementDto {
  agenceId: string;
  type: string;       // 'ecart_caisse' | 'transaction_inhabituelle' | 'autre'
  description: string;
  gravite: 'faible' | 'moyen' | 'critique';
}

// src/supervision/dto/justification.dto.ts
export class DemanderJustificationDto {
  agentId?: string;
  chefAgenceId?: string;
  agenceId: string;
  motif: string;
  operationId?: string;
}

// src/supervision/dto/annotation.dto.ts
export class AnnotationDto {
  operationId: string;
  contenu: string;
  visibiliteDe: 'direction'; // toujours 'direction'
}

// src/supervision/dto/rapport.dto.ts
export class SoumettreRapportDto {
  type: 'caisse' | 'activite' | 'anomalies' | 'performance_agents';
  periode: 'jour' | 'semaine' | 'mois' | 'annee';
  agenceId?: string; // null = toutes agences
  dateDebut: string;
  dateFin: string;
  commentaire?: string;
}
```

### 2.4 Tables à créer si elles n'existent pas

```sql
-- Table des signalements créés par la superviseure
CREATE TABLE IF NOT EXISTS signalements (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  agence_id UUID REFERENCES agences(id),
  auteur_id UUID REFERENCES users(id),
  type VARCHAR(50) NOT NULL,
  description TEXT,
  gravite VARCHAR(20) DEFAULT 'moyen',
  statut VARCHAR(20) DEFAULT 'ouvert',
  created_at TIMESTAMP DEFAULT NOW()
);

-- Table des demandes de justification
CREATE TABLE IF NOT EXISTS demandes_justification (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  demandeur_id UUID REFERENCES users(id),
  destinataire_id UUID REFERENCES users(id),
  agence_id UUID REFERENCES agences(id),
  operation_id UUID,
  motif TEXT NOT NULL,
  statut VARCHAR(20) DEFAULT 'en_attente',
  reponse TEXT,
  created_at TIMESTAMP DEFAULT NOW()
);

-- Table des annotations internes
CREATE TABLE IF NOT EXISTS annotations_supervision (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  auteur_id UUID REFERENCES users(id),
  operation_id UUID NOT NULL,
  contenu TEXT NOT NULL,
  visibilite VARCHAR(20) DEFAULT 'direction',
  created_at TIMESTAMP DEFAULT NOW()
);

-- Table des rapports générés
CREATE TABLE IF NOT EXISTS rapports_supervision (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  auteur_id UUID REFERENCES users(id),
  type VARCHAR(50) NOT NULL,
  periode VARCHAR(20) NOT NULL,
  agence_id UUID REFERENCES agences(id),
  date_debut DATE,
  date_fin DATE,
  commentaire TEXT,
  statut_lecture VARCHAR(20) DEFAULT 'non_lu',
  soumis_a UUID REFERENCES users(id),
  created_at TIMESTAMP DEFAULT NOW()
);
```

> ⚠️ Adapte les noms de tables et les clés étrangères à ton schéma existant (ex: si ta table s'appelle `agence` et non `agences`, etc.)

### 2.5 Enregistrer le module

Dans `src/app.module.ts` (ou le module parent approprié) :
```typescript
// Ajouter SupervisionModule dans les imports
import { SupervisionModule } from './supervision/supervision.module';

@Module({
  imports: [
    // ... modules existants ...
    SupervisionModule, // ← ajouter
  ],
})
```

---

## ÉTAPE 3 — FRONTEND NEXT.JS

### 3.1 Trouver comment le rôle est lu actuellement

Cherche dans le code : comment le frontend récupère le rôle de l'utilisateur connecté.
Probablement dans : `context/AuthContext`, `hooks/useAuth`, `store/auth`, ou via le JWT décodé.

### 3.2 Ajouter la route protégée

Crée le fichier de page (adapte selon si tu utilises App Router ou Pages Router) :

**App Router — `app/supervision/page.tsx` :**
```typescript
import { redirect } from 'next/navigation';
import { getServerSession } from 'next-auth'; // ou ton système d'auth
import SupervisionDashboard from '@/components/supervision/SupervisionDashboard';

export default async function SupervisionPage() {
  const session = await getServerSession();

  if (!session || session.user.role !== 'superviseure_generale') {
    redirect('/unauthorized'); // ou ta page d'erreur existante
  }

  return <SupervisionDashboard />;
}
```

**Pages Router — `pages/supervision/index.tsx` :**
```typescript
import { GetServerSideProps } from 'next';
import SupervisionDashboard from '@/components/supervision/SupervisionDashboard';

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  // Adapte à ton système d'auth existant (cookie, session, JWT)
  const role = ctx.req.cookies['user_role']; // exemple

  if (role !== 'superviseure_generale') {
    return { redirect: { destination: '/unauthorized', permanent: false } };
  }

  return { props: {} };
};

export default function SupervisionPage() {
  return <SupervisionDashboard />;
}
```

### 3.3 Middleware de protection (si tu utilises `middleware.ts`)

Si ton projet a déjà un `middleware.ts` à la racine qui gère les accès par rôle, ajoute simplement :
```typescript
// Dans ton middleware existant — ajouter le cas superviseure_generale
const roleRoutes = {
  // ... routes existantes ...
  '/supervision': ['superviseure_generale'], // ← ajouter
};
```

### 3.4 Structure des composants à créer

```
src/components/supervision/
├── SupervisionDashboard.tsx      ← composant racine avec sidebar + routing
├── layout/
│   ├── SupervisionSidebar.tsx
│   └── SupervisionTopbar.tsx
├── sections/
│   ├── VueGlobale.tsx
│   ├── AgencesListe.tsx
│   ├── AgenceDetail.tsx
│   ├── AgentsPerformance.tsx
│   ├── AnomaliesActives.tsx
│   ├── GenerateurRapports.tsx
│   └── HistoriqueRapports.tsx
├── shared/
│   ├── KpiCard.tsx
│   ├── StatusBadge.tsx           ← normal/surveillance/anomalie
│   └── ActionToast.tsx
└── hooks/
    └── useSupervision.ts         ← tous les appels API de ce rôle
```

### 3.5 Hook API centralisé

**`src/components/supervision/hooks/useSupervision.ts`**
```typescript
import { useQuery, useMutation } from '@tanstack/react-query'; // ou SWR selon ce que tu utilises
import axios from '@/lib/axios'; // ton instance axios existante

// Adapte les URLs à ton préfixe API existant (ex: /api/v1/supervision/...)

export const useKpisConsolides = () =>
  useQuery({ queryKey: ['supervision', 'kpis'], queryFn: () =>
    axios.get('/supervision/dashboard/kpis').then(r => r.data) });

export const useEtatAgences = () =>
  useQuery({ queryKey: ['supervision', 'agences'], queryFn: () =>
    axios.get('/supervision/agences').then(r => r.data) });

export const useDetailAgence = (id: string) =>
  useQuery({ queryKey: ['supervision', 'agence', id], queryFn: () =>
    axios.get(`/supervision/agences/${id}`).then(r => r.data), enabled: !!id });

export const useAnomalies = () =>
  useQuery({ queryKey: ['supervision', 'anomalies'], queryFn: () =>
    axios.get('/supervision/anomalies').then(r => r.data) });

export const useAgents = () =>
  useQuery({ queryKey: ['supervision', 'agents'], queryFn: () =>
    axios.get('/supervision/agents').then(r => r.data) });

export const useSignalement = () =>
  useMutation({ mutationFn: (data) => axios.post('/supervision/signalements', data) });

export const useDemanderJustification = () =>
  useMutation({ mutationFn: (data) => axios.post('/supervision/justifications/demander', data) });

export const useAnnoter = () =>
  useMutation({ mutationFn: (data) => axios.post('/supervision/annotations', data) });

export const useSoumettreRapport = () =>
  useMutation({ mutationFn: (data) => axios.post('/supervision/rapports/soumettre', data) });
```

### 3.6 Redirection après login selon le rôle

Cherche dans ton code où se fait la redirection après authentification (probablement dans un fichier `auth`, `login`, ou `callback`).

Ajoute le cas `superviseure_generale` :
```typescript
// Dans ta logique de redirection post-login existante — ajouter :
const redirectParRole = {
  // ... cas existants (caissiere → /caisse, chef_agence → /agence, etc.) ...
  superviseure_generale: '/supervision', // ← ajouter
};

// Après login réussi :
router.push(redirectParRole[user.role] ?? '/dashboard');
```

---

## ÉTAPE 4 — VÉRIFICATIONS À FAIRE

Avant de tester, vérifie ces 5 points :

```
1. Le rôle 'superviseure_generale' existe bien en base de données
2. Le guard NestJS existant accepte ce nouveau nom de rôle (pas de whitelist figée)
3. La route /supervision est bien protégée côté Next.js middleware
4. L'instance axios du frontend envoie bien le token JWT dans les headers
5. Les nouvelles routes NestJS sont préfixées correctement (ex: /api/supervision si ton API a un préfixe global)
```

---

## CE QU'IL NE FAUT PAS TOUCHER

```
❌ Ne pas modifier les tables existantes : transactions, caisses, agences, users
❌ Ne pas modifier les guards/middleware existants (juste les étendre)
❌ Ne pas changer la structure d'auth (JWT, sessions)
❌ Ne pas toucher aux composants des autres rôles (caissière, chef d'agence, etc.)
❌ Ne pas créer un nouveau système de rôles — utiliser celui qui existe
```

---

## ORDRE D'EXÉCUTION RECOMMANDÉ

```
1. Insérer le rôle en base de données
2. Ajouter la constante dans l'enum des rôles NestJS
3. Créer les 4 nouvelles tables SQL (signalements, justifications, annotations, rapports)
4. Créer SupervisionModule (controller + service + DTOs)
5. Enregistrer le module dans app.module.ts
6. Tester les routes API avec Postman/Insomnia avec un token superviseure
7. Créer la page Next.js /supervision avec protection de rôle
8. Ajouter la redirection post-login pour ce rôle
9. Créer les composants frontend section par section
10. Connecter chaque composant aux hooks API
```

---

*Prompt d'intégration — LBP Superviseure Générale · Stack : Next.js + NestJS + PostgreSQL*
