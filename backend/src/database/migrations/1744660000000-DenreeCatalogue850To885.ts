import { MigrationInterface, QueryRunner } from 'typeorm';

/**
 * Tarif denrées alimentaires au kg : 850 → 885 FCFA (fiche officielle).
 * N’altère pas les autres catégories ni les forfaits (prix_forfaitaire inchangé).
 */
export class DenreeCatalogue850To8851744660000000 implements MigrationInterface {
  name = 'DenreeCatalogue850To8851744660000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 885, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DENREE'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 850
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      UPDATE lbp_produits_catalogue
      SET prix_unitaire = 850, updated_at = NOW()
      WHERE actif = true
        AND categorie::text = 'DENREE'
        AND prix_unitaire IS NOT NULL
        AND prix_unitaire::numeric = 885
        AND LOWER(TRIM(nom)) <> 'denrees alimentaires'
    `);
  }
}
