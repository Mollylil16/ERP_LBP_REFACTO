-- =============================================================================
-- Migration: Colisage Performance & Traçabilité
-- Date: 2026-07-05
-- Description: 
--   1. Index composites pour optimiser le reporting (filtres par date)
--   2. Table d'historique des statuts d'expéditions (traçabilité)
-- =============================================================================

-- -----------------------------------------------
-- 1. INDEX COMPOSITES SUR lbp_colis (Performance)
-- -----------------------------------------------

-- Procédure utilitaire pour ajouter un index si absent
DELIMITER //
DROP PROCEDURE IF EXISTS add_index_if_not_exists//
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_columns VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = p_table AND index_name = p_index
    ) THEN
        SET @ddl = CONCAT('CREATE INDEX ', p_index, ' ON ', p_table, ' (', p_columns, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

CALL add_index_if_not_exists('lbp_colis', 'idx_lbp_colis_created_trajet', 'created_at, trajet');
CALL add_index_if_not_exists('lbp_colis', 'idx_lbp_colis_created_type_expediteur', 'created_at, type_expediteur');
CALL add_index_if_not_exists('lbp_colis', 'idx_lbp_colis_statut_created', 'statut, created_at');

DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- -----------------------------------------------
-- 2. TABLE lbp_expedition_status_history (Traçabilité)
-- -----------------------------------------------

CREATE TABLE IF NOT EXISTS lbp_expedition_status_history (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    expedition_id   INT            NOT NULL,
    statut_depart   VARCHAR(50)    NOT NULL COMMENT 'Statut avant le changement',
    statut_arrive   VARCHAR(50)    NOT NULL COMMENT 'Statut après le changement',
    changed_by_user_id INT         NULL     COMMENT 'ID de l''utilisateur ayant effectué le changement',
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_history_expedition (expedition_id),
    INDEX idx_history_created (created_at),
    
    CONSTRAINT fk_history_expedition
        FOREIGN KEY (expedition_id)
        REFERENCES lbp_expeditions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique des changements de statut des expéditions';
