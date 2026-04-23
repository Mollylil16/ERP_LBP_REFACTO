import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { NotificationModule } from '../notifications/notification.module';
import { UsersModule } from '../users/users.module';
import { RolesModule } from '../roles/roles.module';
import { GroupeursAdminController } from './controllers/groupeurs-admin.controller';
import { GroupeursEspaceController } from './controllers/groupeurs-espace.controller';
import { GroupeurOwnerGuard } from './guards/groupeur-owner.guard';
import { Groupeur } from './entities/groupeur.entity';
import { GroupeurDevis } from './entities/groupeur-devis.entity';
import { GroupeurExpedition } from './entities/groupeur-expedition.entity';
import { GroupeurFacture } from './entities/groupeur-facture.entity';
import { GroupeurDocument } from './entities/groupeur-document.entity';
import { GroupeurRapport } from './entities/groupeur-rapport.entity';
import { GroupeurAuditLog } from './entities/groupeur-audit-log.entity';
import { GroupeursService } from './services/groupeurs.service';
import { DevisService } from './services/devis.service';
import { ExpeditionsService } from './services/expeditions.service';
import { FacturesService } from './services/factures.service';
import { DocumentsService } from './services/documents.service';
import { RapportsGroupeurService } from './services/rapports-groupeur.service';
import { User } from '../users/entities/user.entity';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      Groupeur,
      GroupeurDevis,
      GroupeurExpedition,
      GroupeurFacture,
      GroupeurDocument,
      GroupeurRapport,
      GroupeurAuditLog,
      User,
    ]),
    NotificationModule,
    UsersModule,
    RolesModule,
  ],
  controllers: [GroupeursAdminController, GroupeursEspaceController],
  providers: [
    GroupeursService,
    DevisService,
    ExpeditionsService,
    FacturesService,
    DocumentsService,
    RapportsGroupeurService,
    GroupeurOwnerGuard,
  ],
  exports: [GroupeursService],
})
export class GroupeursModule {}
