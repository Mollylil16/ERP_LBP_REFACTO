import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddAgenceToNotifications1762700000000 implements MigrationInterface {
  name = 'AddAgenceToNotifications1762700000000';

  async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "lbp_notifications"
        ADD COLUMN IF NOT EXISTS "id_agence" INTEGER;
    `);
  }

  async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "lbp_notifications" DROP COLUMN IF EXISTS "id_agence";
    `);
  }
}
