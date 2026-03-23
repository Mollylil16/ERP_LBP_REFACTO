import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Spin, Result, Button, InputNumber, Divider, Card, Typography, Space } from 'antd';
import axios from 'axios';

const { Title, Text, Paragraph } = Typography;

// ✅ Nouveau Numéro Marchand LBP
const LBP_PHONE = '0715512765';
const LBP_PHONE_INTL = '2250715512765';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:3001/api';

const formatMontant = (n: number) =>
    Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

export const InvoicePublicPaymentPage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const [loading, setLoading] = useState(true);
    const [facture, setFacture] = useState<any>(null);
    const [error, setError] = useState<string | null>(null);
    const [amountToPay, setAmountToPay] = useState<number>(0);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (id) {
            axios.get(`${API_BASE_URL}/factures-public/${id}`)
                .then(res => {
                    const data = res.data;
                    setFacture(data);
                    const restant = data.montant_ttc - data.montant_paye;
                    setAmountToPay(restant > 0 ? restant : 0);
                })
                .catch((err: any) => setError(err.response?.data?.message || 'Facture introuvable ou erreur serveur'))
                .finally(() => setLoading(false));
        }
    }, [id]);

    if (loading) {
        return (
            <div style={styles.center}>
                <Spin size="large" tip="Chargement de votre facture..." />
            </div>
        );
    }

    if (error || !facture) {
        return (
            <div style={styles.center}>
                <Result
                    status="error"
                    title="Erreur"
                    subTitle={error || "Impossible de charger les détails de la facture."}
                    extra={<Button type="primary" onClick={() => window.location.href = '/'}>Retour</Button>}
                />
            </div>
        );
    }

    const restant = Number(facture.montant_ttc) - Number(facture.montant_paye);
    const refColis = facture.colis?.ref_colis || '';
    const numFacture = facture.num_facture || '';
    const nomClient = facture.colis?.client?.nom_exp || '';

    // Liens de paiement dynamiques basés sur le montant choisi
    const note = encodeURIComponent(`Paiement ${refColis}`);

    // Wave : Lien marchand (Phase 1)
    const waveLink = `https://pay.wave.com/m/0715512765?amount=${amountToPay}`;

    // Orange Money : USSD par défaut (Plus fiable)
    const omUssdLink = `tel:*144*3*${LBP_PHONE}*${amountToPay}%23`;

    const handleCopyPhone = () => {
        navigator.clipboard.writeText(LBP_PHONE).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div style={styles.page}>
            {/* Header */}
            <div style={styles.header}>
                <div style={styles.logo}>LBP</div>
                <div style={styles.headerText}>LA BELLE PORTE LOGISTICS</div>
                <div style={styles.subtitle}>Paiement de facture sécurisé</div>
            </div>

            {/* Card principale */}
            <div style={styles.card}>
                <Title level={4} style={{ textAlign: 'center', marginBottom: 20 }}>Détail de la facture</Title>

                <div style={styles.invoice}>
                    <div style={styles.invoiceRow}>
                        <span style={styles.invoiceLabel}>Facture</span>
                        <span style={styles.invoiceValue}>{numFacture}</span>
                    </div>
                    <div style={styles.invoiceRow}>
                        <span style={styles.invoiceLabel}>Référence colis</span>
                        <span style={styles.invoiceValue}>{refColis}</span>
                    </div>
                    <div style={styles.invoiceRow}>
                        <span style={styles.invoiceLabel}>Total TTC</span>
                        <span style={styles.invoiceValue}>{formatMontant(facture.montant_ttc)} FCFA</span>
                    </div>
                    <div style={styles.invoiceRow}>
                        <span style={styles.invoiceLabel}>Déjà payé</span>
                        <span style={{ ...styles.invoiceValue, color: '#52c41a' }}>{formatMontant(facture.montant_paye)} FCFA</span>
                    </div>
                    <div style={{ ...styles.invoiceRow, ...styles.montantRow }}>
                        <span style={styles.invoiceLabel}>Reste à payer</span>
                        <span style={styles.montantValue}>{formatMontant(restant)} FCFA</span>
                    </div>
                </div>

                <Divider />

                <div style={{ marginBottom: 20 }}>
                    <Text strong>Montant que vous souhaitez payer :</Text>
                    <InputNumber
                        value={amountToPay}
                        onChange={(val: number | null) => setAmountToPay(val || 0)}
                        min={1}
                        max={restant}
                        style={{ width: '100%', marginTop: 8, height: 45, display: 'flex', alignItems: 'center' }}
                        size="large"
                        formatter={(value: any) => `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')}
                        parser={(value: any) => value!.replace(/\s?|FCFA/g, '')}
                        addonAfter="FCFA"
                    />
                    <Paragraph type="secondary" style={{ fontSize: 12, marginTop: 4 }}>
                        * Vous pouvez payer la totalité ou une partie du montant restant.
                    </Paragraph>
                </div>

                {/* Instructions */}
                <div style={styles.instructions}>
                    <span style={styles.instructionsIcon}>ℹ️</span>
                    Une fois le paiement effectué, envoyez votre reçu ou capture d'écran à LBP pour validation.
                </div>

                {/* Bouton Wave */}
                <a href={waveLink} style={{ display: 'block', textDecoration: 'none' }}>
                    <button style={{ ...styles.payBtn, ...styles.waveBg }}>
                        <div style={styles.btnInner}>
                            <div style={styles.btnIcon}>🌊</div>
                            <div>
                                <div style={styles.btnTitle}>Payer avec Wave</div>
                                <div style={styles.btnSub}>LBP : {LBP_PHONE}</div>
                            </div>
                            <div style={styles.btnArrow}>›</div>
                        </div>
                    </button>
                </a>

                {/* Bouton Orange Money */}
                <a href={omUssdLink} style={{ display: 'block', textDecoration: 'none', marginTop: '12px' }}>
                    <button style={{ ...styles.payBtn, ...styles.omBg }}>
                        <div style={styles.btnInner}>
                            <div style={styles.btnIcon}>🟠</div>
                            <div>
                                <div style={styles.btnTitle}>Payer avec Orange Money</div>
                                <div style={styles.btnSub}>LBP : {LBP_PHONE}</div>
                            </div>
                            <div style={styles.btnArrow}>›</div>
                        </div>
                    </button>
                </a>

                {/* Numéro à copier */}
                <div style={styles.phoneBox}>
                    <div style={styles.phoneNumber}>{LBP_PHONE}</div>
                    <button style={styles.copyBtn} onClick={handleCopyPhone}>
                        {copied ? '✅ Copié !' : '📋 Copier le numéro'}
                    </button>
                </div>
            </div>

            {/* Footer */}
            <div style={styles.footer}>
                🔒 Paiement sécurisé — LBP Logistics &copy; {new Date().getFullYear()}
            </div>
        </div>
    );
};

// ─── Styles inline ────────────────────────────────────────────
const styles: Record<string, React.CSSProperties> = {
    page: {
        minHeight: '100vh',
        background: 'linear-gradient(135deg, #1A2B5B 0%, #2d4a9e 100%)',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        padding: '24px 16px',
        fontFamily: "'Segoe UI', Arial, sans-serif",
    },
    center: {
        display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100vh',
    },
    header: {
        textAlign: 'center', marginBottom: '24px', color: '#fff',
    },
    logo: {
        fontSize: '42px', fontWeight: 900, letterSpacing: '4px', color: '#B8900A',
        textShadow: '0 2px 8px rgba(0,0,0,0.4)',
    },
    headerText: {
        fontSize: '14px', fontWeight: 600, letterSpacing: '2px', color: '#fff', marginTop: '4px',
    },
    subtitle: {
        fontSize: '13px', color: 'rgba(255,255,255,0.7)', marginTop: '4px',
    },
    card: {
        background: '#fff',
        borderRadius: '20px',
        padding: '28px 24px',
        width: '100%',
        maxWidth: '440px',
        boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
    },
    invoice: {
        background: '#F4F6FA',
        borderRadius: '12px',
        padding: '16px',
        marginBottom: '10px',
    },
    invoiceRow: {
        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        padding: '6px 0', borderBottom: '1px solid #E0E4EF',
    },
    invoiceLabel: { color: '#666', fontSize: '13px' },
    invoiceValue: { color: '#1A2B5B', fontWeight: 600, fontSize: '13px' },
    montantRow: { borderBottom: 'none', marginTop: '8px' },
    montantValue: { color: '#d4380d', fontWeight: 800, fontSize: '22px' },
    instructions: {
        background: '#FFF8E6',
        border: '1px solid #FFD666',
        borderRadius: '10px',
        padding: '12px',
        fontSize: '13px',
        color: '#7A5200',
        lineHeight: '1.4',
        marginBottom: '20px',
        display: 'flex',
        gap: '8px',
        alignItems: 'flex-start',
    },
    instructionsIcon: { fontSize: '16px', flexShrink: 0 },
    payBtn: {
        width: '100%',
        border: 'none',
        borderRadius: '14px',
        padding: '16px 20px',
        cursor: 'pointer',
        color: '#fff',
        transition: 'transform 0.1s, box-shadow 0.1s',
    },
    waveBg: {
        background: 'linear-gradient(135deg, #1890ff 0%, #0050b3 100%)',
        boxShadow: '0 4px 15px rgba(24,144,255,0.4)',
    },
    omBg: {
        background: 'linear-gradient(135deg, #ff7900 0%, #cc5500 100%)',
        boxShadow: '0 4px 15px rgba(255,121,0,0.4)',
    },
    btnInner: {
        display: 'flex', alignItems: 'center', gap: '14px',
    },
    btnIcon: { fontSize: '28px' },
    btnTitle: { fontWeight: 700, fontSize: '15px', textAlign: 'left' },
    btnSub: { fontSize: '12px', opacity: 0.85, textAlign: 'left' },
    btnArrow: { marginLeft: 'auto', fontSize: '24px', fontWeight: 300 },
    phoneBox: {
        textAlign: 'center',
        marginTop: '20px',
        padding: '10px',
        background: '#F0F5FF',
        borderRadius: '12px',
        border: '1px dashed #ADC6FF',
    },
    phoneNumber: { fontSize: '22px', fontWeight: 900, color: '#1A2B5B', letterSpacing: '2px' },
    copyBtn: {
        marginTop: '6px',
        padding: '4px 12px',
        background: '#1A2B5B',
        color: '#fff',
        border: 'none',
        borderRadius: '6px',
        cursor: 'pointer',
        fontSize: '12px',
        fontWeight: 600,
    },
    footer: {
        color: 'rgba(255,255,255,0.55)',
        fontSize: '12px',
        marginTop: '24px',
        textAlign: 'center',
    },
};
