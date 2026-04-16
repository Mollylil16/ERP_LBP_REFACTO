import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Rôle métier ASSISTANT_DG : enum PostgreSQL sur lbp_users.role + ligne lbp_roles.
 * Les permissions sont gérées par seed (`npm run seed`) ou assignées via /roles/assign-permissions.
 */
export class AddAssistantDgUserRole1744700100000 implements MigrationInterface {
  name = 'AddAssistantDgUserRole1744700100000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_users_role_enum'
            AND e.enumlabel = 'ASSISTANT_DG'
        ) THEN
          ALTER TYPE "public"."lbp_users_role_enum" ADD VALUE 'ASSISTANT_DG';
        END IF;
      END
      $migration$;
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_roles" ("code", "libelle", "description", "niveau_hierarchique", "est_actif", "created_at", "updated_at")
      SELECT
        'ASSISTANT_DG',
        'Assistant DG',
        'Accès très large proche DG, sans administration sensible (config, gestion utilisateurs/permissions, suppressions critiques).',
        2,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (SELECT 1 FROM "lbp_roles" WHERE "code" = 'ASSISTANT_DG')
    `);
  }

  public async down(): Promise<void> {
    /* Pas de suppression sûre de la valeur d'enum PostgreSQL */
  }
}

