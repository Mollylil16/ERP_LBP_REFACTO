import { MigrationInterface, QueryRunner } from 'typeorm';

export class MakeTypeEmballageArray1744100000000
  implements MigrationInterface
{
  name = 'MakeTypeEmballageArray1744100000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // Convert string -> jsonb array, preserve existing values
    await queryRunner.query(`
      ALTER TABLE "lbp_marchandises"
      ALTER COLUMN "type_emballage" TYPE jsonb
      USING (
        CASE
          WHEN "type_emballage" IS NULL OR "type_emballage" = '' THEN NULL
          WHEN jsonb_typeof("type_emballage"::jsonb) = 'array' THEN "type_emballage"::jsonb
          ELSE jsonb_build_array("type_emballage")
        END
      );
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    // Best-effort: take first element of array -> text
    await queryRunner.query(`
      ALTER TABLE "lbp_marchandises"
      ALTER COLUMN "type_emballage" TYPE varchar(255)
      USING (
        CASE
          WHEN "type_emballage" IS NULL THEN NULL
          WHEN jsonb_typeof("type_emballage") = 'array' THEN ("type_emballage"->>0)
          ELSE ("type_emballage"::text)
        END
      );
    `);
  }
}

