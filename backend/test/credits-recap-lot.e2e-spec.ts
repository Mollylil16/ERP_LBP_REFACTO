import { Test, TestingModule } from '@nestjs/testing';
import { INestApplication, ExecutionContext } from '@nestjs/common';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { JwtAuthGuard } from '../src/auth/guards/jwt-auth.guard';
import { RolesService } from '../src/roles/roles.service';
import { GlobalExceptionFilter } from '../src/common/filters/global-exception.filter';

/**
 * Contrôles HTTP récap / recettes (JWT mock + matrice permissions).
 * L’agrégation CIV des lots est couverte par `credits-colis.service.spec.ts` (sans dépendre de la table en e2e).
 */
describe('Credits recap & lot (e2e)', () => {
  let app: INestApplication;

  afterEach(async () => {
    if (app) {
      await app.close();
    }
  });

  it('GET /credits/recaps-historique-civ sans droit manage → 403', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999101,
            username: 'e2e_credits_read',
            role: 'AGENT_EXPLOITATION',
            code_acces: 1,
          };
          return true;
        },
      })
      .overrideProvider(RolesService)
      .useValue({
        getAppPermissionCodesForRole: jest
          .fn()
          .mockResolvedValue(['exploitation.credits.read']),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    const res = await request(app.getHttpServer())
      .get('/credits/recaps-historique-civ')
      .expect(403);
    expect(res.body.statusCode).toBe(403);
  });

  it('GET /credits/recettes-aujourdhui avec paiements.read → 200', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999103,
            username: 'e2e_paiements_read',
            role: 'CAISSIER',
            code_acces: 1,
          };
          return true;
        },
      })
      .overrideProvider(RolesService)
      .useValue({
        getAppPermissionCodesForRole: jest
          .fn()
          .mockResolvedValue(['paiements.read']),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    const res = await request(app.getHttpServer())
      .get('/credits/recettes-aujourdhui')
      .expect(200);
    expect(res.body).toHaveProperty('recettesJour');
    expect(res.body).toHaveProperty('recettesJourParDevise');
    expect(Array.isArray(res.body.recettesJourParDevise)).toBe(true);
  });
});
