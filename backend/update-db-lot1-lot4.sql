-- LBP - Mise a jour base de donnees Lot 1 -> Lot 4
-- Script idempotent (rejouable sans casser l'existant)

BEGIN;

-- 1) Colonnes utilisateurs (compatibilite prod)
ALTER TABLE IF EXISTS lbp_users
    ADD COLUMN IF NOT EXISTS password_plain text,
    ADD COLUMN IF NOT EXISTS must_change_password boolean DEFAULT true,
    ADD COLUMN IF NOT EXISTS agence_selected boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS phone varchar(20),
    ADD COLUMN IF NOT EXISTS email varchar(100),
    ADD COLUMN IF NOT EXISTS role_id integer,
    ADD COLUMN IF NOT EXISTS peut_voir_toutes_agences boolean DEFAULT false;

-- 2) Colonnes agences (compatibilite geoloc)
ALTER TABLE IF EXISTS agences
    ADD COLUMN IF NOT EXISTS latitude double precision,
    ADD COLUMN IF NOT EXISTS longitude double precision,
    ADD COLUMN IF NOT EXISTS place_id varchar(255);

-- 3) Enum session caisse
DO $$
BEGIN
    CREATE TYPE public.lbp_caisse_sessions_status_enum AS ENUM ('OPEN', 'CLOSED');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

-- 4) Table sessions caisse
CREATE TABLE IF NOT EXISTS lbp_caisse_sessions (
    id SERIAL PRIMARY KEY,
    status public.lbp_caisse_sessions_status_enum NOT NULL DEFAULT 'OPEN',
    date_journee date NOT NULL,
    solde_ouverture_theorique numeric(12,2) NOT NULL DEFAULT 0,
    solde_ouverture_reel numeric(12,2) NOT NULL DEFAULT 0,
    solde_fermeture_theorique numeric(12,2),
    solde_fermeture_reel numeric(12,2),
    ecart_ouverture numeric(12,2),
    ecart_fermeture numeric(12,2),
    opened_by varchar(100),
    closed_by varchar(100),
    note_ouverture text,
    note_fermeture text,
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now(),
    id_caisse integer NOT NULL
);

DO $$
BEGIN
    ALTER TABLE lbp_caisse_sessions
        ADD CONSTRAINT fk_lbp_caisse_sessions_id_caisse
        FOREIGN KEY (id_caisse) REFERENCES lbp_caisses(id)
        ON DELETE NO ACTION ON UPDATE NO ACTION;
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE INDEX IF NOT EXISTS idx_lbp_caisse_sessions_caisse_status
    ON lbp_caisse_sessions (id_caisse, status);

-- 5) Enums workflow mouvements
DO $$
BEGIN
    CREATE TYPE public.lbp_caisse_mouvement_workflows_mouvement_type_enum
    AS ENUM ('APPRO', 'DECAISSEMENT', 'ENTREE_CHEQUE', 'ENTREE_ESPECE', 'ENTREE_VIREMENT');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE public.lbp_caisse_mouvement_workflows_status_enum
    AS ENUM ('DRAFT', 'SUBMITTED', 'VALIDATED', 'REJECTED');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

-- 6) Table workflow mouvements + justificatifs
CREATE TABLE IF NOT EXISTS lbp_caisse_mouvement_workflows (
    id SERIAL PRIMARY KEY,
    mouvement_id integer NOT NULL UNIQUE,
    mouvement_type public.lbp_caisse_mouvement_workflows_mouvement_type_enum NOT NULL,
    status public.lbp_caisse_mouvement_workflows_status_enum NOT NULL DEFAULT 'DRAFT',
    validation_level_required integer NOT NULL DEFAULT 1,
    validation_level_current integer NOT NULL DEFAULT 0,
    submitted_by varchar(100),
    submitted_at timestamp,
    approved_by varchar(100),
    approved_at timestamp,
    rejection_reason text,
    justificatif_url varchar(500),
    created_at timestamp NOT NULL DEFAULT now(),
    updated_at timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_lbp_caisse_workflows_status
    ON lbp_caisse_mouvement_workflows (status);

-- 7) Journal d'audit caisse
CREATE TABLE IF NOT EXISTS lbp_caisse_audit_logs (
    id SERIAL PRIMARY KEY,
    action varchar(100) NOT NULL,
    mouvement_id integer,
    session_id integer,
    actor_username varchar(100),
    before_data jsonb,
    after_data jsonb,
    ip_address varchar(45),
    created_at timestamp NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_lbp_caisse_audit_created_at
    ON lbp_caisse_audit_logs (created_at);

COMMIT;

