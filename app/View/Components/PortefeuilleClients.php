<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Helpers\View;

final class PortefeuilleClients
{
    /**
     * @param array<string, mixed> $donnees
     */
    public static function portefeuillePage(array $donnees, string $agenceLabel): string
    {
        $mois = (int) $donnees['mois'];

        $entete = Ui::pageHeader(
            'Portefeuille Clients',
            'Valeur, fidélité et risque de chaque client, reconstitués depuis la facturation des ' . $mois . ' derniers mois. '
                . 'Segment A : les clients qui font les 80 % du chiffre d\'affaires.',
            [
                'eyebrow' => 'PCL • ' . $agenceLabel,
                'class' => 'rh-hero-white',
                'actions' => Ui::button('Annuaire clients', ['href' => 'crm/clients', 'variant' => 'secondary'])
                    . Ui::button('Balance âgée', ['href' => 'finance/balance-agee', 'variant' => 'ghost']),
            ]
        );

        return '<div class="finea-shell"><div class="finea-container">'
            . $entete
            . Dashboard::kpis((array) $donnees['kpis'])
            . self::bloc(Ui::section(
                'Poids du portefeuille',
                self::tableDevises((array) $donnees['parDevise']),
                'Une ligne par devise : les montants ne s\'additionnent pas entre devises'
            ))
            . self::bloc(Ui::section(
                'Encours à recouvrer',
                self::tableEncours((array) $donnees['encours']),
                'Classé par montant impayé'
            ))
            . self::bloc(Ui::section(
                'Clients par valeur',
                self::tableClients((array) $donnees['clients'])
            ))
            . Ui::section(
                'Clients à relancer',
                self::tableInactifs((array) $donnees['inactifs']),
                'Ont déjà acheté, mais plus rien depuis 90 jours'
            )
            . '</div></div>';
    }

    private static function bloc(string $contenu): string
    {
        return '<div style="margin-bottom:1.5rem;">' . $contenu . '</div>';
    }

    /** @param array<int, array<string, mixed>> $devises */
    private static function tableDevises(array $devises): string
    {
        $lignes = [];
        foreach ($devises as $d) {
            $ca = (float) $d['ca'];
            $taux = $ca > 0 ? (int) round((float) $d['encaisse'] / $ca * 100) : 0;

            $lignes[] = [
                '<strong>' . View::e((string) $d['devise']) . '</strong>',
                View::e((string) $d['nb_clients']),
                ModuleTable::montant($ca, (string) $d['devise']),
                ModuleTable::montant((float) $d['encaisse'], (string) $d['devise']),
                ModuleTable::montant((float) $d['impaye'], (string) $d['devise']),
                ModuleTable::jauge($taux, $taux >= 90 ? 'success' : ($taux >= 70 ? 'warning' : 'danger')),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Devise'],
                ['label' => 'Clients', 'align' => 'center'],
                ['label' => 'Chiffre d\'affaires', 'align' => 'right'],
                ['label' => 'Encaissé', 'align' => 'right'],
                ['label' => 'Impayé', 'align' => 'right'],
                ['label' => 'Recouvrement'],
            ],
            $lignes,
            'Aucune facturation',
            'Aucune facture sur la période et le périmètre retenus.'
        );
    }

    /** @param array<int, array<string, mixed>> $clients */
    private static function tableClients(array $clients): string
    {
        $lignes = [];
        foreach ($clients as $c) {
            $jours = $c['jours_depuis_derniere'];
            $taux = $c['taux_recouvrement'];

            $lignes[] = [
                Ui::badge($c['segment'], match ($c['segment']) {
                    'A' => 'success',
                    'B' => 'info',
                    default => 'neutral',
                }),
                '<strong>' . View::e((string) $c['client']) . '</strong>'
                    . ($c['phone'] ? '<br><small>' . View::e((string) $c['phone']) . '</small>' : ''),
                Ui::badge((string) $c['type'] === 'corporate' ? 'Entreprise' : 'Particulier', 'neutral'),
                View::e((string) $c['nb_factures']),
                ModuleTable::montant((float) $c['ca'], (string) $c['devise']),
                View::e((string) $c['part_ca'] . ' %'),
                ModuleTable::montant((float) $c['impaye'], (string) $c['devise']),
                $taux !== null
                    ? Ui::badge($taux . ' %', $taux >= 90 ? 'success' : ($taux >= 70 ? 'warning' : 'danger'))
                    : '<small>—</small>',
                $jours !== null
                    ? View::e($jours . ' j')
                    : '<small>—</small>',
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Seg.', 'align' => 'center'],
                ['label' => 'Client'],
                ['label' => 'Type', 'align' => 'center'],
                ['label' => 'Factures', 'align' => 'center'],
                ['label' => 'Chiffre d\'affaires', 'align' => 'right'],
                ['label' => 'Part', 'align' => 'right'],
                ['label' => 'Impayé', 'align' => 'right'],
                ['label' => 'Recouvré', 'align' => 'center'],
                ['label' => 'Dernier achat', 'align' => 'center'],
            ],
            $lignes,
            'Aucun client actif',
            'Aucun client n\'a été facturé sur la période et le périmètre retenus.'
        );
    }

    /** @param array<int, array<string, mixed>> $encours */
    private static function tableEncours(array $encours): string
    {
        $lignes = [];
        foreach ($encours as $e) {
            $lignes[] = [
                '<strong>' . View::e((string) $e['client']) . '</strong>'
                    . ($e['phone'] ? '<br><small>' . View::e((string) $e['phone']) . '</small>' : ''),
                View::e((string) $e['nb_factures_ouvertes']),
                ModuleTable::montant((float) $e['impaye'], (string) $e['devise']),
                View::e(self::date((string) $e['plus_ancienne'])),
                Ui::badge((int) $e['anciennete_jours'] . ' j', (string) $e['tone']),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Client'],
                ['label' => 'Factures ouvertes', 'align' => 'center'],
                ['label' => 'Impayé', 'align' => 'right'],
                ['label' => 'Plus ancienne'],
                ['label' => 'Ancienneté', 'align' => 'center'],
            ],
            $lignes,
            'Aucun encours',
            'Toutes les factures du périmètre sont soldées.'
        );
    }

    /** @param array<int, array<string, mixed>> $inactifs */
    private static function tableInactifs(array $inactifs): string
    {
        $lignes = [];
        foreach ($inactifs as $i) {
            $jours = (int) $i['jours_sans_activite'];

            $lignes[] = [
                '<strong>' . View::e((string) $i['client']) . '</strong>'
                    . ($i['phone'] ? '<br><small>' . View::e((string) $i['phone']) . '</small>' : ''),
                Ui::badge((string) $i['type'] === 'corporate' ? 'Entreprise' : 'Particulier', 'neutral'),
                View::e((string) $i['nb_factures']),
                ModuleTable::montant((float) $i['ca_historique'], (string) $i['devise']),
                View::e(self::date((string) $i['derniere_facture'])),
                Ui::badge($jours . ' j', $jours >= 365 ? 'danger' : ($jours >= 180 ? 'warning' : 'info')),
            ];
        }

        return ModuleTable::render(
            [
                ['label' => 'Client'],
                ['label' => 'Type', 'align' => 'center'],
                ['label' => 'Factures', 'align' => 'center'],
                ['label' => 'CA historique', 'align' => 'right'],
                ['label' => 'Dernier achat'],
                ['label' => 'Sans activité', 'align' => 'center'],
            ],
            $lignes,
            'Aucun client dormant',
            'Tous les clients du fichier ont commandé dans les 90 derniers jours.'
        );
    }

    private static function date(string $valeur): string
    {
        $horodatage = strtotime($valeur);

        return $horodatage !== false ? date('d/m/Y', $horodatage) : '—';
    }
}
