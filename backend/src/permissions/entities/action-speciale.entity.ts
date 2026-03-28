import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
} from 'typeorm';
import { UserActionSpeciale } from '../../users/entities/user-action-speciale.entity';

export enum ActionSpecialeType {
  RESTRICTION = 'RESTRICTION',
  PRIVILEGE = 'PRIVILEGE',
}

@Entity('lbp_actions_speciales')
export class ActionSpeciale {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 50 })
  code: string; // 'uniquementGroupage', 'voirToutesAgences', 'nePeutPasModifier', etc.

  @Column({ length: 100 })
  libelle: string;

  @Column({ type: 'text', nullable: true })
  description: string;

  @Column({
    type: 'enum',
    enum: ActionSpecialeType,
  })
  type: ActionSpecialeType;

  @OneToMany(
    () => UserActionSpeciale,
    (userAction) => userAction.actionSpeciale,
  )
  userActions: UserActionSpeciale[];

  @CreateDateColumn()
  created_at: Date;

  @UpdateDateColumn()
  updated_at: Date;
}
