import {
  BadRequestException,
  Injectable,
  Logger,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Brackets, In, Repository } from 'typeorm';
import { CallCenterConversation } from './entities/callcenter-conversation.entity';
import { CallCenterMessage } from './entities/callcenter-message.entity';
import { Client } from '../clients/entities/client.entity';
import { Facture } from '../factures/entities/facture.entity';
import { Litige } from '../litiges/entities/litige.entity';
import { Colis } from '../colis/entities/colis.entity';

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
    @InjectRepository(Colis)
    private readonly colisRepo: Repository<Colis>,
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
    q?: string;
    unreadOnly?: boolean;
    dateFrom?: string;
    dateTo?: string;
    readStatus?: 'all' | 'unread' | 'read';
    caseStatus?: 'all' | 'open' | 'in_progress' | 'resolved';
    agenceId?: number;
  }) {
    const page = Math.max(1, Number(params.page || 1));
    const limit = Math.min(100, Math.max(1, Number(params.limit || 20)));
    const qb = this.convRepo
      .createQueryBuilder('c')
      .leftJoin(Client, 'cl', 'cl.id = c.client_id');

    if (params.channel) {
      qb.andWhere('c.channel = :channel', { channel: params.channel });
    }
    if (params.unreadOnly) {
      qb.andWhere('c.unread_count > 0');
    }
    if (params.readStatus === 'unread') {
      qb.andWhere('c.unread_count > 0');
    } else if (params.readStatus === 'read') {
      qb.andWhere('c.unread_count = 0');
    }
    if (params.caseStatus && params.caseStatus !== 'all') {
      qb.andWhere('c.case_status = :cs', { cs: params.caseStatus });
    }
    if (params.agenceId && Number.isFinite(Number(params.agenceId))) {
      qb.andWhere(
        `EXISTS (
          SELECT 1 FROM lbp_colis co
          WHERE co.id_client = c.client_id AND co.id_agence = :ccAgence
        )`,
        { ccAgence: params.agenceId },
      );
    }
    if (params.dateFrom) {
      const d = new Date(params.dateFrom);
      if (!Number.isNaN(d.getTime())) {
        qb.andWhere('c.last_message_at >= :df', { df: d.toISOString() });
      }
    }
    if (params.dateTo) {
      const d = new Date(params.dateTo);
      if (!Number.isNaN(d.getTime())) {
        qb.andWhere('c.last_message_at <= :dt', { dt: d.toISOString() });
      }
    }

    const rawQ = (params.q || '').trim();
    if (rawQ) {
      const phoneQ = normalizePhone(rawQ);
      qb.andWhere(
        new Brackets((w) => {
          w.where('c.customer_phone ILIKE :q', { q: `%${rawQ}%` })
            .orWhere('c.callcenter_phone ILIKE :q', { q: `%${rawQ}%` })
            .orWhere('cl.nom_exp ILIKE :q', { q: `%${rawQ}%` });
          if (phoneQ) {
            w.orWhere('c.customer_phone ILIKE :pq', { pq: `%${phoneQ}%` }).orWhere(
              'cl.tel_exp ILIKE :pq',
              { pq: `%${phoneQ}%` },
            );
          }
          const asInt = Number(rawQ);
          if (Number.isFinite(asInt)) {
            w.orWhere('c.client_id = :cid', { cid: asInt });
          }
          // Recherche dans le fil de messages (réf colis, num facture, etc.)
          w.orWhere(
            `EXISTS (
              SELECT 1 FROM callcenter_messages m
              WHERE m.conversation_id = c.id AND m.message ILIKE :mq
            )`,
            { mq: `%${rawQ}%` },
          );
        }),
      );
    }

    const total = await qb.clone().getCount();
    const entities = await qb
      .clone()
      .orderBy('c.last_message_at', 'DESC')
      .addOrderBy('c.updated_at', 'DESC')
      .skip((page - 1) * limit)
      .take(limit)
      .getMany();

    const ids = [
      ...new Set(
        entities
          .map((e) => e.client_id)
          .filter((x): x is number => typeof x === 'number' && x > 0),
      ),
    ];
    const nomByClientId = new Map<number, string>();
    if (ids.length) {
      const clients = await this.clientRepo.find({
        where: { id: In(ids) } as any,
        select: ['id', 'nom_exp'] as any,
      });
      for (const cl of clients) {
        nomByClientId.set(cl.id, cl.nom_exp);
      }
    }

    const data = entities.map((e) => ({
      ...e,
      client_nom: e.client_id ? nomByClientId.get(e.client_id) ?? null : null,
    }));

    return { data, total, page, limit, totalPages: Math.ceil(total / limit) };
  }

  async getConversationSummary(conversationId: number): Promise<any> {
    const conv = await this.convRepo.findOne({ where: { id: conversationId } });
    if (!conv) return { conversation_id: conversationId, found: false };

    // Client
    const clientId =
      conv.client_id ?? (await this.guessClientIdByPhone(conv.customer_phone));
    const client = clientId
      ? await this.clientRepo.findOne({ where: { id: clientId } as any })
      : null;

    // Dernier colis du client (si possible)
    const lastColis = clientId
      ? await this.colisRepo
          .createQueryBuilder('colis')
          .select(['colis.id', 'colis.ref_colis', 'colis.date_envoi', 'colis.forme_envoi', 'colis.trafic_envoi'])
          .where('colis.id_client = :cid', { cid: clientId })
          .orderBy('colis.created_at', 'DESC')
          .limit(1)
          .getOne()
      : null;

    const lastFacture = conv.last_facture_id
      ? await this.factureRepo.findOne({
          where: { id: conv.last_facture_id } as any,
          select: ['id', 'num_facture', 'etat', 'payment_status', 'montant_ttc', 'devise'] as any,
        })
      : null;

    const lastLitige = conv.last_litige_id
      ? await this.litigeRepo.findOne({
          where: { id: conv.last_litige_id } as any,
          select: ['id', 'num_litige', 'statut', 'created_at'] as any,
        })
      : null;

    return {
      conversation_id: conv.id,
      found: true,
      case_status: (conv as any).case_status ?? 'open',
      channel: conv.channel,
      customer_phone: conv.customer_phone,
      callcenter_phone: conv.callcenter_phone,
      unread_count: conv.unread_count,
      last_message_at: conv.last_message_at,
      client: client
        ? { id: client.id, nom_exp: client.nom_exp, tel_exp: client.tel_exp, email_exp: client.email_exp ?? null }
        : null,
      last_colis: lastColis
        ? {
            id: lastColis.id,
            ref_colis: (lastColis as any).ref_colis,
            date_envoi: (lastColis as any).date_envoi,
            forme_envoi: (lastColis as any).forme_envoi,
            trafic_envoi: (lastColis as any).trafic_envoi,
          }
        : null,
      last_facture: lastFacture ?? null,
      last_litige: lastLitige ?? null,
    };
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

  async setConversationCaseStatus(
    conversationId: number,
    caseStatus: string,
  ): Promise<CallCenterConversation> {
    const allowed = new Set(['open', 'in_progress', 'resolved']);
    const cs = String(caseStatus || '').trim();
    if (!allowed.has(cs)) {
      throw new BadRequestException(
        'case_status invalide (open | in_progress | resolved)',
      );
    }
    const conv = await this.convRepo.findOne({ where: { id: conversationId } });
    if (!conv) {
      throw new NotFoundException('Conversation introuvable');
    }
    await this.convRepo.update(conversationId, { case_status: cs } as any);
    return (await this.convRepo.findOne({ where: { id: conversationId } }))!;
  }
}
