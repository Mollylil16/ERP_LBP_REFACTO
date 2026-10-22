<?php

declare(strict_types=1);

namespace App\Services\Noel;

/**
 * Les marches a suivre, ecrites a la main et verifiees.
 *
 * « Comment je fais pour integrer un collaborateur » n'attend ni une adresse
 * ni une definition : elle attend les gestes dans l'ordre. Et l'ordre n'est
 * pas decoratif — un dossier cree sans contrat bloque la paie, une sanction
 * posee avant la demande d'explication est indefendable.
 *
 * TOUT CE QUI EST ECRIT ICI A ETE RELEVE DANS LES FORMULAIRES EUX-MEMES, en
 * octobre 2026, ecran par ecran. NOEL ne doit jamais improviser une procedure :
 * quelqu'un la suivrait, et elle serait fausse. Quand une procedure n'existe
 * pas ici, il le dit et propose de l'apprendre.
 *
 * C'est la contrepartie de l'absence d'intelligence artificielle : ce que NOEL
 * explique, un humain l'a ecrit et relu.
 */
final class NoelProcedure
{
    /**
     * Les procedures, rangees par concept du dictionnaire.
     *
     * @var array<string, array{titre: string, etapes: array<int, string>, attention: ?string, ecrans: array<int, string>}>
     */
    private const PAR_CONCEPT = [
        'point_caisse' => [
            'titre' => 'Soumettre un point de caisse, dans l ordre :',
            'etapes' => [
                "Ouvrir Finance puis Points de Caisse, et verifier que la journee affichee est la bonne.",
                "Compter physiquement l argent du tiroir, billet par billet.",
                "Saisir le montant compte dans le comptage physique — pas le theorique, c est lui qu on verifie.",
                "Comparer avec le solde theorique affiche. Un ecart s explique, il ne se recopie pas.",
                "Soumettre le point. Il passe alors a l etat « soumis ».",
            ],
            'attention' => "un appro valide entre en caisse au jour de la REMISE. Si un ecart apparait sans raison, regardez d abord Finance / Appro Caisse.",
            'ecrans' => ['finance/clotures'],
        ],
        'facture' => [
            'titre' => 'Encaisser un reglement :',
            'etapes' => [
                "Ouvrir Finance puis Factures Clients, et retrouver la facture par son numero ou le nom du client.",
                "Cliquer « Consulter / Encaisser » pour ouvrir la fiche.",
                "Descendre au formulaire d encaissement, en bas de la fiche.",
                "Saisir le montant recu et choisir le mode : especes, mobile money ou carte.",
                "Valider. Le recu est alors editable, et le solde de la facture se met a jour.",
            ],
            'attention' => "si un reglement a ete saisi deux fois, ne corrigez pas la facture a la main. Utilisez « Annuler ce reglement » sur la ligne en trop, dans l historique des encaissements — sinon le rapport du jour continuera de compter les deux.",
            'ecrans' => ['finance/factures'],
        ],
        'demande_fonds' => [
            'titre' => 'Faire une demande de fonds :',
            'etapes' => [
                "Ouvrir Finance puis Demandes de Fonds, et cliquer sur la creation d une demande.",
                "Renseigner le cadre — fonctionnement ou traitement de dossier — et le montant.",
                "Expliquer le motif. C est ce que lira la personne qui decidera : etre precis evite un refus.",
                "Enregistrer. La demande part en validation.",
                "Suivre son etat depuis la liste : en attente, validee, puis decaissee.",
            ],
            'attention' => "depuis le 14 octobre, celui qui valide n est pas celui qui decaisse. L assistante DG valide, le comptable et la caissiere principale sortent l argent.",
            'ecrans' => ['finance/fonds'],
        ],
        'decaissement' => [
            'titre' => 'Decaisser une demande validee :',
            'etapes' => [
                "Ouvrir Finance puis Prise en Compte : seules les demandes deja validees y figurent.",
                "Cliquer « Decaisser » sur la ligne voulue — cela ouvre la fiche de la demande.",
                "Descendre au panneau « Prise en compte caisse & decaissement », tout en bas.",
                "Choisir le mode de paiement : especes, mobile money, cheque ou virement.",
                "Enregistrer. Le bon de sortie de caisse devient editable.",
            ],
            'attention' => "le panneau n apparait pas si vous avez vous-meme valide cette demande. Ce n est pas une panne : personne ne decaisse ce qu il a autorise.",
            'ecrans' => ['finance/fonds/prise-en-compte'],
        ],
        'appro' => [
            'titre' => 'Enregistrer un approvisionnement de caisse :',
            'etapes' => [
                "Ouvrir Finance puis Appro Caisse.",
                "Choisir l agence approvisionnee et saisir le montant remis.",
                "Indiquer la DATE DE REMISE — celle ou l argent a change de main, pas celle de la saisie.",
                "Choisir la provenance : siege, retrait bancaire, autre agence.",
                "Ecrire un motif clair : c est ce que l agence lira au moment du comptage.",
            ],
            'attention' => "la saisie revient a la caissiere principale, la validation au comptable. Tant qu il n a pas valide, l argent n entre pas en caisse.",
            'ecrans' => ['finance/appro-caisse'],
        ],
        'colis' => [
            'titre' => 'Enregistrer un colis :',
            'etapes' => [
                "Renseigner l expediteur : le chercher par son nom ou son telephone, ou le creer.",
                "Renseigner le destinataire, avec un telephone au format international — il porte les notifications d arrivee.",
                "Peser la marchandise et reporter le poids brut exact, puis la valeur declaree.",
                "Ajouter les lignes de marchandises : produit, quantite, poids unitaire, emballage.",
                "Enregistrer. Le logiciel genere alors le numero de suivi.",
                "Sur la fiche du colis, cliquer « Facturer » pour finaliser.",
            ],
            'attention' => "pour verrouiller le trajet, passez par Operations et saisissez le colis directement sur un vol ou un cargo : il ne pourra plus etre change par inadvertance.",
            'ecrans' => ['colisage/parcels'],
        ],
        'pointage' => [
            'titre' => 'Pointer une journee :',
            'etapes' => [
                "Ouvrir le pointage et choisir la date — le logiciel affiche alors tout le personnel.",
                "Donner un etat a chacun : present, absent, demi-journee, mission, conge ou repos.",
                "Saisir les heures d arrivee et de depart, puis les heures travaillees.",
                "Reporter les heures supplementaires. Elles alimentent directement la paie du mois.",
                "Enregistrer en une fois, et recommencer le lendemain.",
            ],
            'attention' => "une heure supplementaire non pointee n est pas payee, et ne se rattrape pas au moment du calcul.",
            'ecrans' => ['rh/pointage'],
        ],
        'paie' => [
            'titre' => 'Faire une paie, en quatre temps :',
            'etapes' => [
                "Vider d abord l alerte « Contrats a parametrer » : chaque salarie qui y figure sera absent de la paie.",
                "Ouvrir la periode avec un code, une date de debut et une date de fin.",
                "Saisir les variables du mois pour chaque salarie : primes, retenues, acompte.",
                "Calculer. Les bulletins sont produits et peuvent encore etre corriges.",
                "Verifier bulletin par bulletin, puis cloturer.",
            ],
            'attention' => "la cloture est definitive. Ne la franchissez qu apres relecture complete.",
            'ecrans' => ['rh/paie'],
        ],
        'dossier_personnel' => [
            'titre' => 'Integrer un collaborateur :',
            'etapes' => [
                "Verifier d abord que son service, sa fonction, son statut et son site existent au parametrage.",
                "Creer le dossier : le nom complet suffit a l enregistrer, le matricule se genere seul.",
                "Renseigner l affectation et les dates — celle de recrutement et celle de prise de service.",
                "Completer contacts et famille, dont le contact d urgence.",
                "Deposer les pieces, ou les ajouter plus tard : rien ne bloque la creation.",
                "Creer son contrat. C est l etape la plus oubliee, et sans elle la paie ne peut pas calculer.",
            ],
            'attention' => "si une personne deja partie revient, ne creez pas un second dossier : reintegrez l ancien, sinon vous perdez l anciennete, l historique et les pieces.",
            'ecrans' => ['rh/personnel'],
        ],
    ];

    /**
     * Les procedures rattachees a un module plutot qu'a un geste precis.
     *
     * @var array<string, string>
     */
    private const PAR_MODULE = [
        'finance' => 'point_caisse',
        'colisage' => 'colis',
        'rh' => 'dossier_personnel',
    ];

    /**
     * La marche a suivre pour ce geste, ou null si elle n'est pas ecrite.
     *
     * @return array{titre: string, etapes: array<int, string>, attention: ?string, ecrans: array<int, string>}|null
     */
    public static function pour(?string $concept, ?string $module = null): ?array
    {
        if ($concept !== null && isset(self::PAR_CONCEPT[$concept])) {
            return self::PAR_CONCEPT[$concept];
        }

        if ($module !== null && isset(self::PAR_MODULE[$module])) {
            return self::PAR_CONCEPT[self::PAR_MODULE[$module]] ?? null;
        }

        return null;
    }

    /** Les gestes pour lesquels une marche a suivre existe. */
    public static function gestesConnus(): array
    {
        return array_keys(self::PAR_CONCEPT);
    }
}
