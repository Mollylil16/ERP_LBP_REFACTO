import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * En production, `lbp_permissions.module` est typé en enum PostgreSQL (`lbp_permissions_module_enum`).
 * Le seeder insère des modules `SUPERVISION` et `GROUPEURS` : il faut donc étendre cet enum.
 */
export class AddPermissionsModulesSupervisionAndGroupeurs1762200000000
  implements MigrationInterface
{
  name = 'AddPermissionsModulesSupervisionAndGroupeurs1762200000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    // Ajout valeur SUPERVISION
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF EXISTS (
          SELECT 1 FROM pg_catalog.pg_type t
          WHERE t.typname = 'lbp_permissions_module_enum'
        ) AND NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_permissions_module_enum'
            AND e.enumlabel = 'SUPERVISION'
        ) THEN
          ALTER TYPE "public"."lbp_permissions_module_enum" ADD VALUE 'SUPERVISION';
        END IF;
      END
      $migration$;
    `);

    // Ajout valeur GROUPEURS
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF EXISTS (
          SELECT 1 FROM pg_catalog.pg_type t
          WHERE t.typname = 'lbp_permissions_module_enum'
        ) AND NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_permissions_module_enum'
            AND e.enumlabel = 'GROUPEURS'
        ) THEN
          ALTER TYPE "public"."lbp_permissions_module_enum" ADD VALUE 'GROUPEURS';
        END IF;
      END
      $migration$;
    `);
  }

  public async down(_queryRunner: QueryRunner): Promise<void> {
    // PostgreSQL ne supporte pas le DROP VALUE d'un enum facilement : no-op
  }
}

