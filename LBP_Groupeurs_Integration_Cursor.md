# PROMPT CURSOR — Espace Groupeurs/Grossistes
# Système LBP Logistique — Next.js + NestJS + PostgreSQL

> Système **déjà en production**. Ne pas réécrire l'existant.
> Ajouter uniquement ce qui est décrit ici, en s'intégrant aux patterns existants.
> Rôles concernés : `groupeur_grossiste` (nouveau), `superviseure_regionale` (existant),
> `assistante_dg` (existant), `directeur` (existant).

---

## 0. VUE D'ENSEMBLE

L'espace groupeurs est un module isolé dans lequel :
- Chaque **groupeur/grossiste** gère son propre espace (devis, expéditions, factures, documents)
- La **superviseure régionale** administre tous les groupeurs (CRUD) et supervise leurs activités
- L'**assistante DG** et le **directeur** ont un accès complet en lecture + validation de rapports
- Toute action sensible d'un groupeur déclenche une **notification** via le module existant

---

## 1. BASE DE DONNÉES — NOUVELLES TABLES

> ⚠️ Inspecte d'abord `prisma/schema.prisma` ou tes fichiers de migration existants.
> Adapte les clés étrangères aux vrais noms de tes tables (`users`, `roles`, etc.).
> Utilise le même système de migration que le reste du projet (Prisma migrate ou TypeORM).

```sql
-- ══════════════════════════════════════════
-- TABLE PRINCIPALE : Groupeurs/Grossistes
-- ══════════════════════════════════════════
CREATE TABLE groupeurs (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id           UUID REFERENCES users(id) ON DELETE CASCADE,  -- compte de connexion
  code              VARCHAR(20) UNIQUE NOT NULL,   -- ex: GRP-001
  raison_sociale    VARCHAR(150) NOT NULL,
  nom_commercial    VARCHAR(150),
  type              VARCHAR(20) DEFAULT 'groupeur' CHECK (type IN ('groupeur', 'grossiste', 'mixte')),
  pays              VARCHAR(80),
  ville             VARCHAR(80),
  adresse           TEXT,
  telephone         VARCHAR(30),
  email_contact     VARCHAR(120),
  numero_registre   VARCHAR(60),                  -- registre de commerce
  corridors         TEXT[],                        -- ex: ['Chine→CI', 'Europe→CI']
  modes_transport   TEXT[],                        -- ex: ['maritime', 'aerien']
  statut            VARCHAR(20) DEFAULT 'actif' CHECK (statut IN ('actif', 'suspendu', 'archive')),
  cree_par          UUID REFERENCES users(id),     -- superviseure qui a créé le compte
  created_at        TIMESTAMP DEFAULT NOW(),
  updated_at        TIMESTAMP DEFAULT NOW()
);

-- ══════════════════════════════════════════
-- DEVIS / COTATIONS DE FRET
-- ══════════════════════════════════════════
CREATE TABLE groupeur_devis (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  groupeur_id      UUID REFERENCES groupeurs(id) ON DELETE CASCADE,
  numero           VARCHAR(30) UNIQUE NOT NULL,    -- ex: DEV-2026-0042
  client_nom       VARCHAR(150) NOT NULL,
  client_contact   VARCHAR(120),
  origine          VARCHAR(100) NOT NULL,
  destination      VARCHAR(100) NOT NULL,
  mode_transport   VARCHAR(20) CHECK (mode_transport IN ('maritime', 'aerien', 'terrestre', 'multimodal')),
  type_chargement  VARCHAR(10) CHECK (type_chargement IN ('FCL', 'LCL')),
  marchandise      TEXT,
  poids_kg         DECIMAL(10,2),
  volume_m3        DECIMAL(10,2),
  prix_propose     DECIMAL(15,2),
  devise           VARCHAR(5) DEFAULT 'XOF',
  validite_jours   INTEGER DEFAULT 15,
  statut           VARCHAR(20) DEFAULT 'brouillon'
                   CHECK (statut IN ('brouillon', 'envoye', 'accepte', 'refuse', 'expire')),
  notes            TEXT,
  created_at       TIMESTAMP DEFAULT NOW(),
  updated_at       TIMESTAMP DEFAULT NOW()
);

-- ══════════════════════════════════════════
-- EXPÉDITIONS / SHIPMENTS
-- ══════════════════════════════════════════
CREATE TABLE groupeur_expeditions (
  id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  groupeur_id         UUID REFERENCES groupeurs(id) ON DELETE CASCADE,
  devis_id            UUID REFERENCES groupeur_devis(id),
  numero_expedition   VARCHAR(30) UNIQUE NOT NULL,  -- ex: EXP-2026-0018
  client_nom          VARCHAR(150) NOT NULL,
  client_contact      VARCHAR(120),
  origine             VARCHAR(100) NOT NULL,
  destination         VARCHAR(100) NOT NULL,
  mode_transport      VARCHAR(20),
  type_chargement     VARCHAR(10),
  marchandise         TEXT,
  poids_kg            DECIMAL(10,2),
  volume_m3           DECIMAL(10,2),
  -- Conteneur
  numero_conteneur    VARCHAR(30),
  taille_conteneur    VARCHAR(10),                  -- 20', 40', 40'HC
  -- Dates clés
  date_depart_prevu   DATE,
  date_arrivee_prevu  DATE,
  date_depart_reel    DATE,
  date_arrivee_reelle DATE,
  -- Armateur / Transporteur
  armateur            VARCHAR(100),
  numero_bl_master    VARCHAR(60),                  -- B/L compagnie maritime
  numero_bl_house     VARCHAR(60),                  -- B/L groupeur → client
  -- Statut
  statut              VARCHAR(30) DEFAULT 'en_preparation'
                      CHECK (statut IN (
                        'en_preparation', 'merchandise_recue', 'en_transit',
                        'arrive_port', 'en_dedouanement', 'livre', 'litige'
                      )),
  notes               TEXT,
  created_at          TIMESTAMP DEFAULT NOW(),
  updated_at          TIMESTAMP DEFAULT NOW()
);

-- ══════════════════════════════════════════
-- FACTURATION
-- ══════════════════════════════════════════
CREATE TABLE groupeur_factures (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  groupeur_id      UUID REFERENCES groupeurs(id) ON DELETE CASCADE,
  expedition_id    UUID REFERENCES groupeur_expeditions(id),
  numero_facture   VARCHAR(30) UNIQUE NOT NULL,     -- ex: FAC-2026-0091
  client_nom       VARCHAR(150) NOT NULL,
  client_contact   VARCHAR(120),
  date_emission    DATE DEFAULT CURRENT_DATE,
  date_echeance    DATE,
  -- Lignes de facturation (stockées en JSON)
  lignes           JSONB NOT NULL DEFAULT '[]',
  -- ex: [{ "description": "Fret maritime LCL", "quantite": 1, "prix_unitaire": 450000, "total": 450000 },
  --      { "description": "Frais de groupage", "quantite": 1, "prix_unitaire": 25000, "total": 25000 }]
  sous_total       DECIMAL(15,2) NOT NULL,
  tva_pct          DECIMAL(5,2) DEFAULT 18.00,
  tva_montant      DECIMAL(15,2),
  total_ttc        DECIMAL(15,2) NOT NULL,
  devise           VARCHAR(5) DEFAULT 'XOF',
  statut_paiement  VARCHAR(20) DEFAULT 'en_attente'
                   CHECK (statut_paiement IN ('en_attente', 'partiel', 'paye', 'en_retard', 'annule')),
  montant_recu     DECIMAL(15,2) DEFAULT 0,
  date_paiement    DATE,
  mode_paiement    VARCHAR(30),
  notes            TEXT,
  created_at       TIMESTAMP DEFAULT NOW(),
  updated_at       TIMESTAMP DEFAULT NOW()
);

-- ══════════════════════════════════════════
-- DOCUMENTS (B/L, Douane, Certificats...)
-- ══════════════════════════════════════════
CREATE TABLE groupeur_documents (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  groupeur_id     UUID REFERENCES groupeurs(id) ON DELETE CASCADE,
  expedition_id   UUID REFERENCES groupeur_expeditions(id),
  type_document   VARCHAR(50) NOT NULL
                  CHECK (type_document IN (
                    'bl_master', 'bl_house', 'facture_commerciale',
                    'liste_colisage', 'certificat_origine', 'declaration_douane',
                    'bon_livraison', 'autre'
                  )),
  nom_fichier     VARCHAR(200) NOT NULL,
  url_fichier     TEXT NOT NULL,                    -- chemin S3 ou ton storage existant
  taille_octets   INTEGER,
  statut          VARCHAR(20) DEFAULT 'valide'
                  CHECK (statut IN ('valide', 'expire', 'annule')),
  uploaded_par    UUID REFERENCES users(id),
  created_at      TIMESTAMP DEFAULT NOW()
);

-- ══════════════════════════════════════════
-- RAPPORTS SUPERVISION
-- ══════════════════════════════════════════
CREATE TABLE groupeur_rapports (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  auteur_id       UUID REFERENCES users(id),        -- superviseure_regionale
  type            VARCHAR(50) NOT NULL
                  CHECK (type IN (
                    'activite_groupeur', 'financier', 'expeditions',
                    'anomalies', 'performance_globale'
                  )),
  periode         VARCHAR(20) CHECK (periode IN ('jour', 'semaine', 'mois', 'trimestre', 'annee')),
  date_debut      DATE,
  date_fin        DATE,
  groupeur_id     UUID REFERENCES groupeurs(id),    -- null = tous les groupeurs
  commentaire     TEXT,
  statut_lecture  VARCHAR(20) DEFAULT 'non_lu'
                  CHECK (statut_lecture IN ('non_lu', 'lu')),
  soumis_a        UUID REFERENCES users(id),        -- directeur
  created_at      TIMESTAMP DEFAULT NOW()
);

-- ══════════════════════════════════════════
-- JOURNAL D'AUDIT
-- ══════════════════════════════════════════
CREATE TABLE groupeur_audit_log (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  acteur_id    UUID REFERENCES users(id),
  acteur_role  VARCHAR(50),
  action       VARCHAR(80) NOT NULL,
  -- ex: 'CREER_GROUPEUR', 'MODIFIER_STATUT', 'SUPPRIMER_DEVIS', 'CONSULTER_FACTURE'
  entite       VARCHAR(50),           -- 'groupeur', 'devis', 'expedition', 'facture', 'document'
  entite_id    UUID,
  detail       JSONB,                 -- données avant/après pour les modifications
  ip_address   VARCHAR(45),
  created_at   TIMESTAMP DEFAULT NOW()
);
```

---

## 2. BACKEND NESTJS

### 2.1 Structure du module

```
src/groupeurs/
├── groupeurs.module.ts
├── controllers/
│   ├── groupeurs-admin.controller.ts      ← superviseure + assistante_dg + directeur
│   └── groupeurs-espace.controller.ts     ← le groupeur lui-même (son espace)
├── services/
│   ├── groupeurs.service.ts               ← CRUD groupeurs
│   ├── devis.service.ts
│   ├── expeditions.service.ts
│   ├── factures.service.ts
│   ├── documents.service.ts
│   └── rapports-groupeur.service.ts
├── dto/
│   ├── create-groupeur.dto.ts
│   ├── update-groupeur.dto.ts
│   ├── create-devis.dto.ts
│   ├── create-expedition.dto.ts
│   ├── create-facture.dto.ts
│   └── soumettre-rapport.dto.ts
└── guards/
    └── groupeur-owner.guard.ts            ← vérifie que le groupeur accède à SES données
```

### 2.2 Enum des rôles — ajouter une ligne

```typescript
// Dans ton fichier roles.enum.ts existant — ajouter UNE ligne uniquement
export enum Role {
  // ... rôles existants ...
  GROUPEUR_GROSSISTE = 'groupeur_grossiste', // ← ajouter
}
```

### 2.3 Controller Administration (superviseure + assistante_dg + directeur)

```typescript
// src/groupeurs/controllers/groupeurs-admin.controller.ts
import { Controller, Get, Post, Put, Delete, Body, Param, Query, UseGuards, Req } from '@nestjs/common';
import { JwtAuthGuard } from '../../auth/guards/jwt-auth.guard';    // chemin existant
import { RolesGuard } from '../../auth/guards/roles.guard';          // chemin existant
import { Roles } from '../../auth/decorators/roles.decorator';       // chemin existant

@Controller('groupeurs/admin')
@UseGuards(JwtAuthGuard, RolesGuard)
@Roles('superviseure_regionale', 'assistante_dg', 'directeur')
export class GroupeursAdminController {

  // ── GESTION DES COMPTES GROUPEURS ──

  @Get()
  listerTousGroupeurs(@Query() filters: any) {
    // Filtres possibles : statut, type, corridor, ville
    return this.groupeursService.listerTous(filters);
  }

  @Get('stats')
  getStatsGlobales() {
    // KPIs : nb groupeurs actifs, total devis ce mois, total facturé, expéditions en cours
    return this.groupeursService.getStatsGlobales();
  }

  @Get(':id')
  getDetailGroupeur(@Param('id') id: string) {
    return this.groupeursService.getDetail(id);
  }

  @Get(':id/activite')
  getActiviteGroupeur(@Param('id') id: string, @Query() periode: any) {
    // Tout : devis, expéditions, factures, documents d'un groupeur spécifique
    return this.groupeursService.getActiviteComplete(id, periode);
  }

  // ── CRUD GROUPEURS (superviseure_regionale uniquement pour create/update/delete) ──

  @Post()
  @Roles('superviseure_regionale')   // restreint : assistante_dg et directeur en lecture seule
  creerGroupeur(@Body() dto: CreateGroupeurDto, @Req() req) {
    // Crée le compte groupeur + le user associé + notifie le directeur
    return this.groupeursService.creer(dto, req.user.id);
  }

  @Put(':id')
  @Roles('superviseure_regionale')
  modifierGroupeur(@Param('id') id: string, @Body() dto: UpdateGroupeurDto, @Req() req) {
    return this.groupeursService.modifier(id, dto, req.user.id);
  }

  @Put(':id/statut')
  @Roles('superviseure_regionale')
  changerStatut(@Param('id') id: string, @Body() body: { statut: string; motif: string }, @Req() req) {
    // Activer / Suspendre / Archiver un groupeur
    // Notifie le groupeur concerné + le directeur
    return this.groupeursService.changerStatut(id, body, req.user.id);
  }

  @Delete(':id')
  @Roles('superviseure_regionale')
  supprimerGroupeur(@Param('id') id: string, @Req() req) {
    // Suppression logique (archive) — jamais physique
    return this.groupeursService.archiver(id, req.user.id);
  }

  // ── SUPERVISION DEVIS ──

  @Get(':id/devis')
  getDevisGroupeur(@Param('id') id: string) {
    return this.devisService.getParGroupeur(id);
  }

  // ── SUPERVISION EXPÉDITIONS ──

  @Get(':id/expeditions')
  getExpeditionsGroupeur(@Param('id') id: string) {
    return this.expeditionsService.getParGroupeur(id);
  }

  // ── SUPERVISION FACTURES ──

  @Get(':id/factures')
  getFacturesGroupeur(@Param('id') id: string) {
    return this.facturesService.getParGroupeur(id);
  }

  // ── SUPERVISION DOCUMENTS ──

  @Get(':id/documents')
  getDocumentsGroupeur(@Param('id') id: string) {
    return this.documentsService.getParGroupeur(id);
  }

  // ── RAPPORTS ──

  @Post('rapports/soumettre')
  @Roles('superviseure_regionale')
  soumettreRapport(@Body() dto: SoumettreRapportDto, @Req() req) {
    // Génère + sauvegarde le rapport + notifie le directeur via le module notifications existant
    return this.rapportsService.soumettre(dto, req.user.id);
  }

  @Get('rapports/historique')
  getHistoriqueRapports() {
    return this.rapportsService.getHistorique();
  }

  // ── JOURNAL D'AUDIT ──

  @Get('audit/log')
  @Roles('directeur', 'assistante_dg')  // restreint : seulement direction
  getAuditLog(@Query() filters: any) {
    return this.groupeursService.getAuditLog(filters);
  }
}
```

### 2.4 Controller Espace Groupeur (le groupeur accède à SES données uniquement)

```typescript
// src/groupeurs/controllers/groupeurs-espace.controller.ts
@Controller('groupeurs/espace')
@UseGuards(JwtAuthGuard, RolesGuard, GroupeurOwnerGuard)
@Roles('groupeur_grossiste')
export class GroupeursEspaceController {

  // ── TABLEAU DE BORD PERSONNEL ──

  @Get('dashboard')
  getMonDashboard(@Req() req) {
    // KPIs personnels : devis en cours, expéditions actives, factures impayées
    return this.groupeursService.getDashboardGroupeur(req.user.groupeurId);
  }

  // ── MES DEVIS ──

  @Get('devis')
  getMesDevis(@Req() req) {
    return this.devisService.getParGroupeur(req.user.groupeurId);
  }

  @Post('devis')
  creerDevis(@Body() dto: CreateDevisDto, @Req() req) {
    return this.devisService.creer(dto, req.user.groupeurId);
  }

  @Put('devis/:id')
  modifierDevis(@Param('id') id: string, @Body() dto: any, @Req() req) {
    // Vérifie que ce devis appartient bien à ce groupeur
    return this.devisService.modifier(id, dto, req.user.groupeurId);
  }

  @Delete('devis/:id')
  supprimerDevis(@Param('id') id: string, @Req() req) {
    // Suppression uniquement si statut = 'brouillon'
    return this.devisService.supprimer(id, req.user.groupeurId);
  }

  // ── MES EXPÉDITIONS ──

  @Get('expeditions')
  getMesExpeditions(@Req() req) {
    return this.expeditionsService.getParGroupeur(req.user.groupeurId);
  }

  @Post('expeditions')
  creerExpedition(@Body() dto: CreateExpeditionDto, @Req() req) {
    return this.expeditionsService.creer(dto, req.user.groupeurId);
  }

  @Put('expeditions/:id/statut')
  mettreAJourStatutExpedition(@Param('id') id: string, @Body() body: any, @Req() req) {
    // Met à jour le statut + notifie automatiquement la superviseure_regionale
    return this.expeditionsService.mettreAJourStatut(id, body, req.user.groupeurId);
  }

  // ── MES FACTURES ──

  @Get('factures')
  getMesFactures(@Req() req) {
    return this.facturesService.getParGroupeur(req.user.groupeurId);
  }

  @Post('factures')
  creerFacture(@Body() dto: CreateFactureDto, @Req() req) {
    return this.facturesService.creer(dto, req.user.groupeurId);
  }

  @Put('factures/:id')
  modifierFacture(@Param('id') id: string, @Body() dto: any, @Req() req) {
    // Modification uniquement si statut_paiement = 'en_attente'
    return this.facturesService.modifier(id, dto, req.user.groupeurId);
  }

  // ── MES DOCUMENTS ──

  @Get('documents')
  getMesDocuments(@Req() req) {
    return this.documentsService.getParGroupeur(req.user.groupeurId);
  }

  @Post('documents/upload')
  uploadDocument(@Body() dto: any, @Req() req) {
    // Utilise ton système de stockage existant (S3 ou autre)
    // Après upload → notifie la superviseure_regionale
    return this.documentsService.upload(dto, req.user.groupeurId);
  }

  @Delete('documents/:id')
  supprimerDocument(@Param('id') id: string, @Req() req) {
    return this.documentsService.supprimer(id, req.user.groupeurId);
  }

  // ── MON PROFIL ──

  @Get('profil')
  getMonProfil(@Req() req) {
    return this.groupeursService.getProfil(req.user.groupeurId);
  }

  @Put('profil')
  modifierMonProfil(@Body() dto: any, @Req() req) {
    // Seuls les champs non-sensibles sont modifiables par le groupeur lui-même
    // (téléphone, email_contact, adresse) — pas le statut, code, etc.
    return this.groupeursService.modifierProfil(dto, req.user.groupeurId);
  }
}
```

### 2.5 Guard propriétaire — GroupeurOwnerGuard

```typescript
// src/groupeurs/guards/groupeur-owner.guard.ts
// Ce guard s'assure qu'un groupeur ne peut accéder qu'à SES propres données
import { Injectable, CanActivate, ExecutionContext, ForbiddenException } from '@nestjs/common';

@Injectable()
export class GroupeurOwnerGuard implements CanActivate {
  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest();
    const user = request.user;

    if (user.role !== 'groupeur_grossiste') return true; // les admins passent

    // Le user doit avoir un groupeurId lié à son compte
    if (!user.groupeurId) {
      throw new ForbiddenException('Compte groupeur non configuré');
    }

    return true;
    // La vérification par entité (devis, facture...) se fait dans chaque service
    // avec une clause WHERE groupeur_id = user.groupeurId
  }
}
```

### 2.6 Notifications à brancher sur le module existant

```typescript
// Dans chaque service, utilise ton NotificationsService existant.
// Voici les événements à notifier — adapte à l'interface de ton service :

// Lors de la CRÉATION d'un groupeur :
await this.notificationsService.envoyer({
  destinataires: [directeurId],
  titre: 'Nouveau groupeur créé',
  message: `${groupeur.raison_sociale} a été ajouté par la superviseure régionale`,
  type: 'info',
  lien: `/groupeurs/admin/${groupeur.id}`,
});

// Lors d'un CHANGEMENT DE STATUT (suspension, archivage) :
await this.notificationsService.envoyer({
  destinataires: [directeurId, groupeur.user_id],
  titre: `Statut groupeur modifié — ${groupeur.raison_sociale}`,
  message: `Nouveau statut : ${statut}. Motif : ${motif}`,
  type: statut === 'suspendu' ? 'warning' : 'info',
  lien: `/groupeurs/admin/${groupeur.id}`,
});

// Lors d'une MISE À JOUR DE STATUT EXPÉDITION par le groupeur :
await this.notificationsService.envoyer({
  destinataires: [superviseureRegionaleId],
  titre: `Expédition ${expedition.numero_expedition} mise à jour`,
  message: `Nouveau statut : ${nouveauStatut} — Groupeur : ${groupeur.raison_sociale}`,
  type: nouveauStatut === 'litige' ? 'danger' : 'info',
  lien: `/groupeurs/admin/${groupeur.id}/expeditions`,
});

// Lors d'un UPLOAD DE DOCUMENT par le groupeur :
await this.notificationsService.envoyer({
  destinataires: [superviseureRegionaleId],
  titre: 'Nouveau document déposé',
  message: `${groupeur.raison_sociale} a déposé un document : ${typeDocument}`,
  type: 'info',
  lien: `/groupeurs/admin/${groupeur.id}/documents`,
});

// Lors de la SOUMISSION D'UN RAPPORT au directeur :
await this.notificationsService.envoyer({
  destinataires: [directeurId],
  titre: 'Nouveau rapport groupeurs disponible',
  message: `La superviseure régionale a soumis un rapport : ${type} — ${periode}`,
  type: 'info',
  lien: `/rapports/groupeurs/${rapport.id}`,
});
```

### 2.7 Enregistrer le module

```typescript
// src/app.module.ts — ajouter dans imports :
import { GroupeursModule } from './groupeurs/groupeurs.module';

@Module({
  imports: [
    // ... modules existants ...
    GroupeursModule, // ← ajouter
  ],
})
```

---

## 3. FRONTEND NEXT.JS

### 3.1 Structure des pages et composants

```
src/
├── app/ (ou pages/)
│   ├── groupeurs/
│   │   ├── admin/
│   │   │   ├── page.tsx                    ← dashboard admin (superviseure/dg/directeur)
│   │   │   ├── [id]/
│   │   │   │   ├── page.tsx                ← détail d'un groupeur
│   │   │   │   ├── expeditions/page.tsx
│   │   │   │   ├── factures/page.tsx
│   │   │   │   └── documents/page.tsx
│   │   │   ├── nouveau/page.tsx            ← formulaire création groupeur
│   │   │   └── rapports/page.tsx
│   │   └── espace/
│   │       ├── page.tsx                    ← dashboard personnel du groupeur
│   │       ├── devis/page.tsx
│   │       ├── expeditions/page.tsx
│   │       ├── factures/page.tsx
│   │       ├── documents/page.tsx
│   │       └── profil/page.tsx
│
└── components/groupeurs/
    ├── admin/
    │   ├── GroupeursAdminDashboard.tsx
    │   ├── GroupeursList.tsx               ← tableau avec filtres
    │   ├── GroupeurDetail.tsx
    │   ├── GroupeurForm.tsx                ← création + modification
    │   ├── GroupeurStatutBadge.tsx
    │   ├── StatutsActions.tsx              ← suspendre / archiver
    │   └── RapportForm.tsx
    ├── espace/
    │   ├── GroupeurDashboard.tsx
    │   ├── DevisManager.tsx
    │   ├── ExpeditionsTracker.tsx
    │   ├── FacturesManager.tsx
    │   └── DocumentsVault.tsx
    └── shared/
        ├── StatutExpeditionBadge.tsx
        ├── StatutFactureBadge.tsx
        └── TypeDocumentBadge.tsx
```

### 3.2 Protection des routes selon le rôle

```typescript
// app/groupeurs/admin/page.tsx
import { redirect } from 'next/navigation';
import { getServerSession } from 'next-auth'; // adapte à ton système

export default async function GroupeursAdminPage() {
  const session = await getServerSession();
  const rolesAutorises = ['superviseure_regionale', 'assistante_dg', 'directeur'];

  if (!session || !rolesAutorises.includes(session.user.role)) {
    redirect('/unauthorized');
  }

  return <GroupeursAdminDashboard />;
}

// app/groupeurs/espace/page.tsx
export default async function GroupeursEspacePage() {
  const session = await getServerSession();

  if (!session || session.user.role !== 'groupeur_grossiste') {
    redirect('/unauthorized');
  }

  return <GroupeurDashboard />;
}
```

### 3.3 Middleware — ajouter les nouvelles routes

```typescript
// middleware.ts existant — ajouter dans ta logique de rôles :
const roleRoutes = {
  // ... routes existantes ...
  '/groupeurs/admin':  ['superviseure_regionale', 'assistante_dg', 'directeur'],
  '/groupeurs/espace': ['groupeur_grossiste'],
};
```

### 3.4 Redirection post-login — ajouter le cas groupeur

```typescript
// Dans ta logique de redirection après authentification :
const redirectParRole = {
  // ... cas existants ...
  groupeur_grossiste: '/groupeurs/espace', // ← ajouter
};
```

### 3.5 Hook API centralisé

```typescript
// src/components/groupeurs/hooks/useGroupeurs.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from '@/lib/axios'; // ton instance existante

// ── ADMIN ──
export const useGroupeursList = (filters = {}) =>
  useQuery({ queryKey: ['groupeurs', filters],
    queryFn: () => axios.get('/groupeurs/admin', { params: filters }).then(r => r.data) });

export const useGroupeurDetail = (id: string) =>
  useQuery({ queryKey: ['groupeur', id],
    queryFn: () => axios.get(`/groupeurs/admin/${id}`).then(r => r.data), enabled: !!id });

export const useGroupeursStats = () =>
  useQuery({ queryKey: ['groupeurs', 'stats'],
    queryFn: () => axios.get('/groupeurs/admin/stats').then(r => r.data) });

export const useCreerGroupeur = () => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data) => axios.post('/groupeurs/admin', data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['groupeurs'] }),
  });
};

export const useModifierGroupeur = (id: string) => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data) => axios.put(`/groupeurs/admin/${id}`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['groupeur', id] }),
  });
};

export const useChanterStatut = (id: string) => {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data) => axios.put(`/groupeurs/admin/${id}/statut`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['groupeurs'] }),
  });
};

// ── ESPACE GROUPEUR ──
export const useMonDashboard = () =>
  useQuery({ queryKey: ['mon-dashboard'],
    queryFn: () => axios.get('/groupeurs/espace/dashboard').then(r => r.data) });

export const useMesDevis = () =>
  useQuery({ queryKey: ['mes-devis'],
    queryFn: () => axios.get('/groupeurs/espace/devis').then(r => r.data) });

export const useMesExpeditions = () =>
  useQuery({ queryKey: ['mes-expeditions'],
    queryFn: () => axios.get('/groupeurs/espace/expeditions').then(r => r.data) });

export const useMesFactures = () =>
  useQuery({ queryKey: ['mes-factures'],
    queryFn: () => axios.get('/groupeurs/espace/factures').then(r => r.data) });

export const useMesDocuments = () =>
  useQuery({ queryKey: ['mes-documents'],
    queryFn: () => axios.get('/groupeurs/espace/documents').then(r => r.data) });
```

---

## 4. MATRICE DES PERMISSIONS COMPLÈTE

> Implémenter cette matrice dans chaque service via des vérifications de rôle.

| Action | groupeur_grossiste | superviseure_regionale | assistante_dg | directeur |
|---|---|---|---|---|
| Voir liste groupeurs | ❌ | ✅ | ✅ | ✅ |
| Créer un groupeur | ❌ | ✅ | ❌ | ❌ |
| Modifier un groupeur | ❌ | ✅ | ❌ | ❌ |
| Suspendre / Archiver | ❌ | ✅ | ❌ | ❌ |
| Voir SES devis | ✅ | ✅ | ✅ | ✅ |
| Créer / modifier devis | ✅ (siens) | ❌ | ❌ | ❌ |
| Voir SES expéditions | ✅ | ✅ | ✅ | ✅ |
| Mettre à jour statut expéd. | ✅ (siennes) | ❌ | ❌ | ❌ |
| Voir SES factures | ✅ | ✅ | ✅ | ✅ |
| Créer / modifier facture | ✅ (siennes) | ❌ | ❌ | ❌ |
| Uploader documents | ✅ (siens) | ❌ | ❌ | ❌ |
| Voir tous les documents | ❌ | ✅ | ✅ | ✅ |
| Générer rapport | ❌ | ✅ | ❌ | ❌ |
| Recevoir rapports | ❌ | ❌ | ✅ | ✅ |
| Voir journal d'audit | ❌ | ❌ | ✅ | ✅ |
| Modifier son profil | ✅ (limité) | ❌ | ❌ | ❌ |

---

## 5. ORDRE D'EXÉCUTION

```
1.  Exécuter les migrations SQL (7 nouvelles tables)
2.  Ajouter GROUPEUR_GROSSISTE dans l'enum des rôles NestJS
3.  Créer le GroupeurOwnerGuard
4.  Créer les DTOs
5.  Créer GroupeursService (CRUD de base)
6.  Créer DevisService, ExpeditionsService, FacturesService, DocumentsService
7.  Créer RapportsGroupeurService (branché sur NotificationsService existant)
8.  Créer les deux controllers (admin + espace)
9.  Créer GroupeursModule et l'enregistrer dans app.module.ts
10. Tester toutes les routes API avec Postman (token superviseure + token groupeur)
11. Ajouter les routes protégées Next.js (/groupeurs/admin et /groupeurs/espace)
12. Ajouter les cas dans middleware.ts et la redirection post-login
13. Créer les composants frontend (admin dashboard en premier, puis espace groupeur)
14. Connecter chaque composant aux hooks API
15. Tester le flux complet : créer groupeur → il se connecte → crée un devis → superviseure le voit
```

---

## 6. CE QU'IL NE FAUT PAS TOUCHER

```
❌ Tables existantes (users, roles, et toutes les autres)
❌ Guards et middleware existants — juste les étendre
❌ Module de notifications existant — juste l'appeler
❌ Système d'authentification et JWT
❌ Composants et pages des autres rôles
❌ Aucune suppression physique de données — toujours archivage logique
```

---

*Prompt d'intégration — Module Groupeurs/Grossistes · LBP Logistique*
*Stack : Next.js + NestJS + PostgreSQL · Avril 2026*
