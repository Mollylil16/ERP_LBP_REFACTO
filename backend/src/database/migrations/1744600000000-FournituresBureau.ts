import { MigrationInterface, QueryRunner } from 'typeorm';

export class FournituresBureau1744600000000 implements MigrationInterface {
  name = 'FournituresBureau1744600000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS lbp_fournitures_articles (
        id SERIAL PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        nom VARCHAR(200) NOT NULL,
        unite VARCHAR(30) NOT NULL DEFAULT 'unité',
        quantite_stock INT NOT NULL DEFAULT 0,
        seuil_alerte INT NOT NULL DEFAULT 0,
        actif BOOLEAN NOT NULL DEFAULT true,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
      )
    `);

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS lbp_fournitures_demandes (
        id SERIAL PRIMARY KEY,
        id_agence INT NOT NULL REFERENCES agences(id) ON DELETE RESTRICT,
        id_demandeur INT NOT NULL REFERENCES lbp_users(id) ON DELETE RESTRICT,
        statut VARCHAR(20) NOT NULL DEFAULT 'BROUILLON',
        observations TEXT NULL,
        motif_refus TEXT NULL,
        id_valideur INT NULL REFERENCES lbp_users(id) ON DELETE SET NULL,
        date_validation TIMESTAMPTZ NULL,
        id_livreur INT NULL REFERENCES lbp_users(id) ON DELETE SET NULL,
        date_livraison TIMESTAMPTZ NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
      )
    `);
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS idx_lbp_fournitures_demandes_statut ON lbp_fournitures_demandes(statut)`,
    );
    await queryRunner.query(
      `CREATE INDEX IF NOT EXISTS idx_lbp_fournitures_demandes_agence ON lbp_fournitures_demandes(id_agence)`,
    );

    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS lbp_fournitures_demande_lignes (
        id SERIAL PRIMARY KEY,
        id_demande INT NOT NULL REFERENCES lbp_fournitures_demandes(id) ON DELETE CASCADE,
        id_article INT NOT NULL REFERENCES lbp_fournitures_articles(id) ON DELETE RESTRICT,
        quantite INT NOT NULL,
        quantite_validee INT NULL,
        quantite_livree INT NULL
      )
    `);

    await queryRunner.query(`
      INSERT INTO lbp_fournitures_articles (code, nom, unite, quantite_stock, seuil_alerte)
      VALUES
        ('PAP-A4', 'Ramettes papier A4', 'ramette', 0, 5),
        ('STY-BL', 'Stylos bille (lot 50)', 'lot', 0, 2),
        ('AGR-STD', 'Agrafeuse standard', 'pièce', 0, 1)
      ON CONFLICT (code) DO NOTHING
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE IF EXISTS lbp_fournitures_demande_lignes`);
    await queryRunner.query(`DROP TABLE IF EXISTS lbp_fournitures_demandes`);
    await queryRunner.query(`DROP TABLE IF EXISTS lbp_fournitures_articles`);
  }
}
