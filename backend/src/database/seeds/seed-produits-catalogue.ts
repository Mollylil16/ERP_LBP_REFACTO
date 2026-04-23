import { DataSource } from 'typeorm';
import {
  ProduitCatalogue,
  CategoriesProduit,
  NaturePrix,
} from '../../produits-catalogue/entities/produit-catalogue.entity';

export async function seedProduitsCatalogue(dataSource: DataSource) {
  const produitRepository = dataSource.getRepository(ProduitCatalogue);

  const normalizeKey = (raw: string): string =>
    String(raw || '')
      .trim()
      .toUpperCase()
      .normalize('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .replace(/\s+/g, ' ');

  // Index en mémoire pour éviter N requêtes et gérer accents/espaces
  const existingAll = await produitRepository.find();
  const existingByKey = new Map<string, ProduitCatalogue>();
  for (const p of existingAll) {
    existingByKey.set(normalizeKey(p.nom), p);
  }

  const upsert = async (row: Partial<ProduitCatalogue>) => {
    const nom = String(row.nom || '').trim();
    if (!nom) return;
    const key = normalizeKey(nom);
    const found = existingByKey.get(key) ?? null;

    if (!found) {
      const created = (await produitRepository.save(
        produitRepository.create({
          ...row,
          nom,
          actif: row.actif ?? true,
        } as any),
      )) as unknown as ProduitCatalogue;
      existingByKey.set(key, created);
      return;
    }
    await produitRepository.update(
      { id: found.id } as any,
      {
        ...row,
        nom: found.nom, // ne pas renommer automatiquement en prod
        actif: row.actif ?? found.actif,
      } as any,
    );
  };

  const produits = [
    // ========== CATÉGORIE: DENRÉE ==========
    // Taux groupage cargo « denrées alimentaires » au kg (tarif affiché agence)
    {
      nom: 'DENREES ALIMENTAIRES',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    // Prix unitaire denrées : 885 FCFA/kg (fiche tarifaire — aligné « Denrées alimentaires »)
    {
      nom: 'ATTIEKE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'PLACALI',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'GARI',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    { nom: 'CHAT NOIR', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    {
      nom: 'POUDRE DE CACAO',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'GOMBO',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'GNANGNAN',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'FEUILLE DE PATATE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'SOUMARA',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: "PATE D'ARACHIDE",
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    { nom: 'DORKOUNOU', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'AKASSA', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    {
      nom: 'CHIPS',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    // Alias orthographe ("SHIPS" souvent saisi)
    { nom: 'SHIPS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    {
      nom: 'BISSAP',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'TAMARIN',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'PATE DE GINGEMBRE',
      categorie: CategoriesProduit.DENREE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 900,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    { nom: 'POUDRE DE MIL', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'POUDRE DE MAIS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'POUDRE DE PIMENT', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'POUDRE DE GINGEMBRE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'GINGEMBRE SECHE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'POUDRE DE GOMBO', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'MIL', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'HARICOT', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'TCHONGON', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'RIZ', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'ANANAS SECHE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'MANGUE SECHE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'COUSCOUS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'AROME', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'GRAINE PILE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'EPICE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'MAIS', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'GNONMI', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'BAOBAB', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'BONBON', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'CACAHOUETTE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },
    { nom: 'CROQUETTE', categorie: CategoriesProduit.DENREE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 900, description: 'A PARTIR DE 5 KG', unite: 'kg' },

    // ========== CATÉGORIE: HUILE ET KARITE ==========
    // 1100 FCFA/kg — fiche GROUPAGE CARGO SP-CI ; forfait 0–4 kg : 4500 F
    {
      nom: 'PETIT COLAS',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'HUILE DE COCO',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'BEURRE DE KARITE',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
      prix_forfaitaire: 4500,
      poids_min: 0,
      poids_max: 4,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'KINKELIBA',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    {
      nom: 'DJEKA',
      categorie: CategoriesProduit.HUILE_ET_KARITE,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1100,
      description: 'A PARTIR DE 5 KG',
      unite: 'kg',
    },
    { nom: 'INFUSION', categorie: CategoriesProduit.HUILE_ET_KARITE, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1100, description: 'A PARTIR DE 5 KG', unite: 'kg' },

    // ========== CATÉGORIE: DIVERS ==========
    // 1850 FCFA/kg — fiche ; (POIDS_REQUIS: A PARTIR DE 2 KG)
    {
      nom: 'VETEMENTS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'CHAUSSURES',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'DRAPS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1850,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    { nom: 'OUVRAGE EN PLASTIQUE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'USTENSILES DE CUISINE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'VALISE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'ENCENS', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'SAVOIR NOIR', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'SAC A MAIN', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: "L'EAU BENITE", categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'ECORCE', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: '4 COTES', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'CAOLIN', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'NEP NEP', categorie: CategoriesProduit.DIVERS, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 1850, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    {
      nom: 'ATTOTE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 2100,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'HUILE ROUGE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1600,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'BOUILLONS',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1600,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'CUBE MAGGI',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 1600,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },

    // Bloc 3500
    {
      nom: 'VETEMENTS DE MARQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'CHAUSSURES DE MARQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    {
      nom: 'SACS DE MARQUE',
      categorie: CategoriesProduit.DIVERS,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 3500,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },

    // ========== CATÉGORIE: COLIS RAPIDE EXPORT ==========
    { nom: 'POISSON FUME', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'CREVETTE FUMEE', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'ESCARGOT', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'POULET FUME', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    {
      nom: 'COSMETIQUE',
      categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT,
      nature: NaturePrix.PRIX_UNITAIRE,
      prix_unitaire: 5850,
      description: 'A PARTIR DE 2 KG',
      unite: 'kg',
    },
    { nom: 'POISSON EN POUDRE', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'CREVETTE EN POUDRE', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'KPLO FUME', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
    { nom: 'VIANDE FUME', categorie: CategoriesProduit.COLIS_RAPIDE_EXPORT, nature: NaturePrix.PRIX_UNITAIRE, prix_unitaire: 5500, description: 'A PARTIR DE 2 KG', unite: 'kg' },
  ];

  // Upsert : ajoute les manquants + met à jour prix/description si besoin
  for (const p of produits) {
    // Correction de saisie fréquente : "POUDRE DE MAÏS" -> "POUDRE DE MAIS"
    if (p.nom === 'POUDRE DE MAÏS') (p as any).nom = 'POUDRE DE MAIS';
    await upsert(p as any);
  }
  console.log(`✅ Catalogue produits enrichi : ${produits.length} lignes traitées`);
}
