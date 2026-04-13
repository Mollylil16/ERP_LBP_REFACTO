import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Alignement catalogue sur la fiche tarifaire GROUPAGE / COLIS RAPIDE (SP-CI, export).
 * Voir seed-produits-catalogue.ts pour le détail des montants.
 */
export class AlignCatalogueTarifsFiche1744680000000 implements MigrationInterface {
  name = 'AlignCatalogueTarifsFiche1744680000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 1100, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'HUILE_ET_KARITE'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 1000
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 1850, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DIVERS'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 1780
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 1850, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DIVERS'
        AND nom ILIKE '%INDIGENAT%LIQUIDE%'
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET
        prix_unitaire = 2100,
        prix_forfaitaire = 5000,
        poids_min = 0,
        poids_max = 2,
        updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DIVERS'
        AND LOWER(TRIM(nom)) = 'attote'
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET
        prix_unitaire = 1600,
        prix_forfaitaire = 3500,
        poids_min = 0,
        poids_max = 2,
        updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DIVERS'
        AND LOWER(TRIM(nom)) = 'huile rouge'
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET
        prix_unitaire = 1850,
        prix_forfaitaire = 5000,
        poids_min = 0,
        poids_max = 2,
        updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DIVERS'
        AND LOWER(TRIM(nom)) = 'bouillons'
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 1850, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DIVERS'
        AND LOWER(TRIM(nom)) = 'cube maggi'
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET
        prix_unitaire = 5850,
        prix_forfaitaire = 8000,
        poids_min = 0,
        poids_max = 1,
        updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'COLIS_RAPIDE_EXPORT'
        AND LOWER(nom) LIKE '%cosmetique%'
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET
        poids_min = 0,
        poids_max = 1,
        updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'COLIS_RAPIDE_EXPORT'
        AND (
          prix_forfaitaire::numeric = 7500
          OR nom ILIKE '%POISSON%FUME%CREVETTE%ESCARGOT%POULET%'
        )
    `);
  }

  public async down(_queryRunner: QueryRunner): Promise<void> {
    /* Rollback non trivial — restaurer depuis une sauvegarde si besoin */
  }
}
