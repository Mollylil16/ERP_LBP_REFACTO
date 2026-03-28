import {
  Controller,
  Get,
  Query,
  UseGuards,
  ParseIntPipe,
} from '@nestjs/common';
import { PaiementsService } from './paiements.service';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';

@Controller('paiements')
@UseGuards(JwtAuthGuard, PermissionsGuard)
export class PaiementsHistoryController {
  constructor(private readonly paiementsService: PaiementsService) {}

  @Get('history/daily')
  @RequirePermission('paiements.read')
  async getDailyPaymentHistory(
    @Query('date') date?: string,
    @Query('agenceId', new ParseIntPipe({ optional: true })) agenceId?: number,
  ) {
    const targetDate = date ? new Date(date) : new Date();
    return this.paiementsService.getDailyPaymentHistory(targetDate, agenceId);
  }

  @Get('history/unpaid')
  @RequirePermission('paiements.read')
  async getUnpaidInvoices(
    @Query('agenceId', new ParseIntPipe({ optional: true })) agenceId?: number,
    @Query('status') status?: 'all' | 'overdue' | 'pending',
  ) {
    return this.paiementsService.getUnpaidInvoices(agenceId, status);
  }

  @Get('reconciliation/agency')
  @RequirePermission('paiements.read')
  async getAgencyReconciliation(
    @Query('date') date?: string,
    @Query('agenceId', new ParseIntPipe({ optional: true })) agenceId?: number,
  ) {
    const targetDate = date ? new Date(date) : new Date();
    return this.paiementsService.getAgencyReconciliation(targetDate, agenceId);
  }
}
