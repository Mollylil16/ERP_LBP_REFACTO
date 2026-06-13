BEGIN;

CREATE TABLE IF NOT EXISTS lbp_livreurs (
  id SERIAL PRIMARY KEY,
  id_user INT NOT NULL UNIQUE REFERENCES lbp_users(id) ON DELETE CASCADE,
  vehicule VARCHAR(100) NULL,
  immatriculation VARCHAR(50) NULL,
  disponible BOOLEAN DEFAULT TRUE,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  derniere_localisation TIMESTAMP WITH TIME ZONE NULL
);

CREATE TABLE IF NOT EXISTS lbp_expeditions (
  id SERIAL PRIMARY KEY,
  reference VARCHAR(100) NOT NULL UNIQUE,
  statut VARCHAR(50) DEFAULT 'EN_PREPARATION',
  id_livreur INT NULL REFERENCES lbp_livreurs(id) ON DELETE SET NULL,
  id_agence_depart INT NULL REFERENCES lbp_agences(id) ON DELETE SET NULL,
  id_agence_arrivee INT NULL REFERENCES lbp_agences(id) ON DELETE SET NULL,
  date_depart TIMESTAMP WITH TIME ZONE NULL,
  date_arrivee_prevue TIMESTAMP WITH TIME ZONE NULL,
  date_arrivee_reelle TIMESTAMP WITH TIME ZONE NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS lbp_expedition_colis (
  id_expedition INT NOT NULL REFERENCES lbp_expeditions(id) ON DELETE CASCADE,
  id_colis INT NOT NULL REFERENCES lbp_colis(id) ON DELETE CASCADE,
  PRIMARY KEY (id_expedition, id_colis)
);

CREATE INDEX IF NOT EXISTS idx_lbp_expeditions_reference ON lbp_expeditions(reference);
CREATE INDEX IF NOT EXISTS idx_lbp_expeditions_livreur ON lbp_expeditions(id_livreur);

COMMIT;
