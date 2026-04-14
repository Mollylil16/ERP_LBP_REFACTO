import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Ajoute le chef d'agence (user) sur la table agences.
 * Permet de restreindre la sélection d'agence pour le rôle CHEF_AGENCE.
 */
export class AddChefAgenceToAgences1744675600000 implements MigrationInterface {
  name = 'AddChefAgenceToAgences1744675600000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE "agences"
      ADD COLUMN IF NOT EXISTS "id_chef_agence" integer NULL
    `);

    await queryRunner.query(`
      DO $migration$
      BEGIN
        IF NOT EXISTS (
          SELECT 1
          FROM pg_constraint
          WHERE conname = 'FK_agences_id_chef_agence_users'
        ) THEN
          ALTER TABLE "agences"
          ADD CONSTRAINT "FK_agences_id_chef_agence_users"
          FOREIGN KEY ("id_chef_agence") REFERENCES "lbp_users"("id")
          ON DELETE SET NULL;
        END IF;
      END
      $migration$;
    `);
  }

  public async down(): Promise<void> {
    /* down non destructif : on ne supprime pas la colonne automatiquement */
  }
}

