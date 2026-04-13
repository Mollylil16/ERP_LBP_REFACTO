import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Prix Denrées alimentaires (tarif Groupage kg) : 900 → 885 FCFA/kg si présent.
 * Ajout produit canonique + colonne unite (catalogue).
 */
export class DenreesAlimentaires885AndProduitUnite1744650000000
  implements MigrationInterface
{
  name = 'DenreesAlimentaires885AndProduitUnite1744650000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      ALTER TABLE lbp_produits_catalogue
      ADD COLUMN IF NOT EXISTS unite VARCHAR(30) NULL
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 885, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DENREE'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 900
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 885, updated_at = NOW()
      WHERE actif = true
        AND (
          nom ILIKE '%denrée%alimentaire%'
          OR nom ILIKE '%denree%alimentaire%'
          OR LOWER(TRIM(nom)) = 'denrees alimentaires'
        )
    `);

    await queryRunner.query(`
      INSERT INTO lbp_produits_catalogue (nom, categorie, nature, prix_unitaire, actif, created_at, updated_at)
      SELECT
        'DENREES ALIMENTAIRES',
        'DENREE'::lbp_produits_catalogue_categorie_enum,
        'PRIX_UNITAIRE'::lbp_produits_catalogue_nature_enum,
        885,
        true,
        NOW(),
        NOW()
      WHERE NOT EXISTS (
        SELECT 1 FROM lbp_produits_catalogue p
        WHERE LOWER(TRIM(p.nom)) = 'denrees alimentaires'
      )
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      DELETE FROM lbp_produits_catalogue
      WHERE LOWER(TRIM(nom)) = 'denrees alimentaires' AND prix_unitaire::numeric = 885
    `);
    await queryRunner.query(`
      ALTER TABLE lbp_produits_catalogue DROP COLUMN IF EXISTS unite
    `);
  }
}
