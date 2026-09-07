-- ============================================================================
-- Migration: Attribution des rôles, agences et permissions pour le personnel
-- Date: 2026-09-07
-- Cible: Les 15 collaborateurs de la liste d'affectation
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
-- 2. Mise à jour des Agences et Statuts dans `users`
-- ============================================================================

-- 1. AKOIBLIN ROXANE -> Chef d'agence Adjamé (ID 3404)
UPDATE `users` SET `agence_id` = 3404, `status` = 'active' WHERE `email` LIKE '%roxane.akoiblin%' OR `email` LIKE '%roxane.a%' OR `full_name` LIKE '%AKOIBLIN%';

-- 2. KOUAKOU SALES -> Responsable Groupage (Siège ID 1)
UPDATE `users` SET `agence_id` = 1, `status` = 'active' WHERE `email` LIKE '%sales.kouakou%' OR `email` LIKE '%sales%' OR `full_name` LIKE '%SALES%';

-- 3. DIARRA SIAKA -> Chef d'agence Dokui (ID 3403)
UPDATE `users` SET `agence_id` = 3403, `status` = 'active' WHERE `email` LIKE '%siaka.diarra%' OR `full_name` LIKE '%DIARRA SIAKA%';

-- 4. KOUAME YVETTE -> Agent de Saisie Dokui (ID 3403)
UPDATE `users` SET `full_name` = 'KOUAME YVETTE', `email` = 'yvette.kouame@labelleporte.ci', `agence_id` = 3403, `status` = 'active' WHERE `email` LIKE '%yvette.kouame%' OR `email` LIKE '%grace.kouame%' OR `full_name` LIKE '%KOUAME%';

-- 5. KOLI KONAN ANICET -> Agent de Saisie Dokui (ID 3403)
UPDATE `users` SET `agence_id` = 3403, `status` = 'active' WHERE `email` LIKE '%anicet.koli%' OR `full_name` LIKE '%KOLI%';

-- 6. Mme AGBADAN (CARINE ABOU) -> Caissière Dokui (ID 3403)
UPDATE `users` SET `agence_id` = 3403, `status` = 'active' WHERE `email` LIKE '%carine.abou%' OR `full_name` LIKE '%AGBADAN%' OR `full_name` LIKE '%ABOU ABOUBIE%';

-- 7. ABASSI WILFRIED -> Resp Marketing (Siège ID 1)
UPDATE `users` SET `agence_id` = 1, `status` = 'active' WHERE `email` LIKE '%wilfried.abassi%' OR `full_name` LIKE '%ABASSI%';

-- 8. LASSICI MARIAM -> Agent Call Center (Siège ID 1)
UPDATE `users` SET `agence_id` = 1, `status` = 'active' WHERE `email` LIKE '%mariam.lassici%' OR `full_name` LIKE '%LASSICI%';

-- 9. KOFFI MARQUEZ -> Chef d'agence Aéroport Fret (ID 3402)
UPDATE `users` SET `agence_id` = 3402, `status` = 'active' WHERE `email` LIKE '%marquez.koffi%' OR `full_name` LIKE '%MARQUEZ%';

-- 10. ASSOMA ASSI JEAN EUDES -> Agent de Saisie Aéroport (ID 3402)
UPDATE `users` SET `agence_id` = 3402, `status` = 'active' WHERE `email` LIKE '%jeaneudes.assoma%' OR `full_name` LIKE '%ASSOMA%';

-- 11. ADEPO MARIE ESTHER -> Chef d'agence Sénégal (ID 3401)
UPDATE `users` SET `agence_id` = 3401, `status` = 'active' WHERE `email` LIKE '%estelle.adepo%' OR `email` LIKE '%esther.adepo%' OR `full_name` LIKE '%ADEPO MARIE ESTHER%';

-- 12. DJAMBITCHE SARAH STEPHANIE -> Agent de Saisie Adjamé (ID 3404)
UPDATE `users` SET `agence_id` = 3404, `status` = 'active' WHERE `email` LIKE '%sarah.djambitche%' OR `full_name` LIKE '%DJAMBITCHE SARAH%';

-- 13. KARABBOUE AMY -> Agent de Saisie Adjamé (ID 3404)
UPDATE `users` SET `agence_id` = 3404, `status` = 'active' WHERE `email` LIKE '%amy.dieng%' OR `email` LIKE '%amy.karabboue%' OR `full_name` LIKE '%KARABBOUE%';

-- 14. SERY GRACE -> Agent de Saisie Adjamé (ID 3404)
UPDATE `users` SET `agence_id` = 3404, `status` = 'active' WHERE `email` LIKE '%grace.sery%' OR `full_name` LIKE '%SERY GRACE%';

-- 15. KADJO PRINCE -> Responsable Paris (ID 3400)
UPDATE `users` SET `agence_id` = 3400, `status` = 'active' WHERE `email` LIKE '%prince.kadjo%' OR `full_name` LIKE '%KADJO PRINCE%';

-- ============================================================================
-- 3. Réattribution des Rôles dans `lbp_user_roles`
-- ============================================================================

-- Chefs d'agence : Roxane Akoiblin, Siaka Diarra, Marquez Koffi, Marie Esther Adepo, Prince Kadjo
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (
    SELECT `id` FROM `users` WHERE `email` IN (
        'roxane.akoiblin@labelleporte.ci', 'roxane.a@labelleporte.ci',
        'siaka.diarra@labelleporte.ci',
        'marquez.koffi@labelleporte.ci',
        'estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci',
        'prince.kadjo@labelleporte.ci'
    )
);
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'chef_agence' FROM `users` WHERE `email` IN (
    'roxane.akoiblin@labelleporte.ci', 'roxane.a@labelleporte.ci',
    'siaka.diarra@labelleporte.ci',
    'marquez.koffi@labelleporte.ci',
    'estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci',
    'prince.kadjo@labelleporte.ci'
);

-- Agents de Saisie : Kouame Yvette, Koli Konan Anicet, Assoma Jean Eudes, Sarah Djambitche, Amy Karabboue, Sery Grace
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (
    SELECT `id` FROM `users` WHERE `email` IN (
        'yvette.kouame@labelleporte.ci', 'grace.kouame@labelleporte.ci',
        'anicet.koli@labelleporte.ci',
        'jeaneudes.assoma@labelleporte.ci',
        'sarah.djambitche@labelleporte.ci',
        'amy.dieng@labelleporte.ci', 'amy.karabboue@labelleporte.ci',
        'grace.sery@labelleporte.ci'
    )
);
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'agent_saisie' FROM `users` WHERE `email` IN (
    'yvette.kouame@labelleporte.ci',
    'anicet.koli@labelleporte.ci',
    'jeaneudes.assoma@labelleporte.ci',
    'sarah.djambitche@labelleporte.ci',
    'amy.dieng@labelleporte.ci', 'amy.karabboue@labelleporte.ci',
    'grace.sery@labelleporte.ci'
);

-- Caissière : Carine Abou (Mme Agbadan)
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%carine.abou%');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'caissiere' FROM `users` WHERE `email` LIKE '%carine.abou%';

-- Responsable Marketing : Wilfried Abassi
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%wilfried.abassi%');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'responsable_marketing' FROM `users` WHERE `email` LIKE '%wilfried.abassi%';

-- Agent Call Center : Mariam Lassici
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%mariam.lassici%');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'agent_call_center' FROM `users` WHERE `email` LIKE '%mariam.lassici%';

-- Responsable Groupage : Kouakou Sales
DELETE FROM `lbp_user_roles` WHERE `user_id` IN (SELECT `id` FROM `users` WHERE `email` LIKE '%sales%');
INSERT INTO `lbp_user_roles` (`user_id`, `role`)
SELECT `id`, 'agent_groupage' FROM `users` WHERE `email` LIKE '%sales%';

-- ============================================================================
-- 4. Attribution des Permissions de base (`user_permissions`)
-- ============================================================================

-- Droits CRUD pour les Chefs d'agence
INSERT INTO `user_permissions` (`user_id`, `entity_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, e.id, 1, 1, 1, 0
FROM `users` u
JOIN `permission_entities` e ON e.code IN ('colisage_colis', 'colisage_expeditions', 'crm_clients', 'saisir_facture', 'modifier_facture_apres_creation', 'rapports_agence', 'exporter_rapports_excel', 'entrepot_inventaires')
JOIN `lbp_user_roles` r ON r.user_id = u.id AND r.role = 'chef_agence'
WHERE u.email IN ('roxane.akoiblin@labelleporte.ci', 'roxane.a@labelleporte.ci', 'siaka.diarra@labelleporte.ci', 'marquez.koffi@labelleporte.ci', 'estelle.adepo@labelleporte.ci', 'esther.adepo@labelleporte.ci', 'prince.kadjo@labelleporte.ci')
ON DUPLICATE KEY UPDATE `can_view` = 1, `can_create` = 1, `can_update` = 1, `can_delete` = 0;

-- Droits CRUD pour les Agents de saisie
INSERT INTO `user_permissions` (`user_id`, `entity_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, e.id, 1, 1, 0, 0
FROM `users` u
JOIN `permission_entities` e ON e.code IN ('colisage_colis', 'crm_clients', 'saisir_facture', 'exporter_colisage_sans_montant')
JOIN `lbp_user_roles` r ON r.user_id = u.id AND r.role = 'agent_saisie'
ON DUPLICATE KEY UPDATE `can_view` = 1, `can_create` = 1, `can_update` = 0, `can_delete` = 0;

-- Droits pour la Caissière (Dokui)
INSERT INTO `user_permissions` (`user_id`, `entity_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, e.id, 1, 1, 0, 0
FROM `users` u
JOIN `permission_entities` e ON e.code IN ('saisir_facture', 'finance_retraits', 'colisage_colis', 'crm_clients', 'rapports_agence')
WHERE u.email LIKE '%carine.abou%'
ON DUPLICATE KEY UPDATE `can_view` = 1, `can_create` = 1, `can_update` = 0, `can_delete` = 0;

-- Droits pour le Responsable Marketing
INSERT INTO `user_permissions` (`user_id`, `entity_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, e.id, 1, 1, 1, 0
FROM `users` u
JOIN `permission_entities` e ON e.code IN ('crm_clients', 'crm_opportunities')
WHERE u.email LIKE '%wilfried.abassi%'
ON DUPLICATE KEY UPDATE `can_view` = 1, `can_create` = 1, `can_update` = 1, `can_delete` = 0;

-- Droits pour le Call Center
INSERT INTO `user_permissions` (`user_id`, `entity_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, e.id, 1, 1, 1, 0
FROM `users` u
JOIN `permission_entities` e ON e.code IN ('call_center_view', 'call_center_manage', 'colisage_colis', 'crm_clients')
WHERE u.email LIKE '%mariam.lassici%'
ON DUPLICATE KEY UPDATE `can_view` = 1, `can_create` = 1, `can_update` = 1, `can_delete` = 0;

-- Droits pour le Groupage
INSERT INTO `user_permissions` (`user_id`, `entity_id`, `can_view`, `can_create`, `can_update`, `can_delete`)
SELECT u.id, e.id, 1, 1, 1, 0
FROM `users` u
JOIN `permission_entities` e ON e.code IN ('colisage_colis', 'colisage_expeditions', 'entrepot_inventaires', 'exporter_colisage_sans_montant', 'crm_clients')
WHERE u.email LIKE '%sales%'
ON DUPLICATE KEY UPDATE `can_view` = 1, `can_create` = 1, `can_update` = 1, `can_delete` = 0;
