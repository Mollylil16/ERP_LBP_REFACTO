import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddColisRapideImportCategory1762000001000
  implements MigrationInterface
{
  name = 'AddColisRapideImportCategory1762000001000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TYPE "lbp_produits_catalogue_categorie_enum"
      ADD VALUE IF NOT EXISTS 'COLIS_RAPIDE_IMPORT'
    `);
  }

  public async down(_queryRunner: QueryRunner): Promise<void> {
    // PostgreSQL ne supporte pas la suppression d'une valeur d'enum
    // Recréer le type manuellement si rollback nécessaire
  }
}
