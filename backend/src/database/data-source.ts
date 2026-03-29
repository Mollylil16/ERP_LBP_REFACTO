import 'reflect-metadata';
import { config } from 'dotenv';
import * as path from 'node:path';
import * as pg from 'pg';
import { DataSource } from 'typeorm';

/** Chargé depuis le dossier `backend/` (où se trouve `.env`). */
config({ path: path.resolve(process.cwd(), '.env') });

/**
 * Source TypeORM pour la CLI : `npm run migration:show` / `npm run migration:run`.
 * N’exécute que les migrations **pas encore enregistrées** dans la table `migrations`.
 */
export default new DataSource({
  type: 'postgres',
  driver: pg,
  host: process.env.DB_HOST,
  port: parseInt(process.env.DB_PORT ?? '5432', 10),
  username: process.env.DB_USERNAME,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_DATABASE,
  entities: [path.join(__dirname, '..', '**', '*.entity.js')],
  migrations: [path.join(__dirname, 'migrations', '*.js')],
  synchronize: false,
  logging: process.env.TYPEORM_CLI_LOGGING === 'true',
});
