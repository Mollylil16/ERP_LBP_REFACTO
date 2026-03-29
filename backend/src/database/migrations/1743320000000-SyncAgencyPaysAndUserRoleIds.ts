import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * 1) Pays des agences : valeurs cohérentes pour les préfixes de référence colis (LB-CI / LB-SEN / LB-FR).
 * 2) lbp_users.role_id : aligné sur lbp_roles.code = colonne enum role (source métier).
 * 3) Rôle ADMIN dans lbp_roles si absent (comptes techniques, même matrice que DIRECTEUR via seed).
 */
export class SyncAgencyPaysAndUserRoleIds1743320000000
  implements MigrationInterface
{
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      INSERT INTO "lbp_roles" ("code", "libelle", "description", "niveau_hierarchique", "est_actif", "created_at", "updated_at")
      SELECT
        'ADMIN',
        'Super administrateur (technique)',
        'Compte technique — accès total (distinct du Directeur général métier)',
        1,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (SELECT 1 FROM "lbp_roles" WHERE "code" = 'ADMIN')
    `);

    await queryRunner.query(`
      INSERT INTO "lbp_role_permissions" ("role_id", "permission_id", "created_at")
      SELECT a.id, rp."permission_id", NOW()
      FROM "lbp_roles" a
      CROSS JOIN "lbp_roles" d
      INNER JOIN "lbp_role_permissions" rp ON rp."role_id" = d.id
      WHERE a.code = 'ADMIN' AND d.code = 'DIRECTEUR'
        AND NOT EXISTS (
          SELECT 1 FROM "lbp_role_permissions" x
          WHERE x."role_id" = a.id AND x."permission_id" = rp."permission_id"
        )
    `);

    await queryRunner.query(`
      UPDATE "agences"
      SET "pays" = 'Côte d''Ivoire'
      WHERE "code" ~ '^(CI-|DG)' OR UPPER(TRIM("code")) = 'LBP'
    `);

    await queryRunner.query(`
      UPDATE "agences"
      SET "pays" = 'FRANCE'
      WHERE "code" ~ '^FR-' OR "code" IN ('PAR')
    `);

    await queryRunner.query(`
      UPDATE "agences"
      SET "pays" = 'SENEGAL'
      WHERE "code" ~ '^SN-' OR "code" IN ('DKR')
    `);

    await queryRunner.query(`
      UPDATE "lbp_users" AS u
      SET "role_id" = r."id"
      FROM "lbp_roles" AS r
      WHERE r."code" = u."role"::text
    `);
  }

  public async down(): Promise<void> {
    /* Données métier : pas de retour automatique fiable */
  }
}
