import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { RolesModule } from '../roles/roles.module';

// Entities — module 1 (personnel / congés)
import { RhEmploye } from './entities/rh-employe.entity';
import { RhContrat } from './entities/rh-contrat.entity';
import { RhCongeType } from './entities/rh-conge-type.entity';
import { RhCongeRequest } from './entities/rh-conge-request.entity';
import { RhCongeBalance } from './entities/rh-conge-balance.entity';

// Entities — module 3 (paie)
import { RhConfigPaie } from './entities/rh-config-paie.entity';
import { RhPaieRun } from './entities/rh-paie-run.entity';
import { RhPaieLigne } from './entities/rh-paie-ligne.entity';
import { RhAvanceSalaire } from './entities/rh-avance-salaire.entity';

// Entities — module 4 (présences)
import { RhPresence } from './entities/rh-presence.entity';
import { RhJourFerie } from './entities/rh-jour-ferie.entity';

// Entities — module 5 (évaluations)
import { RhEvaluation } from './entities/rh-evaluation.entity';

// Entities — module 6 (recrutement)
import { RhPoste, RhCandidature } from './entities/rh-recrutement.entity';

// Entities — module 7 (formation)
import { RhFormation, RhInscriptionFormation } from './entities/rh-formation.entity';

// Entities — module V3 (documents, historique, onboarding)
import { RhDocument } from './entities/rh-document.entity';
import { RhHistoriquePoste } from './entities/rh-historique-poste.entity';
import { RhOnboardingChecklist } from './entities/rh-onboarding.entity';

// Services
import { RhService } from './rh.service';
import { PaieService } from './paie.service';
import { PresenceService } from './presence.service';
import { EvaluationService } from './evaluation.service';
import { RecrutementService } from './recrutement.service';
import { FormationService } from './formation.service';
import { RapportsService } from './rapports.service';
import { PdfService } from './pdf.service';
import { DocumentRhService } from './document-rh.service';
import { RhEncryptionService } from './encryption.service';

// Controllers
import { RhController } from './rh.controller';
import { PaieController } from './paie.controller';
import { PresenceController } from './presence.controller';
import { EvaluationController } from './evaluation.controller';
import { RecrutementController } from './recrutement.controller';
import { FormationController } from './formation.controller';
import { RapportsController } from './rapports.controller';
import { DocumentRhController } from './document-rh.controller';

@Module({
  imports: [
    RolesModule,
    TypeOrmModule.forFeature([
      // Personnel & congés
      RhEmploye,
      RhContrat,
      RhCongeType,
      RhCongeRequest,
      RhCongeBalance,
      // Paie
      RhConfigPaie,
      RhPaieRun,
      RhPaieLigne,
      RhAvanceSalaire,
      // Présences
      RhPresence,
      RhJourFerie,
      // Évaluations
      RhEvaluation,
      // Recrutement
      RhPoste,
      RhCandidature,
      // Formation
      RhFormation,
      RhInscriptionFormation,
      // Documents, historique, onboarding
      RhDocument,
      RhHistoriquePoste,
      RhOnboardingChecklist,
    ]),
  ],
  controllers: [
    RhController,
    PaieController,
    PresenceController,
    EvaluationController,
    RecrutementController,
    FormationController,
    RapportsController,
    DocumentRhController,
  ],
  providers: [
    RhService,
    PaieService,
    PresenceService,
    EvaluationService,
    RecrutementService,
    FormationService,
    RapportsService,
    PdfService,
    DocumentRhService,
    RhEncryptionService,
  ],
  exports: [RhService, PdfService, RhEncryptionService],
})
export class RhModule {}
