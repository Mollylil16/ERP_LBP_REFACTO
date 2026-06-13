-- 009_supervision.sql

-- 1. Table des signalements créés par les superviseurs
CREATE TABLE IF NOT EXISTS lbp_signalements (
    id SERIAL PRIMARY KEY,
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE,
    id_auteur INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'ecart_caisse', 'transaction_inhabituelle', 'autre'
    description TEXT,
    gravite VARCHAR(20) DEFAULT 'moyen', -- 'faible', 'moyen', 'critique'
    statut VARCHAR(20) DEFAULT 'ouvert', -- 'ouvert', 'resolu'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Table des demandes de justification
CREATE TABLE IF NOT EXISTS lbp_demandes_justification (
    id SERIAL PRIMARY KEY,
    id_demandeur INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    id_destinataire INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE,
    id_operation INTEGER, -- Peut être l'ID d'un mouvement de caisse ou d'un colis
    type_operation VARCHAR(50), -- 'mouvement_caisse', 'colis'
    motif TEXT NOT NULL,
    statut VARCHAR(20) DEFAULT 'en_attente', -- 'en_attente', 'repondu', 'clos'
    reponse TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Table des annotations internes (Notes invisibles pour les agences)
CREATE TABLE IF NOT EXISTS lbp_annotations_supervision (
    id SERIAL PRIMARY KEY,
    id_auteur INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    id_operation INTEGER NOT NULL,
    type_operation VARCHAR(50) NOT NULL, -- 'mouvement_caisse', 'colis'
    contenu TEXT NOT NULL,
    visibilite VARCHAR(20) DEFAULT 'direction',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Table des rapports générés
CREATE TABLE IF NOT EXISTS lbp_rapports_supervision (
    id SERIAL PRIMARY KEY,
    id_auteur INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'caisse', 'activite', 'anomalies', 'performance_agents'
    periode VARCHAR(20) NOT NULL, -- 'jour', 'semaine', 'mois', 'annee'
    id_agence INTEGER REFERENCES lbp_agences(id) ON DELETE CASCADE, -- NULL = Toutes les agences
    date_debut DATE,
    date_fin DATE,
    commentaire TEXT,
    statut_lecture VARCHAR(20) DEFAULT 'non_lu',
    id_soumis_a INTEGER REFERENCES lbp_users(id) ON DELETE SET NULL, -- Le directeur
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes pour optimiser les requêtes de supervision
CREATE INDEX IF NOT EXISTS idx_signalements_agence ON lbp_signalements(id_agence);
CREATE INDEX IF NOT EXISTS idx_signalements_statut ON lbp_signalements(statut);
CREATE INDEX IF NOT EXISTS idx_justifications_destinataire ON lbp_demandes_justification(id_destinataire);
CREATE INDEX IF NOT EXISTS idx_annotations_operation ON lbp_annotations_supervision(id_operation, type_operation);
CREATE INDEX IF NOT EXISTS idx_rapports_auteur ON lbp_rapports_supervision(id_auteur);
