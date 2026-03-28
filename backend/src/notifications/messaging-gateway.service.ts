import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

type Channel = 'sms' | 'whatsapp';

@Injectable()
export class MessagingGatewayService {
  private readonly logger = new Logger(MessagingGatewayService.name);

  constructor(private readonly configService: ConfigService) {}

  getFrom(channel: Channel): string | undefined {
    return channel === 'sms'
      ? this.configService.get<string>('MESSAGING_FROM_SMS')
      : this.configService.get<string>('MESSAGING_FROM_WHATSAPP');
  }

  /**
   * Envoi via une passerelle HTTP "générique" (fournisseur à préciser).
   * Tant que la passerelle n'est pas configurée, on ne fait que logger.
   *
   * Variables d'env attendues:
   * - MESSAGING_BASE_URL: ex https://gateway.example.com
   * - MESSAGING_API_KEY: token/apiKey
   * - MESSAGING_FROM_SMS: ex 0503467979
   * - MESSAGING_FROM_WHATSAPP: ex 0503497979
   *
   * Contrat HTTP (à adapter au fournisseur):
   * POST {BASE_URL}/messages
   * { to, from, channel, message }
   * Header: Authorization: Bearer {API_KEY}
   */
  async send(channel: Channel, to: string, message: string): Promise<boolean> {
    const baseUrl = this.configService.get<string>('MESSAGING_BASE_URL');
    const apiKey = this.configService.get<string>('MESSAGING_API_KEY');

    const from = this.getFrom(channel);

    if (!baseUrl || !apiKey) {
      this.logger.log(
        `[NOTIF:${channel}] (simulation) to=${to} from=${from ?? '-'} msg="${message}"`,
      );
      return true;
    }

    const url = `${baseUrl.replace(/\/+$/, '')}/messages`;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${apiKey}`,
        },
        body: JSON.stringify({
          to,
          from,
          channel,
          message,
        }),
      });

      if (!res.ok) {
        const body = await res.text().catch(() => '');
        this.logger.warn(
          `[NOTIF:${channel}] gateway error ${res.status} ${res.statusText} body=${body}`,
        );
        return false;
      }

      return true;
    } catch (e: any) {
      this.logger.error(
        `[NOTIF:${channel}] gateway exception: ${e?.message ?? e}`,
        e?.stack,
      );
      return false;
    }
  }

  async sendSms(to: string, message: string) {
    return this.send('sms', to, message);
  }

  async sendWhatsapp(to: string, message: string) {
    return this.send('whatsapp', to, message);
  }
}
