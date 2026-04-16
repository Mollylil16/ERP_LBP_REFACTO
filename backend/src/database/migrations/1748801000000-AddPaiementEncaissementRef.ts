import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddPaiementEncaissementRef1748801000000
  implements MigrationInterface
{
  name = 'AddPaiementEncaissementRef1748801000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "lbp_paiements"
      ADD COLUMN IF NOT EXISTS "encaissement_ref" character varying(80)
    `);
    await queryRunner.query(`
      CREATE INDEX IF NOT EXISTS "idx_lbp_paiements_encaissement_ref"
      ON "lbp_paiements" ("encaissement_ref")
    `);
  }

  public async down(): Promise<void> {
    /* down non destructif */
  }
}

