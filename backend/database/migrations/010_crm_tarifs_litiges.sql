-- 010_crm_tarifs_litiges.sql

-- 1. Modification ou assurance de l'existence de la table lbp_clients (CRM)
-- (La table lbp_clients a déjà été créée dans 001_colissage, mais on peut ajouter des champs CRM ici si nécessaire, par exemple un score ou des notes internes)
ALTER TABLE lbp_clients ADD COLUMN IF NOT EXISTS notes_internes TEXT NULL;
ALTER TABLE lbp_clients ADD COLUMN IF NOT EXISTS type_piece_identite VARCHAR(50) NULL;
ALTER TABLE lbp_clients ADD COLUMN IF NOT EXISTS numero_piece_identite VARCHAR(100) NULL;

-- 2. Table des Tarifs & Catalogue (Paramétrage)
CREATE TABLE IF NOT EXISTS lbp_tarifs (
    id SERIAL PRIMARY KEY,
    type_tarif VARCHAR(50) NOT NULL, -- 'KILO_AERIEN', 'KILO_MARITIME', 'FORFAIT_LETTRE', 'PRODUIT_CATALOGUE'
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    pays_depart VARCHAR(2) NOT NULL, -- 'FR', 'CI', 'SN'
    pays_arrivee VARCHAR(2) NOT NULL, -- 'CI', 'FR', 'SN'
    montant DECIMAL(10,2) NOT NULL,
    devise VARCHAR(3) DEFAULT 'XOF', -- 'XOF', 'EUR'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Table des Litiges (SAV)
CREATE TABLE IF NOT EXISTS lbp_litiges (
    id SERIAL PRIMARY KEY,
    numero_litige VARCHAR(50) UNIQUE NOT NULL, -- ex: LIT-26-0001
    id_client INTEGER REFERENCES lbp_clients(id) ON DELETE CASCADE,
    id_colis INTEGER NULL REFERENCES lbp_colis(id) ON DELETE SET NULL,
    type_litige VARCHAR(50) NOT NULL, -- 'PERTE', 'CASSE', 'RETARD', 'AUTRE'
    description TEXT NOT NULL,
    statut VARCHAR(20) DEFAULT 'OUVERT', -- 'OUVERT', 'EN_COURS', 'RESOLU', 'CLOS'
    priorite VARCHAR(20) DEFAULT 'MOYENNE', -- 'FAIBLE', 'MOYENNE', 'HAUTE', 'URGENTE'
    id_assigne_a INTEGER NULL REFERENCES lbp_users(id) ON DELETE SET NULL, -- L'agent qui traite le litige
    resolution_note TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Index pour optimiser les recherches
CREATE INDEX IF NOT EXISTS idx_tarifs_trajet ON lbp_tarifs(pays_depart, pays_arrivee, is_active);
CREATE INDEX IF NOT EXISTS idx_litiges_client ON lbp_litiges(id_client);
CREATE INDEX IF NOT EXISTS idx_litiges_statut ON lbp_litiges(statut);
