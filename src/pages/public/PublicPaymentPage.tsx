import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Spin, Result, Button } from 'antd';
import { paiementsLienService } from '../../services/paiementsLien.service';

// ✅ Numéro LBP unique (Wave ET Orange Money)
const LBP_PHONE = '0789886013';
// Format international pour les deep links
const LBP_PHONE_INTL = '2250789886013'; // +225 07 89 88 60 13

const formatMontant = (n: number) =>
    Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

export const PublicPaymentPage: React.FC = () => {
    const { token } = useParams<{ token: string }>();
    const [loading, setLoading] = useState(true);
    const [lien, setLien] = useState<any>(null);
    const [error, setError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (token) {
            paiementsLienService.getPublicDetails(token)
                .then(setLien)
                .catch((err: any) => setError(err.response?.data?.message || 'Lien invalide ou expiré'))
                .finally(() => setLoading(false));
        }
    }, [token]);

    if (loading) {
        return (
            <div style={styles.center}>
                <Spin size="large" tip="Chargement de votre facture..." />
            </div>
        );
    }

    if (error || !lien) {
        return (
            <div style={styles.center}>
                <Result
                    status="error"
                    title="Lien Invalide"
                    subTitle={error || "Le lien de paiement que vous avez utilisé n'est plus valide ou a expiré."}
                    extra={<Button type="primary" onClick={() => window.close()}>Fermer</Button>}
                />
            </div>
        );
    }

    const { facture } = lien;
    const montant = Number(lien.montant);
    const refColis = facture?.colis?.ref_colis || '';
    const numFacture = facture?.num_facture || '';
    const nomClient = facture?.colis?.client?.nom_exp || '';
    const note = encodeURIComponent(`Paiement ${refColis}`);

    // ──────────────────────────────────────────────────────────────
    // WAVE CI — deep link officiel pour ouvrir l'app Wave
    // Format : waveci://pay?phone=NUMERO_INTL&amount=MONTANT&note=NOTE
    // Fallback web : https://wave.com/send/
    // ──────────────────────────────────────────────────────────────
    const waveDeepLink = `waveci://pay?phone=${LBP_PHONE_INTL}&amount=${montant}&note=${note}`;
    const waveFallbackLink = 'https://www.wave.com/send/';

    // ──────────────────────────────────────────────────────────────
    // ORANGE MONEY CI — code USSD (toujours fiable sur téléphone)
    // *144*3*NUMERO*MONTANT# — ussd natif Côte d'Ivoire
    // ──────────────────────────────────────────────────────────────
    const omUssdLink = `tel:*144*3*${LBP_PHONE}*${montant}%23`;

    const handleCopyPhone = () => {
        navigator.clipboard.writeText(LBP_PHONE).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const handleWaveClick = () => {
        // Essaie d'ouvrir l'app Wave via deep link
        window.location.href = waveDeepLink;
        // Si l'app n'est pas installée, redirige vers le site Wave après 2s
        setTimeout(() => {
            window.location.href = waveFallbackLink;
        }, 2000);
    };

    return (
        <div style={styles.page}>
            {/* Header */}
            <div style={styles.header}>
                <div style={styles.logo}>LBP</div>
                <div style={styles.headerText}>LA BELLE PORTE LOGISTICS</div>
                <div style={styles.subtitle}>Paiement de facture sécurisé <span style={{ opacity: 0.5 }}>(v2.1)</span></div>
            </div>

            {/* Card principale */}
            <div style={styles.card}>
                {/* Récapitulatif facture */}
                <div style={styles.invoice}>
                    <div style={styles.invoiceRow}>
                        <span style={styles.invoiceLabel}>Facture</span>
                        <span style={styles.invoiceValue}>{numFacture}</span>
                    </div>
                    <div style={styles.invoiceRow}>
                        <span style={styles.invoiceLabel}>Référence colis</span>
                        <span style={styles.invoiceValue}>{refColis}</span>
                    </div>
                    {nomClient && (
                        <div style={styles.invoiceRow}>
                            <span style={styles.invoiceLabel}>Client</span>
                            <span style={styles.invoiceValue}>{nomClient}</span>
                        </div>
                    )}
                    <div style={{ ...styles.invoiceRow, ...styles.montantRow }}>
                        <span style={styles.invoiceLabel}>Montant à payer</span>
                        <span style={styles.montantValue}>{formatMontant(montant)} FCFA</span>
                    </div>
                </div>

                {/* Instructions */}
                <div style={styles.instructions}>
                    <span style={styles.instructionsIcon}>ℹ️</span>
                    Appuyez sur "Payer". Mentionnez <strong style={{ color: '#1A2B5B' }}>"{refColis}"</strong> dans le motif.
                </div>

                {/* Bouton Wave */}
                <a href={waveDeepLink} style={{ display: 'block', textDecoration: 'none' }}>
                    <button style={{ ...styles.payBtn, ...styles.waveBg }}>
                        <div style={styles.btnInner}>
                            <div style={styles.btnIcon}>🌊</div>
                            <div>
                                <div style={styles.btnTitle}>Payer avec Wave</div>
                                <div style={styles.btnSub}>Ouvre l'application Wave CI</div>
                            </div>
                            <div style={styles.btnArrow}>›</div>
                        </div>
                    </button>
                </a>

                {/* Bouton Orange Money — USSD (le plus fiable en CI) */}
                <a href={omUssdLink} style={{ display: 'block', textDecoration: 'none', marginTop: '12px' }}>
                    <button style={{ ...styles.payBtn, ...styles.omBg }}>
                        <div style={styles.btnInner}>
                            <div style={styles.btnIcon}>🟠</div>
                            <div>
                                <div style={styles.btnTitle}>Payer avec Orange Money</div>
                                <div style={styles.btnSub}>Lance le code USSD *144*3*... directement</div>
                            </div>
                            <div style={styles.btnArrow}>›</div>
                        </div>
                    </button>
                </a>

                {/* Numéro à copier — solution universelle */}
                <div style={styles.phoneBox}>
                    <div style={styles.phoneLabel}>Ou envoyez manuellement au numéro LBP :</div>
                    <div style={styles.phoneNumber}>{LBP_PHONE}</div>
                    <div style={styles.phoneNote}>Valable pour Wave et Orange Money</div>
                    <button
                        style={styles.copyBtn}
                        onClick={handleCopyPhone}
                    >
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
        marginBottom: '20px',
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
        lineHeight: '1.6',
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
        marginTop: '24px',
        padding: '16px',
        background: '#F0F5FF',
        borderRadius: '12px',
        border: '1px dashed #ADC6FF',
    },
    phoneLabel: { fontSize: '12px', color: '#666', marginBottom: '4px' },
    phoneNumber: { fontSize: '28px', fontWeight: 900, color: '#1A2B5B', letterSpacing: '2px' },
    phoneNote: { fontSize: '11px', color: '#888', marginTop: '4px' },
    copyBtn: {
        marginTop: '10px',
        padding: '8px 20px',
        background: '#1A2B5B',
        color: '#fff',
        border: 'none',
        borderRadius: '8px',
        cursor: 'pointer',
        fontSize: '13px',
        fontWeight: 600,
    },
    footer: {
        color: 'rgba(255,255,255,0.55)',
        fontSize: '12px',
        marginTop: '24px',
        textAlign: 'center',
    },
};
