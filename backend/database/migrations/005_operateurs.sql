-- 005_operateurs.sql

-- 1. Table des opérateurs liés à une agence (pour les comptes uniques)
CREATE TABLE IF NOT EXISTS lbp_operateurs (
    id SERIAL PRIMARY KEY,
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE,
    nom_complet VARCHAR(255) NOT NULL,
    code_secret_hash VARCHAR(255) NOT NULL,
    "isActive" BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Ajout de la colonne id_operateur sur les tables critiques
ALTER TABLE lbp_colis ADD COLUMN IF NOT EXISTS id_operateur INTEGER REFERENCES lbp_operateurs(id) ON DELETE SET NULL;
ALTER TABLE lbp_factures ADD COLUMN IF NOT EXISTS id_operateur INTEGER REFERENCES lbp_operateurs(id) ON DELETE SET NULL;
ALTER TABLE lbp_paiements ADD COLUMN IF NOT EXISTS id_operateur INTEGER REFERENCES lbp_operateurs(id) ON DELETE SET NULL;
