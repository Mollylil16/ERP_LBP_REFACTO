import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  ManyToOne,
  JoinColumn,
} from 'typeorm';
import { FournitureDemande } from './fourniture-demande.entity';
import { FournitureArticle } from './fourniture-article.entity';

@Entity('lbp_fournitures_demande_lignes')
export class FournitureDemandeLigne {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => FournitureDemande, (d) => d.lignes, {
    onDelete: 'CASCADE',
  })
  @JoinColumn({ name: 'id_demande' })
  demande: FournitureDemande;

  @ManyToOne(() => FournitureArticle, { nullable: false })
  @JoinColumn({ name: 'id_article' })
  article: FournitureArticle;

  @Column({ type: 'int' })
  quantite: number;

  @Column({ type: 'int', nullable: true })
  quantite_validee: number | null;

  @Column({ type: 'int', nullable: true })
  quantite_livree: number | null;
}
