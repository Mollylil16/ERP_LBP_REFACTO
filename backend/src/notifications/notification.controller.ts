import { Controller, Get, Param, Patch, Post, UseGuards } from '@nestjs/common';
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
  getUnread() {
    return this.notificationService.getUnread();
  }

  @Patch(':id/read')
  markAsRead(@Param('id') id: string) {
    return this.notificationService.markAsRead(+id);
  }

  @Post('read-all')
  markAllAsRead() {
    return this.notificationService.markAllAsRead();
  }
}
