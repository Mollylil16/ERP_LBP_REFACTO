<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Messagerie\MessagerieEspace;
use App\Services\Messagerie\MessagerieException;
use App\Services\Messagerie\MessagerieFormats;
use App\Services\Messagerie\MessagerieService;
use App\Services\Messagerie\MessagerieStockage;
use DateTimeImmutable;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\Messagerie\DepotMessagerieMemoire;
use Tests\Support\Messagerie\ImagesDeTest;
use Tests\TestCase;
use ZipArchive;

/**
 * Lot 3 de la messagerie : fichiers, photos, téléchargement protégé,
 * favoris, classeur et jauge.
 *
 * Le disque est réel, mais dans un dossier temporaire propre à chaque test :
 * le stockage y range les fichiers comme il le ferait sous storage/.
 *
 * Personnages : 1 Awa, 2 Bakary, 3 Clarisse, 9 le DG (Direction).
 */
final class MessagerieFichiersTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
        . "2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private DepotMessagerieMemoire $depot;
    private DateTimeImmutable $heure;
    private string $racine;
    private string $envois;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depot = new DepotMessagerieMemoire();
        $this->depot->ajouterUtilisateur(1, 'Awa');
        $this->depot->ajouterUtilisateur(2, 'Bakary');
        $this->depot->ajouterUtilisateur(3, 'Clarisse');
        $this->depot->ajouterUtilisateur(9, 'Directeur Général');
        $this->heure = new DateTimeImmutable('2026-10-22 09:00:00');

        $base = sys_get_temp_dir() . '/lbp_msg_' . bin2hex(random_bytes(4));
        $this->racine = $base . '/storage';
        $this->envois = $base . '/envois';
        mkdir($this->racine, 0777, true);
        mkdir($this->envois, 0777, true);
    }

    protected function tearDown(): void
    {
        $base = dirname($this->racine);
        if (is_dir($base)) {
            $tout = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($tout as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($base);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Type réel et taille
    // ------------------------------------------------------------------

    public function test_un_faux_pdf_contenant_du_php_est_refuse(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);

        $refus = $this->refus(422, fn() => $this->en(1)->envoyerFichier(
            $groupe,
            $this->fichier('facture.pdf', "<?php system(\$_GET['c']); ?>\n")
        ));

        self::assertStringContainsString('n’est pas un PDF', $refus);
        self::assertSame([], $this->depot->messages, 'Aucun message pour un fichier refusé.');
        self::assertSame(0, $this->fichiersSurDisque());
    }

    public function test_executables_scripts_svg_html_et_zip_sont_refuses(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $zip = $this->zip(['notes.txt' => 'bonjour']);

        $essais = [
            'rapport.pdf' => "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00" . str_repeat("\x00", 48) . 'PE',
            'logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'logo.png' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'page.html' => '<!DOCTYPE html><html><body>Bonjour</body></html>',
            'note.txt' => '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>',
            'script.sh' => "#!/bin/sh\nrm -rf /\n",
            'archive.zip' => (string) file_get_contents($zip),
            'contrat.docx' => (string) file_get_contents($zip),
            'tableau.csv' => "a;b\n<?php echo 1; ?>\n",
        ];

        foreach ($essais as $nom => $contenu) {
            $this->refus(422, fn() => $this->en(1)->envoyerFichier($groupe, $this->fichier($nom, $contenu)), $nom);
        }

        self::assertSame([], $this->depot->messages);
    }

    public function test_les_formats_bureautiques_sont_acceptes_sur_leur_contenu(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $docx = $this->zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>']);
        $xlsx = $this->zip(['[Content_Types].xml' => '<Types/>', 'xl/workbook.xml' => '<workbook/>']);

        $envois = [
            'Contrat été.docx' => ['word', (string) file_get_contents($docx)],
            'Caisse.xlsx' => ['tableur', (string) file_get_contents($xlsx)],
            'notes.txt' => ['texte', "Point de caisse : 125 000 FCFA\n"],
            'export.csv' => ['tableur', "agence;montant\nDokui;125000\n"],
            'Facture.PDF' => ['pdf', self::PDF],
        ];

        foreach ($envois as $nom => [$categorie, $contenu]) {
            $message = $this->en(1)->envoyerFichier($groupe, $this->fichier($nom, $contenu));
            self::assertSame($categorie, $message['pieces_jointes'][0]['categorie'], $nom);
            self::assertSame($nom, $message['pieces_jointes'][0]['nom'], 'Le nom d’origine est gardé, accents compris.');
            $this->heure = $this->heure->modify('+1 minute');
        }
    }

    public function test_un_nom_envoye_en_windows_1252_garde_ses_accents(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);

        // Ce qu'envoie curl sous Windows, ou un navigateur ancien.
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier("C:\\Docs\\Relev\xe9 \xe9t\xe9.pdf", self::PDF), "Relev\xe9 de septembre");

        self::assertSame('Relevé été.pdf', $message['pieces_jointes'][0]['nom']);
        self::assertSame('Relevé de septembre', $message['pieces_jointes'][0]['legende'], 'La légende n’est pas perdue.');
        self::assertSame('rapport.v2.final.pdf', MessagerieFormats::nomAffichable('../../rapport.v2.final.pdf'));
        self::assertNull(MessagerieFormats::extension('.htaccess'));
    }

    public function test_un_fichier_de_plus_de_25_mo_est_refuse_en_413(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $chemin = $this->envois . '/gros.pdf';
        $f = fopen($chemin, 'wb');
        fwrite($f, '%PDF-1.4');
        ftruncate($f, MessagerieFormats::TAILLE_MAX + 1);
        fclose($f);

        $message = $this->refus(413, fn() => $this->en(1)->envoyerFichier($groupe, $this->entree('gros.pdf', $chemin)));
        self::assertStringContainsString('25 Mo', $message);

        // PHP lui-même a refusé (upload_max_filesize) : même réponse lisible.
        $this->refus(413, fn() => $this->en(1)->envoyerFichier($groupe, ['name' => 'x.pdf', 'error' => UPLOAD_ERR_INI_SIZE]));
        $this->refus(422, fn() => $this->en(1)->envoyerFichier($groupe, ['name' => 'x.pdf', 'error' => UPLOAD_ERR_PARTIAL]));
        $this->refus(422, fn() => $this->en(1)->envoyerFichier($groupe, ['error' => UPLOAD_ERR_NO_FILE]));
        $this->refus(500, fn() => $this->en(1)->envoyerFichier($groupe, ['name' => 'x.pdf', 'error' => UPLOAD_ERR_NO_TMP_DIR]));
        $this->refus(422, fn() => $this->en(1)->envoyerFichier($groupe, ['name' => ['a.pdf', 'b.pdf'], 'error' => [0, 0]]));
    }

    public function test_seul_un_membre_qui_peut_ecrire_envoie_un_fichier(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);

        $this->refus(404, fn() => $this->en(3)->envoyerFichier($groupe, $this->fichier('a.pdf', self::PDF)));
        $this->refus(404, fn() => $this->en(9, true)->envoyerFichier($groupe, $this->fichier('a.pdf', self::PDF)));
        self::assertSame(0, $this->fichiersSurDisque());
    }

    // ------------------------------------------------------------------
    // Rangement et dédoublonnage
    // ------------------------------------------------------------------

    public function test_le_fichier_est_range_sous_un_nom_aleatoire_par_mois(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('Relevé banque.pdf', self::PDF), '  Le relevé de septembre ');

        $fichier = array_values($this->depot->fichiers)[0];
        self::assertMatchesRegularExpression('#^messagerie/2026/10/[0-9a-f]{32}\.pdf$#', $fichier['chemin']);
        self::assertFileExists($this->racine . '/' . $fichier['chemin']);
        self::assertSame(hash('sha256', self::PDF), $fichier['empreinte']);
        self::assertFileExists($this->racine . '/messagerie/.htaccess', 'La barrière est posée si elle manquait.');

        self::assertSame('Le relevé de septembre', $message['texte'], 'La légende sert de texte.');
        $piece = $message['pieces_jointes'][0];
        self::assertSame('Le relevé de septembre', $piece['legende']);
        self::assertSame('application/pdf', $piece['mime']);
        self::assertSame(strlen(self::PDF), $piece['taille']);
        self::assertSame('/api/pieces/' . $piece['id'], $piece['url']);
        self::assertNull($piece['url_miniature']);
        self::assertFalse($piece['favori']);
    }

    public function test_le_meme_pdf_envoye_dans_trois_groupes_n_occupe_la_place_qu_une_fois(): void
    {
        $groupes = [
            $this->en(1)->creerGroupe('Compta', '', [2]),
            $this->en(1)->creerGroupe('Direction', '', [9]),
            $this->en(1)->creerGroupe('Dokui', '', [3]),
        ];

        foreach ($groupes as $groupe) {
            $this->en(1)->envoyerFichier($groupe, $this->fichier('procedure.pdf', self::PDF));
        }

        self::assertCount(1, $this->depot->fichiers);
        self::assertCount(3, $this->depot->pieces);
        self::assertSame(1, $this->fichiersSurDisque());

        $espace = (new MessagerieEspace($this->depot))->etat();
        self::assertSame(strlen(self::PDF), $espace['octets']);
        self::assertSame(2 * strlen(self::PDF), $espace['economie_octets']);
    }

    public function test_les_fichiers_comptent_dans_les_trente_envois_par_minute(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        for ($i = 0; $i < MessagerieService::ENVOIS_PAR_MINUTE; $i++) {
            $this->en(1)->envoyer($direct, 'Message ' . $i);
        }

        $this->refus(429, fn() => $this->en(1)->envoyerFichier($direct, $this->fichier('a.pdf', self::PDF)));
        self::assertSame(0, $this->fichiersSurDisque(), 'Refusé avant d’écrire sur le disque.');
    }

    // ------------------------------------------------------------------
    // Photos
    // ------------------------------------------------------------------

    public function test_une_photo_de_telephone_de_5_mo_est_stockee_a_moins_de_1_mo_redressee_et_sans_exif(): void
    {
        $groupe = $this->en(1)->creerGroupe('Chantier', '', [2]);
        $photo = ImagesDeTest::photoDeTelephone(4000, 3000, 92, 6);
        self::assertGreaterThan(5 * 1024 * 1024, strlen($photo), 'L’image d’essai pèse bien plus de 5 Mo.');

        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('IMG_2026.jpg', $photo));
        $piece = $message['pieces_jointes'][0];
        $fichier = array_values($this->depot->fichiers)[0];
        $stocke = $this->racine . '/' . $fichier['chemin'];

        self::assertLessThan(1024 * 1024, filesize($stocke));
        self::assertSame($piece['taille'], filesize($stocke));
        // Orientation 6 : prise de côté, elle se redresse en portrait, 2 000 px au plus.
        self::assertSame([1500, 2000], [$piece['largeur'], $piece['hauteur']]);
        self::assertSame([1500, 2000], array_slice((array) getimagesize($stocke), 0, 2));

        $exif = @exif_read_data($stocke);
        self::assertFalse(is_array($exif) && (isset($exif['Orientation']) || isset($exif['GPSLatitudeRef'])), 'Ni orientation ni GPS ne restent.');
        self::assertStringNotContainsString('Exif', (string) file_get_contents($stocke, false, null, 0, 4096));

        self::assertNotNull($piece['url_miniature']);
        $miniature = (array) getimagesize($this->racine . '/' . $fichier['chemin_miniature']);
        self::assertSame(400, max($miniature[0], $miniature[1]));
    }

    public function test_un_png_transparent_reste_png_et_garde_sa_transparence(): void
    {
        $groupe = $this->en(1)->creerGroupe('Communication', '', [2]);
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('logo.png', ImagesDeTest::pngTransparent()));

        $piece = $message['pieces_jointes'][0];
        self::assertSame(['png', 'image/png', 'logo.png'], [$piece['extension'], $piece['mime'], $piece['nom']]);

        $image = imagecreatefrompng($this->racine . '/' . array_values($this->depot->fichiers)[0]['chemin']);
        self::assertSame(127, (imagecolorat($image, 5, 5) >> 24) & 0x7F, 'Le fond reste transparent.');
        self::assertSame(0, (imagecolorat($image, 150, 100) >> 24) & 0x7F, 'Le motif reste opaque.');
    }

    public function test_une_capture_png_opaque_devient_un_jpeg(): void
    {
        $groupe = $this->en(1)->creerGroupe('Support', '', [2]);
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('capture.png', ImagesDeTest::pngOpaque()));

        $piece = $message['pieces_jointes'][0];
        self::assertSame(['jpg', 'image/jpeg', 'capture.jpg'], [$piece['extension'], $piece['mime'], $piece['nom']]);
        self::assertSame('capture.png', array_values($this->depot->pieces)[0]['nom_origine'], 'Le nom reçu reste en base.');
    }

    // ------------------------------------------------------------------
    // Téléchargement protégé
    // ------------------------------------------------------------------

    public function test_droits_de_telechargement(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('bilan.pdf', self::PDF));
        $pieceId = $message['pieces_jointes'][0]['id'];

        $servi = $this->en(2)->fichierAServir($pieceId);
        self::assertSame(self::PDF, file_get_contents($servi['chemin']));
        self::assertSame(['application/pdf', 'bilan.pdf', true], [$servi['mime'], $servi['nom'], $servi['en_ligne']]);

        // Non-membre : introuvable, sans confirmer que la pièce existe.
        $this->refus(404, fn() => $this->en(3)->fichierAServir($pieceId));
        $this->refus(404, fn() => $this->en(3)->fichierAServir(99999));
        $this->refus(404, fn() => $this->en(2)->fichierAServir($pieceId, true), 'Un PDF n’a pas de miniature.');

        // Parti du groupe : plus rien.
        $this->en(1)->retirerMembre($groupe, 2);
        $this->refus(404, fn() => $this->en(2)->fichierAServir($pieceId));
    }

    public function test_la_piece_d_un_message_supprime_ne_se_telecharge_plus_sauf_pour_la_direction(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('bilan.pdf', self::PDF), 'Bilan');
        $pieceId = $message['pieces_jointes'][0]['id'];

        $supprime = $this->en(1)->supprimer($message['id']);
        self::assertSame([], $supprime['pieces_jointes']);
        self::assertSame([], $this->en(2)->messages($groupe)['messages'][0]['pieces_jointes']);

        $this->refus(404, fn() => $this->en(1)->fichierAServir($pieceId));
        $this->refus(404, fn() => $this->en(2)->fichierAServir($pieceId));

        // La Direction garde l'information : la pièce figure, et s'ouvre.
        self::assertSame($pieceId, $this->en(9, true)->messages($groupe)['messages'][0]['pieces_jointes'][0]['id']);
        self::assertFileExists($this->en(9, true)->fichierAServir($pieceId)['chemin']);
    }

    public function test_la_direction_ouvre_un_fichier_et_c_est_inscrit_une_fois(): void
    {
        $groupe = $this->en(1)->creerGroupe('Chantier', '', [2]);
        $message = $this->en(1)->envoyerFichier($groupe, $this->fichier('photo.jpg', ImagesDeTest::photoDeTelephone(800, 600)));
        $pieceId = $message['pieces_jointes'][0]['id'];

        // Les vignettes accompagnent le fil : pas de ligne de plus.
        $this->en(9, true)->fichierAServir($pieceId, true);
        self::assertSame([], $this->depot->accesDirection);

        $this->en(9, true)->fichierAServir($pieceId);
        self::assertCount(1, $this->depot->accesDirection, 'Ouvrir le fichier compte comme une ouverture.');
        self::assertSame($groupe, $this->depot->accesDirection[0]['conversation_id']);

        // Membre, la Direction n'est pas inscrite.
        $direct = $this->en(1)->ouvrirDirecte(9);
        $autre = $this->en(1)->envoyerFichier($direct, $this->fichier('b.pdf', self::PDF));
        $this->en(9, true)->fichierAServir($autre['pieces_jointes'][0]['id']);
        self::assertCount(1, $this->depot->accesDirection);
    }

    // ------------------------------------------------------------------
    // Transfert, relevé
    // ------------------------------------------------------------------

    public function test_le_transfert_emporte_la_piece_sans_recopier_le_fichier(): void
    {
        $compta = $this->en(1)->creerGroupe('Compta', '', [2]);
        $direct = $this->en(1)->ouvrirDirecte(3);
        $message = $this->en(1)->envoyerFichier($compta, $this->fichier('bilan.pdf', self::PDF), 'À relire');

        $transfere = $this->en(1)->transferer($message['id'], $direct);

        self::assertTrue($transfere['transfere']);
        self::assertSame('À relire', $transfere['texte']);
        self::assertSame('bilan.pdf', $transfere['pieces_jointes'][0]['nom']);
        self::assertNotSame($message['pieces_jointes'][0]['id'], $transfere['pieces_jointes'][0]['id']);
        self::assertCount(1, $this->depot->fichiers);
        self::assertSame(1, $this->fichiersSurDisque());

        // Clarisse ouvre la pièce transférée, pas l'originale.
        self::assertFileExists($this->en(3)->fichierAServir($transfere['pieces_jointes'][0]['id'])['chemin']);
        $this->refus(404, fn() => $this->en(3)->fichierAServir($message['pieces_jointes'][0]['id']));
    }

    public function test_le_releve_et_la_liste_portent_les_pieces(): void
    {
        $direct = $this->en(1)->ouvrirDirecte(2);
        $releve = $this->en(2)->nouveautes(null, null);

        $this->en(1)->envoyerFichier($direct, $this->fichier('bon.pdf', self::PDF));

        $suite = $this->en(2)->nouveautes($releve['curseur'], $releve['serveur_at']);
        self::assertSame('bon.pdf', $suite['messages'][0]['pieces_jointes'][0]['nom']);
        self::assertSame('', $suite['messages'][0]['texte'], 'Sans légende, le texte est vide.');

        $liste = $this->en(2)->conversations();
        self::assertSame('Document : bon.pdf', $liste[0]['dernier_message']['apercu']);
    }

    // ------------------------------------------------------------------
    // Favoris et classeur
    // ------------------------------------------------------------------

    public function test_favoris(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $pieceId = $this->en(1)->envoyerFichier($groupe, $this->fichier('bilan.pdf', self::PDF))['pieces_jointes'][0]['id'];

        $this->en(2)->marquerFavori($pieceId, true);
        $this->en(2)->marquerFavori($pieceId, true);
        self::assertCount(1, $this->depot->favoris);
        self::assertTrue($this->en(2)->messages($groupe)['messages'][0]['pieces_jointes'][0]['favori']);
        self::assertFalse($this->en(1)->messages($groupe)['messages'][0]['pieces_jointes'][0]['favori'], 'Le favori est personnel.');
        self::assertCount(1, $this->en(2)->documents(['favoris' => '1'])['documents']);
        self::assertCount(0, $this->en(1)->documents(['favoris' => '1'])['documents']);

        $this->en(2)->marquerFavori($pieceId, false);
        self::assertSame([], $this->depot->favoris);

        $this->refus(404, fn() => $this->en(3)->marquerFavori($pieceId, true));
        $this->refus(404, fn() => $this->en(9, true)->marquerFavori($pieceId, true));
    }

    public function test_le_classeur_ne_montre_que_les_discussions_dont_on_est_membre_actif(): void
    {
        $compta = $this->en(1)->creerGroupe('Compta', '', [2, 3]);
        $prive = $this->en(1)->ouvrirDirecte(2);
        $quitte = $this->en(1)->creerGroupe('Ancien projet', '', [3]);

        $this->en(1)->envoyerFichier($compta, $this->fichier('bilan.pdf', self::PDF));
        $this->en(1)->envoyerFichier($prive, $this->fichier('salaire.txt', "Confidentiel\n"));
        $this->en(1)->envoyerFichier($quitte, $this->fichier('ancien.csv', "a;b\n"));
        $this->en(3)->quitter($quitte);

        self::assertSame(['bilan.pdf'], $this->noms($this->en(3)->documents([])));
        self::assertSame(['salaire.txt', 'bilan.pdf'], $this->noms($this->en(2)->documents([])));
        self::assertSame('Awa', $this->en(2)->documents([])['documents'][0]['conversation']['nom'], 'Une directe porte le nom du correspondant.');
        // La Direction n'a pas de vue élargie dans le classeur.
        self::assertSame([], $this->en(9, true)->documents([])['documents']);

        // Le message supprimé emporte sa pièce hors du classeur.
        $this->en(1)->supprimer(array_values($this->depot->pieces)[0]['message_id']);
        self::assertSame([], $this->noms($this->en(3)->documents([])));
    }

    public function test_le_classeur_filtre_trie_cherche_et_pagine(): void
    {
        $compta = $this->en(1)->creerGroupe('Compta', '', [2]);
        $chantier = $this->en(2)->creerGroupe('Chantier', '', [1]);

        $this->en(1)->envoyerFichier($compta, $this->fichier('b-bilan.pdf', self::PDF), 'Bilan annuel');
        $this->heure = new DateTimeImmutable('2026-10-23 10:00:00');
        $this->en(2)->envoyerFichier($chantier, $this->fichier('a-photo.png', ImagesDeTest::pngOpaque(1200, 900)));
        $this->heure = new DateTimeImmutable('2026-10-25 10:00:00');
        $this->en(1)->envoyerFichier($compta, $this->fichier('c-releve.csv', "agence;montant\nDokui;125000\nPlateau;98000\n"), 'Relevé caisse');

        $moi = $this->en(1);
        self::assertSame(['c-releve.csv', 'a-photo.jpg', 'b-bilan.pdf'], $this->noms($moi->documents([])), 'Par défaut, le plus récent d’abord.');
        self::assertSame(['a-photo.jpg', 'b-bilan.pdf', 'c-releve.csv'], $this->noms($moi->documents(['tri' => 'nom'])));
        self::assertSame(['c-releve.csv', 'b-bilan.pdf', 'a-photo.jpg'], $this->noms($moi->documents(['tri' => 'nom', 'ordre' => 'desc'])));
        self::assertSame('a-photo.jpg', $this->noms($moi->documents(['tri' => 'taille']))[0], 'La photo est la plus lourde.');
        self::assertSame(['b-bilan.pdf'], $this->noms($moi->documents(['categorie' => 'pdf'])));
        self::assertSame(['a-photo.jpg'], $this->noms($moi->documents(['categorie' => 'image'])));
        self::assertSame(['a-photo.jpg'], $this->noms($moi->documents(['expediteur_id' => '2'])));
        self::assertSame(['c-releve.csv', 'b-bilan.pdf'], $this->noms($moi->documents(['conversation_id' => (string) $compta])));
        self::assertSame(['c-releve.csv'], $this->noms($moi->documents(['q' => 'caisse'])), 'La recherche lit la légende.');
        self::assertSame(['b-bilan.pdf'], $this->noms($moi->documents(['q' => 'bilan'])));
        self::assertSame(['a-photo.jpg'], $this->noms($moi->documents(['du' => '2026-10-23', 'au' => '2026-10-23'])), '« au » est inclus.');
        self::assertSame(['c-releve.csv', 'a-photo.jpg'], $this->noms($moi->documents(['du' => '2026-10-23'])));
        self::assertSame(['b-bilan.pdf'], $this->noms($moi->documents(['au' => '2026-10-22'])));
        self::assertSame(['b-bilan.pdf', 'c-releve.csv', 'a-photo.jpg'], $this->noms($moi->documents(['tri' => 'expediteur'])), 'Awa avant Bakary.');
        self::assertSame(['a-photo.jpg', 'b-bilan.pdf', 'c-releve.csv'], $this->noms($moi->documents(['tri' => 'type'])), 'image, pdf, tableur.');

        $ligne = $moi->documents(['q' => 'caisse'])['documents'][0];
        self::assertSame($compta, $ligne['conversation']['id']);
        self::assertSame(array_values($this->depot->pieces)[2]['message_id'], $ligne['message_id'], 'Aller au message.');
        self::assertSame(['id' => 1, 'nom' => 'Awa'], $ligne['expediteur']);

        $this->refus(422, fn() => $moi->documents(['du' => '23/10/2026']));
        $this->refus(422, fn() => $moi->documents(['du' => '2026-10-25', 'au' => '2026-10-20']));
        // Un tri inconnu ne casse rien : on revient à la date.
        self::assertSame('date', $moi->documents(['tri' => 'pj.id; DROP TABLE'])['filtres']['tri']);
    }

    public function test_modifier_le_texte_d_un_message_a_fichier_change_la_legende_de_la_piece(): void
    {
        $compta = $this->en(1)->creerGroupe('Compta', '', [2]);
        $message = $this->en(1)->envoyerFichier($compta, $this->fichier('bilan.pdf', self::PDF), 'Brouillon');
        $sansLegende = $this->en(1)->envoyerFichier($compta, $this->fichier('releve.csv', "a;b
"));

        $modifie = $this->en(1)->modifier($message['id'], 'Bilan définitif');
        $this->en(1)->modifier($sansLegende['id'], 'Relevé de caisse');

        self::assertSame('Bilan définitif', $modifie['pieces_jointes'][0]['legende']);
        self::assertSame(['bilan.pdf'], $this->noms($this->en(2)->documents(['q' => 'définitif'])), 'Le classeur cherche la nouvelle légende.');
        self::assertSame([], $this->noms($this->en(2)->documents(['q' => 'Brouillon'])), 'L’ancienne ne répond plus.');
        self::assertSame(['releve.csv'], $this->noms($this->en(2)->documents(['q' => 'caisse'])), 'Une légende ajoutée après coup compte aussi.');
    }

    public function test_le_classeur_pagine_par_cinquante(): void
    {
        $groupe = $this->en(1)->creerGroupe('Archives', '', [2]);
        for ($i = 0; $i < 51; $i++) {
            $this->heure = $this->heure->modify('+3 seconds');
            $this->en(1)->envoyerFichier($groupe, $this->fichier(sprintf('piece-%02d.txt', $i), "Pièce {$i}\n"));
        }

        $premiere = $this->en(2)->documents([]);
        self::assertSame([51, 2, 50], [$premiere['total'], $premiere['pages'], count($premiere['documents'])]);
        $seconde = $this->en(2)->documents(['page' => '2']);
        self::assertSame(['piece-00.txt'], $this->noms($seconde));
    }

    // ------------------------------------------------------------------
    // Photo de groupe
    // ------------------------------------------------------------------

    public function test_la_photo_du_groupe_par_un_admin(): void
    {
        $groupe = $this->en(1)->creerGroupe('Chantier', '', [2]);

        $this->refus(403, fn() => $this->en(2)->definirPhoto($groupe, $this->fichier('p.jpg', ImagesDeTest::photoDeTelephone(900, 600))));
        $this->refus(422, fn() => $this->en(1)->definirPhoto($groupe, $this->fichier('p.pdf', self::PDF)));

        $url = $this->en(1)->definirPhoto($groupe, $this->fichier('p.jpg', ImagesDeTest::photoDeTelephone(900, 600)))['photo'];
        self::assertStringStartsWith('/api/conversations/' . $groupe . '/photo?v=', $url);
        self::assertSame($url, $this->en(2)->detail($groupe)['photo']);
        self::assertSame($url, $this->en(2)->conversations()[0]['photo']);

        $photo = $this->en(2)->photo($groupe);
        self::assertSame([256, 256], array_slice((array) getimagesize($photo['chemin']), 0, 2));
        self::assertSame('image/jpeg', $photo['mime']);
        $this->refus(404, fn() => $this->en(3)->photo($groupe));

        // Une nouvelle photo remplace l'ancienne, sur le disque aussi.
        $ancien = $photo['chemin'];
        $nouvelle = $this->en(1)->definirPhoto($groupe, $this->fichier('logo.png', ImagesDeTest::pngTransparent()))['photo'];
        self::assertNotSame($url, $nouvelle);
        self::assertFileDoesNotExist($ancien);
        self::assertSame('image/png', $this->en(2)->photo($groupe)['mime']);

        $this->en(1)->retirerPhoto($groupe);
        self::assertNull($this->en(2)->detail($groupe)['photo']);
        $this->refus(404, fn() => $this->en(2)->photo($groupe));

        $direct = $this->en(1)->ouvrirDirecte(3);
        $this->refus(403, fn() => $this->en(1)->definirPhoto($direct, $this->fichier('p.png', ImagesDeTest::pngOpaque())));
    }

    // ------------------------------------------------------------------
    // Jauge
    // ------------------------------------------------------------------

    public function test_la_jauge_compte_le_disque_par_mois_et_par_categorie(): void
    {
        $groupe = $this->en(1)->creerGroupe('Compta', '', [2]);
        $this->en(1)->envoyerFichier($groupe, $this->fichier('a.pdf', self::PDF));
        $this->heure = new DateTimeImmutable('2026-11-02 08:00:00');
        $this->en(1)->envoyerFichier($groupe, $this->fichier('b.png', ImagesDeTest::pngOpaque()));

        $disque = 0;
        foreach ($this->depot->fichiers as $f) {
            $disque += filesize($this->racine . '/' . $f['chemin']) + ($f['chemin_miniature'] ? filesize($this->racine . '/' . $f['chemin_miniature']) : 0);
        }

        $etat = (new MessagerieEspace($this->depot))->etat();
        self::assertSame($disque, $etat['octets'], 'Miniatures comprises.');
        self::assertSame(2, $etat['fichiers']);
        self::assertSame(['2026-11', '2026-10'], array_column($etat['par_mois'], 'mois'));
        self::assertEqualsCanonicalizing(['pdf', 'image'], array_column($etat['par_categorie'], 'categorie'));
        self::assertFalse($etat['alerte']);
        self::assertSame('20 Go', $etat['seuil_lisible']);

        $alerte = (new MessagerieEspace($this->depot, 1000))->etat();
        self::assertTrue($alerte['alerte']);
        self::assertGreaterThanOrEqual(100, $alerte['pourcentage']);

        self::assertSame('1,5 Ko', MessagerieEspace::lisible(1536));
        self::assertSame('512 octets', MessagerieEspace::lisible(512));
    }

    public function test_le_stockage_ne_sert_rien_hors_de_son_dossier(): void
    {
        $stockage = new MessagerieStockage($this->racine, false);

        self::assertNull($stockage->cheminAbsolu('../../etc/passwd'));
        self::assertNull($stockage->cheminAbsolu('messagerie/../../secret.txt'));
        self::assertNull($stockage->cheminAbsolu('dossiers_envoi/2026/10/x.pdf'));
        self::assertNull($stockage->cheminAbsolu(null));
    }

    // ------------------------------------------------------------------

    private function en(int $userId, bool $direction = false): MessagerieService
    {
        return (new MessagerieService(
            $this->depot,
            $userId,
            $direction,
            fn(): DateTimeImmutable => $this->heure,
            new MessagerieStockage($this->racine, false),
        ))->avecUrlApi('/api');
    }

    /** @return array<string, mixed> une entrée façon $_FILES */
    private function fichier(string $nom, string $contenu): array
    {
        $chemin = $this->envois . '/' . bin2hex(random_bytes(6));
        file_put_contents($chemin, $contenu);

        return $this->entree($nom, $chemin);
    }

    /** @return array<string, mixed> */
    private function entree(string $nom, string $chemin): array
    {
        return ['name' => $nom, 'type' => 'application/octet-stream', 'tmp_name' => $chemin, 'error' => UPLOAD_ERR_OK, 'size' => filesize($chemin)];
    }

    /** @param array<string, string> $entrees */
    private function zip(array $entrees): string
    {
        $chemin = $this->envois . '/' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($chemin, ZipArchive::CREATE);
        foreach ($entrees as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }
        $zip->close();

        return $chemin;
    }

    private function fichiersSurDisque(): int
    {
        $dossier = $this->racine . '/messagerie';
        if (!is_dir($dossier)) {
            return 0;
        }

        $n = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && $f->getFilename() !== '.htaccess' && !str_contains($f->getFilename(), '_m.')) {
                $n++;
            }
        }

        return $n;
    }

    /** @param array<string, mixed> $resultat @return array<int, string> */
    private function noms(array $resultat): array
    {
        return array_column($resultat['documents'], 'nom');
    }

    private function refus(int $code, callable $action, string $contexte = ''): string
    {
        try {
            $action();
        } catch (MessagerieException $e) {
            self::assertSame($code, $e->getCode(), trim($contexte . ' : ' . $e->getMessage(), ' :'));

            return $e->getMessage();
        }

        self::fail('Un refus ' . $code . ' était attendu' . ($contexte !== '' ? ' (' . $contexte . ')' : '') . '.');
    }
}
