import {
  Controller,
  Get,
  Param,
  Patch,
  Post,
  UseGuards,
  Request,
} from '@nestjs/common';
import { NotificationService } from './notification.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';

/**
 * Notifications cloche : tout utilisateur authentifié (tous rôles).
 * Ne pas exiger dashboard.view : certains rôles (ex. AGENT_GROUPAGE) n’ont pas le tableau de bord mais doivent voir les alertes.
 */
@Controller('notifications')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class NotificationController {
  constructor(private readonly notificationService: NotificationService) {}

  @Get('unread')
  getUnread(@Request() req: { user: { id: number; id_agence?: number | null } }) {
    return this.notificationService.getUnreadForUser(req.user.id, req.user.id_agence ?? null);
  }

  @Patch(':id/read')
  markAsRead(
    @Param('id') id: string,
    @Request() req: { user: { id: number } },
  ) {
    return this.notificationService.markAsReadForUser(+id, req.user.id);
  }

  @Post('read-all')
  markAllAsRead(@Request() req: { user: { id: number } }) {
    return this.notificationService.markAllAsReadForUser(req.user.id);
  }
}
