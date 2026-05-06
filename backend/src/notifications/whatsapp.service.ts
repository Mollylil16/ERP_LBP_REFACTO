import { Injectable, Logger } from '@nestjs/common';
import { MessagingGatewayService } from './messaging-gateway.service';

@Injectable()
export class WhatsappService {
  private readonly logger = new Logger(WhatsappService.name);
  constructor(private readonly messagingGateway: MessagingGatewayService) {}

  /**
   * Simule l'envoi d'un message WhatsApp ou SMS
   */
  async sendMessage(phone: string, message: string): Promise<boolean> {
    const ok = await this.messagingGateway.sendWhatsapp(phone, message);
    if (!ok) {
      this.logger.warn(`[NOTIF] Échec envoi WhatsApp vers ${phone}`);
    } else {
      this.logger.log(`[NOTIF] WhatsApp envoyé vers ${phone}`);
    }
    return ok;
  }

  async notifyDeparture(
    clientName: string,
    phone: string,
    refColis: string,
    origin: string,
    destination: string,
  ) {
    const msg = `Bonjour ${clientName}, votre colis ${refColis} a bien quitté ${origin} à destination de ${destination}. Vous recevrez une notification dès son arrivée. Merci de votre confiance ! - LBP Logistics`;
    return this.sendMessage(phone, msg);
  }

  async notifyArrivalAtHub(
    clientName: string,
    phone: string,
    refColis: string,
    location: string,
    address?: string,
  ) {
    const addressPart = address ? ` (${address})` : '';
    const msg = `Bonjour ${clientName}, bonne nouvelle ! Votre colis ${refColis} est bien arrivé à notre agence de ${location}${addressPart}. Nos équipes préparent sa mise à disposition. - LBP Logistics`;
    return this.sendMessage(phone, msg);
  }

  async notifyColisCreated(
    clientName: string,
    phone: string,
    refColis: string,
    destination: string,
  ) {
    const msg =
      `Votre colis ${refColis} à destination de ${destination} a bien été enregistré chez LBP Logistics. ` +
      `Vous recevrez un SMS dans un bref delais pour le recuperer. ` +
      `Merci de votre confiance ! - LBP Logistics https://labelleporte.net`;
    return this.sendMessage(phone, msg);
  }

  /** Notification automatique au destinataire quand le statut du colis change */
  async notifyStatusChange(
    destinataireName: string,
    phone: string,
    refColis: string,
    oldStatut: string,
    newStatut: string,
    agenceNom?: string,
  ) {
    const statusLabels: Record<string, string> = {
      EMBALLE: 'emballé et prêt à expédier',
      EXPEDIE: 'expédié',
      EN_TRANSIT: 'en transit',
      REC_BOBIGNY: `reçu à notre agence${agenceNom ? ' de ' + agenceNom : ''}`,
      EN_LIVRAISON: 'en cours de livraison',
      LIVRE: 'livré',
      RETOUR: 'en retour',
    };
    const label = statusLabels[newStatut] || newStatut;
    const msg = `Bonjour ${destinataireName}, votre colis ${refColis} est maintenant ${label}. Suivez votre colis sur https://labelleporte.net - LBP Logistics`;
    return this.sendMessage(phone, msg);
  }

  /** Reçu automatique WhatsApp après paiement validé */
  async sendPaymentReceipt(
    clientName: string,
    phone: string,
    numFacture: string,
    montant: number,
    modePaiement: string,
    refColis?: string,
  ) {
    const msg = [
      `✅ REÇU DE PAIEMENT — LBP Logistics`,
      ``,
      `Client : ${clientName}`,
      `Facture : ${numFacture}`,
      refColis ? `Colis : ${refColis}` : null,
      `Montant payé : ${montant.toLocaleString('fr-FR')} FCFA`,
      `Mode : ${modePaiement}`,
      `Date : ${new Date().toLocaleDateString('fr-FR')}`,
      ``,
      `Merci de votre confiance !`,
      `LBP Logistics — https://labelleporte.net`,
    ]
      .filter(Boolean)
      .join('\n');
    return this.sendMessage(phone, msg);
  }

  /** Relance client pour facture impayée */
  async sendUnpaidReminder(
    clientName: string,
    phone: string,
    numFacture: string,
    montantRestant: number,
    joursRetard: number,
  ) {
    const msg = [
      `Bonjour ${clientName},`,
      ``,
      `Nous vous rappelons que votre facture ${numFacture} présente un solde impayé de ${montantRestant.toLocaleString('fr-FR')} FCFA (${joursRetard} jours).`,
      ``,
      `Merci de régulariser votre situation au plus vite auprès de votre agence LBP.`,
      ``,
      `Cordialement,`,
      `LBP Logistics`,
    ].join('\n');
    return this.sendMessage(phone, msg);
  }
}

