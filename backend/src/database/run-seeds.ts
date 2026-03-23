import { DataSource } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { config } from 'dotenv';
import { ProduitCatalogue } from '../produits-catalogue/entities/produit-catalogue.entity';
import { Tarif } from '../tarifs/entities/tarif.entity';
import { seedProduitsCatalogue } from './seeds/seed-produits-catalogue';
import { seedTarifs } from './seeds/seed-tarifs';

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

async function runSeeds() {
    try {
        console.log('🔄 Initializing database connection...');
        await AppDataSource.initialize();
        console.log('✅ Database connected');

        // FORCER LA SUPPRESSION ET RECRÉATION
        console.log('🗑️  Deleting existing products and tariffs (CASCADE)...');
        await AppDataSource.query('TRUNCATE TABLE "lbp_produits_catalogue" CASCADE');
        await AppDataSource.query('TRUNCATE TABLE "lbp_tarifs" CASCADE');

        console.log('✅ Existing data deleted');

        console.log('🌱 Running seeds...');
        await seedProduitsCatalogue(AppDataSource);
        await seedTarifs(AppDataSource);

        console.log('✅ All seeds completed successfully');
        await AppDataSource.destroy();
        process.exit(0);
    } catch (error) {
        console.error('❌ Error during seeding:', error);
        if (AppDataSource.isInitialized) {
            await AppDataSource.destroy();
        }
        process.exit(1);
    }
}

runSeeds();
