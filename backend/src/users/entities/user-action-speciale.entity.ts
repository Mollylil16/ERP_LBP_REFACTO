import {
  Entity,
  PrimaryGeneratedColumn,
  ManyToOne,
  JoinColumn,
  CreateDateColumn,
} from 'typeorm';
import { User } from './user.entity';
import { ActionSpeciale } from '../../permissions/entities/action-speciale.entity';

@Entity('lbp_user_actions_speciales')
export class UserActionSpeciale {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => User, (user) => user.actionsSpeciales, {
    onDelete: 'CASCADE',
  })
  @JoinColumn({ name: 'user_id' })
  user: User;

  @ManyToOne(() => ActionSpeciale, (action) => action.userActions, {
    onDelete: 'CASCADE',
  })
  @JoinColumn({ name: 'action_speciale_id' })
  actionSpeciale: ActionSpeciale;

  @CreateDateColumn()
  created_at: Date;
}
