import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddPointJournalierRapprochementFields1748802000000
  implements MigrationInterface
{
  name = 'AddPointJournalierRapprochementFields1748802000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "lbp_points_journaliers"
      ADD COLUMN IF NOT EXISTS "total_reel_caisse" numeric(12,2)
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_points_journaliers"
      ADD COLUMN IF NOT EXISTS "ecart_caisse" numeric(12,2)
    `);
    await queryRunner.query(`
      ALTER TABLE "lbp_points_journaliers"
      ADD COLUMN IF NOT EXISTS "details_rapprochement" jsonb
    `);
  }

  public async down(): Promise<void> {
    /* down non destructif */
  }
}

