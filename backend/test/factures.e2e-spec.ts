import { Test, TestingModule } from '@nestjs/testing';
import { INestApplication } from '@nestjs/common';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { JwtService } from '@nestjs/jwt';

describe('Factures (e2e)', () => {
  let app: INestApplication | undefined;
  let jwtService: JwtService;
  let token: string;

  beforeAll(async () => {
    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    await app.init();

    jwtService = app.get<JwtService>(JwtService);
    // Generate a mock token for admin
    token = jwtService.sign({ username: 'admin', role: 'SUPER_ADMIN' });
  });

  afterAll(async () => {
    if (app) {
      await app.close();
    }
  });

  it('/factures (GET) - unauthorized', () => {
    return request(app.getHttpServer()).get('/factures').expect(401);
  });

  it('/factures (GET) - authorized', () => {
    return request(app.getHttpServer())
      .get('/factures')
      .set('Authorization', `Bearer ${token}`)
      .expect(200);
  });

  // Note: To test generateFromColis, we would need a real colis in the DB or a complex mock.
  // For now, we verify the structure of the routes.

  it('/factures/:id (GET) - not found', () => {
    return request(app.getHttpServer())
      .get('/factures/99999')
      .set('Authorization', `Bearer ${token}`)
      .expect(404);
  });
});
