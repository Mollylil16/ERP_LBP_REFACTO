-- Migration pour le suivi des Codes Non Payés (Groupages) et l'accès Responsable Groupage
-- Date: 2026-09-22

-- 1. Attribution du rôle responsable_groupage à KOUAKOU SALES
INSERT IGNORE INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'responsable_groupage' 
FROM `users` 
WHERE `email` IN ('kouakou.sales@labelleporte.ci', 'sales.kouakou@labelleporte.ci');

-- 2. Ajout de la colonne groupe_code dans lbp_colis si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'lbp_colis' 
      AND COLUMN_NAME = 'groupe_code'
);

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `lbp_colis` ADD COLUMN `groupe_code` VARCHAR(50) NULL AFTER `trajet_id`', 
    'SELECT "Column groupe_code already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Indexation pour optimiser les requêtes du tableau des codes non payés
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'lbp_colis' 
      AND INDEX_NAME = 'idx_colis_groupe_code'
);
SET @sql_idx = IF(@idx_exists = 0, 
    'ALTER TABLE `lbp_colis` ADD INDEX `idx_colis_groupe_code` (`groupe_code`)', 
    'SELECT "Index idx_colis_groupe_code already exists"'
);
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
