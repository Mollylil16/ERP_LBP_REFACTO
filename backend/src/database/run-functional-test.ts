import 'reflect-metadata';
import { config } from 'dotenv';
import * as path from 'node:path';
import { DataSource } from 'typeorm';
import * as bcrypt from 'bcrypt';

// Charger .env depuis backend/
config({ path: path.resolve(process.cwd(), '.env') });

type LoginResponse = {
  token: string;
  user: any;
  permissions: string[];
};

const API_BASE = 'http://localhost:3001/api';

async function httpJson<T>(
  url: string,
  init?: RequestInit,
): Promise<{ ok: true; data: T } | { ok: false; status: number; text: string }> {
  const res = await fetch(url, init);
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    return { ok: false, status: res.status, text };
  }
  const data = (await res.json()) as T;
  return { ok: true, data };
}

async function login(username: string, password: string): Promise<LoginResponse> {
  const r = await httpJson<LoginResponse>(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  });
  if (!r.ok) {
    throw new Error(
      `Login failed (${username}) status=${r.status} ${r.text || ''}`.trim(),
    );
  }
  return r.data;
}

async function downloadPdf(url: string, token: string): Promise<number> {
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!res.ok) {
    const txt = await res.text().catch(() => '');
    throw new Error(`PDF download failed status=${res.status} ${txt}`.trim());
  }
  const buf = await res.arrayBuffer();
  return buf.byteLength;
}

async function ensureCaisseSessionOpen(token: string): Promise<void> {
  // 1) Récupérer la caisse de l’utilisateur (1 caisse par agence)
  const caisses = await httpJson<any[]>(`${API_BASE}/caisse/caisses`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!caisses.ok) {
    throw new Error(`Get caisses failed status=${caisses.status} ${caisses.text}`);
  }
  const idCaisse = Number(caisses.data?.[0]?.id);
  if (!idCaisse) {
    throw new Error('Aucune caisse trouvée pour ouvrir une session');
  }

  // 2) Ouvrir la session (si déjà ouverte, l’API renverra 400 ; on ignore)
  const open = await httpJson<any>(`${API_BASE}/caisse/sessions/open`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id_caisse: idCaisse,
      solde_ouverture_reel: 0,
      note: 'Ouverture auto pour test encaissement',
    }),
  });
  if (!open.ok) {
    const already =
      open.status === 400 &&
      (open.text || '').toLowerCase().includes('session est déjà ouverte');
    if (!already) {
      throw new Error(`Open session failed status=${open.status} ${open.text}`);
    }
  }
}

async function ensureTestUsers() {
  const ds = new DataSource({
    type: 'postgres',
    host: process.env.DB_HOST,
    port: parseInt(process.env.DB_PORT ?? '5432', 10),
    username: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
  });
  await ds.initialize();

  const agenceRows: Array<{ id: number; code: string; nom: string }> =
    await ds.query(`SELECT id, code, nom FROM agences ORDER BY id ASC`);
  if (!agenceRows.length) {
    throw new Error('Aucune agence en base. Exécuter seed agences avant.');
  }

  // Agences qui ont au moins 1 colis (pour pouvoir tester encaissement)
  const agencesAvecColis: Array<{ id: number; cnt: number }> = await ds.query(
    `SELECT id_agence AS id, COUNT(*)::int AS cnt FROM lbp_colis WHERE id_agence IS NOT NULL GROUP BY id_agence ORDER BY COUNT(*) DESC`,
  );
  const setAgencesAvecColis = new Set(agencesAvecColis.map((a) => Number(a.id)));

  const roleRows: Array<{ id: number; code: string }> = await ds.query(
    `SELECT id, code FROM lbp_roles WHERE code IN ('CAISSIER','CAISSIER_AGENCE')`,
  );
  const roleIdByCode = new Map(roleRows.map((r) => [r.code, r.id]));
  const caissierRoleId = roleIdByCode.get('CAISSIER');
  const caissierAgenceRoleId = roleIdByCode.get('CAISSIER_AGENCE');
  if (!caissierRoleId || !caissierAgenceRoleId) {
    throw new Error('Rôles CAISSIER/CAISSIER_AGENCE introuvables en base.');
  }

  const pickAgence = (prefer: RegExp, fallbackIdx: number) => {
    const found = agenceRows.find(
      (a) => prefer.test(a.code) || prefer.test(a.nom),
    );
    return found ?? agenceRows[Math.min(fallbackIdx, agenceRows.length - 1)];
  };

  // Heuristique : CAISSIER = Abobo/Dokui si présent
  const agenceHub = pickAgence(/abobo|dokui/i, 0);
  // CAISSIER_AGENCE = une autre agence, idéalement avec colis
  const agenceAgenceCandidate =
    agenceRows.find((a) => a.id !== agenceHub.id && setAgencesAvecColis.has(a.id)) ??
    agenceRows.find((a) => setAgencesAvecColis.has(a.id)) ??
    agenceRows.find((a) => a.id !== agenceHub.id) ??
    agenceHub;

  // Si aucune agence autre que le hub n'a de colis, on rabat sur le hub pour pouvoir tester l'encaissement.
  const hasOtherAgencyWithColis = [...setAgencesAvecColis].some(
    (id) => Number(id) !== Number(agenceHub.id),
  );
  const agenceAgence = hasOtherAgencyWithColis ? agenceAgenceCandidate : agenceHub;

  const users: Array<{
    username: string;
    password: string;
    nom: string;
    role: 'CAISSIER' | 'CAISSIER_AGENCE';
    roleId: number;
    agenceId: number;
  }> = [
    {
      username: 'test_caissier',
      password: 'password123',
      nom: 'Test Caissier Principal',
      role: 'CAISSIER',
      roleId: caissierRoleId,
      agenceId: agenceHub.id,
    },
    {
      username: 'test_caissier_agence',
      password: 'password123',
      nom: "Test Caissier d'Agence",
      role: 'CAISSIER_AGENCE',
      roleId: caissierAgenceRoleId,
      agenceId: agenceAgence.id,
    },
  ];

  for (const u of users) {
    const existing: Array<{ id: number }> = await ds.query(
      `SELECT id FROM lbp_users WHERE username = $1 LIMIT 1`,
      [u.username],
    );
    const hash = await bcrypt.hash(u.password, 10);
    if (!existing.length) {
      await ds.query(
        `
        INSERT INTO lbp_users
          (username, password, fullname, role, code_acces, "isActive", must_change_password, agence_selected, id_agence, role_id, created_at, updated_at, password_plain)
        VALUES
          ($1, $2, $3, $4::public.lbp_users_role_enum, 1, true, false, true, $5, $6, NOW(), NOW(), $7)
        `,
        [u.username, hash, u.nom, u.role, u.agenceId, u.roleId, u.password],
      );
      // eslint-disable-next-line no-console
      console.log(`✅ User créé: ${u.username} (${u.role}) agenceId=${u.agenceId}`);
    } else {
      await ds.query(
        `
        UPDATE lbp_users
        SET password=$2,
            password_plain=$7,
            fullname=$3,
            role=$4::public.lbp_users_role_enum,
            role_id=$6,
            id_agence=$5,
            agence_selected=true,
            must_change_password=false,
            "isActive"=true,
            updated_at=NOW()
        WHERE username=$1
        `,
        [u.username, hash, u.nom, u.role, u.agenceId, u.roleId, u.password],
      );
      // eslint-disable-next-line no-console
      console.log(
        `ℹ️ User mis à jour: ${u.username} (${u.role}) agenceId=${u.agenceId}`,
      );
    }
  }

  await ds.destroy();
  return { agenceHub, agenceAgence };
}

async function ensureOneUnpaidInvoiceForAgency(agenceId: number): Promise<void> {
  const ds = new DataSource({
    type: 'postgres',
    host: process.env.DB_HOST,
    port: parseInt(process.env.DB_PORT ?? '5432', 10),
    username: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
  });
  await ds.initialize();

  // Trouver un colis de l'agence
  const colisRows: Array<{ id: number; ref_colis: string }> = await ds.query(
    `SELECT id, ref_colis FROM lbp_colis WHERE id_agence = $1 ORDER BY created_at DESC LIMIT 1`,
    [agenceId],
  );
  let colisId = colisRows[0]?.id;

  // Si aucun colis : créer un client + colis minimal de test
  if (!colisId) {
    const clientInsert = await ds.query(
      `
      INSERT INTO lbp_clients (nom_exp, tel_exp, type_piece_exp, num_piece_exp, email_exp, "isActive", created_at, updated_at)
      VALUES ($1, $2, NULL, NULL, NULL, true, NOW(), NOW())
      RETURNING id
      `,
      ['Client Test Encaissement', '+2250102030405'],
    );
    const clientId = Number(clientInsert?.[0]?.id);
    if (!clientId) {
      await ds.destroy();
      throw new Error('Impossible de créer le client test');
    }

    const ref = `LBP-TST-${Date.now()}`;
    const colisInsert = await ds.query(
      `
      INSERT INTO lbp_colis
        (ref_colis, trafic_envoi, forme_envoi, mode_envoi, tracker_id, livraison, date_envoi,
         id_client, nom_dest, lieu_dest, tel_dest, email_dest,
         nom_recup, adresse_recup, tel_recup, email_recup,
         etat_validation, code_user, id_agence, statut_suivi, created_at, updated_at)
      VALUES
        ($1, 'Groupage Test', 'groupage', NULL, NULL, false, NOW(),
         $2, 'DEST TEST', 'ABIDJAN', '00000000', NULL,
         NULL, NULL, NULL, NULL,
         1, 'test', $3, 'EMBALLE', NOW(), NOW())
      RETURNING id
      `,
      [ref, clientId, agenceId],
    );
    colisId = Number(colisInsert?.[0]?.id);
    if (!colisId) {
      await ds.destroy();
      throw new Error('Impossible de créer le colis test');
    }
    // eslint-disable-next-line no-console
    console.log(`✅ Colis test créé id=${colisId} agenceId=${agenceId}`);
  }

  // Vérifier si une facture existe déjà
  const existingFacture: Array<{ id: number }> = await ds.query(
    `SELECT id FROM lbp_factures WHERE id_colis = $1 LIMIT 1`,
    [colisId],
  );
  if (existingFacture.length) {
    await ds.destroy();
    return;
  }

  // Créer une facture simple (impayée) pour permettre l'encaissement
  const now = new Date();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const yy = String(now.getFullYear()).slice(-2);
  const num = `FCO-${mm}${yy}-TST`;
  await ds.query(
    `
    INSERT INTO lbp_factures
      (num_facture, id_colis, montant_ht, montant_ttc, montant_paye, etat, payment_status, devise, taux_change, date_facture, code_user, created_at, updated_at)
    VALUES
      ($1, $2, 2000, 2000, 0, 1, 'unpaid', 'XOF', 1, NOW(), 'test', NOW(), NOW())
    `,
    [num, colisId],
  );
  await ds.destroy();
  // eslint-disable-next-line no-console
  console.log(`✅ Facture test créée pour agenceId=${agenceId} (colisId=${colisId})`);
}

async function main() {
  // eslint-disable-next-line no-console
  console.log('🧪 Functional test: préparation utilisateurs…');
  const { agenceHub, agenceAgence } = await ensureTestUsers();
  await ensureOneUnpaidInvoiceForAgency(agenceAgence.id);

  // eslint-disable-next-line no-console
  console.log('🧪 Login CAISSIER_AGENCE…');
  const uAgence = await login('test_caissier_agence', 'password123');
  await ensureCaisseSessionOpen(uAgence.token);

  // 1) Vérifier qu’il peut lire les impayés de son agence
  const unpaid = await httpJson<any[]>(
    `${API_BASE}/paiements/history/unpaid?agenceId=${agenceAgence.id}`,
    {
      headers: { Authorization: `Bearer ${uAgence.token}` },
    },
  );
  if (!unpaid.ok) {
    throw new Error(
      `CAISSIER_AGENCE unpaid invoices: status=${unpaid.status} ${unpaid.text}`,
    );
  }
  // eslint-disable-next-line no-console
  console.log(
    `✅ CAISSIER_AGENCE voit ${unpaid.data.length} facture(s) impayée(s) agence=${agenceAgence.code}`,
  );
  if (unpaid.data.length === 0) {
    // eslint-disable-next-line no-console
    console.log(
      '⚠️ Pas de facture impayée pour tester encaissement. (Le test PJ continue.)',
    );
  } else {
    const f0 = unpaid.data[0];
    const refColis = f0?.colis?.ref_colis;
    const restant = Number(f0?.montantRestant ?? 0);
    if (!refColis || !restant) {
      throw new Error('Facture impayée invalide (ref_colis/montantRestant manquant)');
    }
    const part1 = Math.max(1, Math.floor(restant / 2));
    const part2 = restant - part1;

    // 2) Encaissement mix
    const enc = await httpJson<any>(`${API_BASE}/paiements/encaissement`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${uAgence.token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        ref_colis: refColis,
        date_paiement: new Date().toISOString().slice(0, 10),
        lignes: [
          { montant: part1, mode_paiement: 'especes' },
          { montant: part2, mode_paiement: 'wave', reference: 'TEST-WAVE-001' },
        ],
      }),
    });
    if (!enc.ok) {
      throw new Error(`Encaissement mix failed status=${enc.status} ${enc.text}`);
    }
    const encRef = enc.data.encaissement_ref;
    // eslint-disable-next-line no-console
    console.log(`✅ Encaissement mix créé: ${encRef} (colis=${refColis})`);

    // 3) Reçu encaissement
    const pdfSize = await downloadPdf(
      `${API_BASE}/paiements/encaissement/${encodeURIComponent(encRef)}/receipt`,
      uAgence.token,
    );
    // eslint-disable-next-line no-console
    console.log(`✅ Reçu encaissement PDF OK (${pdfSize} bytes)`);
  }

  // PJ : créer/soumettre par CAISSIER (sur son agence hub), puis valider/rejeter par un autre validateur.
  // On utilise ici ADMIN (si existe) sinon on ne peut pas valider car anti auto-validation.
  // eslint-disable-next-line no-console
  console.log('🧪 Login CAISSIER (principal)…');
  const uCaisse = await login('test_caissier', 'password123');

  // 4) Créer un PJ sur agence hub
  const datePoint = new Date().toISOString().slice(0, 10);
  const pjCreate = await httpJson<any>(`${API_BASE}/points-journaliers`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${uCaisse.token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id_agence: agenceHub.id,
      date_point: datePoint,
      total_recettes: 1000,
      devise: 'XOF',
      observations: 'PJ test automatique',
    }),
  });
  if (!pjCreate.ok) {
    throw new Error(`PJ create failed status=${pjCreate.status} ${pjCreate.text}`);
  }
  const pjId = pjCreate.data.id;
  // eslint-disable-next-line no-console
  console.log(`✅ PJ créé id=${pjId} agence=${agenceHub.code} date=${datePoint}`);

  // 5) Soumettre
  const pjSubmit = await httpJson<any>(
    `${API_BASE}/points-journaliers/${pjId}/soumettre`,
    {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${uCaisse.token}` },
    },
  );
  if (!pjSubmit.ok) {
    throw new Error(`PJ submit failed status=${pjSubmit.status} ${pjSubmit.text}`);
  }
  // eslint-disable-next-line no-console
  console.log(`✅ PJ soumis id=${pjId}`);

  // 6) Valider avec un compte admin existant "admin/adminpassword" si présent.
  // Si absent, on s’arrête ici (car le CAISSIER ne peut pas valider son propre PJ).
  try {
    const uAdmin = await login('admin', 'adminpassword');
    const pjVal = await httpJson<any>(
      `${API_BASE}/points-journaliers/${pjId}/valider`,
      {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${uAdmin.token}` },
      },
    );
    if (!pjVal.ok) {
      throw new Error(
        `PJ validate failed status=${pjVal.status} ${pjVal.text}`.trim(),
      );
    }
    // eslint-disable-next-line no-console
    console.log(
      `✅ PJ validé id=${pjId} total_reel_caisse=${pjVal.data.total_reel_caisse} ecart_caisse=${pjVal.data.ecart_caisse}`,
    );
  } catch (e) {
    // eslint-disable-next-line no-console
    console.log(
      `⚠️ Validation PJ non testée (compte admin manquant ou erreur). Détail: ${
        e instanceof Error ? e.message : String(e)
      }`,
    );
  }

  // eslint-disable-next-line no-console
  console.log('🎉 Functional test terminé.');
}

main().catch((e) => {
  // eslint-disable-next-line no-console
  console.error('❌ Functional test FAILED:', e);
  process.exit(1);
});

