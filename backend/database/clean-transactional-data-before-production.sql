-- =============================================================================
-- Nettoyage des DONNÉES DE TEST avant mise en production
-- =============================================================================
-- Conserve : agences, lbp_users, rôles, permissions, tarifs, catalogue, migrations
-- Supprime  : colis, factures, paiements, clients, expéditions, caisse (mouvements),
--             notifications opérationnelles, call center, litiges, suivi GPS test
--
-- IMPORTANT :
--   1) Faire une SAUVEGARDE complète (pg_dump) avant d'exécuter.
--   2) Exécuter sur la bonne base (ex. lbp_db), idéalement dans une transaction :
--        BEGIN;
--        (coller ce script)
--        COMMIT;   -- ou ROLLBACK; si quelque chose échoue
--   3) Si une table n'existe pas encore chez vous, commentez la ligne TRUNCATE concernée.
-- =============================================================================

BEGIN;

-- Call center (indépendant du reste)
TRUNCATE TABLE callcenter_messages RESTART IDENTITY CASCADE;
TRUNCATE TABLE callcenter_conversations RESTART IDENTITY CASCADE;

-- Litiges (référencent colis / factures / clients)
TRUNCATE TABLE lbp_litige_messages RESTART IDENTITY CASCADE;
TRUNCATE TABLE lbp_litiges RESTART IDENTITY CASCADE;

-- Paiements liés aux factures
TRUNCATE TABLE lbp_liens_paiement RESTART IDENTITY CASCADE;
TRUNCATE TABLE lbp_paiements RESTART IDENTITY CASCADE;

-- Factures (liées aux colis)
TRUNCATE TABLE lbp_factures RESTART IDENTITY CASCADE;

-- Positions traceurs (données de test)
TRUNCATE TABLE lbp_tracker_positions RESTART IDENTITY CASCADE;

-- Colis : marchandises puis colis (FK vers client, agence, expédition)
TRUNCATE TABLE lbp_marchandises RESTART IDENTITY CASCADE;

-- Délier les colis des expéditions avant de vider les expéditions
UPDATE lbp_colis SET id_expedition = NULL WHERE id_expedition IS NOT NULL;

TRUNCATE TABLE lbp_colis RESTART IDENTITY CASCADE;

TRUNCATE TABLE lbp_expeditions RESTART IDENTITY CASCADE;

-- Clients (expéditeurs en base)
TRUNCATE TABLE lbp_clients RESTART IDENTITY CASCADE;

-- Caisse : workflows / audit / mouvements / sessions (les lignes lbp_caisses restent)
TRUNCATE TABLE lbp_caisse_mouvement_workflows RESTART IDENTITY CASCADE;
TRUNCATE TABLE lbp_caisse_audit_logs RESTART IDENTITY CASCADE;
TRUNCATE TABLE lbp_mouvements_caisse RESTART IDENTITY CASCADE;
TRUNCATE TABLE lbp_caisse_sessions RESTART IDENTITY CASCADE;

-- Notifications générées (alertes solde, etc.)
TRUNCATE TABLE lbp_notifications RESTART IDENTITY CASCADE;

-- Optionnel : journal d'audit applicatif (décommentez si vous voulez repartir à zéro)
-- TRUNCATE TABLE audit_logs RESTART IDENTITY CASCADE;

COMMIT;

-- =============================================================================
-- Après exécution : reconnectez-vous à l'app ; les listes colis / factures / clients
-- doivent être vides. Les comptes utilisateurs et les agences ne sont pas modifiés.
-- =============================================================================
