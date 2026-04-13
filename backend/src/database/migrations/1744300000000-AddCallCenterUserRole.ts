import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Rôle métier CALL_CENTER : enum PostgreSQL sur lbp_users.role + ligne lbp_roles + permissions boîte d'appel.
 */
export class AddCallCenterUserRole1744300000000 implements MigrationInterface {
  name = 'AddCallCenterUserRole1744300000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_enum e
          INNER JOIN pg_catalog.pg_type t ON e.enumtypid = t.oid
          WHERE t.typname = 'lbp_users_role_enum'
            AND e.enumlabel = 'CALL_CENTER'
        ) THEN
          ALTER TYPE "public"."lbp_users_role_enum" ADD VALUE 'CALL_CENTER';
        END IF;
      END
      $migration$;
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_roles" ("code", "libelle", "description", "niveau_hierarchique", "est_actif", "created_at", "updated_at")
      SELECT
        'CALL_CENTER',
        'Call center',
        'Service client : boîte SMS/WhatsApp, litiges basiques, consultation clients',
        5,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (SELECT 1 FROM "lbp_roles" WHERE "code" = 'CALL_CENTER')
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_role_permissions" ("role_id", "permission_id", "created_at")
      SELECT r.id, p.id, NOW()
      FROM "lbp_roles" r
      INNER JOIN "lbp_permissions" p ON p.code IN (
        'callcenter.inbox',
        'structures.clients.read',
        'structures.agences.read',
        'litiges.view',
        'litiges.create'
      )
      WHERE r.code = 'CALL_CENTER'
        AND NOT EXISTS (
          SELECT 1 FROM "lbp_role_permissions" x
          WHERE x.role_id = r.id AND x.permission_id = p.id
        )
    `);
  }

  public async down(): Promise<void> {
    /* Pas de suppression sûre de la valeur d'enum PostgreSQL */
  }
}
