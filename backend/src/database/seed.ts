import { DataSource } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { config } from 'dotenv';
import { runAllSeeders } from './seeders';

// Load environment variables
config();

async function bootstrap() {
  const configService = new ConfigService();

  const dataSource = new DataSource({
    type: 'postgres',
    host: configService.get<string>('DB_HOST'),
    port: configService.get<number>('DB_PORT', 5432),
    username: configService.get<string>('DB_USERNAME'),
    password: configService.get<string>('DB_PASSWORD'),
    database: configService.get<string>('DB_DATABASE'),
    entities: [__dirname + '/../**/*.entity{.ts,.js}'],
    synchronize: false,
  });

  try {
    await dataSource.initialize();
    console.log('✅ Connexion à la base de données établie\n');

    await runAllSeeders(dataSource);

    await dataSource.destroy();
    console.log('\n✅ Connexion à la base de données fermée');
    process.exit(0);
  } catch (error) {
    console.error('❌ Erreur:', error);
    await dataSource.destroy();
    process.exit(1);
  }
}

bootstrap();
