-- 008_suivi_caisse.sql

-- 1. Table des séquences pour la génération automatique de numéros
CREATE TABLE IF NOT EXISTS lbp_numeros_sequences (
    id SERIAL PRIMARY KEY,
    type VARCHAR(50) UNIQUE NOT NULL, -- 'DOSSIER', 'FICHE_RECETTE', 'BORDEREAU_VI', 'ORDRE_DEC'
    prefixe VARCHAR(10),
    annee INTEGER,
    mois INTEGER,
    numero INTEGER DEFAULT 0,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Table des caisses par agence
CREATE TABLE IF NOT EXISTS lbp_caisses (
    id SERIAL PRIMARY KEY,
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE UNIQUE, -- Une caisse par agence
    solde_actuel DECIMAL(15,2) DEFAULT 0,
    statut VARCHAR(50) DEFAULT 'OUVERTE', -- OUVERTE, FERMEE
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Table des mouvements de caisse
CREATE TABLE IF NOT EXISTS lbp_mouvements_caisse (
    id SERIAL PRIMARY KEY,
    id_caisse INTEGER REFERENCES lbp_caisses(id) ON DELETE CASCADE,
    id_createur INTEGER REFERENCES lbp_users(id) ON DELETE SET NULL,
    date_mouvement TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    type_mouvement VARCHAR(50) NOT NULL, -- APPRO, DECAISSEMENT, ENTREE
    libelle VARCHAR(300),
    montant DECIMAL(15,2) NOT NULL,
    solde_apres_operation DECIMAL(15,2) NOT NULL,
    mode_reglement VARCHAR(50), -- ESPECE, CHEQUE, VIREMENT
    numero_dossier VARCHAR(50), -- Ex: LB-CI 001
    numero_piece VARCHAR(50), -- Chèque, Virement, etc.
    numero_fiche_recette VARCHAR(50), -- FR...
    numero_bordereau_versement VARCHAR(50), -- BVI...
    numero_ordre_decaissement VARCHAR(50), -- DEC...
    nom_client VARCHAR(255),
    nom_demandeur VARCHAR(255),
    etat INTEGER DEFAULT 1, -- 0 = Brouillon, 1 = Validé
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Index pour la recherche rapide
CREATE INDEX IF NOT EXISTS idx_mouvements_caisse_date ON lbp_mouvements_caisse(date_mouvement);
CREATE INDEX IF NOT EXISTS idx_mouvements_caisse_type ON lbp_mouvements_caisse(type_mouvement);
CREATE INDEX IF NOT EXISTS idx_mouvements_caisse_idcaisse ON lbp_mouvements_caisse(id_caisse);
