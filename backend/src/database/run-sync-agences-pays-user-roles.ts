import { DataSource } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { config } from 'dotenv';
import { seedAgences } from './seeds/seed-agences';
import { seedRoles } from './seeders/roles.seeder';

config();

const configService = new ConfigService();

const AppDataSource = new DataSource({
  type: 'postgres',
  host: configService.get<string>('DB_HOST'),
  port: parseInt(configService.get<string>('DB_PORT') || '5432', 10),
  username: configService.get<string>('DB_USERNAME'),
  password: configService.get<string>('DB_PASSWORD'),
  database: configService.get<string>('DB_DATABASE'),
  entities: [__dirname + '/../**/*.entity{.ts,.js}'],
  synchronize: false,
});

async function main() {
  await AppDataSource.initialize();
  try {
    console.log('🌱 Mise à jour des agences (pays par code métier)...');
    await seedAgences(AppDataSource);

    console.log('🌱 Vérification des rôles (ex. ADMIN)...');
    await seedRoles(AppDataSource);

    console.log('🔗 Synchronisation lbp_users.role_id ← lbp_roles (code = role enum)...');
    await AppDataSource.query(`
      UPDATE "lbp_users" AS u
      SET "role_id" = r."id"
      FROM "lbp_roles" AS r
      WHERE r."code" = u."role"::text
    `);
    console.log('✅ Colonne role_id alignée sur le rôle enum de chaque utilisateur');

    const orphans = await AppDataSource.query(`
      SELECT u."id", u."username", u."role"::text AS role_enum, u."role_id"
      FROM "lbp_users" u
      LEFT JOIN "lbp_roles" r ON r."id" = u."role_id"
      WHERE u."role_id" IS NULL OR r."id" IS NULL
    `);
    if (orphans?.length) {
      console.warn(
        '⚠️ Comptes sans role_id valide (rôle enum inconnu dans lbp_roles ?) :',
        orphans,
      );
    }
  } finally {
    await AppDataSource.destroy();
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
