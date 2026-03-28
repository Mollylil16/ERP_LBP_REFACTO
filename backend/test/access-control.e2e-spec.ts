import { Test, TestingModule } from '@nestjs/testing';
import { INestApplication, ExecutionContext } from '@nestjs/common';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { JwtAuthGuard } from '../src/auth/guards/jwt-auth.guard';
import { RolesService } from '../src/roles/roles.service';
import { GlobalExceptionFilter } from '../src/common/filters/global-exception.filter';

/**
 * Intégration HTTP : JWT (401) et PermissionsGuard (403).
 * Nécessite PostgreSQL joignable (variables DB comme en dev). TypeORM reçoit `driver: pg` depuis AppModule (évite l’échec de require interne sous Jest).
 */
describe('Access control (e2e)', () => {
  let app: INestApplication;

  afterEach(async () => {
    if (app) {
      await app.close();
    }
  });

  it('GET /clients sans token → 401', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    await request(app.getHttpServer()).get('/clients').expect(401);
  });

  it('GET /colis avec utilisateur mock sans droit colis → 403', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999001,
            username: 'e2e_no_colis',
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

    const res = await request(app.getHttpServer()).get('/colis').expect(403);
    expect(res.body.statusCode).toBe(403);
  });

  it('GET /tracking/live sans token → 401', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    await request(app.getHttpServer()).get('/tracking/live').expect(401);
  });

  it('GET /tracking/live avec JWT mock sans droit colis → 403', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999002,
            username: 'e2e_no_tracking',
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
          .mockResolvedValue(['caisse.view']),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    const res = await request(app.getHttpServer())
      .get('/tracking/live')
      .expect(403);
    expect(res.body.statusCode).toBe(403);
  });

  it('GET /tracking/live avec JWT mock et colis.groupage.read → 200', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999003,
            username: 'e2e_map_ok',
            role: 'AGENT_GROUPAGE',
            code_acces: 1,
          };
          return true;
        },
      })
      .overrideProvider(RolesService)
      .useValue({
        getAppPermissionCodesForRole: jest.fn().mockResolvedValue([
          'colis.groupage.read',
        ]),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    const res = await request(app.getHttpServer())
      .get('/tracking/live')
      .expect(200);
    expect(Array.isArray(res.body)).toBe(true);
  });

  it('GET /users avec JWT mock sans users.read → 403', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideGuard(JwtAuthGuard)
      .useValue({
        canActivate: (ctx: ExecutionContext) => {
          const req = ctx.switchToHttp().getRequest();
          req.user = {
            id: 999004,
            username: 'e2e_no_users',
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

    const res = await request(app.getHttpServer()).get('/users').expect(403);
    expect(res.body.statusCode).toBe(403);
  });

  it('POST /tracking/update sans clé traceur → 401', async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    app.useGlobalFilters(new GlobalExceptionFilter());
    await app.init();

    await request(app.getHttpServer())
      .post('/tracking/update')
      .send({
        tracker_id: 'T-1',
        ref_colis: 'X',
        latitude: 0,
        longitude: 0,
      })
      .expect(401);
  });
});
