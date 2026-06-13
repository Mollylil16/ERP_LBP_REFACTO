-- 006_international_expansion.sql

-- ==========================================
-- PHASE 1: LOGISTIQUE (RETRAIT ET INVENTAIRE)
-- ==========================================

-- Informations de retrait sur le colis
ALTER TABLE lbp_colis ADD COLUMN IF NOT EXISTS nom_recuperateur VARCHAR(255);
ALTER TABLE lbp_colis ADD COLUMN IF NOT EXISTS cni_recuperateur VARCHAR(255);
ALTER TABLE lbp_colis ADD COLUMN IF NOT EXISTS telephone_recuperateur VARCHAR(50);
ALTER TABLE lbp_colis ADD COLUMN IF NOT EXISTS date_retrait TIMESTAMP WITH TIME ZONE;

-- Table des inventaires
CREATE TABLE IF NOT EXISTS lbp_inventaires (
    id SERIAL PRIMARY KEY,
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE,
    id_createur INTEGER REFERENCES lbp_users(id) ON DELETE SET NULL,
    date_inventaire TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    statut VARCHAR(50) DEFAULT 'EN_COURS', -- EN_COURS, CLOTURE
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Lignes d'inventaire
CREATE TABLE IF NOT EXISTS lbp_inventaire_lignes (
    id SERIAL PRIMARY KEY,
    id_inventaire INTEGER REFERENCES lbp_inventaires(id) ON DELETE CASCADE,
    id_colis INTEGER REFERENCES lbp_colis(id) ON DELETE CASCADE,
    statut_constate VARCHAR(50) NOT NULL, -- PRESENT, MANQUANT, ENDOMMAGE
    commentaire TEXT
);

-- ==========================================
-- PHASE 2: FINANCE (DEVISES ET POINTS DE CAISSE)
-- ==========================================

-- Gestion des devises sur les factures
ALTER TABLE lbp_factures ADD COLUMN IF NOT EXISTS taux_change_eur_xof DECIMAL(10, 4) DEFAULT 655.957;
ALTER TABLE lbp_factures ADD COLUMN IF NOT EXISTS montant_xof DECIMAL(15, 2);
ALTER TABLE lbp_factures ADD COLUMN IF NOT EXISTS montant_eur DECIMAL(15, 2);
ALTER TABLE lbp_factures ADD COLUMN IF NOT EXISTS code_imputation VARCHAR(100);

-- Moyens de paiement sur les paiements existants
ALTER TABLE lbp_paiements ADD COLUMN IF NOT EXISTS moyen_paiement VARCHAR(100);
ALTER TABLE lbp_paiements ADD COLUMN IF NOT EXISTS reference_paiement VARCHAR(255);

-- Points de caisse journaliers
CREATE TABLE IF NOT EXISTS lbp_points_caisse (
    id SERIAL PRIMARY KEY,
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE,
    id_caissiere INTEGER REFERENCES lbp_users(id) ON DELETE SET NULL,
    date_point DATE NOT NULL,
    total_encaisse_xof DECIMAL(15, 2) DEFAULT 0,
    total_encaisse_eur DECIMAL(15, 2) DEFAULT 0,
    statut VARCHAR(50) DEFAULT 'SOUMIS', -- SOUMIS, VALIDE, REJETE
    id_validateur INTEGER REFERENCES lbp_users(id) ON DELETE SET NULL,
    date_validation TIMESTAMP WITH TIME ZONE,
    commentaire TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Notifications de paiement
CREATE TABLE IF NOT EXISTS lbp_notifications_paiement (
    id SERIAL PRIMARY KEY,
    id_facture INTEGER REFERENCES lbp_factures(id) ON DELETE CASCADE,
    id_paiement INTEGER REFERENCES lbp_paiements(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    lu BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- PHASE 3: CARTOGRAPHIE ET EXPEDITIONS
-- ==========================================

-- Table des expéditions (Voyages/Manifestes)
CREATE TABLE IF NOT EXISTS lbp_expeditions (
    id SERIAL PRIMARY KEY,
    numero_expedition VARCHAR(100) UNIQUE NOT NULL,
    type VARCHAR(50) NOT NULL, -- AERIEN, MARITIME, TERRESTRE
    id_agence_depart INTEGER REFERENCES lbp_agences(id) ON DELETE SET NULL,
    id_agence_arrivee INTEGER REFERENCES lbp_agences(id) ON DELETE SET NULL,
    date_depart TIMESTAMP WITH TIME ZONE,
    date_arrivee_prevue TIMESTAMP WITH TIME ZONE,
    statut VARCHAR(50) DEFAULT 'EN_PREPARATION', -- EN_PREPARATION, EN_TRANSIT, ARRIVE
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Lier les colis à une expédition
ALTER TABLE lbp_colis ADD COLUMN IF NOT EXISTS id_expedition INTEGER REFERENCES lbp_expeditions(id) ON DELETE SET NULL;

-- Table de tracking GPS/Étapes pour les expéditions
CREATE TABLE IF NOT EXISTS lbp_tracking_gps (
    id SERIAL PRIMARY KEY,
    id_expedition INTEGER REFERENCES lbp_expeditions(id) ON DELETE CASCADE,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    etape_description VARCHAR(255) NOT NULL, -- ex: "Arrivée à l'aéroport CDG", "Dédouanement"
    date_enregistrement TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
