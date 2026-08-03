-- =============================================================================
-- Migration: Colisage & Opération Refacto (Additions schema & Seed Trajets)
-- Date: 2026-08-03
-- Description:
--   - Additive schema changes for Trajets, Factures audit log, locking & FKs.
--   - Idempotent additions using procedures.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //

DROP PROCEDURE IF EXISTS add_col_if_not_exists//
CREATE PROCEDURE add_col_if_not_exists(
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

DROP PROCEDURE IF EXISTS add_foreign_key_if_not_exists//
CREATE PROCEDURE add_foreign_key_if_not_exists(
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

-- 1. TABLE TRAJETS
CREATE TABLE IF NOT EXISTS trajets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(150) NOT NULL,
    type_transport ENUM('cargo', 'maritime', 'aerien', 'rapide') NOT NULL,
    agence_depart_id INT UNSIGNED NULL,
    destination VARCHAR(150) NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_trajets_agence_depart FOREIGN KEY (agence_depart_id) REFERENCES company_sites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLE AUDIT LOG FACTURES
CREATE TABLE IF NOT EXISTS factures_audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facture_id INT UNSIGNED NOT NULL,
    modifie_par INT NOT NULL,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    champ_modifie VARCHAR(100) NOT NULL,
    ancienne_valeur TEXT NULL,
    nouvelle_valeur TEXT NULL,
    CONSTRAINT fk_factures_audit_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE CASCADE,
    CONSTRAINT fk_factures_audit_user FOREIGN KEY (modifie_par) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lbp_factures_audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facture_id INT UNSIGNED NOT NULL,
    modifie_par INT NOT NULL,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    champ_modifie VARCHAR(100) NOT NULL,
    ancienne_valeur TEXT NULL,
    nouvelle_valeur TEXT NULL,
    CONSTRAINT fk_lbp_factures_audit_facture FOREIGN KEY (facture_id) REFERENCES lbp_factures(id) ON DELETE CASCADE,
    CONSTRAINT fk_lbp_factures_audit_user FOREIGN KEY (modifie_par) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ADD NULLABLE COLUMNS TO lbp_factures
CALL add_col_if_not_exists('lbp_factures', 'trajet_id', 'INT UNSIGNED NULL');
CALL add_col_if_not_exists('lbp_factures', 'agent_id', 'INT NULL');
CALL add_col_if_not_exists('lbp_factures', 'created_by', 'INT NULL');
CALL add_col_if_not_exists('lbp_factures', 'locked', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL add_col_if_not_exists('lbp_factures', 'locked_at', 'DATETIME NULL');

CALL add_foreign_key_if_not_exists('lbp_factures', 'fk_lbp_factures_trajet', 'trajet_id', 'trajets', 'id', 'SET NULL');
CALL add_foreign_key_if_not_exists('lbp_factures', 'fk_lbp_factures_agent', 'agent_id', 'users', 'id', 'SET NULL');
CALL add_foreign_key_if_not_exists('lbp_factures', 'fk_lbp_factures_created_by', 'created_by', 'users', 'id', 'SET NULL');

-- 4. ADD NULLABLE COLUMNS TO lbp_colis FOR DIRECT TRAJET REFERENCE
CALL add_col_if_not_exists('lbp_colis', 'trajet_id', 'INT UNSIGNED NULL');
CALL add_foreign_key_if_not_exists('lbp_colis', 'fk_lbp_colis_trajet', 'trajet_id', 'trajets', 'id', 'SET NULL');

-- 5. SEED TABLE TRAJETS
INSERT IGNORE INTO trajets (code, libelle, type_transport, destination, actif) VALUES
('LB-CI', 'Abidjan → France', 'maritime', 'France', 1),
('LB-FR', 'France → Abidjan', 'maritime', 'Abidjan', 1),
('S-FR', 'Sénégal → France', 'maritime', 'France', 1),
('LB-CA', 'Abidjan → Canada', 'maritime', 'Canada', 1),
('F-SN', 'France → Sénégal', 'maritime', 'Sénégal', 1),
('CA-CI', 'Abidjan → Paris', 'rapide', 'Paris', 1),
('CA-FR', 'Paris → Abidjan', 'rapide', 'Abidjan', 1),
('CA-SN', 'Sénégal → Côte d\'Ivoire', 'rapide', 'Côte d\'Ivoire', 1),
('CA-IS', 'Côte d\'Ivoire → Sénégal', 'rapide', 'Sénégal', 1),
('CA-IC', 'Côte d\'Ivoire → Canada', 'rapide', 'Canada', 1),
('CA-CC', 'Canada → Abidjan', 'rapide', 'Abidjan', 1),
('DHL', 'Services DHL Express', 'rapide', 'International', 1);

SET FOREIGN_KEY_CHECKS = 1;
