import { Injectable, Logger } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { SupervisionService } from './supervision.service';

@Injectable()
export class SupervisionCronService {
  private readonly logger = new Logger(SupervisionCronService.name);

  constructor(private readonly supervisionService: SupervisionService) {}

  /**
   * Tâche automatisée s'exécutant tous les soirs à 23h00.
   * Analyse les données de la journée pour trouver des doublons, trous de séquence et incohérences.
   */
  @Cron('0 23 * * *')
  async handleDailyAnomalyDetection() {
    this.logger.log('Démarrage de la détection automatique des anomalies de supervision...');
    try {
      // Prend la journée en cours
      const today = new Date().toISOString().slice(0, 10);
      
      const result = await this.supervisionService.autoSignalerAnomalies(today, today);
      
      this.logger.log(
        `Analyse terminée : ${result.signalements_crees} nouveau(x) signalement(s) critique(s) généré(s).`,
      );
    } catch (error) {
      this.logger.error(
        `Échec de la tâche Cron de supervision : ${error instanceof Error ? error.message : String(error)}`,
      );
    }
  }
}
