import {
  ensureDashboardPermissions,
  mapDbPermissionCodesToAppCodes,
} from './permission-code-map';

describe('mapDbPermissionCodesToAppCodes', () => {
  it('conserve les codes déjà au format app', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['litiges.view', 'colis.groupage.read']),
    ).toEqual(expect.arrayContaining(['litiges.view', 'colis.groupage.read']));
  });

  it('mappe exploitation.groupage_colis.* vers colis.groupage.*', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['exploitation.groupage_colis.read']),
    ).toEqual(['colis.groupage.read']);
    expect(
      mapDbPermissionCodesToAppCodes(['exploitation.groupage_colis.create']),
    ).toEqual(['colis.groupage.create']);
  });

  it('mappe exploitation.autres_envois.* vers colis.autres-envois.*', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['exploitation.autres_envois.update']),
    ).toEqual(['colis.autres-envois.update']);
  });

  it('mappe facturation.facturer.* vers factures.*', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['facturation.facturer.read']),
    ).toEqual(['factures.read']);
    expect(
      mapDbPermissionCodesToAppCodes(['facturation.facturer.create']),
    ).toEqual(['factures.create']);
  });

  it('mappe structures.clients.* vers clients.*', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['structures.clients.create']),
    ).toEqual(['clients.create']);
  });

  it('mappe operation_caisse vers caisse / paiements', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['operation_caisse.gestion_caisses.read']),
    ).toEqual(['caisse.view']);
    expect(
      mapDbPermissionCodesToAppCodes([
        'operation_caisse.mouvements_caisses.create',
      ]),
    ).toEqual(expect.arrayContaining(['caisse.view', 'caisse.operations']));
  });

  it('mappe rapports.*.read vers rapports.view', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['rapports.statistiques.read']),
    ).toEqual(['rapports.view']);
  });

  it('mappe structures.agences.read vers agences.read (pas config.view)', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['structures.agences.read']),
    ).toEqual(['agences.read']);
  });

  it('mappe structures.parametres_application.read vers config.view', () => {
    expect(
      mapDbPermissionCodesToAppCodes(['structures.parametres_application.read']),
    ).toEqual(['config.view']);
  });

  it('dédoublonne les résultats', () => {
    const r = mapDbPermissionCodesToAppCodes([
      'rapports.statistiques.read',
      'rapports.suivi_envois.read',
    ]);
    expect(r.filter((x) => x === 'rapports.view').length).toBe(1);
  });
});

describe('ensureDashboardPermissions', () => {
  it('ajoute dashboard.view', () => {
    expect(ensureDashboardPermissions(['factures.read'])).toEqual(
      expect.arrayContaining(['dashboard.view', 'factures.read']),
    );
  });

  it('ajoute dashboard.caisse si caisse.* présent', () => {
    expect(ensureDashboardPermissions(['caisse.view'])).toEqual(
      expect.arrayContaining([
        'dashboard.view',
        'dashboard.caisse',
        'caisse.view',
      ]),
    );
  });

  it('ne modifie pas un tableau vide', () => {
    expect(ensureDashboardPermissions([])).toEqual([]);
  });
});
