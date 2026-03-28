import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Permission DB pour mapper vers config.view (écran paramètres généraux).
 * Idempotent : ignore si la ligne existe déjà.
 */
export class AddParametresApplicationPermission1743300000000
  implements MigrationInterface
{
  name = 'AddParametresApplicationPermission1743300000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      INSERT INTO "lbp_permissions" ("module", "fonctionnalite", "action", "code", "description")
      SELECT 'STRUCTURES', 'parametres_application', 'READ', 'structures.parametres_application.read',
             'Consulter les paramètres généraux (société, branding)'
      WHERE NOT EXISTS (
        SELECT 1 FROM "lbp_permissions" WHERE "code" = 'structures.parametres_application.read'
      );
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_role_permissions" ("role_id", "permission_id")
      SELECT r.id, p.id
      FROM "lbp_roles" r
      CROSS JOIN "lbp_permissions" p
      WHERE r.code = 'MANAGER'
        AND p.code = 'structures.parametres_application.read'
        AND NOT EXISTS (
          SELECT 1 FROM "lbp_role_permissions" rp
          WHERE rp.role_id = r.id AND rp.permission_id = p.id
        );
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DELETE FROM "lbp_role_permissions"
      WHERE "permission_id" IN (
        SELECT "id" FROM "lbp_permissions" WHERE "code" = 'structures.parametres_application.read'
      );
    `);
    await queryRunner.query(`
      DELETE FROM "lbp_permissions" WHERE "code" = 'structures.parametres_application.read';
    `);
  }
}
