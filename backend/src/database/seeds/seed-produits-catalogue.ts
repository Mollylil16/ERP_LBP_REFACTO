import { DataSource } from 'typeorm';
import { ProduitCatalogue, CategoriesProduit, NaturePrix } from '../../produits-catalogue/entities/produit-catalogue.entity';

export async function seedProduitsCatalogue(dataSource: DataSource) {
    const produitRepository = dataSource.getRepository(ProduitCatalogue);

    // Check if products already exist
    const count = await produitRepository.count();
    if (count > 0) {
        console.log('✅ Produits catalogue already seeded');
        return;
    }

    const produits = [
        // ========== CATÉGORIE: DENRÉE ==========
        // Prix unitaire 850 FCFA pour tout ce bloc
        { nom: 'ATTIEKE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'PLACALI', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'GARI', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'POUDRE DE CACAO', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'GOMBO', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'GNANGNAN', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'FEUILLE DE PATATE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'SOUMARA', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: "PATE D'ARACHIDE", categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'BANANE PLANTIN', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'CHIPS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'BISSAP', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'TAMARIN', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'PATE DE GINGEMBRE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },

        // Spécifiques Forfait 3500 (0-4kg)
        { nom: 'POUDRE DE MIL', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_FORFAITAIRE, prix_unitaire: 850, prix_forfaitaire: 3500, poids_min: 0, poids_max: 4 },
        { nom: 'POUDRE DE MAÏS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_FORFAITAIRE, description: 'À partir de 5 kg', prix_unitaire: 850, prix_forfaitaire: 3500, poids_min: 0, poids_max: 4 },

        // Reste des DENREE à 850
        { nom: 'POUDRE DE GOMBO', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'MIL', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'HARICOT', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'TCHONGON', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'AROME', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'GRAINE PILE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'EPICE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'MAIS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'GNONMI', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'FONIO', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'BAOBAB', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'BONBON', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'CACAHOUETTE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'PIMENT', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },
        { nom: 'CROQUETTE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 850 },

        // ========== CATÉGORIE: HUILE ET KARITE ==========
        // Unit 1000 pour tous
        { nom: 'PETIT COLAS', categorie: CategoriesProduit.HUILE_ET_KARITE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1000 },
        { nom: 'HUILE DE COCO', categorie: CategoriesProduit.HUILE_ET_KARITE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1000 },
        { nom: 'BEURRE DE KARITE', categorie: CategoriesProduit.HUILE_ET_KARITE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1000, prix_forfaitaire: 4500, poids_min: 0, poids_max: 4 },
        { nom: 'KINKELIBA', categorie: CategoriesProduit.HUILE_ET_KARITE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1000 },
        { nom: 'DJEKA', categorie: CategoriesProduit.HUILE_ET_KARITE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1000 },

        // ========== CATÉGORIE: DIVERS ==========
        // Unit 1780, Forfait 5000 (0-2kg)
        { nom: 'VETEMENTS', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'CHAUSSURES', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'MECHE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'DRAPS', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'OUVRAGE EN PLASTIQUE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'USTENSILES DE CUISINE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'VALISE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'ENCENS', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'SAVOIR NOIR', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'SAC A MAIN', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'ECORCE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'NEP NEP', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1780, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },

        { nom: 'INDIGENAT LIQUIDE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 2000, prix_forfaitaire: 5000, poids_min: 0, poids_max: 2 },
        { nom: 'ATTOTE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1500 },
        { nom: 'HUILE ROUGE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1500 },
        { nom: 'BOUILLONS', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1500, prix_forfaitaire: 3500, poids_min: 0, poids_max: 2 },
        { nom: 'CUBE MAGGI', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1500 },

        // Bloc 3500
        { nom: 'VETEMENTS DE MARQUE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 3500, prix_forfaitaire: 8500, poids_min: 0, poids_max: 2 },
        { nom: 'PAGNE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 3500 },
        { nom: 'CHAUSSURES DE MARQUE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 3500, prix_forfaitaire: 8500, poids_min: 0, poids_max: 2 },
        { nom: 'SACS DE MARQUE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 3500 },

        // ========== CATÉGORIE: COLIS RAPIDE EXPORT ==========
        { nom: 'POISSON FUME-CREVETTE-ESCARGOT-POULET FUME', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, prix_forfaitaire: 7500 },
        { nom: 'COSMETIQUE', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5750 },
    ];

    await produitRepository.save(produits);
    console.log(`✅ ${produits.length} produits catalogue insérés avec succès`);
}
