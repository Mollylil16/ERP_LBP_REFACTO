BEGIN;

CREATE TABLE IF NOT EXISTS lbp_clients (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  prenom VARCHAR(150) NULL,
  telephone VARCHAR(50) NULL,
  email VARCHAR(150) NULL,
  adresse TEXT NULL,
  type_client VARCHAR(50) DEFAULT 'STANDARD',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS lbp_colis (
  id SERIAL PRIMARY KEY,
  numero_tracking VARCHAR(100) NOT NULL UNIQUE,
  statut VARCHAR(50) DEFAULT 'RECEPTIONNE',
  poids DECIMAL(10,2) NULL,
  valeur_declaree DECIMAL(10,2) NULL,
  id_expediteur INT NULL REFERENCES lbp_clients(id) ON DELETE SET NULL,
  id_destinataire INT NULL REFERENCES lbp_clients(id) ON DELETE SET NULL,
  id_agence_depart INT NULL REFERENCES lbp_agences(id) ON DELETE SET NULL,
  id_agence_arrivee INT NULL REFERENCES lbp_agences(id) ON DELETE SET NULL,
  id_createur INT NULL REFERENCES lbp_users(id) ON DELETE SET NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS lbp_marchandises (
  id SERIAL PRIMARY KEY,
  id_colis INT NOT NULL REFERENCES lbp_colis(id) ON DELETE CASCADE,
  description TEXT NOT NULL,
  quantite INT DEFAULT 1,
  poids_unitaire DECIMAL(10,2) NULL
);

CREATE INDEX IF NOT EXISTS idx_lbp_colis_tracking ON lbp_colis(numero_tracking);
CREATE INDEX IF NOT EXISTS idx_lbp_colis_expediteur ON lbp_colis(id_expediteur);
CREATE INDEX IF NOT EXISTS idx_lbp_colis_destinataire ON lbp_colis(id_destinataire);
CREATE INDEX IF NOT EXISTS idx_lbp_colis_statut ON lbp_colis(statut);

COMMIT;
