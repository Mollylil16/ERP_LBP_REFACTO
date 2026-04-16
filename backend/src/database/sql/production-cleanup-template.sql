/*
  Nettoyage PRODUCTION (template) — LBP

  Objectif :
  - Supprimer des données "test" (colis/groupages/autres envois, factures, paiements, crédits inter-agences)
  - Sans casser les contraintes FK
  - Avec mode DRY RUN (par défaut) pour valider l'impact avant suppression

  IMPORTANT
  - Exécuter d’abord en DRY RUN (ROLLBACK), puis en COMMIT.
  - Toujours filtrer par période et/ou par agence et/ou par username/code_user.
  - Adapter les filtres "LIKE 'LBP-TST-%'" à vos vrais préfixes de test.
*/

BEGIN;

-- =========================
-- PARAMÈTRES (à adapter)
-- =========================
-- Fenêtre temporelle
-- \set date_debut '2026-04-01'
-- \set date_fin   '2026-04-16'

-- Filtre agence (optionnel)
-- \set id_agence  3

-- Filtre "test" par code_user / username / ref_colis (optionnel)
-- Exemple : colis refs générées en test
-- \set ref_prefix 'LBP-TST-%'

-- =========================
-- 1) LISTE CIBLE (DRY RUN)
-- =========================
-- Colis ciblés
WITH cible_colis AS (
  SELECT c.id, c.ref_colis, c.created_at, c.id_agence
  FROM lbp_colis c
  WHERE 1=1
    -- AND c.created_at::date BETWEEN :'date_debut'::date AND :'date_fin'::date
    -- AND c.id_agence = :'id_agence'::int
    -- AND c.ref_colis LIKE :'ref_prefix'
)
SELECT
  (SELECT COUNT(*) FROM cible_colis) AS nb_colis,
  (SELECT COUNT(*) FROM lbp_factures f JOIN cible_colis cc ON cc.id=f.id_colis) AS nb_factures,
  (SELECT COUNT(*) FROM lbp_paiements p JOIN lbp_factures f ON f.id=p.id_facture JOIN cible_colis cc ON cc.id=f.id_colis) AS nb_paiements,
  (SELECT COUNT(*) FROM lbp_credits_colis cr JOIN cible_colis cc ON cc.id=cr.id_colis) AS nb_credits
;

-- =========================
-- 2) SUPPRESSIONS (ordre FK)
-- =========================
-- A) Paiements
DELETE FROM lbp_paiements p
USING lbp_factures f, lbp_colis c
WHERE p.id_facture = f.id
  AND f.id_colis = c.id
  AND 1=1
  -- AND c.created_at::date BETWEEN :'date_debut'::date AND :'date_fin'::date
  -- AND c.id_agence = :'id_agence'::int
  -- AND c.ref_colis LIKE :'ref_prefix'
;

-- B) Crédits inter-agences
DELETE FROM lbp_credits_colis cr
USING lbp_colis c
WHERE cr.id_colis = c.id
  AND 1=1
  -- AND c.created_at::date BETWEEN :'date_debut'::date AND :'date_fin'::date
  -- AND c.id_agence = :'id_agence'::int
  -- AND c.ref_colis LIKE :'ref_prefix'
;

-- C) Factures
DELETE FROM lbp_factures f
USING lbp_colis c
WHERE f.id_colis = c.id
  AND 1=1
  -- AND c.created_at::date BETWEEN :'date_debut'::date AND :'date_fin'::date
  -- AND c.id_agence = :'id_agence'::int
  -- AND c.ref_colis LIKE :'ref_prefix'
;

-- D) Marchandises (si pas cascade)
DELETE FROM lbp_marchandises m
USING lbp_colis c
WHERE m.id_colis = c.id
  AND 1=1
  -- AND c.created_at::date BETWEEN :'date_debut'::date AND :'date_fin'::date
  -- AND c.id_agence = :'id_agence'::int
  -- AND c.ref_colis LIKE :'ref_prefix'
;

-- E) Colis
DELETE FROM lbp_colis c
WHERE 1=1
  -- AND c.created_at::date BETWEEN :'date_debut'::date AND :'date_fin'::date
  -- AND c.id_agence = :'id_agence'::int
  -- AND c.ref_colis LIKE :'ref_prefix'
;

-- =========================
-- MODE DRY RUN (par défaut)
-- =========================
-- ROLLBACK;  -- laisser ROLLBACK au 1er passage
COMMIT;       -- passer à COMMIT uniquement après validation des compteurs

