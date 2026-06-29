<?php

declare(strict_types=1);

namespace App\View\Components;

use App\View\Pages\Rh\PayrollEnginePage;
use App\Helpers\View;

final class PayrollEngine
{
    public static function enginePage(PayrollEnginePage $page, string $csrfToken): string
    {
        // 1. Hero Header & Metrics
        $header = Ui::pageHeader(
            'Paie Cote d\'Ivoire avec versions',
            'Espace de controle des versions, rubriques, tranches fiscales, cotisations, plafonds et transports utilises pour calculer la paie. En cas de changement legal ou interne, on cree une nouvelle version au lieu d\'ecraser l\'ancienne.',
            [
                'eyebrow' => 'MOTEUR DE PAIE',
                'class' => 'rh-hero rh-hero-wizard', // reuse wizard dark background style
            ]
        );

        // Metrics under header
        $metricsHtml = '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: -15px; margin-bottom: 30px; position: relative; z-index: 10;">'
            . self::metricCard('VERSION', 'PAYROLL_CI_2024')
            . self::metricCard('RUBRIQUES', (string) count($page->lineItems))
            . self::metricCard('TRANCHES', '6')
            . self::metricCard('COTISATIONS', '4')
            . '</div>';

        // 2. Version a parametrer
        $versionParamHtml = '<div style="display: flex; justify-content: space-between; align-items: center;">'
            . '<div><h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Version a parametrer</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px;">Selectionnez la version sur laquelle les reglages doivent etre consultes ou ajustes.</p></div>'
            . '<div style="display: flex; gap: 12px; align-items: center;">'
            . '<select class="finea-form-control" style="width: 300px; padding: 8px 12px; border: 1px solid var(--finea-border); border-radius: 6px;"><option>Cote d\'Ivoire - regles fiscales et sociales 2024</option></select>'
            . '<button class="finea-btn" style="background: #0B1120; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600;">Charger</button>'
            . '</div></div>';
        $versionParamCard = self::baseCard($versionParamHtml);

        // 3. Generation groupee des bulletins
        $genGroupeeHtml = '<div style="display: flex; justify-content: space-between; align-items: center;">'
            . '<div><h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Generation groupee des bulletins</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px;">Le moteur prend les contrats actifs, ignore les bulletins deja generes et remonte les erreurs salarie par salarie.</p></div>'
            . '<div style="display: flex; gap: 12px; align-items: center;">'
            . '<input type="date" class="finea-form-control" style="padding: 8px 12px; border: 1px solid var(--finea-border); border-radius: 6px;">'
            . '<button class="finea-btn" style="background: #0F766E; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600;">Generer tous les bulletins</button>'
            . '</div></div>';
        $genGroupeeCard = self::baseCard($genGroupeeHtml);

        // 4. Types de contrats relies au moteur
        $contratsHtml = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">'
            . '<div><h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Types de contrats relies au moteur</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px;">Ces profils alimentent l\'assistant de paie : jours de reference, heures par jour, heures supplementaires, prime de precarite et taux payes. Le moteur applique ensuite la version de paie active, les rubriques, cotisations, impots et plafonds.</p></div>'
            . '<button class="finea-btn" style="background: #0B1120; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600;">Parametrer les contrats</button>'
            . '</div>';
        
        $contratsRows = [];
        foreach ($page->contractRules as $rule) {
            $contratsRows[] = [
                $rule['label'],
                number_format((float)$rule['working_days'], 2, ',', ' '),
                number_format((float)$rule['hours_per_day'], 2, ',', ' '),
                number_format((float)$rule['overtime_multiplier'], 2, ',', ' '),
                number_format((float)$rule['precarity_auto_rate'], 0) . ' %',
                number_format((float)$rule['mission_rate'], 0) . ' %',
                number_format((float)$rule['leave_rate'], 0) . ' %',
                number_format((float)$rule['absence_rate'], 0) . ' %',
            ];
        }

        $contratsTable = self::createTable(
            ['TYPE DE CONTRAT', 'JOURS', 'HEURES / JOUR', 'HEURES SUPP.', 'PRECARITE', 'MISSION', 'CONGE', 'ABSENCE'],
            $contratsRows
        );
        $contratsCard = self::baseCard($contratsHtml . $contratsTable);

        // 5. Cloture des periodes
        $clotureHtml = '<div style="display: flex; justify-content: space-between; align-items: center;">'
            . '<div><h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Cloture des periodes</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px;">Une periode cloturee empeche la creation de nouveaux bulletins pour le mois valide.</p></div>'
            . '<div style="display: flex; gap: 12px; align-items: center; background: #F8FAFC; padding: 10px; border: 1px solid var(--finea-border); border-radius: 8px;">'
            . '<input type="date" class="finea-form-control" style="padding: 8px 12px; border: 1px solid var(--finea-border); border-radius: 6px;">'
            . '<button class="finea-btn" style="background: #0B1120; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600;">Cloturer</button>'
            . '</div></div>';
        $clotureCard = self::baseCard($clotureHtml);

        // 6. Versions de paie
        $versionsTop = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">'
            . '<div style="flex: 1; padding-right: 20px;"><h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Versions de paie</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px; line-height: 1.5;">Pour preparer une reforme ou une correction de regles, copiez une version existante, puis ajustez les nouveaux parametrages.</p></div>'
            . '<div style="flex: 1; background: #F8FAFC; padding: 15px; border-radius: 8px; border: 1px solid var(--finea-border);">'
            . '<div style="display: flex; gap: 10px; margin-bottom: 12px;">'
            . '<select class="finea-form-control" style="flex: 1; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;"><option>Cote d\'Ivoire - regl...</option></select>'
            . '<input type="text" placeholder="Nom de la version" class="finea-form-control" style="flex: 1; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;">'
            . '<input type="date" class="finea-form-control" style="flex: 1; padding: 8px; border: 1px solid var(--finea-border); border-radius: 4px;">'
            . '<label style="display: flex; align-items: center; gap: 5px; font-size: 13px;"><input type="checkbox"> Version active</label>'
            . '</div>'
            . '<button class="finea-btn" style="width: 100%; background: #0B1120; color: white; border: none; padding: 10px; border-radius: 6px; font-weight: 600;">Creer une copie</button>'
            . '</div>'
            . '</div>';
        
        $versionsTable = self::createTable(
            ['VERSION', 'PAYS', 'DEBUT', 'FIN', 'UTILISEE'],
            [
                ['Cote d\'Ivoire - regles fiscales et sociales 2024', 'CI', '2024-01-01', '-', 'Oui'],
            ]
        );
        $versionsCard = self::baseCard($versionsTop . $versionsTable);

        // 7. Rubriques de paie
        $rubriquesHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Rubriques de paie</h3>';
        
        $rubriqueMetadata = [
            'alloc_assist_famille' => ['fiscal' => 'Non', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'alloc_familiales_cps' => ['fiscal' => 'Non', 'social' => 'Non', 'exon' => 'Totalement exonere'],
            'alloc_speciales'      => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Exonere jusqu a 10 % du salaire'],
            'indem_apprentissage'  => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Indemnite d apprentissage exoneree jusqu a 100 000 FCFA'],
            'indem_stage'          => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Indemnite de stage exoneree jusqu a 150 000 FCFA'],
            'prime_outillage'      => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Exonere jusqu a 10 x SMIG horaire'],
            'prime_panier'         => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Exonere jusqu a 3 x SMIG horaire'],
            'prime_salissure'      => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Exonere jusqu a 13 x SMIG horaire'],
            'prime_tenue'          => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Exonere jusqu a 7 x SMIG horaire'],
            'prime_transport'      => ['fiscal' => 'Partiel', 'social' => 'Oui', 'exon' => 'Exonere jusqu a 30 000 FCFA'],
            'avantage_logement'    => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'avantage_vehicule'    => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'heures_supp'          => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'prime_anciennete'     => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'prime_assiduite'      => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'prime_bilan'          => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'indemnites_conges'    => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'prime_fin_annee'      => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'prime_precarite'      => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'prime_rendement'      => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'salaire_categoriel'   => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
            'sursalaire'           => ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'],
        ];

        $rubriquesRows = [];
        foreach ($page->lineItems as $item) {
            $meta = $rubriqueMetadata[$item['code']] ?? ['fiscal' => 'Oui', 'social' => 'Oui', 'exon' => 'Aucune exoneration'];
            $natureLabel = [
                'allocation_prime' => 'Allocation / prime',
                'avantage_nature' => 'Avantage en nature',
                'gain' => 'Gain'
            ][$item['nature']] ?? $item['nature'];
            
            $rubriquesRows[] = [
                $item['name'],
                $natureLabel,
                $meta['fiscal'],
                $meta['social'],
                $meta['exon'],
                $item['is_active'] ? 'Oui' : 'Non'
            ];
        }

        $rubriquesTable = self::createTable(
            ['RUBRIQUE', 'TYPE', 'IMPACT FISCAL ?', 'IMPACT SOCIAL ?', 'MODE D\'EXONERATION ?', 'UTILISEE'],
            $rubriquesRows
        );
        $rubriquesCard = self::baseCard($rubriquesHtml . $rubriquesTable);

        // 8. Modifier les rubriques (Accordion)
        $modifierRubriquesHtml = '<div style="background: #F8FAFC; border: 1px solid var(--finea-border); border-radius: 8px; padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333;">'
            . '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>'
            . 'Modifier les rubriques'
            . '</div>';

        // 9. Historique des bulletins
        $historiqueHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Historique des bulletins</h3>';
        
        $slipsRows = [];
        foreach ($page->slips as $slip) {
            $monthsFr = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
                7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ];
            $periodLabel = ($monthsFr[(int)$slip['month']] ?? $slip['month']) . ' ' . $slip['year'];
            $sbi = number_format((float)$slip['base_salary'], 0, ',', ' ') . ' FCFA';
            $sbs = number_format((float)$slip['base_salary'] + (float)$slip['bonuses_total'], 0, ',', ' ') . ' FCFA';
            $net = number_format((float)$slip['net_salary'], 0, ',', ' ') . ' FCFA';
            $statusFr = ['draft' => 'Brouillon', 'validated' => 'Validé', 'paid' => 'Payé'][$slip['status']] ?? $slip['status'];
            
            $slipsRows[] = [
                $slip['id'],
                $periodLabel,
                $slip['full_name'],
                'PAYROLL_CI_2024',
                $sbi,
                $sbs,
                $net,
                $statusFr
            ];
        }

        $historiqueTable = self::createTable(
            ['N', 'PERIODE', 'SALARIE', 'VERSION', 'SBI', 'SBS', 'NET', 'ETAT'],
            $slipsRows
        );
        $historiqueCard = self::baseCard($historiqueHtml . $historiqueTable);

        // 10. Audit paie
        $auditHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Audit paie</h3>';
        $auditTable = self::createTable(
            ['DATE', 'ACTION', 'UTILISATEUR', 'ELEMENT CONCERNE', 'RESULTAT', 'DETAIL'],
            [] // empty table
        );
        $auditCard = self::baseCard($auditHtml . $auditTable);

        // 11. Bareme progressif & Cotisations sociales
        $baremeRows = [
            ['0 - 75 000', '0,00%'],
            ['75 000 - 240 000', '16,00%'],
            ['240 000 - 800 000', '21,00%'],
            ['800 000 - 2 400 000', '24,00%'],
            ['2 400 000 - 8 000 000', '28,00%'],
            ['8 000 000 - Sans limite', '32,00%'],
        ];
        $baremeHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Bareme progressif</h3>'
            . '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($baremeRows as $row) {
            $baremeHtml .= '<tr style="border-bottom: 1px solid var(--finea-border);">'
                . '<td style="padding: 12px 0; font-size: 14px;">' . $row[0] . '</td>'
                . '<td style="padding: 12px 0; font-weight: 600; font-size: 14px; text-align: right;">' . $row[1] . '</td>'
                . '</tr>';
        }
        $baremeHtml .= '</table>';
        $baremeHtml .= '<div style="background: #F8FAFC; border: 1px solid var(--finea-border); border-radius: 8px; padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333; margin-top: 15px;">'
            . '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>'
            . 'Modifier les tranches'
            . '</div>';
        $baremeCard = self::baseCard($baremeHtml);

        $accidentRate = number_format((float)($page->payrollSettings['work_accident_rate'] ?? 3.00), 2, ',', ' ') . ' %';
        $cnpsSalarial = number_format((float)($page->payrollSettings['cnps_salarial_rate'] ?? 6.30), 2, ',', ' ') . ' %';
        $cnpsPatronal = number_format((float)($page->payrollSettings['cnps_patronal_rate'] ?? 7.70), 2, ',', ' ') . ' %';
        $familyRate = number_format((float)($page->payrollSettings['family_benefits_rate'] ?? 5.75), 2, ',', ' ') . ' %';

        $cotisationsRows = [
            ['Accident du travail', 'Part salarie / part employeur. Base Salaire brut social, plafond aucun', '0 FCFA / ' . $accidentRate],
            ['Couverture maladie universelle', 'Part salarie / part employeur. Base Salaire brut social, plafond aucun', '500 FCFA / 500 FCFA'],
            ['Retraite generale CNPS', 'Part salarie / part employeur. Base Salaire brut social, plafond 3 375 000', $cnpsSalarial . ' / ' . $cnpsPatronal],
            ['Prestations familiales', 'Part salarie / part employeur. Base Salaire brut social, plafond 75 000', '0 FCFA / ' . $familyRate],
        ];
        $cotisationsHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Cotisations sociales</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 13px; margin-bottom: 20px;">Utilisez les taux pour les cotisations proportionnelles et les montants fixes pour les cotisations forfaitaires comme la CMU.</p>'
            . '<div style="display: flex; flex-direction: column; gap: 15px;">';
        foreach ($cotisationsRows as $row) {
            $cotisationsHtml .= '<div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 15px; border-bottom: 1px solid var(--finea-border);">'
                . '<div>'
                . '<div style="font-weight: 500; font-size: 14px; margin-bottom: 4px;">' . $row[0] . '</div>'
                . '<div style="font-size: 12px; color: var(--finea-text-muted);">' . $row[1] . '</div>'
                . '</div>'
                . '<div style="font-weight: 600; font-size: 14px; white-space: nowrap; margin-left: 15px;">' . $row[2] . '</div>'
                . '</div>';
        }
        $cotisationsHtml .= '</div>';
        $cotisationsHtml .= '<div style="background: #F8FAFC; border: 1px solid var(--finea-border); border-radius: 8px; padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333; margin-top: 15px;">'
            . '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>'
            . 'Modifier les cotisations <span style="display: inline-block; width: 16px; height: 16px; border-radius: 50%; border: 1px solid #ccc; text-align: center; line-height: 14px; font-size: 10px; color: #888;">?</span>'
            . '</div>';
        $cotisationsCard = self::baseCard($cotisationsHtml);

        // 12. Transport minimum
        $transportHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Transport minimum</h3>'
            . '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 15px;">'
            . '<div style="background: #F8FAFC; padding: 20px; border-radius: 8px; border: 1px solid var(--finea-border);">'
            . '<div style="font-size: 12px; color: var(--finea-text-dark); font-weight: 500; margin-bottom: 8px;">District autonome d\'Abidjan</div>'
            . '<div style="font-size: 20px; font-weight: 600;">30 000 FCFA</div></div>'
            . '<div style="background: #F8FAFC; padding: 20px; border-radius: 8px; border: 1px solid var(--finea-border);">'
            . '<div style="font-size: 12px; color: var(--finea-text-dark); font-weight: 500; margin-bottom: 8px;">Bouake</div>'
            . '<div style="font-size: 20px; font-weight: 600;">24 000 FCFA</div></div>'
            . '<div style="background: #F8FAFC; padding: 20px; border-radius: 8px; border: 1px solid var(--finea-border);">'
            . '<div style="font-size: 12px; color: var(--finea-text-dark); font-weight: 500; margin-bottom: 8px;">Autres localites</div>'
            . '<div style="font-size: 20px; font-weight: 600;">20 000 FCFA</div></div>'
            . '</div>'
            . '<div style="background: #F8FAFC; border: 1px solid var(--finea-border); border-radius: 8px; padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #333;">'
            . '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>'
            . 'Modifier les transports'
            . '</div>';
        $transportCard = self::baseCard($transportHtml);

        // 13. Parametres et plafonds generaux
        $paramsHtml = '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;">Parametres et plafonds generaux</h3>'
            . '<p style="color: var(--finea-text-muted); font-size: 14px; margin-bottom: 20px;">Les parametres existants peuvent etre ajustes par les RH. L\'ajout d\'un nouveau parametre est reserve aux administrateurs, car il doit correspondre a une regle lue par le moteur.</p>';
        
        $paramRows = [
            ['Nombre maximum de parts RICF', '5', 'Nombre', 'Plafond de parts RICF'],
            ['Montant RICF par demi-part', '5500', 'Nombre', 'Montant par demi-part supplem...'],
            ['SMIG horaire', '454.5455', 'Nombre', 'SMIG horaire utilise pour les plaf...'],
            ['Abattement avant impot', '20', 'Nombre', 'Abattement reglementaire avant...'],
        ];

        $paramsHtml .= '<div style="display: flex; flex-direction: column; gap: 15px;">';
        foreach ($paramRows as $row) {
            $paramsHtml .= '<div style="display: flex; gap: 10px; align-items: center;">'
                . '<div style="flex: 2; padding: 12px; background: white; border: 1px solid var(--finea-border); border-radius: 8px; font-size: 14px; color: var(--finea-text-dark);">' . $row[0] . '</div>'
                . '<div style="flex: 1;"><input type="text" class="finea-form-control" value="' . $row[1] . '" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px;"></div>'
                . '<div style="flex: 1;"><select class="finea-form-control" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px;"><option>' . $row[2] . '</option></select></div>'
                . '<div style="flex: 2;"><input type="text" class="finea-form-control" value="' . $row[3] . '" style="width: 100%; padding: 12px; border: 1px solid var(--finea-border); border-radius: 8px; color: var(--finea-text-muted);"></div>'
                . '<div><button class="finea-btn" style="background: #0B1120; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600;">Enregistrer</button></div>'
                . '</div>';
        }
        $paramsHtml .= '</div>';
        $paramsCard = self::baseCard($paramsHtml);


        // Combine all in main layout
        $content = '<div style="display: flex; flex-direction: column; gap: 24px; padding-bottom: 50px;">'
            . $versionParamCard
            . $genGroupeeCard
            . $contratsCard
            . $clotureCard
            . $versionsCard
            . $rubriquesCard
            . $modifierRubriquesHtml
            . $historiqueCard
            . $auditCard
            . '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">'
            . $baremeCard
            . $cotisationsCard
            . '</div>'
            . $transportCard
            . $paramsCard
            . '</div>';


        return '<div class="finea-shell rh-payroll-engine-page">'
            . '<div class="finea-container">'
            . $header
            . $metricsHtml
            . $content
            . '</div>'
            . '</div>';
    }

    private static function metricCard(string $label, string $value): string
    {
        return '<div style="background: white; border: 1px solid var(--finea-border); border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">'
            . '<div style="font-size: 11px; font-weight: 600; color: var(--finea-text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">' . $label . '</div>'
            . '<div style="font-size: 20px; font-weight: 600; color: var(--finea-text-dark);">' . $value . '</div>'
            . '</div>';
    }

    private static function baseCard(string $content): string
    {
        return '<div style="background: white; border: 1px solid var(--finea-border); border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">'
            . $content
            . '</div>';
    }

    private static function createTable(array $headers, array $rows): string
    {
        $html = '<div style="overflow-x: auto; margin-top: 10px;">'
            . '<table style="width: 100%; border-collapse: collapse; text-align: left;">'
            . '<thead>'
            . '<tr style="background: #F8FAFC;">';
        foreach ($headers as $h) {
            $html .= '<th style="padding: 12px; font-size: 11px; font-weight: 600; color: var(--finea-text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--finea-border);">' . $h . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        if (empty($rows)) {
            $html .= '<tr><td colspan="' . count($headers) . '" style="padding: 20px; text-align: center; color: var(--finea-text-muted); font-size: 13px;">Aucune donnee disponible</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr style="border-bottom: 1px solid var(--finea-border);">';
                foreach ($row as $i => $cell) {
                    $fontWeight = $i === 0 ? '600' : '500';
                    $html .= '<td style="padding: 12px; font-size: 13px; color: var(--finea-text-dark); font-weight: ' . $fontWeight . ';">' . $cell . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table></div>';
        
        return $html;
    }
}
