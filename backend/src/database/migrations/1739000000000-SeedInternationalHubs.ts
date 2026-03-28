import { MigrationInterface, QueryRunner } from 'typeorm';

export class SeedInternationalHubs1739000000000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // Enregistrement des hubs internationaux
    await queryRunner.query(`
            INSERT INTO agences (code, nom, pays, ville, adresse, telephone, actif, devise) VALUES
            ('PAR', 'LBP France - Paris Hub', 'France', 'Paris', 'Gonesse, Paris CDG', '+33 1 XX XX XX XX', true, 'EUR'),
            ('DKR', 'LBP Sénégal - Dakar Hub', 'Sénégal', 'Dakar', 'Aéroport Blaise Diagne', '+221 33 XX XX XX XX', true, 'XOF')
        `);

    // Création de personnels pour ces hubs (password: lbp123)
    // Note: use existing role_id (1=ADMIN, 2=GESTIONNAIRE, etc.)
    await queryRunner.query(`
            INSERT INTO users (username, password, nom_complet, email, role_id, id_agence, actif) VALUES
            ('paris_op', '$2b$10$Y5O8W8m6P1r8s.HubParis', 'Agent Paris', 'paris@lbp.net', 2, 2, true),
            ('dakar_op', '$2b$10$D5A8K8A6R.SeneHubDkr', 'Agent Dakar', 'dakar@lbp.net', 2, 3, true)
        `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `DELETE FROM users WHERE username IN ('paris_op', 'dakar_op')`,
    );
    await queryRunner.query(`DELETE FROM agences WHERE code IN ('PAR', 'DKR')`);
  }
}
