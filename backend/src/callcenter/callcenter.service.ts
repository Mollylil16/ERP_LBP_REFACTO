import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { CallCenterConversation } from './entities/callcenter-conversation.entity';
import { CallCenterMessage } from './entities/callcenter-message.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Litige } from '../litiges/entities/litige.entity';

function normalizePhone(input: string): string {
  return (input || '').replace(/[^\d+]/g, '').trim();
}

@Injectable()
export class CallCenterService {
  private readonly logger = new Logger(CallCenterService.name);

  constructor(
    @InjectRepository(CallCenterConversation)
    private readonly convRepo: Repository<CallCenterConversation>,
    @InjectRepository(CallCenterMessage)
    private readonly msgRepo: Repository<CallCenterMessage>,
    @InjectRepository(Client)
    private readonly clientRepo: Repository<Client>,
    @InjectRepository(Facture)
    private readonly factureRepo: Repository<Facture>,
    @InjectRepository(Litige)
    private readonly litigeRepo: Repository<Litige>,
  ) {}

  async upsertConversation(params: {
    channel: 'sms' | 'whatsapp';
    customer_phone: string;
    callcenter_phone?: string | null;
    client_id?: number | null;
    last_facture_id?: number | null;
    last_litige_id?: number | null;
  }) {
    const channel = params.channel;
    const customer_phone = normalizePhone(params.customer_phone);
    const callcenter_phone = params.callcenter_phone
      ? normalizePhone(params.callcenter_phone)
      : null;

    let conv = await this.convRepo.findOne({
      where: { channel, customer_phone },
    });
    if (!conv) {
      conv = this.convRepo.create({
        channel,
        customer_phone,
        callcenter_phone,
        client_id: params.client_id ?? null,
        last_facture_id: params.last_facture_id ?? null,
        last_litige_id: params.last_litige_id ?? null,
        unread_count: 0,
        last_message_at: null,
      });
      conv = await this.convRepo.save(conv);
      return conv;
    }

    // Update non-null hints
    if (callcenter_phone) conv.callcenter_phone = callcenter_phone;
    if (params.client_id) conv.client_id = params.client_id;
    if (params.last_facture_id) conv.last_facture_id = params.last_facture_id;
    if (params.last_litige_id) conv.last_litige_id = params.last_litige_id;
    return await this.convRepo.save(conv);
  }

  private async guessClientIdByPhone(phone: string): Promise<number | null> {
    const p = normalizePhone(phone);
    if (!p) return null;
    const client = await this.clientRepo.findOne({
      where: { tel_exp: p } as any,
    });
    return client?.id ?? null;
  }

  async recordInbound(dto: {
    channel: 'sms' | 'whatsapp';
    from: string;
    to: string;
    message: string;
    provider?: string;
    provider_message_id?: string;
    raw_payload?: any;
  }) {
    const from = normalizePhone(dto.from);
    const to = normalizePhone(dto.to);
    const clientId = await this.guessClientIdByPhone(from);

    const conv = await this.upsertConversation({
      channel: dto.channel,
      customer_phone: from,
      callcenter_phone: to,
      client_id: clientId,
    });

    const msg = await this.msgRepo.save(
      this.msgRepo.create({
        conversation_id: conv.id,
        channel: dto.channel,
        direction: 'in',
        from_phone: from,
        to_phone: to,
        message: dto.message,
        provider: dto.provider ?? null,
        provider_message_id: dto.provider_message_id ?? null,
        raw_payload: dto.raw_payload ?? null,
      }),
    );

    // Try to link to litige/facture by keyword in message (non-bloquant)
    try {
      const litigeMatch = dto.message.match(/\bLIT-\d{4}-\d{3}\b/i);
      if (litigeMatch) {
        const lit = await this.litigeRepo.findOne({
          where: { num_litige: litigeMatch[0].toUpperCase() } as any,
        });
        if (lit) {
          await this.convRepo.update(conv.id, { last_litige_id: lit.id });
        }
      }

      const factureMatch = dto.message.match(/\bFCO-\d{4}-\d{3}\b/i);
      if (factureMatch) {
        const fac = await this.factureRepo.findOne({
          where: { num_facture: factureMatch[0].toUpperCase() } as any,
        });
        if (fac) {
          await this.convRepo.update(conv.id, { last_facture_id: fac.id });
        }
      }
    } catch (e: any) {
      this.logger.warn(`Linking hints failed: ${e?.message ?? e}`);
    }

    await this.convRepo.update(conv.id, {
      unread_count: () => `"unread_count" + 1`,
      last_message_at: new Date(),
      callcenter_phone: conv.callcenter_phone ?? to,
    } as any);

    return { conversation_id: conv.id, message_id: msg.id };
  }

  async recordOutbound(params: {
    channel: 'sms' | 'whatsapp';
    from: string;
    to: string;
    message: string;
    provider?: string;
    provider_message_id?: string;
    raw_payload?: any;
    last_facture_id?: number | null;
  }) {
    const from = normalizePhone(params.from);
    const to = normalizePhone(params.to);
    const clientId = await this.guessClientIdByPhone(to);

    const conv = await this.upsertConversation({
      channel: params.channel,
      customer_phone: to,
      callcenter_phone: from,
      client_id: clientId,
      last_facture_id: params.last_facture_id ?? null,
    });

    const msg = await this.msgRepo.save(
      this.msgRepo.create({
        conversation_id: conv.id,
        channel: params.channel,
        direction: 'out',
        from_phone: from,
        to_phone: to,
        message: params.message,
        provider: params.provider ?? null,
        provider_message_id: params.provider_message_id ?? null,
        raw_payload: params.raw_payload ?? null,
      }),
    );

    await this.convRepo.update(conv.id, {
      last_message_at: new Date(),
      callcenter_phone: conv.callcenter_phone ?? from,
      // outbound does not increase unread
    } as any);

    return { conversation_id: conv.id, message_id: msg.id };
  }

  async listConversations(params: {
    page?: number;
    limit?: number;
    channel?: 'sms' | 'whatsapp';
  }) {
    const page = Math.max(1, Number(params.page || 1));
    const limit = Math.min(100, Math.max(1, Number(params.limit || 20)));
    const where: any = {};
    if (params.channel) where.channel = params.channel;

    const [data, total] = await this.convRepo.findAndCount({
      where,
      order: { last_message_at: 'DESC', updated_at: 'DESC' } as any,
      skip: (page - 1) * limit,
      take: limit,
    });

    return { data, total, page, limit, totalPages: Math.ceil(total / limit) };
  }

  async getConversationMessages(
    conversationId: number,
    params: { limit?: number; offset?: number },
  ) {
    const take = Math.min(200, Math.max(1, Number(params.limit || 50)));
    const skip = Math.max(0, Number(params.offset || 0));

    const [data, total] = await this.msgRepo.findAndCount({
      where: { conversation_id: conversationId } as any,
      order: { created_at: 'ASC' } as any,
      skip,
      take,
    });
    return { data, total, offset: skip, limit: take };
  }

  async markConversationRead(conversationId: number) {
    await this.convRepo.update(conversationId, { unread_count: 0 } as any);
    return { ok: true };
  }
}
