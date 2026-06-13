BEGIN;

CREATE TABLE IF NOT EXISTS lbp_factures (
  id SERIAL PRIMARY KEY,
  numero VARCHAR(100) NOT NULL UNIQUE,
  montant_total DECIMAL(12,2) NOT NULL,
  statut VARCHAR(50) DEFAULT 'NON_PAYEE',
  id_client INT NOT NULL REFERENCES lbp_clients(id) ON DELETE RESTRICT,
  id_createur INT NULL REFERENCES lbp_users(id) ON DELETE SET NULL,
  date_emission TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  date_echeance TIMESTAMP WITH TIME ZONE NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS lbp_facture_colis (
  id_facture INT NOT NULL REFERENCES lbp_factures(id) ON DELETE CASCADE,
  id_colis INT NOT NULL REFERENCES lbp_colis(id) ON DELETE CASCADE,
  PRIMARY KEY (id_facture, id_colis)
);

CREATE TABLE IF NOT EXISTS lbp_paiements (
  id SERIAL PRIMARY KEY,
  reference VARCHAR(100) NULL,
  montant DECIMAL(12,2) NOT NULL,
  mode_paiement VARCHAR(50) NOT NULL,
  id_facture INT NOT NULL REFERENCES lbp_factures(id) ON DELETE CASCADE,
  id_caissier INT NULL REFERENCES lbp_users(id) ON DELETE SET NULL,
  date_paiement TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS lbp_caisses (
  id SERIAL PRIMARY KEY,
  nom_caisse VARCHAR(100) NOT NULL,
  solde_actuel DECIMAL(15,2) DEFAULT 0.00,
  id_agence INT NOT NULL REFERENCES lbp_agences(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lbp_transactions_caisse (
  id SERIAL PRIMARY KEY,
  id_caisse INT NOT NULL REFERENCES lbp_caisses(id) ON DELETE CASCADE,
  type_transaction VARCHAR(50) NOT NULL, -- ENTREE / SORTIE
  montant DECIMAL(12,2) NOT NULL,
  description TEXT NULL,
  id_user INT NULL REFERENCES lbp_users(id) ON DELETE SET NULL,
  date_transaction TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_lbp_factures_numero ON lbp_factures(numero);
CREATE INDEX IF NOT EXISTS idx_lbp_factures_client ON lbp_factures(id_client);

COMMIT;
