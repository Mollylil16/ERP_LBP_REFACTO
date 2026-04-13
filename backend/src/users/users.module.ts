import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { UsersService } from './users.service';
import { UsersController } from './users.controller';
import { User } from './entities/user.entity';
import { Agence } from '../agences/entities/agence.entity';
import { ActionSpeciale } from '../permissions/entities/action-speciale.entity';
import { UserActionSpeciale } from './entities/user-action-speciale.entity';
import { WhatsappService } from '../notifications/whatsapp.service';
import { NotificationModule } from '../notifications/notification.module';
import { RolesModule } from '../roles/roles.module';
import { Role } from '../roles/entities/role.entity';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      User,
      Agence,
      ActionSpeciale,
      UserActionSpeciale,
      Role,
    ]),
    NotificationModule,
    RolesModule,
  ],
  providers: [UsersService, WhatsappService],
  controllers: [UsersController],
  exports: [UsersService],
})
export class UsersModule {}
