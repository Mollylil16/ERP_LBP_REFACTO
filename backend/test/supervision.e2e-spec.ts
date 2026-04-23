import { Test, TestingModule } from '@nestjs/testing';
import { INestApplication, ExecutionContext } from '@nestjs/common';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { JwtAuthGuard } from '../src/auth/guards/jwt-auth.guard';
import { RolesService } from '../src/roles/roles.service';
import { GlobalExceptionFilter } from '../src/common/filters/global-exception.filter';

/**
 * Supervision : JWT (401) et permissions sur /supervision/kpis.
 * Nécessite PostgreSQL joignable (comme les autres e2e).
 */
describe('Supervision (e2e)', () => {
  let app: INestApplication;

  afterEach(async () => {
    if (app) {
      await app.close();
    }
  });

  it('GET /supervision/kpis sans token → 401', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    await request(app.getHttpServer()).get('/supervision/kpis').expect(401);
  });

  it('GET /supervision/kpis avec JWT mock sans supervision.dashboard.read → 403', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999101,
            username: 'e2e_no_supervision',
            role: 'AGENT_SUIVI',
            code_acces: 9,
          };
          return true;
        },
      })
      .overrideProvider(RolesService)
      .useValue({
        getAppPermissionCodesForRole: jest
          .fn()
          .mockResolvedValue(['clients.read']),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    const res = await request(app.getHttpServer())
      .get('/supervision/kpis')
      .expect(403);
    expect(res.body.statusCode).toBe(403);
  });

  it('GET /supervision/kpis avec JWT mock et supervision.dashboard.read → 200', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999102,
            username: 'e2e_supervision',
            role: 'SUPERVISEURE_GENERALE',
            code_acces: 1,
          };
          return true;
        },
      })
      .overrideProvider(RolesService)
      .useValue({
        getAppPermissionCodesForRole: jest.fn().mockResolvedValue([
          'supervision.dashboard.read',
          'dashboard.view',
        ]),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    const res = await request(app.getHttpServer())
      .get('/supervision/kpis')
      .expect(200);
    expect(res.body).toMatchObject({
      colisCrees: expect.any(Number),
      agences: expect.any(Number),
    });
  });
});
