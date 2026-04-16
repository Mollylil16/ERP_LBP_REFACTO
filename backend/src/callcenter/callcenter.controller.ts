import {
  Body,
  Controller,
  Get,
  Param,
  ParseIntPipe,
  Patch,
  Post,
  Query,
  Headers,
  UnauthorizedException,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiOperation,
  ApiResponse,
  ApiTags,
} from '@nestjs/swagger';
import { CallCenterService } from './callcenter.service';
import { InboundWebhookDto } from './dto/inbound-webhook.dto';
import { ConfigService } from '@nestjs/config';
import { MessagingGatewayService } from '../notifications/messaging-gateway.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@ApiTags('callcenter')
@Controller('callcenter')
export class CallCenterController {
  constructor(
    private readonly callCenterService: CallCenterService,
    private readonly messagingGateway: MessagingGatewayService,
    private readonly configService: ConfigService,
  ) {}

  @Get('conversations')
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('callcenter.inbox')
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Lister les conversations (boîte de réception)' })
  async listConversations(
    @Query('page') page?: number,
    @Query('limit') limit?: number,
    @Query('channel') channel?: 'sms' | 'whatsapp',
    @Query('q') q?: string,
    @Query('unread_only') unread_only?: string,
    @Query('date_from') date_from?: string,
    @Query('date_to') date_to?: string,
    @Query('read_status') read_status?: 'all' | 'unread' | 'read',
    @Query('case_status') case_status?: 'all' | 'open' | 'in_progress' | 'resolved',
    @Query('agence_id') agence_id?: string,
  ) {
    const unreadOnly =
      unread_only === '1' ||
      unread_only === 'true' ||
      unread_only === 'yes' ||
      unread_only === 'on';
    const aid = agence_id ? Number.parseInt(agence_id, 10) : NaN;
    return this.callCenterService.listConversations({
      page,
      limit,
      channel,
      q,
      unreadOnly,
      dateFrom: date_from,
      dateTo: date_to,
      readStatus: read_status,
      caseStatus: case_status,
      agenceId: Number.isFinite(aid) ? aid : undefined,
    });
  }

  @Get('conversations/:id/summary')
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('callcenter.inbox')
  @ApiBearerAuth()
  @ApiOperation({ summary: "Résumé dossier (client, dernier colis, liens facture/litige)" })
  async getSummary(@Param('id', ParseIntPipe) id: number) {
    return this.callCenterService.getConversationSummary(id);
  }

  @Get('conversations/:id/messages')
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('callcenter.inbox')
  @ApiBearerAuth()
  @ApiOperation({ summary: "Lister les messages d'une conversation" })
  async getMessages(
    @Param('id', ParseIntPipe) id: number,
    @Query('limit') limit?: number,
    @Query('offset') offset?: number,
  ) {
    return this.callCenterService.getConversationMessages(id, {
      limit,
      offset,
    });
  }

  @Patch('conversations/:id/read')
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('callcenter.inbox')
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Marquer conversation comme lue' })
  async markRead(@Param('id', ParseIntPipe) id: number) {
    return this.callCenterService.markConversationRead(id);
  }

  @Patch('conversations/:id/case-status')
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('callcenter.inbox')
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Statut dossier relationnel (open / in_progress / resolved)' })
  async setCaseStatus(
    @Param('id', ParseIntPipe) id: number,
    @Body() body: { case_status: string },
  ) {
    return this.callCenterService.setConversationCaseStatus(
      id,
      body?.case_status ?? '',
    );
  }

  @Post('send')
  @UseGuards(JwtAuthGuard, PermissionsGuard)
  @RequirePermission('callcenter.inbox')
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Envoyer un message (SMS/WhatsApp) et journaliser' })
  @ApiResponse({ status: 200 })
  async send(
    @Body() body: { channel: 'sms' | 'whatsapp'; to: string; message: string },
  ) {
    const channel = body.channel;
    const to = body.to;
    const message = body.message;

    const from = this.messagingGateway.getFrom(channel) || 'CALLCENTER';
    const ok =
      channel === 'sms'
        ? await this.messagingGateway.sendSms(to, message)
        : await this.messagingGateway.sendWhatsapp(to, message);

    await this.callCenterService
      .recordOutbound({
        channel,
        from,
        to,
        message,
        provider: 'gateway',
      })
      .catch(() => undefined);

    return { ok };
  }

  /**
   * Webhook INBOUND (public) — à configurer chez le fournisseur international.
   * IMPORTANT: protéger via header secret simple.
   */
  @Post('/webhooks/inbound')
  @ApiOperation({ summary: 'Webhook entrant (SMS/WhatsApp)' })
  async inbound(
    @Body() dto: InboundWebhookDto,
    @Headers('x-webhook-secret') secret?: string,
  ) {
    const expected = this.configService.get<string>(
      'CALLCENTER_WEBHOOK_SECRET',
    );
    if (expected && secret !== expected) {
      throw new UnauthorizedException('Webhook secret invalide');
    }
    return this.callCenterService.recordInbound({
      ...dto,
      raw_payload: dto,
    });
  }
}
