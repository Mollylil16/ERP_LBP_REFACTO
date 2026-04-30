GROUPAGE CARGO SP-CI — ABIDJAN → PARIS





Denrées alimentaires — 900 F/Kg (1,37 €)



Huile et karité — 1100 F/Kg (1,68 €)



Divers — 1850 F/Kg (2,83 €)



Huile rouge — 1600 F/Kg (2,44 €)



Atoté — 2100 F/Kg (3,21 €)

COLIS RAPIDE CA-CI — ABIDJAN → PARIS





Poisson Fumé 5500 F/Kg (8,40 €), Crevette 5500 F/Kg (8,40 €), Escargot 5500 F/Kg (8,40 €), Poulet — 5500 F/Kg (8,40 €)



Cosmétiques — 5850 F/Kg (8,93 €)

COLIS RAPIDE CA-FR — PARIS → ABIDJAN





Denrées / Divers — 11 €



Téléphone et appareil électronique — À partir de 18 €

PRIX FORFAITAIRE GROUPAGE SP-CI





Denrées alimentaires — 0 à 4 Kg : 3500 F



Huile et karité — 0 à 4 Kg : 4500 F



Divers — 0 à 2 Kg : 5000 F



Huile rouge — 0 à 2 Kg : 3500 F



Attoté — 0 à 2 Kg : 5000 F

PRIX FORFAITAIRE EXPORT CA-CI





Poisson 0 à 1 Kg : 7500 F, Crevette 0 à 1 Kg : 7500 F , Escargot 0 à 1 Kg : 7500 F, Poulet (Fumé) — 0 à 1 Kg : 7500 F



Cosmétique — 0 à 1 Kg : 8000 F


PS C:\Users\ASUS\lbp_projet\backend> npm run migration:run

> backend@0.0.1 migration:run
> npm run build && npx typeorm migration:run -d dist/src/database/data-source.js


> backend@0.0.1 build
> nest build

src/database/seeders/role-permissions.seeder.ts:303:5 - error TS1127: Invalid character.

303     ‘facturation.facturer.create’,
        ~
src/database/seeders/role-permissions.seeder.ts:303:6 - error TS2304: Cannot find name 'facturation'.

303     ‘facturation.facturer.create’,
         ~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:303:33 - error TS1127: Invalid character.

303     ‘facturation.facturer.create’,
                                    ~
src/database/seeders/role-permissions.seeder.ts:303:34 - error TS1128: Declaration or statement expected.

303     ‘facturation.facturer.create’,
                                     ~
src/database/seeders/role-permissions.seeder.ts:304:5 - error TS1127: Invalid character.

304     ‘facturation.facturer.read’,
        ~
src/database/seeders/role-permissions.seeder.ts:304:6 - error TS2304: Cannot find name 'facturation'.

304     ‘facturation.facturer.read’,
         ~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:304:31 - error TS1127: Invalid character.

304     ‘facturation.facturer.read’,
                                  ~
src/database/seeders/role-permissions.seeder.ts:304:32 - error TS1128: Declaration or statement expected.

304     ‘facturation.facturer.read’,
                                   ~
src/database/seeders/role-permissions.seeder.ts:307:5 - error TS1127: Invalid character.

307     ‘structures.clients.create’,
        ~
src/database/seeders/role-permissions.seeder.ts:307:6 - error TS2304: Cannot find name 'structures'.

307     ‘structures.clients.create’,
         ~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:307:31 - error TS1127: Invalid character.

307     ‘structures.clients.create’,
                                  ~
src/database/seeders/role-permissions.seeder.ts:307:32 - error TS1128: Declaration or statement expected.

307     ‘structures.clients.create’,
                                   ~
src/database/seeders/role-permissions.seeder.ts:308:5 - error TS2695: Left side of comma operator is unused and has no side effects.

308     'structures.clients.read',
        ~~~~~~~~~~~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:308:5 - error TS2695: Left side of comma operator is unused and has no side effects.

308     'structures.clients.read',
        ~~~~~~~~~~~~~~~~~~~~~~~~~~
309     'structures.agences.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:308:5 - error TS2695: Left side of comma operator is unused and has no side effects.

308     'structures.clients.read',
        ~~~~~~~~~~~~~~~~~~~~~~~~~~
309     'structures.agences.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
310     'litiges.view',
    ~~~~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:308:5 - error TS2695: Left side of comma operator is unused and has no side effects.

308     'structures.clients.read',
        ~~~~~~~~~~~~~~~~~~~~~~~~~~
309     'structures.agences.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
310     'litiges.view',
    ~~~~~~~~~~~~~~~~~~~
311     'litiges.create',
    ~~~~~~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:308:5 - error TS2695: Left side of comma operator is unused and has no side effects.

308     'structures.clients.read',
        ~~~~~~~~~~~~~~~~~~~~~~~~~~
309     'structures.agences.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
...
311     'litiges.create',
    ~~~~~~~~~~~~~~~~~~~~~
312     'callcenter.inbox',
    ~~~~~~~~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:313:3 - error TS1109: Expression expected.

313   ],
      ~
src/database/seeders/role-permissions.seeder.ts:313:4 - error TS1128: Declaration or statement expected.

313   ],
       ~
src/database/seeders/role-permissions.seeder.ts:314:13 - error TS2695: Left side of comma operator is unused and has no side effects.

314   CAISSIER: [
                ~
315     'operation_caisse.gestion_caisses.create',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
...
350     'facturation.facturer.create',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
351   ],
    ~~~
src/database/seeders/role-permissions.seeder.ts:352:3 - error TS2304: Cannot find name 'CAISSIER_AGENCE'.

352   CAISSIER_AGENCE: [
      ~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:352:18 - error TS1005: ';' expected.

352   CAISSIER_AGENCE: [
                     ~
src/database/seeders/role-permissions.seeder.ts:352:20 - error TS2695: Left side of comma operator is unused and has no side effects.

352   CAISSIER_AGENCE: [
                       ~
353     // Caisse agence : opérations et lecture sur sa caisse (API filtre par id_agence)
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
...
371     'callcenter.inbox',
    ~~~~~~~~~~~~~~~~~~~~~~~
372   ],
    ~~~
src/database/seeders/role-permissions.seeder.ts:373:3 - error TS2304: Cannot find name 'AGENT_SUIVI'.

373   AGENT_SUIVI: [
      ~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:373:14 - error TS1005: ';' expected.

373   AGENT_SUIVI: [
                 ~
src/database/seeders/role-permissions.seeder.ts:373:16 - error TS2695: Left side of comma operator is unused and has no side effects.

373   AGENT_SUIVI: [
                   ~
374     'exploitation.groupage_colis.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
...
386     'callcenter.inbox',
    ~~~~~~~~~~~~~~~~~~~~~~~
387   ],
    ~~~
src/database/seeders/role-permissions.seeder.ts:388:3 - error TS2304: Cannot find name 'CALL_CENTER'.

388   CALL_CENTER: [
      ~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:388:14 - error TS1005: ';' expected.

388   CALL_CENTER: [
                 ~
src/database/seeders/role-permissions.seeder.ts:388:16 - error TS2695: Left side of comma operator is unused and has no side effects.

388   CALL_CENTER: [
                   ~
389     'callcenter.inbox',
    ~~~~~~~~~~~~~~~~~~~~~~~
...
399     'litiges.create',
    ~~~~~~~~~~~~~~~~~~~~~
400   ],
    ~~~
src/database/seeders/role-permissions.seeder.ts:405:3 - error TS2304: Cannot find name 'SUPERVISEURE_GENERALE'.

405   SUPERVISEURE_GENERALE: [
      ~~~~~~~~~~~~~~~~~~~~~
src/database/seeders/role-permissions.seeder.ts:405:24 - error TS1005: ';' expected.

405   SUPERVISEURE_GENERALE: [
                           ~
src/database/seeders/role-permissions.seeder.ts:405:26 - error TS2695: Left side of comma operator is unused and has no side effects.

405   SUPERVISEURE_GENERALE: [
                             ~
406     'exploitation.groupage_colis.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
...
444     'groupeurs.rapports.read',
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
445   ],
    ~~~
src/database/seeders/role-permissions.seeder.ts:446:1 - error TS1109: Expression expected.

446 };
    ~

Found 33 error(s).

PS C:\Users\ASUS\lbp_projet\backend> 
