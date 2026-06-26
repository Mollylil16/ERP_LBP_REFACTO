<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Pages\Rh\SignatoryIndexPage;

final class Signatories
{
    public static function signatoriesPage(SignatoryIndexPage $page, string $csrfToken): string
    {
        $settings = $page->settings;

        $header = Ui::pageHeader(
            'Signataires et identite des contrats',
            'Cet ecran centralise la presentation de l employeur, les signataires DG et RH, ainsi que les informations reutilisees dans les contrats PDF avec numerotation et tracabilite RH.',
            [
                'eyebrow' => 'PARAMETRAGE CENTRAL RH',
                'class' => 'rh-hero',
                'actions' => [
                    '<a href="' . View::url('rh/dashboard') . '" class="finea-action-btn finea-action-btn--secondary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; background: #ffffff; border: 1px solid #dfe6f1; border-radius: 8px; padding: 10px 16px; color: #1e293b; text-decoration: none;">
                      <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="color: #0284c7;">
                        <line x1="18" y1="20" x2="18" y2="10"></line>
                        <line x1="12" y1="20" x2="12" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="14"></line>
                      </svg>
                      Tableau de bord RH
                    </a>',
                    '<a href="' . View::url('rh/regles-contrats') . '" class="finea-action-btn finea-action-btn--secondary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; background: #ffffff; border: 1px solid #dfe6f1; border-radius: 8px; padding: 10px 16px; color: #1e293b; text-decoration: none; margin-left: 8px;">
                      <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b;">
                        <line x1="4" y1="21" x2="4" y2="14"></line>
                        <line x1="4" y1="10" x2="4" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12" y2="3"></line>
                        <line x1="20" y1="21" x2="20" y2="16"></line>
                        <line x1="20" y1="12" x2="20" y2="3"></line>
                        <line x1="1" y1="14" x2="7" y2="14"></line>
                        <line x1="9" y1="8" x2="15" y2="8"></line>
                        <line x1="17" y1="16" x2="23" y2="16"></line>
                      </svg>
                      Regles de paie
                    </a>'
                ],
            ]
        );

        $docCard = '
        <div class="finea-section-card" style="margin-bottom: 24px; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; gap: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1;">
            <div style="flex-shrink: 0;">
                <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #0284c7; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">DOCUMENTAIRE</p>
                <h2 class="finea-section-title" style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Ce que pilote cet ecran</h2>
            </div>
            <div style="background-color: #f0f9ff; border: 1px solid #e0f2fe; color: #0369a1; padding: 12px 24px; border-radius: 9999px; font-size: 0.88rem; line-height: 1.5; flex: 1;">
                Le contrat PDF affiche un filigrane Brouillon ou Original signe, un numero unique CTRH annuel et les signataires DG / RH pre-remplis.
            </div>
        </div>';

        $employerCard = '
        <div class="finea-section-card" style="margin-bottom: 24px; padding: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1;">
            <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #64748b; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">EMPLOYEUR</p>
            <h2 class="finea-section-title" style="margin: 0 0 20px 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Identite legale sur le contrat</h2>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Nom de l\'employeur</label>
                    <input type="text" name="employer_name" value="' . View::e((string)($settings['employer_name'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Forme juridique</label>
                    <input type="text" name="legal_form" value="' . View::e((string)($settings['legal_form'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Mention capital</label>
                    <input type="text" name="capital_mention" value="' . View::e((string)($settings['capital_mention'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1.33fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Siege social</label>
                    <input type="text" name="address" value="' . View::e((string)($settings['address'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">RCCM</label>
                    <input type="text" name="rccm" value="' . View::e((string)($settings['rccm'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
            </div>
            
            <div>
                <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Texte de representation</label>
                <textarea name="representation_text" class="finea-form-control" rows="3" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit;" required>' . View::e((string)($settings['representation_text'] ?? '')) . '</textarea>
            </div>
        </div>';

        $signatoriesCard = '
        <div class="finea-section-card" style="margin-bottom: 24px; padding: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1;">
            <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #64748b; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">SIGNATAIRES</p>
            <h2 class="finea-section-title" style="margin: 0 0 20px 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Direction generale et RH</h2>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Ville de signature</label>
                    <input type="text" name="signature_city" value="' . View::e((string)($settings['signature_city'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Nom signataire DG</label>
                    <input type="text" name="dg_signatory_name" value="' . View::e((string)($settings['dg_signatory_name'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Qualite DG</label>
                    <input type="text" name="dg_title" value="' . View::e((string)($settings['dg_title'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Nom signataire RH</label>
                    <input type="text" name="rh_signatory_name" value="' . View::e((string)($settings['rh_signatory_name'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 3fr; gap: 16px; align-items: center;">
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Qualite RH</label>
                    <input type="text" name="rh_title" value="' . View::e((string)($settings['rh_title'] ?? '')) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div style="background-color: #f0f9ff; border: 1px solid #e0f2fe; color: #0369a1; padding: 16px 20px; border-radius: 8px; font-size: 0.85rem; line-height: 1.5; margin-top: 20px;">
                    Le PDF utilisera automatiquement ces noms et qualites dans le bloc final des signatures. Le mode Brouillon affichera un filigrane BROUILLON, et le mode Original signe affichera ORIGINAL SIGNE avec un numero CTRH unique.
                </div>
            </div>
        </div>';

        $footerCard = '
        <div class="finea-section-card" style="margin-bottom: 24px; padding: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1;">
            <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #64748b; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">PIED DE PAGE</p>
            <h2 class="finea-section-title" style="margin: 0 0 20px 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Mentions imprimees</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Ligne 1</label>
                    <textarea name="footer_line1" class="finea-form-control" rows="3" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit;" required>' . View::e((string)($settings['footer_line1'] ?? '')) . '</textarea>
                </div>
                <div>
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Ligne 2</label>
                    <textarea name="footer_line2" class="finea-form-control" rows="3" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit;" required>' . View::e((string)($settings['footer_line2'] ?? '')) . '</textarea>
                </div>
            </div>
        </div>';

        $stickyFooter = '
        <div class="rh-sticky-footer" style="position: sticky; bottom: 0; background: #fff; border-top: 1px solid #dfe6f1; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05); margin-left: -24px; margin-right: -24px; margin-top: 32px; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; z-index: 100;">
            <span style="color: #64748b; font-size: 0.9rem;">
                Les prochains contrats RH reutiliseront immediatement ces informations dans le PDF et le registre de tracabilite.
            </span>
            <button type="submit" class="finea-action-btn" style="background-color: #0f172a; color: #fff; font-weight: bold; border-radius: 8px; padding: 12px 24px; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; font-size: 0.9rem;">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Enregistrer le parametrage contrats
            </button>
        </div>';

        $formContent = '<form method="post" action="' . View::url('rh/signataires') . '">'
            . Form::hidden('_csrf_token', $csrfToken)
            . $docCard
            . $employerCard
            . $signatoriesCard
            . $footerCard
            . $stickyFooter
            . '</form>';

        return '<div class="finea-shell rh-signatories-page">'
            . '<div class="finea-container">'
            . $header
            . $formContent
            . '</div>'
            . '</div>';
    }
}
