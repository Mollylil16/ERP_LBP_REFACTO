import { Injectable, Logger } from '@nestjs/common';

@Injectable()
export class WhatsappService {
    private readonly logger = new Logger(WhatsappService.name);

    /**
     * Simule l'envoi d'un message WhatsApp ou SMS
     */
    async sendMessage(phone: string, message: string): Promise<boolean> {
        this.logger.log(`[NOTIF] Envoi message à ${phone}: ${message}`);
        // Ici on intégrerait une API comme Twilio, Infobip ou une passerelle WhatsApp locale
        return true;
    }

    async notifyDeparture(clientName: string, phone: string, refColis: string, origin: string, destination: string) {
        const msg = `Bonjour ${clientName}, votre colis ${refColis} a bien quitté ${origin} à destination de ${destination}. Vous recevrez une notification dès son arrivée. Merci de votre confiance ! - LBP Logistics`;
        return this.sendMessage(phone, msg);
    }

    async notifyArrivalAtHub(clientName: string, phone: string, refColis: string, location: string, address?: string) {
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
}
