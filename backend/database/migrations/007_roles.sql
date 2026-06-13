-- 007_roles.sql

CREATE TABLE IF NOT EXISTS lbp_roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lbp_role_permissions (
    id_role INTEGER REFERENCES lbp_roles(id) ON DELETE CASCADE,
    id_permission INTEGER REFERENCES lbp_permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (id_role, id_permission)
);

-- Ajouter la colonne id_role à lbp_users
ALTER TABLE lbp_users ADD COLUMN IF NOT EXISTS id_role INTEGER REFERENCES lbp_roles(id) ON DELETE SET NULL;

-- Insérer les rôles fondamentaux fournis par l'entreprise
INSERT INTO lbp_roles (name, description) VALUES
('Directeur Général', 'Accès total global'),
('Assistante DG', 'Accès global étendu'),
('Chef d\'agence', 'Maître de son agence, supervise les agents locaux'),
('Caissière Principale', 'Gestion financière globale et validation des clôtures de caisse'),
('Caissier', 'Gestion de la caisse locale de son agence'),
('Comptabilité Finance', 'Équipe comptable et financière globale'),
('Agent Exploitation', 'Suivi global logistique inter-pays (Cartographie, Expéditions)'),
('Agent Groupage', 'Logistique locale, confection des colis groupés'),
('Groupeurs', 'Opérateurs de groupage'),
('Call Center', 'Service client et support téléphonique'),
('RH', 'Ressources Humaines globale'),
('Superviseur Général', 'Supervision lecture seule de l\'ensemble du réseau LBP'),
('Superviseur Régional', 'Supervision lecture seule de toutes les agences de son pays')
ON CONFLICT (name) DO NOTHING;
