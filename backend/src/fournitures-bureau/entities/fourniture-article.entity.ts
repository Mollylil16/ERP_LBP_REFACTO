import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
} from 'typeorm';

@Entity('lbp_fournitures_articles')
export class FournitureArticle {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 50 })
  code: string;

  @Column({ length: 200 })
  nom: string;

  @Column({ length: 30, default: 'unité' })
  unite: string;

  @Column({ type: 'int', default: 0 })
  quantite_stock: number;

  @Column({ type: 'int', default: 0 })
  seuil_alerte: number;

  @Column({ default: true })
  actif: boolean;

  @CreateDateColumn({ type: 'timestamptz' })
  created_at: Date;

  @UpdateDateColumn({ type: 'timestamptz' })
  updated_at: Date;
}
