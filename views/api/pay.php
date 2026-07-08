<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail de Paiement Sécurisé - La Belle Porte (LBP)</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-wave: #4f46e5;
            --accent-orange: #f97316;
            --accent-mtn: #eab308;
            --accent-success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow-x: hidden;
        }

        /* Glassmorphism card */
        .payment-container {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            width: 100%;
            max-width: 520px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
            position: relative;
        }

        .payment-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.02) 50%, rgba(255, 255, 255, 0.1) 100%);
            z-index: -1;
            pointer-events: none;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(to right, #a78bfa, #818cf8, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Invoice info box */
        .invoice-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .invoice-row:last-child {
            margin-bottom: 0;
            padding-top: 0.75rem;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .label {
            color: var(--text-secondary);
        }

        .val {
            font-weight: 500;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #60a5fa;
        }

        /* Operator selection */
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .providers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .provider-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .provider-card:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .provider-card.selected {
            background: rgba(99, 102, 241, 0.1);
            border-color: #6366f1;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.2);
        }

        .provider-logo {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .provider-name {
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Form fields */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.875rem 1rem;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.25);
        }

        /* Pay button */
        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border: none;
            border-radius: 14px;
            color: white;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 10px 20px -10px rgba(99, 102, 241, 0.5);
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -10px rgba(99, 102, 241, 0.6);
            filter: brightness(1.1);
        }

        .btn-pay:active {
            transform: translateY(0);
        }

        /* Loading / Success State screen */
        .state-screen {
            display: none;
            text-align: center;
            padding: 2rem 0;
            animation: fadeIn 0.5s ease-out;
        }

        .state-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid var(--accent-success);
            color: var(--accent-success);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem auto;
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border-left-color: #6366f1;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .success-desc {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>

    <div class="payment-container">
        <!-- Main Form Screen -->
        <div id="form-screen">
            <div class="header">
                <div class="logo">LA BELLE PORTE</div>
                <div class="subtitle">Portail de Paiement Sécurisé</div>
            </div>

            <div class="invoice-card">
                <div class="invoice-row">
                    <span class="label">N° Facture</span>
                    <span class="val"><?= htmlspecialchars($facture->numeroFacture) ?></span>
                </div>
                <div class="invoice-row">
                    <span class="label">Client</span>
                    <span class="val"><?= htmlspecialchars($client['name'] ?? '—') ?></span>
                </div>
                <div class="invoice-row">
                    <span class="label">Téléphone</span>
                    <span class="val"><?= htmlspecialchars($client['phone'] ?? '—') ?></span>
                </div>
                <div class="invoice-row">
                    <span class="label">Reste à payer</span>
                    <span class="val total-amount"><?= number_format($facture->montantRestant, 0, ',', ' ') ?> <?= htmlspecialchars($facture->devise) ?></span>
                </div>
            </div>

            <div class="section-title">Sélectionnez votre moyen de paiement</div>

            <div class="providers-grid">
                <div class="provider-card selected" data-provider="wave">
                    <span class="provider-logo">🌊</span>
                    <span class="provider-name">Wave</span>
                </div>
                <div class="provider-card" data-provider="orange">
                    <span class="provider-logo">🍊</span>
                    <span class="provider-name">Orange Money</span>
                </div>
                <div class="provider-card" data-provider="mtn">
                    <span class="provider-logo">🟡</span>
                    <span class="provider-name">MTN MoMo</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone-input">Numéro de téléphone mobile money</label>
                <input type="tel" id="phone-input" class="form-input" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" placeholder="Ex: 0707070707">
            </div>

            <button class="btn-pay" id="btn-pay">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Payer <?= number_format($facture->montantRestant, 0, ',', ' ') ?> <?= htmlspecialchars($facture->devise) ?>
            </button>
        </div>

        <!-- Processing Screen -->
        <div id="processing-screen" class="state-screen">
            <div class="spinner"></div>
            <h3 class="success-title">Paiement en cours</h3>
            <p class="success-desc">Veuillez valider la notification de paiement sur votre téléphone mobile.</p>
        </div>

        <!-- Success Screen -->
        <div id="success-screen" class="state-screen">
            <div class="state-icon">✓</div>
            <h3 class="success-title" style="color: var(--accent-success)">Paiement Réussi !</h3>
            <p class="success-desc">Votre paiement a été traité avec succès et votre solde a été mis à jour dans notre système.<br><br>Vous pouvez fermer cet onglet.</p>
            <button class="btn-pay" onclick="window.close()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary); box-shadow: none;">
                Fermer le portail
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const providerCards = document.querySelectorAll('.provider-card');
            let selectedProvider = 'wave';

            providerCards.forEach(card => {
                card.addEventListener('click', () => {
                    providerCards.forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    selectedProvider = card.dataset.provider;
                });
            });

            const btnPay = document.getElementById('btn-pay');
            const formScreen = document.getElementById('form-screen');
            const processingScreen = document.getElementById('processing-screen');
            const successScreen = document.getElementById('success-screen');
            const phoneInput = document.getElementById('phone-input');

            btnPay.addEventListener('click', function() {
                if (!phoneInput.value.trim()) {
                    alert('Veuillez saisir votre numéro de téléphone.');
                    return;
                }

                // Afficher l'écran de chargement
                formScreen.style.display = 'none';
                processingScreen.style.display = 'block';

                // Simuler la validation de la notification push (3 secondes)
                setTimeout(() => {
                    const transRef = 'TX-' + Math.random().toString(36).substr(2, 9).toUpperCase();
                    const payload = {
                        facture_id: <?= (int) $facture->id ?>,
                        transaction_reference: transRef,
                        montant: <?= (float) $facture->montantRestant ?>,
                        devise: "<?= htmlspecialchars($facture->devise) ?>",
                        statut: "success",
                        provider: selectedProvider
                    };

                    // Appeler le webhook API
                    fetch('<?= View::url("api/paiements/callback") ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(response => response.json())
                    .then(data => {
                        processingScreen.style.display = 'none';
                        if (data.ok) {
                            successScreen.style.display = 'block';
                        } else {
                            alert('Erreur lors de la validation du paiement : ' + data.message);
                            formScreen.style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        processingScreen.style.display = 'none';
                        alert('Une erreur réseau est survenue.');
                        formScreen.style.display = 'block';
                    });

                }, 3000);
            });
        });
    </script>
</body>
</html>
