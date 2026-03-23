import { Entity, Column, PrimaryGeneratedColumn, CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn } from 'typeorm';
import { Colis } from '../../colis/entities/colis.entity';

@Entity('lbp_factures')
export class Facture {
    @PrimaryGeneratedColumn()
    id: number;

    @Column({ unique: true })
    num_facture: string; // format: FCO-MMYY-XXX

    @ManyToOne(() => Colis)
    @JoinColumn({ name: 'id_colis' })
    colis: Colis;

    @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
    montant_ht: number;

    @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
    montant_ttc: number;

    @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
    montant_paye: number;

    @Column({ default: 0 })
    etat: number; // 0: Proforma, 1: Définitive, 2: Annulée

    @Column({
        type: 'enum',
        enum: ['unpaid', 'partial', 'paid'],
        default: 'unpaid'
    })
    payment_status: string;

    @Column({ length: 10, default: 'XOF' })
    devise: string;

    @Column({ type: 'decimal', precision: 12, scale: 4, default: 1 })
    taux_change: number;

    @Column({ type: 'date' })
    date_facture: Date;

    @Column({ nullable: true })
    code_user: string;

    @CreateDateColumn()
    created_at: Date;

    @UpdateDateColumn()
    updated_at: Date;
}
