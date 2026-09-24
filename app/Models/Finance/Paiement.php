<?php

namespace App\Models\Finance;

class Paiement
{
    public function __construct(
        public ?int $id,
        public int $factureId,
        public ?int $caissiereId,
        public float $montant,
        public string $devise,
        public string $mode,
        public string $type,
        public ?string $datePaiement = null,
        /**
         * Agence ou le billet a ete pris.
         *
         * Elle est figee ici au moment de l'encaissement, et non deduite plus
         * tard du compte de la caissiere : une mutation d'agence reecrirait
         * alors l'histoire des journees deja closes.
         */
        public ?int $agenceId = null
    ) {}
}
