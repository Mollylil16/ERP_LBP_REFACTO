import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import {
  ProduitCatalogue,
  CategoriesProduit,
} from './entities/produit-catalogue.entity';
import { CreateProduitCatalogueDto } from './dto/create-produit-catalogue.dto';
import { UpdateProduitCatalogueDto } from './dto/update-produit-catalogue.dto';

@Injectable()
export class ProduitsCatalogueService {
  constructor(
    @InjectRepository(ProduitCatalogue)
    private readonly produitRepository: Repository<ProduitCatalogue>,
  ) {}

  async findAll(): Promise<ProduitCatalogue[]> {
    return this.produitRepository.find({
      where: { actif: true },
      order: { categorie: 'ASC', nom: 'ASC' },
    });
  }

  async findByCategorie(
    categorie: CategoriesProduit,
  ): Promise<ProduitCatalogue[]> {
    return this.produitRepository.find({
      where: { categorie, actif: true },
      order: { nom: 'ASC' },
    });
  }

  async findOne(id: number): Promise<ProduitCatalogue> {
    const produit = await this.produitRepository.findOne({ where: { id } });
    if (!produit) {
      throw new NotFoundException(`Produit avec l'ID ${id} non trouvé`);
    }
    return produit;
  }

  async create(
    createDto: CreateProduitCatalogueDto,
  ): Promise<ProduitCatalogue> {
    const produit = this.produitRepository.create(createDto);
    return this.produitRepository.save(produit);
  }

  async update(
    id: number,
    updateDto: UpdateProduitCatalogueDto,
  ): Promise<ProduitCatalogue> {
    const produit = await this.findOne(id);
    Object.assign(produit, updateDto);
    return this.produitRepository.save(produit);
  }

  async remove(id: number): Promise<void> {
    const produit = await this.findOne(id);
    produit.actif = false;
    await this.produitRepository.save(produit);
  }

  async search(query: string): Promise<ProduitCatalogue[]> {
    if (!query || query.length < 2) {
      return [];
    }

    return this.produitRepository
      .createQueryBuilder('produit')
      .where('produit.actif = :actif', { actif: true })
      .andWhere(
        '(LOWER(produit.nom) LIKE LOWER(:query) OR LOWER(produit.code) LIKE LOWER(:query))',
        { query: `%${query}%` },
      )
      .orderBy('produit.nom', 'ASC')
      .limit(20)
      .getMany();
  }

  async getHistoriqueUtilisation(): Promise<any[]> {
    // Récupérer l'historique d'utilisation des produits depuis les marchandises
    const result = await this.produitRepository.query(`
            SELECT 
                m.nom_marchandise,
                COUNT(*) as nb_utilisations,
                ROUND(AVG(m.prix_unit)::numeric, 2) as prix_moyen,
                MIN(m.prix_unit) as prix_min,
                MAX(m.prix_unit) as prix_max,
                STRING_AGG(DISTINCT c.nom_exp, ', ') as clients
            FROM lbp_marchandises m
            JOIN lbp_colis col ON m.id_colis = col.id
            JOIN lbp_clients c ON col.id_client = c.id
            GROUP BY m.nom_marchandise
            HAVING COUNT(*) > 0
            ORDER BY nb_utilisations DESC
            LIMIT 100
        `);

    return result;
  }

  async hardDelete(id: number): Promise<void> {
    const result = await this.produitRepository.delete(id);
    if (result.affected === 0) {
      throw new NotFoundException(`Produit avec l'ID ${id} non trouvé`);
    }
  }
}
