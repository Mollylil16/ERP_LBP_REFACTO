import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
} from 'typeorm';
import { Marchandise } from '../../colis/entities/colis.entity';

@Entity('lbp_tarifs')
export class Tarif {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true })
  nom: string; // ex: Aérien Express, Maritime Groupage

  @Column({ type: 'decimal', precision: 10, scale: 2 })
  prix_vente_conseille: number;

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  cout_transport_kg: number; // Ce que LBP paye à la compagnie par kg

  @Column({ type: 'decimal', precision: 10, scale: 2, default: 0 })
  charges_fixes_unit: number; // Charges additionnelles par article/colis

  @OneToMany(() => Marchandise, (marchandise) => marchandise.tarif)
  marchandises: Marchandise[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
