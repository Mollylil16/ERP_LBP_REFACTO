import { DataSource } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { config } from 'dotenv';
import { seedAgences } from './seeds/seed-agences';
import { Agence } from '../agences/entities/agence.entity';
import { User } from '../users/entities/user.entity';
import { Colis } from '../colis/entities/colis.entity';

// Load environment variables
config();

const configService = new ConfigService();

const AppDataSource = new DataSource({
    type: 'postgres',
    host: configService.get<string>('DB_HOST'),
    port: parseInt(configService.get<string>('DB_PORT') || '5432'),
    username: configService.get<string>('DB_USERNAME'),
    password: configService.get<string>('DB_PASSWORD'),
    database: configService.get<string>('DB_DATABASE'),
    entities: [__dirname + '/../**/*.entity{.ts,.js}'],
    synchronize: false,
});

async function runAgencesSeeds() {
    try {
        console.log('🔄 Initializing database connection...');
        await AppDataSource.initialize();
        console.log('✅ Database connected');

        console.log('🌱 Running agences seeds...');
        await seedAgences(AppDataSource);

        console.log('✅ All agences seeds completed successfully');
        await AppDataSource.destroy();
        process.exit(0);
    } catch (error) {
        if (AppDataSource.isInitialized) {
            await AppDataSource.destroy();
        }
        process.exit(1);
    }
}

runAgencesSeeds();
