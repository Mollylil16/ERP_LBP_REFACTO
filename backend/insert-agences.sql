-- Insert all LBP agencies
INSERT INTO agences (code, nom, pays, ville, adresse, telephone, email, nom_responsable, tel_responsable, devise, actif, created_at, updated_at)
VALUES
    ('CI-AEROPORT', 'AEROPORT FELIX HOUPHOUET BOIGNY ZONE FRET', 'Côte d''Ivoire', 'Abidjan', 'Zone Fret Aéroport FHB', '0500200376', 'aeroport@lbp-ci.com', 'Adepo Marie-Estelle', '+225 0508003635', 'XOF', true, NOW(), NOW()),
    ('CI-ADJAME', 'ADJAME RENAULT NON LOIN DE LA PHARMACIE LATIN', 'Côte d''Ivoire', 'Abidjan Adjamé', 'Adjamé Renault, près Pharmacie Latin', '0500200474', 'adjame@lbp-ci.com', 'Akoiblin Roxanne', '+225 0508003635', 'XOF', true, NOW(), NOW()),
    ('CI-ABOBO', 'LBP COTE D''IVOIRE ABOBO', 'Côte d''Ivoire', 'Abidjan Abobo', 'Abobo', NULL, NULL, NULL, NULL, 'XOF', true, NOW(), NOW()),
    ('DG', 'DIRECTION GENERALE', 'Côte d''Ivoire', 'ABIDJAN', 'Abidjan', '2721580978', 'direction@lbp-ci.com', NULL, NULL, 'XOF', true, NOW(), NOW()),
    ('FR-PARIS', 'LBP FRANCE', 'FRANCE', 'PARIS', 'PARIS 17 CHEMIN DES VIGNES 93000 BOBIGNY', '+33775732797', 'ospfrance@stt-inter.com', 'KADJO', '+33 751 1983 82', 'EUR', true, NOW(), NOW()),
    ('SN-DAKAR', 'LBP SENEGAL', 'SENEGAL', 'DAKAR', 'DAKAR PARCELLES ASSAINIES', NULL, 'senegal@stt-inter.com', NULL, NULL, 'XOF', true, NOW(), NOW())
ON CONFLICT (code) DO NOTHING;
