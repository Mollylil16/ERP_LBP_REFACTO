import { DataSource } from 'typeorm';
import {
  ProduitCatalogue,
  CategoriesProduit,
  NaturePrix,
} from '../../produits-catalogue/entities/produit-catalogue.entity';

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
    // Taux groupage cargo « denrées alimentaires » au kg (tarif affiché agence)
    {
      nom: 'DENREES ALIMENTAIRES',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    // Prix unitaire denrées : 885 FCFA/kg (fiche tarifaire — aligné « Denrées alimentaires »)
    {
      nom: 'ATTIEKE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'PLACALI',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'GARI',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'POUDRE DE CACAO',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'GOMBO',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'GNANGNAN',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'FEUILLE DE PATATE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'SOUMARA',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: "PATE D'ARACHIDE",
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'BANANE PLANTIN',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'CHIPS',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'BISSAP',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'TAMARIN',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'PATE DE GINGEMBRE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },

    // Spécifiques Forfait 3500 (0-4kg)
    {
      nom: 'POUDRE DE MIL',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_FORFAITAIRE,
      prix_unitaire: 885,
      prix_forfaitaire: 3500,
      poids_min: 0,
      poids_max: 4,
    },
    {
      nom: 'POUDRE DE MAÏS',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_FORFAITAIRE,
      description: 'À partir de 5 kg',
      prix_unitaire: 885,
      prix_forfaitaire: 3500,
      poids_min: 0,
      poids_max: 4,
    },

    // Reste des DENREE au même tarif kg
    {
      nom: 'POUDRE DE GOMBO',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'MIL',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'HARICOT',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'TCHONGON',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'AROME',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'GRAINE PILE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'EPICE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'MAIS',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'GNONMI',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'FONIO',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'BAOBAB',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'BONBON',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'CACAHOUETTE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'PIMENT',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },
    {
      nom: 'CROQUETTE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 885,
    },

    // ========== CATÉGORIE: HUILE ET KARITE ==========
    // 1100 FCFA/kg — fiche GROUPAGE CARGO SP-CI ; forfait 0–4 kg : 4500 F
    {
      nom: 'PETIT COLAS',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
    },
    {
      nom: 'HUILE DE COCO',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
    },
    {
      nom: 'BEURRE DE KARITE',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
      prix_forfaitaire: 4500,
      poids_min: 0,
      poids_max: 4,
    },
    {
      nom: 'KINKELIBA',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
    },
    {
      nom: 'DJEKA',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
    },

    // ========== CATÉGORIE: DIVERS ==========
    // 1850 FCFA/kg — fiche ; forfait 0–2 kg : 5000 F (sauf lignes spécifiques ci-dessous)
    {
      nom: 'VETEMENTS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'CHAUSSURES',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'MECHE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'DRAPS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'OUVRAGE EN PLASTIQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'USTENSILES DE CUISINE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'VALISE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'ENCENS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'SAVOIR NOIR',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'SAC A MAIN',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'ECORCE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'NEP NEP',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },

    {
      nom: 'INDIGENAT LIQUIDE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'ATTOTE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 2100,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'HUILE ROUGE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1600,
      prix_forfaitaire: 3500,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'BOUILLONS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      prix_forfaitaire: 5000,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'CUBE MAGGI',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
    },

    // Bloc 3500
    {
      nom: 'VETEMENTS DE MARQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
      prix_forfaitaire: 8500,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'PAGNE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
    },
    {
      nom: 'CHAUSSURES DE MARQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
      prix_forfaitaire: 8500,
      poids_min: 0,
      poids_max: 2,
    },
    {
      nom: 'SACS DE MARQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
    },

    // ========== CATÉGORIE: COLIS RAPIDE EXPORT ==========
    // Fiche CA-CI : 5500 / 5850 F/kg ; forfaits 0–1 kg : 7500 / 8000 F
    {
      nom: 'POISSON FUME-CREVETTE-ESCARGOT-POULET FUME',
      categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 5500,
      prix_forfaitaire: 7500,
      poids_min: 0,
      poids_max: 1,
    },
    {
      nom: 'COSMETIQUE',
      categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 5850,
      prix_forfaitaire: 8000,
      poids_min: 0,
      poids_max: 1,
    },
  ];

  await produitRepository.save(produits);
  console.log(`✅ ${produits.length} produits catalogue insérés avec succès`);
}
