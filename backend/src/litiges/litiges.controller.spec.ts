import { LitigesController } from './litiges.controller';
import { LitigesService } from './litiges.service';

describe('LitigesController', () => {
  let controller: LitigesController;
  let litigesService: jest.Mocked<Pick<LitigesService, 'findAll'>>;

  beforeEach(() => {
    litigesService = {
      findAll: jest.fn().mockResolvedValue({
        data: [],
        total: 0,
        page: 1,
        limit: 20,
        totalPages: 0,
      }),
    };

    controller = new LitigesController(
      litigesService as unknown as LitigesService,
    );
  });

  it('ignore with_deleted=true pour un utilisateur non-admin', async () => {
    await controller.findAll(
      undefined,
      undefined,
      undefined,
      undefined,
      1,
      20,
      'true',
      { user: { role: 'AGENT_SUIVI' } },
    );

    expect(litigesService.findAll).toHaveBeenCalledWith(
      expect.objectContaining({ with_deleted: false }),
    );
  });
});
