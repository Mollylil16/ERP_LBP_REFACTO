import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { TrackerPosition } from './entities/tracker-position.entity';
import { TrackingGateway } from './tracking.gateway';
import { TrackingService } from './tracking.service';
import { TrackingController } from './tracking.controller';

@Module({
    imports: [TypeOrmModule.forFeature([TrackerPosition])],
    providers: [TrackingGateway, TrackingService],
    controllers: [TrackingController],
    exports: [TrackingService, TrackingGateway],
})
export class TrackingModule { }
