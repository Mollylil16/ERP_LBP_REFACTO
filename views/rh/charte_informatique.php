<?php

declare(strict_types=1);

use App\Helpers\View;
use App\View\Components\Ui;

/** @var \App\Support\ViewBag $viewData */
$viewData ??= \App\Support\ViewBag::from(get_defined_vars());

$header = Ui::pageHeader(
    'Charte d\'Utilisation Informatique & Traçabilité des Transactions',
    'Document juridique d\'encadrement et de protection de la direction générale (Conforme au Code du Travail Ivoirien - Loi n° 2015-532).',
    [
        'eyebrow' => 'Règlement Intérieur & Sécurité Juridique',
        'class' => 'rh-hero-white',
        'actions' => [
            Ui::button(' Imprimer la Charte (PDF)', [
                'variant' => 'accent',
                'type' => 'button',
                'onclick' => 'window.print(); return false;'
            ])
        ]
    ]
);

?>
<div class="finea-shell">
    <div class="finea-container">
        <?= $header ?>

        <div style="background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:2.5rem; color:#1e293b; font-size:0.95rem; line-height:1.7; box-shadow:0 4px 12px rgba(0,0,0,0.03);">
            
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #0f172a; padding-bottom:1.5rem; margin-bottom:2rem;">
                <div>
                    <h2 style="font-size:1.4rem; font-weight:800; color:#0f172a; margin:0;">SOCIÉTÉ LA BELLE PORTE TRANSIT (LBP TRANSIT - CI)</h2>
                    <span style="font-size:0.85rem; color:#64748b; font-weight:600;">Siège Social : Abidjan, Côte d'Ivoire &bull; Réseau d'Agences Internationales</span>
                </div>
                <div style="text-align:right; background:#f1f5f9; padding:8px 16px; border-radius:8px;">
                    <strong style="font-size:0.75rem; color:#2563eb; display:block; text-transform:uppercase;">ANNEXE RÉGLEMENTAIRE N° 2026-IT</strong>
                    <small style="color:#64748b;">Code du Travail Ivoirien Art. 16.1 & suivants</small>
                </div>
            </div>

            <div style="background:#eff6ff; border-left:4px solid #2563eb; padding:1.25rem; border-radius:6px; margin-bottom:2rem; color:#1e40af;">
                <strong style="display:block; font-size:1rem; margin-bottom:4px;"> ⚠️ AVIS JURIDIQUE & PROTECTION DU DIRECTEUR GÉNÉRAL</strong>
                En application de la législation sociale de la République de Côte d'Ivoire, l'utilisation de l'ERP LBP Transit et des terminaux d'agences s'effectue sous traçabilité automatisée. Tout agent accédant au système reconnaît l'opposabilité juridique de la présente charte.
            </div>

            <h3 style="color:#0f172a; font-size:1.1rem; font-weight:700; margin-top:1.5rem; margin-bottom:0.75rem;">ARTICLE 1 : OBJET & CHAMP D'APPLICATION</h3>
            <p>La présente Charte fixe les conditions générales d'utilisation du système d'information, des logiciels de colisage, des caisses automatiques et des réseaux de télécommunication au sein de <strong>LBP Transit</strong>. Elle s'impose de plein droit à l'ensemble du personnel (cadres, chefs d'agence, caissiers, agents logistiques, stagiaires et prestataires).</p>

            <h3 style="color:#0f172a; font-size:1.1rem; font-weight:700; margin-top:1.5rem; margin-bottom:0.75rem;">ARTICLE 2 : TRAÇABILITÉ AUTOMATISÉE & AUDIT TRAIL IMMUABLE</h3>
            <p>Afin d'assurer la sécurité des actifs financiers et la transparence des opérations :</p>
            <ul style="padding-left:1.5rem; margin-bottom:1rem;">
                <li><strong>Enregistrement Infalsifiable :</strong> Toute création de colis, modification de tarif, encaissement d'espèces, annulation de facture ou clôture de caisse donne lieu à un enregistrement chronologique immuable par <em>chaînage cryptographique SHA-256 (Audit Log)</em>.</li>
                <li><strong>Attribution Nominative :</strong> Chaque transaction est associée à l'identifiant individuel, à l'adresse IP et à l'horodatage exact de l'employé ayant validé l'action.</li>
                <li><strong>Moteur d'Intégrité :</strong> Les disparités de pesée, les sous-déclarations de valeur au détriment du client ou de l'entreprise, et les retenues d'espèces hors caisse font l'objet d'un signalement automatique qualifié à la Direction Générale.</li>
            </ul>

            <h3 style="color:#0f172a; font-size:1.1rem; font-weight:700; margin-top:1.5rem; margin-bottom:0.75rem;">ARTICLE 3 : MANQUANTS DE CAISSE, DÉTOURNEMENTS ET FAUTES LOURDES</h3>
            <p>Conformément au Code du Travail Ivoirien :</p>
            <ul style="padding-left:1.5rem; margin-bottom:1rem;">
                <li><strong>Qualification de Faute Lourde :</strong> Constitue une faute lourde susceptible d'entraîner la rupture immédiate du contrat de travail sans préavis ni indemnité : toute dissimulation d'encaissement, toute saisie délibérément erronée visant un enrichissement personnel, et toute suppression frauduleuse d'enregistrement système.</li>
                <li><strong>Poursuites Pénales :</strong> LBP Transit se réserve l'exercice de poursuites judiciaires devant les juridictions répressives compétentes d'Abidjan pour la restitution intégrale des sommes soustraites.</li>
            </ul>

            <h3 style="color:#0f172a; font-size:1.1rem; font-weight:700; margin-top:1.5rem; margin-bottom:0.75rem;">ARTICLE 4 : RECONNAISSANCE & SIGNATURE DE L'EMPLOYÉ</h3>
            <p>Chaque employé confirme avoir pris connaissance de la présente charte. L'utilisation continue des identifiants d'accès ERP vaut acceptation formelle et signature électronique des présentes dispositions.</p>

            <div style="margin-top:3rem; display:grid; grid-template-columns:1fr 1fr; gap:2rem; border-top:1px solid #e2e8f0; padding-top:2rem;">
                <div>
                    <strong style="display:block; color:#0f172a; margin-bottom:0.5rem;">Pour la Direction Générale LBP Transit</strong>
                    <div style="border:1px dashed #cbd5e1; height:80px; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:0.85rem;">
                        Cachet & Signature Direction
                    </div>
                </div>
                <div>
                    <strong style="display:block; color:#0f172a; margin-bottom:0.5rem;">Visa & Empreinte de l'Employé</strong>
                    <div style="border:1px dashed #cbd5e1; height:80px; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:0.85rem;">
                        Mention "Lu et Approuvé"
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
