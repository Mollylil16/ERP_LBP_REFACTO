import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CaisseService } from './caisse.service';
import { CaisseController } from './caisse.controller';
import { Caisse } from './entities/caisse.entity';
import { MouvementCaisse } from './entities/mouvement-caisse.entity';
import { CaisseSession } from './entities/caisse-session.entity';
import { CaisseMouvementWorkflow } from './entities/caisse-mouvement-workflow.entity';
import { CaisseAuditLog } from './entities/caisse-audit-log.entity';
import { Agence } from '../agences/entities/agence.entity';
import { Paiement } from '../paiements/entities/paiement.entity';
import { Facture } from '../factures/entities/facture.entity';
import { RolesModule } from '../roles/roles.module';
import { AuditModule } from '../audit/audit.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      Caisse,
      MouvementCaisse,
      CaisseSession,
      CaisseMouvementWorkflow,
      CaisseAuditLog,
      Agence,
      Paiement,
      Facture,
    ]),
    RolesModule,
    AuditModule,
  ],
  providers: [CaisseService],
  controllers: [CaisseController],
  exports: [CaisseService],
})
export class CaisseModule {}
