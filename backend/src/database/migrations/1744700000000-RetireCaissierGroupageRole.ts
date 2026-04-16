import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Retrait du rôle CAISSIER_GROUPAGE : réaffectation vers CAISSIER,
 * suppression des permissions et de la ligne lbp_roles.
 * La valeur d’enum PostgreSQL peut rester sur les vieilles versions (pas de DROP VALUE fiable avant PG 15).
 */
export class RetireCaissierGroupageRole1744700000000
  implements MigrationInterface
{
  name = 'RetireCaissierGroupageRole1744700000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE "lbp_users" AS u
      SET
        "role" = 'CAISSIER'::"public"."lbp_users_role_enum",
        "role_id" = r_c."id"
      FROM "lbp_roles" AS r_c
      WHERE r_c."code" = 'CAISSIER'
        AND u."role"::text = 'CAISSIER_GROUPAGE'
    `);

    await queryRunner.query(`
      DELETE FROM "lbp_role_permissions" AS rp
      USING "lbp_roles" AS r
      WHERE rp."role_id" = r."id" AND r."code" = 'CAISSIER_GROUPAGE'
    `);

    await queryRunner.query(`
      DELETE FROM "lbp_roles" WHERE "code" = 'CAISSIER_GROUPAGE'
    `);
  }

  public async down(): Promise<void> {
    /* Réintroduction du rôle : données métier non rejouables automatiquement */
  }
}
