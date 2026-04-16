import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Tarif denrées alimentaires au kg : 885 → 900 FCFA (nouvelle grille).
 * Met à jour la catégorie DENREE (produit catalogue) sans toucher les autres catégories.
 */
export class DenreeCatalogue885To9001748810000000
  implements MigrationInterface
{
  name = 'DenreeCatalogue885To9001748810000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 900, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DENREE'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 885
    `);

    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 900, updated_at = NOW()
      WHERE actif = true
        AND (
          nom ILIKE '%denrée%alimentaire%'
          OR nom ILIKE '%denree%alimentaire%'
          OR LOWER(TRIM(nom)) = 'denrees alimentaires'
        )
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 885
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 885, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DENREE'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 900
    `);
  }
}

