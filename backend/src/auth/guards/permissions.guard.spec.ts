import { ForbiddenException, ExecutionContext } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { PermissionsGuard } from './permissions.guard';
import { RolesService } from '../../roles/roles.service';

function createContext(
  user: Record<string, unknown> | undefined,
): ExecutionContext {
  return {
    getHandler: () => jest.fn(),
    getClass: () => class Test {},
    switchToHttp: () => ({
      getRequest: () => ({ user }),
    }),
  } as unknown as ExecutionContext;
}

describe('PermissionsGuard', () => {
  let guard: PermissionsGuard;
  let reflector: jest.Mocked<Pick<Reflector, 'getAllAndOverride'>>;
  let rolesService: { getAppPermissionCodesForRole: jest.Mock };

  beforeEach(() => {
    reflector = { getAllAndOverride: jest.fn() };
    rolesService = { getAppPermissionCodesForRole: jest.fn() };
    guard = new PermissionsGuard(
      reflector as unknown as Reflector,
      rolesService as unknown as RolesService,
    );
  });

  it('laisse passer si aucune permission requise (métadonnées absentes)', async () => {
    reflector.getAllAndOverride.mockReturnValue(undefined);
    const ctx = createContext({
      id: 1,
      role: 'AGENT_EXPLOITATION',
      code_acces: 1,
    });
    await expect(guard.canActivate(ctx)).resolves.toBe(true);
    expect(rolesService.getAppPermissionCodesForRole).not.toHaveBeenCalled();
  });

  it('laisse passer pour DIRECTEUR sans appeler la base', async () => {
    reflector.getAllAndOverride.mockReturnValue(['colis.groupage.read']);
    const ctx = createContext({ id: 1, role: 'DIRECTEUR', code_acces: 1 });
    await expect(guard.canActivate(ctx)).resolves.toBe(true);
    expect(rolesService.getAppPermissionCodesForRole).not.toHaveBeenCalled();
  });

  it('laisse passer si le rôle a au moins une des permissions requises', async () => {
    reflector.getAllAndOverride.mockReturnValue([
      'colis.groupage.read',
      'colis.autres-envois.read',
    ]);
    rolesService.getAppPermissionCodesForRole.mockResolvedValue([
      'colis.groupage.read',
      'dashboard.view',
    ]);
    const ctx = createContext({ id: 2, role: 'AGENT_GROUPAGE', code_acces: 1 });
    await expect(guard.canActivate(ctx)).resolves.toBe(true);
  });

  it('rejette (403) si la matrice ne contient aucune permission requise', async () => {
    reflector.getAllAndOverride.mockReturnValue(['factures.delete']);
    rolesService.getAppPermissionCodesForRole.mockResolvedValue([
      'clients.read',
    ]);
    const ctx = createContext({ id: 3, role: 'AGENT_SUIVI', code_acces: 9 });
    await expect(guard.canActivate(ctx)).rejects.toThrow(ForbiddenException);
  });

  it('rejette si utilisateur sans rôle', async () => {
    reflector.getAllAndOverride.mockReturnValue(['clients.read']);
    const ctx = createContext({ id: 4, code_acces: 1 });
    await expect(guard.canActivate(ctx)).rejects.toThrow(ForbiddenException);
  });

  it('rejette si non authentifié', async () => {
    reflector.getAllAndOverride.mockReturnValue(['clients.read']);
    const ctx = createContext(undefined);
    await expect(guard.canActivate(ctx)).rejects.toThrow(ForbiddenException);
  });

  it('passe required dans la réponse 403 pour audit', async () => {
    reflector.getAllAndOverride.mockReturnValue(['paiements.cancel']);
    rolesService.getAppPermissionCodesForRole.mockResolvedValue([
      'paiements.read',
    ]);
    const ctx = createContext({ id: 5, role: 'CAISSIER', code_acces: 1 });
    let thrown: unknown;
    try {
      await guard.canActivate(ctx);
    } catch (e) {
      thrown = e;
    }
    expect(thrown).toBeInstanceOf(ForbiddenException);
    const res = (thrown as ForbiddenException).getResponse() as {
      required?: string[];
    };
    expect(res.required).toEqual(['paiements.cancel']);
  });
});
