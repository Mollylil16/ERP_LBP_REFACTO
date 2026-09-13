<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Tests\TestCase;

/**
 * Une seule chose bascule au lendemain après 15h : le colis.
 *
 * La règle métier, telle qu'elle a été posée par la direction :
 *
 *   « toutes les factures encaissé avant 15h restent pour les jours j ;
 *     après 15h, les factures encaissé restent toujours pour les jour j ;
 *     mais les colis saisit pour envoie, après 15h les dates
 *     d'enregistrement prennent les dates du lendemain. »
 *
 * Le code faisait autre chose : trois dépôts sur trois décalaient au
 * lendemain. Un règlement pris au guichet à 17h03 était daté du lendemain,
 * alors que l'argent, lui, était bien dans le tiroir le soir même. La
 * caissière comptait plus que ce que l'écran affichait — et personne ne
 * savait pourquoi. Huit règlements en ont été déplacés sur la seule semaine
 * du 09 au 13/09/2026, pour 149 600 XOF.
 *
 * Ce test garde les trois dépôts sur leur position respective.
 */
final class BasculeQuinzeHeuresTest extends TestCase
{
    /** Le seul endroit où la bascule de 15h a le droit d'exister. */
    private const DEPOT_QUI_BASCULE = 'app/Repositories/Colisage/ColisageRepository.php';

    /**
     * Dépôts qui écrivent une date d'argent. Aucun ne doit décaler.
     *
     * @var array<int, string>
     */
    private const DEPOTS_SANS_BASCULE = [
        'app/Repositories/Finance/PaiementRepository.php',
        'app/Repositories/Finance/FactureRepository.php',
    ];

    public function test_l_argent_ne_bascule_jamais_au_lendemain(): void
    {
        foreach (self::DEPOTS_SANS_BASCULE as $relatif) {
            $this->verifierAbsenceDeBascule($relatif);
        }
    }

    private function verifierAbsenceDeBascule(string $relatif): void
    {
        $source = $this->source($relatif);

        self::assertStringNotContainsString(
            "modify('+1 day')",
            $source,
            "{$relatif} décale une date d'un jour. Une facture établie ou réglée "
                . "après 15h reste au jour J : elle suit l'argent, et l'argent est "
                . "en caisse le soir même."
        );

        self::assertStringNotContainsString(
            "' 15:00:00'",
            $source,
            "{$relatif} porte une heure de bascule. Seul l'enregistrement des "
                . 'colis bascule à 15h.'
        );
    }

    /**
     * L'inverse compte autant : retirer la bascule des colis ferait partir les
     * colis du soir avec le chargement de la veille, qui est déjà parti.
     */
    public function test_le_colis_depose_apres_15h_part_bien_au_lendemain(): void
    {
        $source = $this->source(self::DEPOT_QUI_BASCULE);

        self::assertStringContainsString("' 15:00:00'", $source);
        self::assertStringContainsString("modify('+1 day')", $source);
    }

    /**
     * Le décalage était invisible parce qu'il se produisait au moment de
     * l'écriture, sans trace. On vérifie que la date passée explicitement par
     * l'appelant est toujours respectée telle quelle : c'est par là que passent
     * les reprises d'historique et les scripts de correction.
     */
    public function test_une_date_fournie_par_l_appelant_est_respectee(): void
    {
        foreach ([...self::DEPOTS_SANS_BASCULE, self::DEPOT_QUI_BASCULE] as $relatif) {
            self::assertMatchesRegularExpression(
                '/if \(empty\(\$(datePaiement|dateEmission|createdAt)\)\)/',
                $this->source($relatif),
                "{$relatif} doit n'inventer une date que si l'appelant n'en donne pas."
            );
        }
    }

    private function source(string $chemin): string
    {
        return (string) file_get_contents(BASE_PATH . '/' . $chemin);
    }
}
