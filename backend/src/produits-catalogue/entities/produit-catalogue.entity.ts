import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
} from 'typeorm';

export enum CategoriesProduit {
  DENREE = 'DENREE',
  HUILE_ET_KARITE = 'HUILE_ET_KARITE',
  DIVERS = 'DIVERS',
  COLIS_RAPIDE_EXPORT = 'COLIS_RAPIDE_EXPORT',
  COLIS_RAPIDE_IMPORT = 'COLIS_RAPIDE_IMPORT',
}

export enum NaturePrix {
  PRIX_UNITAIRE = 'PRIX_UNITAIRE',
  PRIX_FORFAITAIRE = 'PRIX_FORFAITAIRE',
}

@Entity('lbp_produits_catalogue')
export class ProduitCatalogue {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ length: 100 })
  nom: string; // ex: ATTIEKE, POUDRE DE MIL, etc.

  @Column({
    type: 'enum',
    enum: CategoriesProduit,
  })
  categorie: CategoriesProduit;

  @Column({
    type: 'enum',
    enum: NaturePrix,
    nullable: true,
  })
  nature: NaturePrix; // PRIX_UNITAIRE ou PRIX_FORFAITAIRE

  @Column({ type: 'decimal', precision: 10, scale: 2, nullable: true })
  prix_unitaire: number; // Prix au kg en FCFA

  @Column({ type: 'decimal', precision: 10, scale: 2, nullable: true })
  prix_forfaitaire: number; // Prix forfaitaire en FCFA

  @Column({ type: 'decimal', precision: 5, scale: 2, nullable: true })
  poids_min: number; // Poids minimum pour le forfait (en kg)

  @Column({ type: 'decimal', precision: 5, scale: 2, nullable: true })
  poids_max: number; // Poids maximum pour le forfait (en kg)

  @Column({ type: 'text', nullable: true })
  description: string; // Description optionnelle du produit

  /** Unité d’affichage / facturation : kg, unité, carton, etc. */
  @Column({ type: 'varchar', length: 30, nullable: true })
  unite: string | null;

  @Column({ default: true })
  actif: boolean; // Pour activer/désactiver un produit

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
