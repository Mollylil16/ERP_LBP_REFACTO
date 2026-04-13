/**
 * Mappe les codes permissions issus de la base (seed domaine : exploitation.*, facturation.*, etc.)
 * vers les codes utilisés par le frontend (colis.groupage.read, factures.read, …).
 * Les codes déjà au format app (ex: litiges.view) sont conservés tels quels.
 */

const APP_CODE =
  /^(?:colis|dashboard|clients|factures|paiements|rapports|caisse|config|users|litiges|callcenter|exploitation)\.[a-z0-9_.-]+$/i;

export function mapDbPermissionCodesToAppCodes(dbCodes: string[]): string[] {
  const out = new Set<string>();

  for (const code of dbCodes) {
    if (!code) continue;

    const mapped = mapOneDbCode(code);
    if (mapped.length > 0) {
      for (const m of mapped) {
        out.add(m);
      }
      continue;
    }

    if (APP_CODE.test(code)) {
      out.add(code);
    }
  }

  return [...out];
}

function mapOneDbCode(code: string): string[] {
  // Colis / exploitation
  let m = /^exploitation\.groupage_colis\.(\w+)$/i.exec(code);
  if (m) {
    return [`colis.groupage.${m[1].toLowerCase()}`];
  }
  m = /^exploitation\.autres_envois\.(\w+)$/i.exec(code);
  if (m) {
    return [`colis.autres-envois.${m[1].toLowerCase()}`];
  }
  m = /^exploitation\.rapports_envois\.read$/i.exec(code);
  if (m) {
    return ['rapports.view'];
  }

  // Structures / clients
  m = /^structures\.clients\.(\w+)$/i.exec(code);
  if (m) {
    return [`clients.${m[1].toLowerCase()}`];
  }

  // Structures / utilisateurs → users.*
  m = /^structures\.utilisateurs\.(\w+)$/i.exec(code);
  if (m) {
    const a = m[1].toLowerCase();
    if (a === 'read') return ['users.read'];
    if (a === 'create') return ['users.create'];
    if (a === 'update') return ['users.update'];
    if (a === 'delete') return ['users.delete'];
    return [`users.${a}`];
  }

  // Structures / agences : lecture = liste (sélecteurs, 1ère connexion), pas l’écran « Paramètres société »
  m = /^structures\.agences\.read$/i.exec(code);
  if (m) {
    return ['agences.read'];
  }
  m = /^structures\.agences\.(create|update)$/i.exec(code);
  if (m) {
    return ['config.update', 'agences.read'];
  }
  m = /^structures\.agences\.delete$/i.exec(code);
  if (m) {
    return ['config.system'];
  }

  // Paramètres généraux application (écran /settings société)
  m = /^structures\.parametres_application\.read$/i.exec(code);
  if (m) {
    return ['config.view'];
  }

  // Facturation → factures (aligné front)
  m = /^facturation\.facturer\.(\w+)$/i.exec(code);
  if (m) {
    const a = m[1].toLowerCase();
    if (a === 'read') return ['factures.read'];
    if (a === 'create') return ['factures.create'];
    if (a === 'update') return ['factures.update'];
    if (a === 'delete') return ['factures.delete'];
    return [`factures.${a}`];
  }
  m = /^facturation\.cotation\.(\w+)$/i.exec(code);
  if (m) {
    const a = m[1].toLowerCase();
    if (a === 'create' || a === 'update')
      return ['factures.read', 'factures.create'];
    return ['factures.read'];
  }

  // Paiements (règlement client)
  m = /^operation_caisse\.reglement_client\.(\w+)$/i.exec(code);
  if (m) {
    const a = m[1].toLowerCase();
    if (a === 'create' || a === 'update')
      return ['paiements.create', 'paiements.read', 'caisse.operations'];
    if (a === 'read') return ['paiements.read', 'caisse.view'];
    return ['paiements.read'];
  }

  // Caisse
  m = /^operation_caisse\.gestion_caisses\.(\w+)$/i.exec(code);
  if (m) {
    return ['caisse.view'];
  }
  m = /^operation_caisse\.mouvements_caisses\.(\w+)$/i.exec(code);
  if (m) {
    const a = m[1].toLowerCase();
    if (a === 'read') return ['caisse.view'];
    return ['caisse.view', 'caisse.operations'];
  }
  m = /^operation_caisse\.journal\.read$/i.exec(code);
  if (m) {
    return ['caisse.view'];
  }

  // Rapports
  m =
    /^rapports\.(suivi_envois|statistiques|ca_detaille|business_analyst)\.read$/i.exec(
      code,
    );
  if (m) {
    return ['rapports.view'];
  }

  return [];
}

/** Le front attend toujours au moins dashboard.view ; la caisse ajoute dashboard.caisse. */
export function ensureDashboardPermissions(codes: string[]): string[] {
  if (codes.length === 0) return codes;
  const s = new Set(codes);
  s.add('dashboard.view');
  if ([...s].some((c) => c.startsWith('caisse.'))) {
    s.add('dashboard.caisse');
  }
  return [...s];
}
