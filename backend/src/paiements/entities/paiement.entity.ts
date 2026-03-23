import { Entity, Column, PrimaryGeneratedColumn, CreateDateColumn, UpdateDateColumn, ManyToOne, JoinColumn } from 'typeorm';
import { Facture } from '../../factures/entities/facture.entity';

export enum PaymentMode {
    COMPTANT = 'comptant',
    ESPECES = 'especes',
    OM = 'om',
    ORANGE_MONEY = 'orange_money',
    WAVE = 'wave',
    CHEQUE = 'cheque',
    VIREMENT = 'virement',
    TRENTE_JOURS = '30j',
    QUARANTE_CINQ_JOURS = '45j',
    SOIXANTE_JOURS = '60j',
    QUATRE_VINGT_DIX_JOURS = '90j',
}

@Entity('lbp_paiements')
export class Paiement {
    @PrimaryGeneratedColumn()
    id: number;

    @ManyToOne(() => Facture)
    @JoinColumn({ name: 'id_facture' })
    facture: Facture;

    @Column({ type: 'decimal', precision: 12, scale: 2 })
    montant: number;

    @Column({ type: 'decimal', precision: 12, scale: 2, default: 0 })
    monnaie_rendue: number;

    @Column({
        type: 'enum',
        enum: PaymentMode,
        default: PaymentMode.COMPTANT,
    })
    mode_paiement: PaymentMode;

    @Column({ nullable: true })
    reference_paiement: string; // n° chèque, virement, etc.

    @Column({ type: 'date' })
    date_paiement: Date;

    @Column({ default: 1 })
    etat_validation: number; // 0: Annulé, 1: Validé

    @Column({ nullable: true })
    code_user: string;

    @CreateDateColumn()
    created_at: Date;

    @UpdateDateColumn()
    updated_at: Date;
}
