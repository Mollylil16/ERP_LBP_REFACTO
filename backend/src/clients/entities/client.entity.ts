import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
} from 'typeorm';
import { Colis } from '../../colis/entities/colis.entity';

@Entity('lbp_clients')
export class Client {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  nom_exp: string;

  @Column({ nullable: true })
  type_piece_exp: string;

  @Column({ nullable: true })
  num_piece_exp: string;

  @Column()
  tel_exp: string;

  @Column({ nullable: true })
  email_exp: string;

  @Column({ default: true })
  isActive: boolean;

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;

  @OneToMany(() => Colis, (colis) => colis.client)
  colis: Colis[];
}
