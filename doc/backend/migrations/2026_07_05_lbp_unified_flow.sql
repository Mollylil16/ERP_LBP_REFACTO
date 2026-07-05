-- =============================================================================
-- Migration: LBP Unified Flow (Logistique, Facturation, Finance)
-- Date: 2026-07-05
-- Description:
--   - Idempotent table and column additions using stored procedures.
--   - MySQL 8.4.7 compliant.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- PROCEDURES FOR IDEMPOTENT ALTERS
-- -----------------------------------------------------

DELIMITER //

DROP PROCEDURE IF EXISTS add_column_if_not_exists//
CREATE PROCEDURE add_column_if_not_exists(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_definition VARCHAR(500)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = p_table AND column_name = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN ', p_column, ' ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DROP PROCEDURE IF EXISTS add_fk_if_not_exists//
CREATE PROCEDURE add_fk_if_not_exists(
    IN p_table VARCHAR(128),
    IN p_constraint VARCHAR(128),
    IN p_fk_col VARCHAR(128),
    IN p_ref_table VARCHAR(128),
    IN p_ref_col VARCHAR(128),
    IN p_on_delete VARCHAR(50)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.key_column_usage
        WHERE table_schema = DATABASE() AND table_name = p_table AND constraint_name = p_constraint
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE ', p_table, ' ADD CONSTRAINT ', p_constraint, ' FOREIGN KEY (', p_fk_col, ') REFERENCES ', p_ref_table, '(', p_ref_col, ') ON DELETE ', p_on_delete);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- -----------------------------------------------------
-- 1. REGIONS & USER CONFIGURATIONS
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_regions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modifying company_sites (Agences)
CALL add_column_if_not_exists('company_sites', 'zone_regionale_id', 'INT UNSIGNED NULL');
CALL add_fk_if_not_exists('company_sites', 'fk_company_sites_region', 'zone_regionale_id', 'lbp_regions', 'id', 'SET NULL');

-- Modifying users
CALL add_column_if_not_exists('users', 'agence_id', 'INT UNSIGNED NULL');
CALL add_column_if_not_exists('users', 'zone_regionale_id', 'INT UNSIGNED NULL');
CALL add_fk_if_not_exists('users', 'fk_users_agence', 'agence_id', 'company_sites', 'id', 'SET NULL');
CALL add_fk_if_not_exists('users', 'fk_users_region', 'zone_regionale_id', 'lbp_regions', 'id', 'SET NULL');

-- Table pivot des rôles (RBAC multi-rôles)
CREATE TABLE IF NOT EXISTS lbp_user_roles (
    user_id INT NOT NULL,
    role ENUM(
        'agent_groupage',
        'caissiere',
        'chef_agence',
        'caissiere_principale',
        'superviseur_regional',
        'superviseur_general',
        'assistant_dg',
        'dg',
        'agent_exploitation',
        'comptable'
    ) NOT NULL,
    PRIMARY KEY (user_id, role),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 2. LOGISTIQUE: CLIENTS, COLIS & HISTORIQUE
-- -----------------------------------------------------

-- Extension de lbp_clients
CALL add_column_if_not_exists('lbp_clients', 'piece_identite', 'VARCHAR(100) NULL');
CALL add_column_if_not_exists('lbp_clients', 'agence_creation_id', 'INT UNSIGNED NULL');
CALL add_column_if_not_exists('lbp_clients', 'agent_groupage_id', 'INT NULL');
CALL add_fk_if_not_exists('lbp_clients', 'fk_lbp_clients_creation_site', 'agence_creation_id', 'company_sites', 'id', 'SET NULL');
CALL add_fk_if_not_exists('lbp_clients', 'fk_lbp_clients_agent', 'agent_groupage_id', 'users', 'id', 'SET NULL');

-- Extension de lbp_colis
CALL add_column_if_not_exists('lbp_colis', 'volume', 'DECIMAL(10,2) NULL');
CALL add_column_if_not_exists('lbp_colis', 'categorie_produit', 'VARCHAR(100) NULL');
CALL add_column_if_not_exists('lbp_colis', 'destination_ville', 'VARCHAR(150) NULL');
CALL add_column_if_not_exists('lbp_colis', 'destination_pays', 'VARCHAR(100) NULL');
CALL add_column_if_not_exists('lbp_colis', 'agent_groupage_id', 'INT NULL');

-- Uniformisation des statuts de colis (minuscules, snake_case)
ALTER TABLE lbp_colis
MODIFY COLUMN statut ENUM('enregistre', 'facture', 'en_transit', 'arrive', 'livre', 'retire', 'annule') NOT NULL DEFAULT 'enregistre';

CALL add_fk_if_not_exists('lbp_colis', 'fk_lbp_colis_agent', 'agent_groupage_id', 'users', 'id', 'SET NULL');


-- -----------------------------------------------------
-- 3. FINANCE: FACTURATION, PAIEMENTS & RECONCILIATION
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_factures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_facture VARCHAR(100) NOT NULL UNIQUE,
    colis_id INT UNSIGNED NOT NULL UNIQUE,
    client_id INT UNSIGNED NOT NULL,
    caissiere_id INT NOT NULL,
    agence_id INT UNSIGNED NOT NULL,
    montant_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    montant_encaisse DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    montant_restant DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    taux_change DECIMAL(12,6) NULL,
    statut ENUM('emise', 'partiellement_payee', 'payee', 'en_retard', 'annulee') NOT NULL DEFAULT 'emise',
    qr_code_paiement VARCHAR(255) NULL UNIQUE,
    date_expiration_qr DATETIME NULL,
    date_emission DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_echeance_solde DATETIME NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_factures_colis FOREIGN KEY (colis_id) REFERENCES lbp_colis(id) ON DELETE RESTRICT,
    CONSTRAINT fk_factures_client FOREIGN KEY (client_id) REFERENCES lbp_clients(id) ON DELETE RESTRICT,
    CONSTRAINT fk_factures_caissiere FOREIGN KEY (caissiere_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_factures_agence FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
    CONSTRAINT chk_taux_change_devise CHECK (devise = 'XOF' OR taux_change IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_paiements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facture_id INT UNSIGNED NOT NULL,
    caissiere_id INT NULL,
    montant DECIMAL(15,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    mode ENUM('especes', 'mobile_money', 'carte', 'qr_en_ligne') NOT NULL,
    type ENUM('partiel', 'total', 'solde') NOT NULL,
    date_paiement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_paiements_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE RESTRICT,
    CONSTRAINT fk_paiements_caissiere FOREIGN KEY (caissiere_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_recus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paiement_id INT UNSIGNED NOT NULL UNIQUE,
    numero_recu VARCHAR(100) NOT NULL UNIQUE,
    pdf_url VARCHAR(255) NULL,
    date_emission DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recus_paiement FOREIGN KEY (paiement_id) REFERENCES lbp_paiements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_paiement_callbacks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facture_id INT UNSIGNED NULL,
    paiement_id INT UNSIGNED NULL,
    provider VARCHAR(50) NOT NULL,
    transaction_reference VARCHAR(150) NOT NULL UNIQUE,
    montant DECIMAL(15,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    statut ENUM('pending', 'success', 'failed', 'unmatched') NOT NULL DEFAULT 'pending',
    raw_payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_callbacks_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE SET NULL,
    CONSTRAINT fk_callbacks_paiement FOREIGN KEY (paiement_id) REFERENCES lbp_paiements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_rappel_soldes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facture_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    montant_du DECIMAL(15,2) NOT NULL,
    date_envoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    canal ENUM('sms', 'whatsapp', 'email') NOT NULL,
    delai_remboursement INT NOT NULL,
    statut ENUM('envoye', 'relance', 'solde') NOT NULL DEFAULT 'envoye',
    
    CONSTRAINT fk_rappels_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE CASCADE,
    CONSTRAINT fk_rappels_client FOREIGN KEY (client_id) REFERENCES lbp_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 4. RAPPORT DU JOUR & TRESORERIE CONSOLIDEE
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_etats_journaliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agence_id INT UNSIGNED NOT NULL,
    chef_agence_id INT NULL,
    date_jour DATE NOT NULL,
    nb_colis_enregistres INT UNSIGNED NOT NULL DEFAULT 0,
    nb_factures_emises INT UNSIGNED NOT NULL DEFAULT 0,
    total_facture_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_facture_eur DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_encaisse_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_encaisse_eur DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_restant_du_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_restant_du_eur DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    solde_caisse_agence_xof DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    solde_caisse_agence_eur DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    statut ENUM('brouillon', 'soumis', 'consolide') NOT NULL DEFAULT 'brouillon',
    date_soumission DATETIME NULL,
    consolide_par_id INT NULL,
    date_consolidation DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uniq_etat_agence_date (agence_id, date_jour),
    CONSTRAINT fk_etats_agence FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE RESTRICT,
    CONSTRAINT fk_etats_chef FOREIGN KEY (chef_agence_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_etats_consolide FOREIGN KEY (consolide_par_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_mouvements_caisse_principale (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_mouvement ENUM('caisse_agence', 'prestataire', 'autre') NOT NULL,
    reference_id INT UNSIGNED NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    sens ENUM('ENTREE', 'SORTIE') NOT NULL,
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    solde_apres DECIMAL(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 5. COMPTABILITE GENERALE
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_plan_comptable (
    code VARCHAR(20) PRIMARY KEY,
    libelle VARCHAR(255) NOT NULL,
    classe TINYINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_ecritures_comptables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_ecriture DATE NOT NULL,
    journal ENUM('achats', 'ventes', 'banque', 'caisse', 'OD') NOT NULL,
    compte_debit VARCHAR(20) NOT NULL,
    compte_credit VARCHAR(20) NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    taux_change DECIMAL(12,6) NULL,
    piece_justificative_id VARCHAR(100) NULL,
    libelle VARCHAR(255) NOT NULL,
    lettrage VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_ecritures_debit FOREIGN KEY (compte_debit) REFERENCES lbp_plan_comptable(code) ON DELETE RESTRICT,
    CONSTRAINT fk_ecritures_credit FOREIGN KEY (compte_credit) REFERENCES lbp_plan_comptable(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 6. PRESTATAIRES & DEPENSES AVEC SOD
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_prestataires (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    type_prestation VARCHAR(150) NOT NULL,
    contact VARCHAR(100) NULL,
    zone_regionale_id INT UNSIGNED NULL,
    CONSTRAINT fk_prestataires_region FOREIGN KEY (zone_regionale_id) REFERENCES lbp_regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_demandes_paiement_prestataires (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prestataire_id INT UNSIGNED NOT NULL,
    superviseur_regional_id INT NOT NULL,
    montant DECIMAL(15,2) NOT NULL,
    devise CHAR(3) NOT NULL DEFAULT 'XOF',
    motif VARCHAR(255) NOT NULL,
    justificatif_url VARCHAR(255) NULL,
    statut ENUM('en_attente', 'approuvee', 'rejetee', 'payee') NOT NULL DEFAULT 'en_attente',
    caissiere_principale_id INT NULL,
    date_demande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_demandes_prestataire FOREIGN KEY (prestataire_id) REFERENCES lbp_prestataires(id) ON DELETE RESTRICT,
    CONSTRAINT fk_demandes_superviseur FOREIGN KEY (superviseur_regional_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_demandes_caissiere_p FOREIGN KEY (caissiere_principale_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_sod_paiement CHECK (caissiere_principale_id IS NULL OR caissiere_principale_id <> superviseur_regional_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_rapports_supervision (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auteur_id INT NOT NULL,
    agence_id INT UNSIGNED NULL,
    type ENUM('point_periodique', 'demande_explication', 'critique') NOT NULL,
    contenu TEXT NOT NULL,
    destinataires TEXT NOT NULL,
    statut ENUM('envoye', 'en_reponse', 'cloture') NOT NULL DEFAULT 'envoye',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_rapports_auteur FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_rapports_agence FOREIGN KEY (agence_id) REFERENCES company_sites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------
-- 7. AUDIT LOGS ENRICHIS
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(150) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up helper procedures
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS add_fk_if_not_exists;

SET FOREIGN_KEY_CHECKS = 1;
