<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;
use App\View\Components\Ui;
use App\View\Pages\Rh\HolidayIndexPage;

final class Holidays
{
    public static function holidaysPage(HolidayIndexPage $page, string $csrfToken): string
    {
        $header = Ui::pageHeader(
            'Gestion des feries',
            'Les dates enregistrees ici sont exclues des jours ouvres (lun->ven) pour le calcul de la paie et pour le bouton "Pointer tout le mois (jours ouvres)".',
            [
                'eyebrow' => 'RESSOURCES HUMAINES',
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
                    '<a href="' . View::url('rh/pointage') . '" class="finea-action-btn finea-action-btn--secondary" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; background: #ffffff; border: 1px solid #dfe6f1; border-radius: 8px; padding: 10px 16px; color: #1e293b; text-decoration: none; margin-left: 8px;">
                      <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" style="color: #10b981;">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <polyline points="16 11 18 13 22 9"></polyline>
                      </svg>
                      Pointage
                    </a>'
                ],
            ]
        );

        $style = '<style>
            .rh-holidays-page .rh-hero {
                background: linear-gradient(120deg, #064e3b, #14532d 62%, #0f766e) !important;
            }
        </style>';

        $leftCard = '
        <div class="finea-section-card" style="padding: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1; height: 100%; box-sizing: border-box;">
            <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #16a34a; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">PERIODE</p>
            <h2 class="finea-section-title" style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Selection du mois</h2>
            <p style="color: #64748b; font-size: 0.9rem; margin: 4px 0 20px 0;">Reference jours ouvres (mois) : <strong>' . (int)$page->businessDaysCount . '</strong></p>
            
            <form method="get" action="' . View::url('rh/feries') . '" style="display: flex; align-items: flex-end; gap: 12px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Mois</label>
                    <input type="month" name="month" value="' . View::e($page->selectedMonth) . '" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;" required>
                </div>
                <button type="submit" class="finea-action-btn" style="background-color: #0f172a; color: #fff; border-radius: 6px; padding: 11px 20px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; height: 41px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    Charger
                </button>
            </form>
            
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 16px; border-radius: 8px; font-size: 0.85rem; line-height: 1.5;">
                Astuce : si un ferie tombe un samedi/dimanche, il n\'impacte pas les jours ouvres (deja exclus).
            </div>
        </div>';

        $rightCard = '
        <div class="finea-section-card" style="padding: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1; height: 100%; box-sizing: border-box;">
            <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #6366f1; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">AJOUT</p>
            <h2 class="finea-section-title" style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Enregistrer un ferie</h2>
            <p style="color: #64748b; font-size: 0.9rem; margin: 4px 0 20px 0;">Date + libelle (optionnel). Le meme jour peut etre mis a jour.</p>
            
            <form method="post" action="' . View::url('rh/feries') . '">
                ' . Form::hidden('_csrf_token', $csrfToken) . '
                <input type="hidden" name="month" value="' . View::e($page->selectedMonth) . '">
                
                <div style="display: grid; grid-template-columns: 1.2fr 2fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Date</label>
                        <input type="date" name="holiday_date" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;" required>
                    </div>
                    <div>
                        <label class="finea-form-label" style="display: block; font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px;">Libelle</label>
                        <input type="text" name="name" placeholder="Ex: Fete du travail" class="finea-form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                    </div>
                </div>
                
                <button type="submit" class="finea-action-btn" style="background-color: #4f46e5; color: #fff; border-radius: 8px; padding: 12px 24px; font-weight: bold; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; height: 44px;">
                    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Ajouter / Mettre a jour
                </button>
            </form>
        </div>';

        $grid = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">'
            . $leftCard
            . $rightCard
            . '</div>';

        $parts = explode('-', $page->selectedMonth);
        $year = $parts[0];
        $month = $parts[1];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
        $rangeText = $year . '-' . $month . '-01 -> ' . $year . '-' . $month . '-' . sprintf('%02d', $daysInMonth);

        $totalCount = count($page->filteredHolidays);
        $badge = '<span style="background-color: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 6px 16px; border-radius: 9999px; font-weight: bold; font-size: 0.85rem;">Total : ' . $totalCount . '</span>';

        $rowsHtml = '';
        if ($page->filteredHolidays === []) {
            $rowsHtml = '<tr>
                <td colspan="3" style="text-align: center; color: #64748b; padding: 32px; font-size: 0.95rem;">
                    Aucun ferie enregistre pour cette periode.
                </td>
            </tr>';
        } else {
            foreach ($page->filteredHolidays as $row) {
                $rowId = (int)$row['id'];
                $isActive = (int)($row['is_active'] ?? 0) === 1;
                $activeClass = $isActive ? '' : 'color: #94a3b8; text-decoration: line-through;';
                $btnLabel = $isActive ? 'Actif' : 'Inactif';
                $btnVariant = $isActive ? 'success' : 'secondary';
                $isRecurringStr = (int)($row['is_recurring'] ?? 0) === 1 ? ' <span style="background-color: #eff6ff; color: #1d4ed8; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 600; margin-left: 8px; display: inline-block;">Recurrent</span>' : '';

                $form = '<form method="post" action="' . View::url('rh/feries/toggle') . '" style="margin: 0; display: inline-block;">'
                    . Form::hidden('_csrf_token', $csrfToken)
                    . Form::hidden('id', $rowId)
                    . '<input type="hidden" name="month" value="' . View::e($page->selectedMonth) . '">'
                    . Ui::button($btnLabel, ['variant' => $btnVariant, 'type' => 'submit', 'class' => 'finea-action-btn--xs'])
                    . '</form>';

                $rowsHtml .= '<tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px 16px; font-weight: 600; ' . $activeClass . '">' . $page->formatDate($row['holiday_date']) . '</td>
                    <td style="padding: 12px 16px; font-weight: 500; ' . $activeClass . '">' . View::e((string)$row['name']) . $isRecurringStr . '</td>
                    <td style="padding: 12px 16px;">' . $form . '</td>
                </tr>';
            }
        }

        $listCard = '
        <div class="finea-section-card" style="padding: 24px; border-radius: 12px; background: #fff; border: 1px solid #dfe6f1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <p class="rh-eyebrow" style="margin: 0 0 2px 0; color: #64748b; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase;">LISTE</p>
                    <h2 class="finea-section-title" style="margin: 0; font-size: 1.4rem; font-weight: 700; color: #1e293b;">Feries du mois</h2>
                    <p style="color: #64748b; font-size: 0.85rem; margin: 4px 0 0 0;">' . $rangeText . '</p>
                </div>
                ' . $badge . '
            </div>
            
            <div class="finea-table-wrap" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <table class="finea-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 0.85rem; width: 25%;">Date</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 0.85rem; width: 55%;">Libelle</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; font-size: 0.85rem; width: 20%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $rowsHtml . '
                    </tbody>
                </table>
            </div>
        </div>';

        return $style
            . '<div class="finea-shell rh-holidays-page">'
            . '<div class="finea-container">'
            . $header
            . $grid
            . $listCard
            . '</div>'
            . '</div>';
    }
}
