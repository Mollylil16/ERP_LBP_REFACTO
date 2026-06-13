-- 004_security_rh.sql

-- 1. Table des permissions système
CREATE TABLE IF NOT EXISTS lbp_permissions (
    id SERIAL PRIMARY KEY,
    code VARCHAR(100) UNIQUE NOT NULL,
    description TEXT
);

-- Insertion de permissions de base
INSERT INTO lbp_permissions (code, description) VALUES
('users.read', 'Voir les utilisateurs'),
('users.create', 'Créer des utilisateurs'),
('users.update', 'Modifier des utilisateurs'),
('users.delete', 'Supprimer des utilisateurs'),
('dashboard.view', 'Voir le tableau de bord'),
('colis.read', 'Voir les colis'),
('colis.create', 'Créer des colis'),
('colis.update', 'Modifier des colis'),
('clients.read', 'Voir les clients'),
('clients.create', 'Créer des clients'),
('expeditions.read', 'Voir les expéditions'),
('expeditions.create', 'Créer des expéditions'),
('expeditions.update', 'Modifier les expéditions'),
('factures.read', 'Voir les factures'),
('factures.create', 'Créer des factures'),
('paiements.read', 'Voir les paiements'),
('paiements.create', 'Créer des paiements')
ON CONFLICT (code) DO NOTHING;

-- 2. Table de liaison Utilisateur <-> Permissions
CREATE TABLE IF NOT EXISTS lbp_user_permissions (
    id_user INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    id_permission INTEGER REFERENCES lbp_permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (id_user, id_permission)
);

-- Migration optionnelle: Attribuer des permissions aux SUPER_ADMIN et ADMIN existants
INSERT INTO lbp_user_permissions (id_user, id_permission)
SELECT u.id, p.id
FROM lbp_users u
CROSS JOIN lbp_permissions p
WHERE u.id_role IN (SELECT id FROM lbp_roles WHERE nom IN ('SUPER_ADMIN', 'ADMIN'))
ON CONFLICT DO NOTHING;

-- 3. Table de suivi de géolocalisation pour le changement d'agence
CREATE TABLE IF NOT EXISTS lbp_user_locations_log (
    id SERIAL PRIMARY KEY,
    id_user INTEGER REFERENCES lbp_users(id) ON DELETE CASCADE,
    agence_id_session INTEGER REFERENCES lbp_agences(id) ON DELETE SET NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    ip_address VARCHAR(45),
    date_connexion TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
