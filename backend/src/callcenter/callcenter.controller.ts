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
  ) {
    return this.callCenterService.listConversations({ page, limit, channel });
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
