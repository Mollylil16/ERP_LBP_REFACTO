-- ============================================================================
-- Migration: Attribution des rôles, agences et permissions pour le personnel
-- Date: 2026-09-07
-- Cible: Les 22 collaborateurs officiels de l'ERP LA BELLE PORTE
-- ============================================================================

-- 1. S'assurer de la présence des tables
CREATE TABLE IF NOT EXISTS `lbp_user_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `role` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_role` (`user_id`, `role`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `entity_id` INT NULL,
    `can_view` TINYINT(1) NOT NULL DEFAULT 0,
    `can_create` TINYINT(1) NOT NULL DEFAULT 0,
    `can_update` TINYINT(1) NOT NULL DEFAULT 0,
    `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_entity` (`user_id`, `entity_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 2. Mise à jour des Noms, Emails, Agences et Statuts dans `users`
-- ============================================================================

-- 1. AKOIBLIN ROXANE -> Chef d'agence Adjamé (ID 3404)
UPDATE `users` SET `full_name` = 'AKOIBLIN ROXANE', `email` = 'roxane.akoiblin@labelleporte.ci', `agence_id` = 3404, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%roxane.akoiblin%' OR `full_name` LIKE '%AKOIBLIN%';

-- 2. KOUAKOU SALES -> Responsable Groupage Général (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'KOUAKOU SALES', `email` = 'kouakou.sales@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%kouakou.sales%' OR `email` LIKE '%sales.kouakou%' OR `full_name` LIKE '%SALES%';

-- 3. DIARRA SIAKA -> Chef d'agence Dokui (ID 3403)
UPDATE `users` SET `full_name` = 'DIARRA SIAKA', `email` = 'siaka.diarra@labelleporte.ci', `agence_id` = 3403, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%siaka.diarra%' OR `full_name` LIKE '%DIARRA SIAKA%';

-- 4. KOUAME YVETTE -> Agent de Saisie Dokui (ID 3403)
UPDATE `users` SET `full_name` = 'KOUAME Yvette', `email` = 'kouame.yvette@labelleporte.ci', `agence_id` = 3403, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%kouame.yvette%' OR `email` LIKE '%yvette.kouame%' OR `email` LIKE '%grace.kouame%' OR `full_name` LIKE '%KOUAME%';

-- 5. KOLI KONAN ANICET -> Agent de Saisie Dokui (ID 3403)
UPDATE `users` SET `full_name` = 'KOLI KONAN ANICET', `email` = 'anicet.konan@labelleporte.ci', `agence_id` = 3403, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%anicet.konan%' OR `email` LIKE '%anicet.koli%' OR `full_name` LIKE '%KOLI%';

-- 6. Mme AGBADAN (Carine Abou) -> Caissière Principale & Dokui (ID 3403)
UPDATE `users` SET `full_name` = 'Mme AGBADAN (Carine Abou)', `email` = 'carine.abou@labelleporte.ci', `agence_id` = 3403, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%carine.abou%' OR `full_name` LIKE '%AGBADAN%' OR `full_name` LIKE '%ABOU ABOUBIE%';

-- 7. ABASSI WILFRIED -> Resp Marketing & Call Center (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'ABASSI WILFRIED', `email` = 'wilfried.abassi@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%wilfried.abassi%' OR `full_name` LIKE '%ABASSI%';

-- 8. LASSICI MARIAM -> Agent Call Center (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'LASSICI MARIAM', `email` = 'mariam.lassici@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%mariam.lassici%' OR `full_name` LIKE '%LASSICI%';

-- 9. KOFFI MARQUEZ -> Chef d'agence Aéroport Fret (ID 3402)
UPDATE `users` SET `full_name` = 'KOFFI MARQUEZ', `email` = 'marquez.koffi@labelleporte.ci', `agence_id` = 3402, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%marquez.koffi%' OR `full_name` LIKE '%MARQUEZ%';

-- 10. ASSOMA ASSI JEAN EUDES -> Agent de Saisie Aéroport (ID 3402)
UPDATE `users` SET `full_name` = 'ASSOMA ASSI JEAN EUDES', `email` = 'jean.eudes@labelleporte.ci', `agence_id` = 3402, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%jean.eudes%' OR `email` LIKE '%jeaneudes.assoma%' OR `full_name` LIKE '%ASSOMA%';

-- 11. ADEPO MARIE ESTHER -> Chef d'agence Sénégal (ID 3401)
UPDATE `users` SET `full_name` = 'ADEPO MARIE ESTHER', `email` = 'estelle.adepo@labelleporte.ci', `agence_id` = 3401, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%estelle.adepo%' OR `email` LIKE '%esther.adepo%' OR `full_name` LIKE '%ADEPO MARIE ESTHER%';

-- 12. DJAMBITCHE SARAH STEPHANIE -> Agent de Saisie Adjamé (ID 3404)
UPDATE `users` SET `full_name` = 'DJAMBITCHE SARAH STEPHANIE', `email` = 'sarah.djambitche@labelleporte.ci', `agence_id` = 3404, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%sarah.djambitche%' OR `full_name` LIKE '%SARAH%DJAMBITCHE%';

-- 13. KARABBOUE AMY -> Agent de Saisie Adjamé (ID 3404)
UPDATE `users` SET `full_name` = 'KARABBOUE AMY', `email` = 'karabboue.amy@labelleporte.ci', `agence_id` = 3404, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%karabboue.amy%' OR `full_name` LIKE '%KARABBOUE%';

-- 14. SERY GRACE -> Agent de Saisie Adjamé (ID 3404)
UPDATE `users` SET `full_name` = 'SERY GRACE', `email` = 'sery.grace@labelleporte.ci', `agence_id` = 3404, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%sery.grace%' OR `email` LIKE '%grace.sery%' OR `full_name` LIKE '%SERY GRACE%';

-- 15. KADJO PRINCE -> Chef d'agence France Paris (ID 3400)
UPDATE `users` SET `full_name` = 'KADJO PRINCE', `email` = 'prince.kadjo@labelleporte.ci', `agence_id` = 3400, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%prince.kadjo%' OR `full_name` LIKE '%PRINCE%KADJO%';

-- 16. Claude Yedess -> Assistante DG (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'Claude Yedess', `email` = 'claude.yedess@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%claude.yedess%' OR `full_name` LIKE '%YEDESS%';

-- 17. Dieng Amy -> Agent de Saisie Sénégal (ID 3401)
UPDATE `users` SET `full_name` = 'Dieng Amy', `email` = 'amy.dieng@labelleporte.ci', `agence_id` = 3401, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%amy.dieng%' OR `full_name` LIKE '%DIENG%';

-- 18. BRUNELL OMEPIEUR -> Administrateur (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'BRUNELL OMEPIEUR', `email` = 'brunellomepieu@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 1 WHERE `email` LIKE '%brunellomepieu%' OR `full_name` LIKE '%BRUNELL%';

-- 19. SIBRI PAUL -> Comptable (ID 3403)
UPDATE `users` SET `full_name` = 'SIBRI PAUL', `email` = 'sibri.paulaime@labelleporte.ci', `agence_id` = 3403, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%sibri.paulaime%' OR `full_name` LIKE '%SIBRI%';

-- 20. SORO IBRAHIM -> Stagiaire RH (ID 3403)
UPDATE `users` SET `full_name` = 'SORO IBRAHIM', `email` = 'soro.ibrahim@labelleporte.ci', `agence_id` = 3403, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%soro.ibrahim%' OR `full_name` LIKE '%SORO%';

-- 21. Serge Kadjo -> Directeur Général (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'Serge Kadjo', `email` = 'serge.kadjo@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 1 WHERE `email` LIKE '%serge.kadjo%' OR `email` LIKE '%serges.kadjo%' OR `full_name` LIKE '%SERGE%KADJO%';

-- 22. Adje Roxane -> Responsable RH (Toutes agences / NULL)
UPDATE `users` SET `full_name` = 'Adje Roxane', `email` = 'roxane.a@labelleporte.ci', `agence_id` = NULL, `status` = 'active', `is_admin` = 0 WHERE `email` LIKE '%roxane.a%' OR `full_name` LIKE '%ADJE%';

-- ============================================================================
-- 3. Réattribution des Rôles dans `lbp_user_roles`
-- ============================================================================

-- Nettoyer les rôles des 22 utilisateurs
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (
    SELECT `id` FROM `users` WHERE `email` IN (
        'roxane.akoiblin@labelleporte.ci',
        'kouakou.sales@labelleporte.ci',
        'siaka.diarra@labelleporte.ci',
        'kouame.yvette@labelleporte.ci',
        'anicet.konan@labelleporte.ci',
        'carine.abou@labelleporte.ci',
        'wilfried.abassi@labelleporte.ci',
        'mariam.lassici@labelleporte.ci',
        'marquez.koffi@labelleporte.ci',
        'jean.eudes@labelleporte.ci',
        'estelle.adepo@labelleporte.ci',
        'sarah.djambitche@labelleporte.ci',
        'karabboue.amy@labelleporte.ci',
        'sery.grace@labelleporte.ci',
        'prince.kadjo@labelleporte.ci',
        'claude.yedess@labelleporte.ci',
        'amy.dieng@labelleporte.ci',
        'brunellomepieu@labelleporte.ci',
        'sibri.paulaime@labelleporte.ci',
        'soro.ibrahim@labelleporte.ci',
        'serge.kadjo@labelleporte.ci',
        'roxane.a@labelleporte.ci'
    )
);

-- Insertion des rôles respectifs
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'chef_agence' FROM `users` WHERE `email` IN ('roxane.akoiblin@labelleporte.ci', 'siaka.diarra@labelleporte.ci', 'marquez.koffi@labelleporte.ci', 'estelle.adepo@labelleporte.ci', 'prince.kadjo@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'agent_groupage' FROM `users` WHERE `email` IN ('kouakou.sales@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'agent_saisie' FROM `users` WHERE `email` IN ('kouame.yvette@labelleporte.ci', 'anicet.konan@labelleporte.ci', 'jean.eudes@labelleporte.ci', 'sarah.djambitche@labelleporte.ci', 'karabboue.amy@labelleporte.ci', 'sery.grace@labelleporte.ci', 'amy.dieng@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'caissiere_principale' FROM `users` WHERE `email` IN ('carine.abou@labelleporte.ci');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'caissiere' FROM `users` WHERE `email` IN ('carine.abou@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'responsable_marketing' FROM `users` WHERE `email` IN ('wilfried.abassi@labelleporte.ci');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'responsable_call_center' FROM `users` WHERE `email` IN ('wilfried.abassi@labelleporte.ci');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'agent_call_center' FROM `users` WHERE `email` IN ('wilfried.abassi@labelleporte.ci', 'mariam.lassici@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'assistant_dg' FROM `users` WHERE `email` IN ('claude.yedess@labelleporte.ci');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'assistante_dg' FROM `users` WHERE `email` IN ('claude.yedess@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'admin' FROM `users` WHERE `email` IN ('brunellomepieu@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'comptable' FROM `users` WHERE `email` IN ('sibri.paulaime@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'rh' FROM `users` WHERE `email` IN ('soro.ibrahim@labelleporte.ci', 'roxane.a@labelleporte.ci');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'responsable_rh' FROM `users` WHERE `email` IN ('roxane.a@labelleporte.ci');

INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'dg' FROM `users` WHERE `email` IN ('serge.kadjo@labelleporte.ci');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'dg_surveillance' FROM `users` WHERE `email` IN ('serge.kadjo@labelleporte.ci');
