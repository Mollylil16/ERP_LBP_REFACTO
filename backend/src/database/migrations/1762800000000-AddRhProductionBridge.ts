import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddRhProductionBridge1762800000000 implements MigrationInterface {
  name = 'AddRhProductionBridge1762800000000';

  async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "rh_paie_lignes"
        ADD COLUMN IF NOT EXISTS "prime_performance" NUMERIC(15,2) NOT NULL DEFAULT 0;
    `);
    await queryRunner.query(`
      ALTER TABLE "rh_evaluations"
        ADD COLUMN IF NOT EXISTS "metriques_auto" JSONB;
    `);
  }

  async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`ALTER TABLE "rh_paie_lignes" DROP COLUMN IF EXISTS "prime_performance";`);
    await queryRunner.query(`ALTER TABLE "rh_evaluations" DROP COLUMN IF EXISTS "metriques_auto";`);
  }
}
