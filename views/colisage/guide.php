<?php

declare(strict_types=1);

use App\View\Components\Ui;

// Titre et en-tête de la page
$header = Ui::pageHeader(
    'Guide d\'Utilisation de la Saisie',
    'Manuel d\'aide interactif pour assister les agents de facturation et de colisage au quotidien.',
    [
        'eyebrow' => 'Aide & Documentation',
        'class' => 'rh-hero-white',
    ]
);

?>

<div class="finea-shell">
    <div class="finea-container">
        <?= $header ?>

        <!-- Styles personnalisés pour l'interactivité et le rendu premium -->
        <style>
            .guide-tabs {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1.5rem;
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 0.5rem;
            }
            .guide-tab-btn {
                background: none;
                border: none;
                padding: 0.75rem 1.25rem;
                font-weight: 600;
                color: #64748b;
                cursor: pointer;
                border-radius: 6px;
                transition: all 0.2s ease;
                font-size: 0.95rem;
            }
            .guide-tab-btn:hover {
                background: #f1f5f9;
                color: #1e293b;
            }
            .guide-tab-btn.active {
                background: #1e3a5f;
                color: #ffffff;
            }
            .guide-pane {
                display: none;
                animation: fadeIn 0.3s ease;
            }
            .guide-pane.active {
                display: block;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(5px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .step-badge {
                width: 26px;
                height: 26px;
                background: #f08c00;
                color: white;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                margin-right: 0.5rem;
            }
            .info-box {
                background: #eff6ff;
                border-left: 5px solid #3b82f6;
                padding: 1rem;
                border-radius: 0 8px 8px 0;
                margin-bottom: 1.5rem;
            }
            .info-box h4 {
                margin: 0 0 0.5rem 0;
                color: #1d4ed8;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .warning-box {
                background: #fffbeb;
                border-left: 5px solid #d97706;
                padding: 1rem;
                border-radius: 0 8px 8px 0;
                margin-bottom: 1.5rem;
            }
            .warning-box h4 {
                margin: 0 0 0.5rem 0;
                color: #b45309;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
            .scenario-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 1.25rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .scenario-card h4 {
                margin-top: 0;
                color: #1e3a5f;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 0.5rem;
            }
        </style>

        <!-- Navigation par onglets -->
        <div class="guide-tabs">
            <button class="guide-tab-btn active" data-target="pane-saisie">📝 1. Saisie de Colis</button>
            <button class="guide-tab-btn" data-target="pane-trajets">🚚 2. Types & Trajets</button>
            <button class="guide-tab-btn" data-target="pane-tarifs">🧮 3. Tarification & Emballages</button>
            <button class="guide-tab-btn" data-target="pane-retrait">🔄 4. Retrait & Gardiennage</button>
            <button class="guide-tab-btn" data-target="pane-ia">🛡️ 5. Règles Anti-Fraude</button>
        </div>

        <!-- Contenu des Onglets -->

        <!-- 1. SAISIE DE COLIS -->
        <div class="guide-pane active" id="pane-saisie">
            <div class="info-box">
                <h4>📌 Raccourci Opérationnel</h4>
                <p>Pour éviter toute erreur de trajet, naviguez via la section <strong>"Opérations"</strong> dans le menu latéral gauche pour saisir directement un colis sur un vol/cargo spécifique. Cela verrouille le trajet et empêche toute modification accidentelle.</p>
            </div>

            <div class="grid-2">
                <section class="finea-section-card">
                    <div class="finea-section-heading">
                        <h2 class="finea-section-title">Expéditeur & Destinataire</h2>
                    </div>
                    <p>Chaque colis doit être lié à deux contacts distincts et joignables :</p>
                    <ul style="padding-left: 1.25rem; line-height: 1.6;">
                        <li><strong>Recherche Rapide</strong> : Saisir les premières lettres du nom ou le téléphone pour charger un client existant.</li>
                        <li><strong>Création rapide</strong> : Remplir l'encadré pour un nouveau client.</li>
                        <li><strong>Le numéro de Téléphone</strong> : Doit obligatoirement être saisi au format international. Il est crucial pour les notifications SMS/WhatsApp d'arrivée en agence.</li>
                        <li><strong>Adresse Géographique</strong> : Indiquer la ville et le quartier de livraison ou de ramassage.</li>
                    </ul>
                </section>

                <section class="finea-section-card">
                    <div class="finea-section-heading">
                        <h2 class="finea-section-title">Pesée & Fret</h2>
                    </div>
                    <ul style="padding-left: 1.25rem; line-height: 1.6;">
                        <li><strong>Poids total (kg)</strong> : Peser la marchandise sur la balance et reporter la valeur brute exacte.</li>
                        <li><strong>Valeur déclarée</strong> : Doit être honnête. Elle sert de base d'indemnisation et pour le calcul de l'assurance.</li>
                        <li><strong>Devise</strong> : Sélectionner <code>XOF</code> (par défaut), <code>EUR</code> ou <code>USD</code>.</li>
                        <li><strong>Agences départ / arrivée</strong> : Indiquer l'agence de prise en charge et le point de retrait final.</li>
                        <li><strong>Assurance</strong> : Cocher pour appliquer la couverture d'assurance facultative.</li>
                    </ul>
                </section>
            </div>

            <section class="finea-section-card" style="margin-top: 1.5rem;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Étapes pas-à-pas pour l'enregistrement</h2>
                </div>
                <div style="line-height: 1.8;">
                    <p><span class="step-badge">1</span> Renseigner les informations de l'expéditeur.</p>
                    <p><span class="step-badge">2</span> Renseigner les informations du destinataire.</p>
                    <p><span class="step-badge">3</span> Indiquer le poids et la valeur déclarée globale.</p>
                    <p><span class="step-badge">4</span> Ajouter les lignes de marchandises (produit, quantité, poids unitaire, emballage).</p>
                    <p><span class="step-badge">5</span> Cliquer sur <strong>Enregistrer le Colis</strong>. L'ERP génère le tracking unique.</p>
                    <p><span class="step-badge">6</span> Sur la fiche colis, cliquer sur <strong>⚡ Facturer (1-Clic)</strong> pour finaliser la facturation.</p>
                </div>
            </section>
        </div>

        <!-- 2. TYPES & TRAJETS -->
        <div class="guide-pane" id="pane-trajets">
            <div class="warning-box">
                <h4>⚠️ Fret Aérien vs Maritime</h4>
                <p>Assurez-vous de bien sélectionner le bon trajet. Les tarifs aériens sont appliqués au kilogramme sur les marchandises légères et de valeur, tandis que les conteneurs maritimes tolèrent de plus grands volumes mais appliquent des délais plus longs.</p>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                <div class="scenario-card">
                    <h4>🚢 Maritime Standard (`LB-CI`)</h4>
                    <p><strong>Exemple</strong> : Sacs de vêtements d'Abidjan vers la France.</p>
                    <p>Saisir le poids total (ex: 75 kg) et sélectionner l'emballage <em>Sac Bôrô</em> dans le tableau. Le système calculera la douane et le fret selon le tarif au kg spécifique.</p>
                </div>
                <div class="scenario-card">
                    <h4>✈️ Aérien Urgent (`CA-CI`)</h4>
                    <p><strong>Exemple</strong> : Ordinateur portable ou smartphones.</p>
                    <p>Cocher l'assurance (2% de la valeur) et utiliser du <em>Papier film</em>. Renseigner scrupuleusement la marque et la valeur pour la douane aérienne.</p>
                </div>
                <div class="scenario-card">
                    <h4>✉️ Express International (`DHL`)</h4>
                    <p><strong>Exemple</strong> : Documents ou enveloppe.</p>
                    <p>Appliquer le forfait fixe DHL (ex: 25 000 XOF) et utiliser l'emballage carton DHL officiel. Conserver le bordereau DHL dans la description.</p>
                </div>
            </div>
        </div>

        <!-- 3. TARIFICATION & EMBALLAGES -->
        <div class="guide-pane" id="pane-tarifs">
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Comment l'ERP calcule-t-il les tarifs ?</h2>
                </div>
                <table class="finea-table" style="table-layout: auto; margin-top: 1rem;">
                    <thead>
                        <tr style="background:#1e3a5f; color:#fff;">
                            <th>Élément</th>
                            <th>Formule de Calcul</th>
                            <th>Explication</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Total Ligne Marchandise</strong></td>
                            <td><code>(Nbre Colis × Poids Unitaire × Prix/Kg) + (Qté Emballage × Prix Emballage)</code></td>
                            <td>Calcule le montant brut des marchandises et des cartons fournis par l'agence.</td>
                        </tr>
                        <tr>
                            <td><strong>Montant Assurance</strong></td>
                            <td><code>Valeur Déclarée × 0.02 (2%)</code></td>
                            <td>Calculé automatiquement si la case "Assurance souscrite" est cochée.</td>
                        </tr>
                        <tr>
                            <td><strong>Grand Total</strong></td>
                            <td><code>Somme(Total Lignes) + Montant Assurance</code></td>
                            <td>Montant final à payer par le client (affiché en XOF et EUR).</td>
                        </tr>
                    </tbody>
                </table>
                <div class="warning-box" style="margin-top:1.5rem;">
                    <h4>⚠️ Règle de cohérence des prix de ligne</h4>
                    <p>Si vous sélectionnez plusieurs produits sur une même ligne du tableau (multi-select), ils doivent obligatoirement avoir le même tarif/kg configuré. Si ce n'est pas le cas, le système affichera un message d'erreur et bloquera la validation. Vous devez créer une ligne distincte.</p>
                </div>
            </section>
        </div>

        <!-- 4. RETRAIT & GARDIENNAGE -->
        <div class="guide-pane" id="pane-retrait">
            <div class="grid-2">
                <section class="finea-section-card">
                    <div class="finea-section-heading">
                        <h2 class="finea-section-title">Contrôle de Facture obligatoire</h2>
                    </div>
                    <p>Avant de remettre un colis physique à son destinataire :</p>
                    <div class="warning-box">
                        <p><strong>Règle d'or</strong> : La facture liée au colis doit avoir le statut <strong>PAYÉE</strong>. Si le statut de paiement est "Émise" ou "Partiel", refusez la remise et orientez le client vers la caisse.</p>
                    </div>
                </section>

                <section class="finea-section-card">
                    <div class="finea-section-heading">
                        <h2 class="finea-section-title">Frais de Gardiennage en cas de retard</h2>
                    </div>
                    <p>Le destinataire dispose de <strong>7 jours de franchise gratuits</strong> à compter de la date d'arrivée du colis en agence.</p>
                    <p>Au-delà de ces 7 jours :</p>
                    <ul style="padding-left:1.25rem;">
                        <li>L'ERP calcule automatiquement le dépassement.</li>
                        <li>Des pénalités de <strong>500 FCFA par jour de retard</strong> sont facturées et doivent être réglées lors du retrait.</li>
                    </ul>
                </section>
            </div>

            <section class="finea-section-card" style="margin-top:1.5rem;">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Procédure de Retrait de Colis</h2>
                    <span>Fiche colis > Section "Remise du colis / Retrait"</span>
                </div>
                <p>Compléter rigoureusement les informations d'identité du récupérateur :</p>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:1rem;">
                    <div style="background:#f8fafc; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0;">
                        <strong>1. Nom Complet</strong><br><span style="color:#64748b; font-size:0.85rem;">Nom de la personne présente au guichet</span>
                    </div>
                    <div style="background:#f8fafc; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0;">
                        <strong>2. N° de CNI / Caisse</strong><br><span style="color:#64748b; font-size:0.85rem;">Numéro de pièce d'identité ou de passeport</span>
                    </div>
                    <div style="background:#f8fafc; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0;">
                        <strong>3. Téléphone</strong><br><span style="color:#64748b; font-size:0.85rem;">Numéro de téléphone du récupérateur</span>
                    </div>
                </div>
                <p style="margin-top:1.5rem;">Une fois les champs complétés, cliquez sur <strong>"Confirmer le Retrait"</strong>. Le statut du colis passe automatiquement à <code>RETIRÉ</code>.</p>
            </section>
        </div>

        <!-- 5. REGLES ANTI-FRAUDE -->
        <div class="guide-pane" id="pane-ia">
            <section class="finea-section-card">
                <div class="finea-section-heading">
                    <h2 class="finea-section-title">Moteur de Surveillance ML Anti-Fraude</h2>
                    <span>Surveillance en temps réel des actions de saisie</span>
                </div>
                <p>Chaque action sur l'ERP est analysée par un système d'IA. Pour éviter les alertes sur votre profil, veillez à respecter ces règles :</p>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-top:1rem;">
                    <div style="border-left: 4px solid #ef4444; padding-left: 1rem;">
                        <h4 style="color:#ef4444; margin-top:0;">🚫 Interdiction de sous-déclaration</h4>
                        <p style="font-size:0.9rem; color:#475569;">Ne diminuez pas artificiellement la valeur déclarée des colis lourds. L'IA compare le ratio valeur/poids avec l'historique et signale toute anomalie.</p>
                    </div>
                    <div style="border-left: 4px solid #ef4444; padding-left: 1rem;">
                        <h4 style="color:#ef4444; margin-top:0;">🚫 Non-cumul des tâches (SoD)</h4>
                        <p style="font-size:0.9rem; color:#475569;">L'agent ayant saisi le colis ne peut pas encaisser le paiement. L'encaissement doit être effectué par la caissière ou un autre utilisateur qualifié.</p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-top:1.5rem;">
                    <div style="border-left: 4px solid #d97706; padding-left: 1rem;">
                        <h4 style="color:#d97706; margin-top:0;">⏳ Actions Hors-Horaires</h4>
                        <p style="font-size:0.9rem; color:#475569;">La création ou la modification de colis et factures la nuit ou les week-ends sans autorisation est enregistrée comme suspecte par le système.</p>
                    </div>
                    <div style="border-left: 4px solid #d97706; padding-left: 1rem;">
                        <h4 style="color:#d97706; margin-top:0;">🔒 Modifications Post-Validation</h4>
                        <p style="font-size:0.9rem; color:#475569;">Modifier des données d'un colis ou d'une facture déjà clôturée/payée déclenche une alerte de sécurité. Obtenez toujours l'accord écrit du DG.</p>
                    </div>
                </div>
            </section>
        </div>

    </div>
</div>

<!-- Script interactif de gestion des onglets -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.guide-tab-btn');
        const panes = document.querySelectorAll('.guide-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Retirer la classe active de tous les onglets
                tabs.forEach(t => t.classList.remove('active'));
                // Cacher tous les panneaux
                panes.forEach(p => p.classList.remove('active'));

                // Activer l'onglet cliqué
                this.classList.add('active');
                // Afficher le panneau correspondant
                const target = this.getAttribute('data-target');
                const targetPane = document.getElementById(target);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    });
</script>
