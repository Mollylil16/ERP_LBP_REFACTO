import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Rôle métier CAISSIER_AGENCE : enum PostgreSQL sur lbp_users.role + ligne lbp_roles.
 * Les permissions sont gérées par seed (`npm run seed`) ou assignées via /roles/assign-permissions.
 */
export class AddCaissierAgenceUserRole1748800000000
  implements MigrationInterface
{
  name = 'AddCaissierAgenceUserRole1748800000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_users_role_enum'
            AND e.enumlabel = 'CAISSIER_AGENCE'
        ) THEN
          ALTER TYPE "public"."lbp_users_role_enum" ADD VALUE 'CAISSIER_AGENCE';
        END IF;
      END
      $migration$;
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_roles" ("code", "libelle", "description", "niveau_hierarchique", "est_actif", "created_at", "updated_at")
      SELECT
        'CAISSIER_AGENCE',
        'Caissier d''agence',
        'Caissier rattaché à une agence : encaissements et caisse sur son agence uniquement',
        6,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (SELECT 1 FROM "lbp_roles" WHERE "code" = 'CAISSIER_AGENCE')
    `);
  }

  public async down(): Promise<void> {
    /* Pas de suppression sûre de la valeur d'enum PostgreSQL */
  }
}

