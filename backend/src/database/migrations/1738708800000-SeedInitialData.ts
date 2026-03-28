import { MigrationInterface, QueryRunner } from 'typeorm';
import * as bcrypt from 'bcrypt';

export class SeedInitialData1738708800000 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    // 1. Seed Roles
    await queryRunner.query(`
            INSERT INTO roles (code, nom, description) VALUES
            ('ADMIN', 'Administrateur', 'Accès complet au système'),
            ('GESTIONNAIRE', 'Gestionnaire', 'Gestion des colis et factures'),
            ('CAISSIER', 'Caissier', 'Gestion de la caisse et paiements'),
            ('COMPTABLE', 'Comptable', 'Consultation et rapports'),
            ('OPERATEUR', 'Opérateur', 'Saisie de colis uniquement')
        `);

    // 2. Seed Permissions
    await queryRunner.query(`
            INSERT INTO permissions (code, nom, module, description) VALUES
            -- Colis
            ('COLIS_CREATE', 'Créer colis', 'colis', 'Créer un nouveau colis'),
            ('COLIS_READ', 'Consulter colis', 'colis', 'Voir les colis'),
            ('COLIS_UPDATE', 'Modifier colis', 'colis', 'Modifier un colis existant'),
            ('COLIS_DELETE', 'Supprimer colis', 'colis', 'Supprimer un colis'),
            ('COLIS_VALIDATE', 'Valider colis', 'colis', 'Valider un colis'),
            
            -- Factures
            ('FACTURE_CREATE', 'Créer facture', 'factures', 'Générer une facture'),
            ('FACTURE_READ', 'Consulter factures', 'factures', 'Voir les factures'),
            ('FACTURE_UPDATE', 'Modifier facture', 'factures', 'Modifier une facture'),
            ('FACTURE_DELETE', 'Supprimer facture', 'factures', 'Supprimer une facture'),
            ('FACTURE_VALIDATE', 'Valider facture', 'factures', 'Valider une facture'),
            
            -- Paiements
            ('PAIEMENT_CREATE', 'Enregistrer paiement', 'paiements', 'Enregistrer un paiement'),
            ('PAIEMENT_READ', 'Consulter paiements', 'paiements', 'Voir les paiements'),
            ('PAIEMENT_CANCEL', 'Annuler paiement', 'paiements', 'Annuler un paiement'),
            
            -- Caisse
            ('CAISSE_APPRO', 'Approvisionner caisse', 'caisse', 'Faire un approvisionnement'),
            ('CAISSE_ENTREE', 'Entrée caisse', 'caisse', 'Enregistrer une entrée'),
            ('CAISSE_DECAISSEMENT', 'Décaissement', 'caisse', 'Faire un décaissement'),
            ('CAISSE_RAPPORT', 'Rapport caisse', 'caisse', 'Voir les rapports de caisse'),
            
            -- Clients
            ('CLIENT_CREATE', 'Créer client', 'clients', 'Créer un nouveau client'),
            ('CLIENT_READ', 'Consulter clients', 'clients', 'Voir les clients'),
            ('CLIENT_UPDATE', 'Modifier client', 'clients', 'Modifier un client'),
            ('CLIENT_DELETE', 'Supprimer client', 'clients', 'Supprimer un client'),
            
            -- Users
            ('USER_CREATE', 'Créer utilisateur', 'users', 'Créer un utilisateur'),
            ('USER_READ', 'Consulter utilisateurs', 'users', 'Voir les utilisateurs'),
            ('USER_UPDATE', 'Modifier utilisateur', 'users', 'Modifier un utilisateur'),
            ('USER_DELETE', 'Supprimer utilisateur', 'users', 'Supprimer un utilisateur'),
            
            -- Rapports
            ('RAPPORT_COLIS', 'Rapport colis', 'rapports', 'Voir rapport des colis'),
            ('RAPPORT_FINANCIER', 'Rapport financier', 'rapports', 'Voir rapport financier'),
            ('RAPPORT_CAISSE', 'Rapport caisse', 'rapports', 'Voir rapport de caisse')
        `);

    // 3. Assign Permissions to Roles
    // ADMIN - All permissions
    await queryRunner.query(`
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 1, id FROM permissions
        `);

    // GESTIONNAIRE - Colis, Factures, Clients
    await queryRunner.query(`
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 2, id FROM permissions 
            WHERE module IN ('colis', 'factures', 'clients', 'rapports')
        `);

    // CAISSIER - Paiements, Caisse
    await queryRunner.query(`
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 3, id FROM permissions 
            WHERE module IN ('paiements', 'caisse', 'rapports')
            OR code IN ('FACTURE_READ', 'COLIS_READ', 'CLIENT_READ')
        `);

    // COMPTABLE - Read only + Rapports
    await queryRunner.query(`
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 4, id FROM permissions 
            WHERE code LIKE '%_READ' OR module = 'rapports'
        `);

    // OPERATEUR - Colis create/read only
    await queryRunner.query(`
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 5, id FROM permissions 
            WHERE code IN ('COLIS_CREATE', 'COLIS_READ', 'CLIENT_CREATE', 'CLIENT_READ')
        `);

    // 4. Seed Agence par défaut
    await queryRunner.query(`
            INSERT INTO agences (code, nom, pays, ville, adresse, telephone, actif) VALUES
            ('LBP', 'LBP Logistics - Siège', 'Côte d''Ivoire', 'Abidjan', 'Cocody, Abidjan', '+225 27 XX XX XX XX', true)
        `);

    // 5. Seed Caisse par défaut
    await queryRunner.query(`
            INSERT INTO caisse (nom, id_agence, solde_initial, solde_minimum) VALUES
            ('Caisse Principale', 1, 0, 50000)
        `);

    // 6. Seed Admin User (password: admin123)
    const hashedPassword = await bcrypt.hash('admin123', 10);
    await queryRunner.query(`
            INSERT INTO users (username, password, nom_complet, email, role_id, id_agence, actif) VALUES
            ('admin', '${hashedPassword}', 'Administrateur Système', 'admin@lbp.ci', 1, 1, true)
        `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DELETE FROM users`);
    await queryRunner.query(`DELETE FROM caisse`);
    await queryRunner.query(`DELETE FROM agences`);
    await queryRunner.query(`DELETE FROM role_permissions`);
    await queryRunner.query(`DELETE FROM permissions`);
    await queryRunner.query(`DELETE FROM roles`);
  }
}
