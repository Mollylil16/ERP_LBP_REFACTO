import { Module } from '@nestjs/common';
import { WorkflowService } from './workflow.service';
import { ColisModule } from '../colis/colis.module';
import { FacturesModule } from '../factures/factures.module';
import { NotificationModule } from '../notifications/notification.module';

@Module({
  imports: [ColisModule, FacturesModule, NotificationModule],
  providers: [WorkflowService],
  exports: [WorkflowService],
})
export class WorkflowModule {}
