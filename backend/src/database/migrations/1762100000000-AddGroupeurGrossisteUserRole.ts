import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Ajout rôle legacy `GROUPEUR_GROSSISTE` dans l'enum PostgreSQL lbp_users_role_enum.
 * Permet un affichage "propre" dans /auth/me et l'écosystème legacy.
 */
export class AddGroupeurGrossisteUserRole1762100000000 implements MigrationInterface {
  name = 'AddGroupeurGrossisteUserRole1762100000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_users_role_enum'
            AND e.enumlabel = 'GROUPEUR_GROSSISTE'
        ) THEN
          ALTER TYPE "public"."lbp_users_role_enum" ADD VALUE 'GROUPEUR_GROSSISTE';
        END IF;
      END
      $migration$;
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_roles" ("code", "libelle", "description", "niveau_hierarchique", "est_actif", "created_at", "updated_at")
      SELECT
        'GROUPEUR_GROSSISTE',
        'Groupeur / Grossiste',
        'Espace dédié : devis, expéditions, factures, documents (périmètre propre)',
        6,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (SELECT 1 FROM "lbp_roles" WHERE "code" = 'GROUPEUR_GROSSISTE')
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    // PostgreSQL ne supporte pas le DROP VALUE d'un enum facilement : no-op
  }
}
