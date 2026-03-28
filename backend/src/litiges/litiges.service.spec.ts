import { BadRequestException, NotFoundException } from '@nestjs/common';
import { DataSource } from 'typeorm';
import { LitigesService } from './litiges.service';
import {
  Litige,
  LitigePriorite,
  LitigeStatut,
  LitigeType,
} from './entities/litige.entity';
import { LitigeMessage } from './entities/litige-message.entity';

describe('LitigesService', () => {
  let service: LitigesService;
  let litigeRepository: any;
  let messageRepository: any;
  let dataSource: any;
  let manager: any;

  beforeEach(() => {
    litigeRepository = {
      findOne: jest.fn(),
      findAndCount: jest.fn(),
      save: jest.fn(),
      softRemove: jest.fn(),
      restore: jest.fn(),
    };

    messageRepository = {
      create: jest.fn((payload) => payload),
      save: jest.fn(),
    };

    manager = {
      getRepository: jest.fn((entity) => {
        if (entity === Litige) return litigeRepository;
        if (entity === LitigeMessage) return messageRepository;
        return null;
      }),
      query: jest.fn(),
    };

    dataSource = {
      transaction: jest.fn(async (callback) => callback(manager)),
    } as Partial<DataSource>;

    service = new LitigesService(
      dataSource as DataSource,
      litigeRepository,
      messageRepository,
    );
  });

  it('rejette une transition de statut invalide', async () => {
    const currentLitige = {
      id: 1,
      statut: LitigeStatut.OUVERT,
      assigne: null,
    } as Litige;

    litigeRepository.findOne.mockResolvedValueOnce(currentLitige);

    await expect(
      service.update(1, { statut: LitigeStatut.RESOLU }, 99),
    ).rejects.toBeInstanceOf(BadRequestException);
  });

  it('utilise withDeleted dans findAll quand demandé', async () => {
    litigeRepository.findAndCount.mockResolvedValueOnce([[], 0]);

    await service.findAll({ with_deleted: true, page: 1, limit: 20 });

    expect(litigeRepository.findAndCount).toHaveBeenCalledWith(
      expect.objectContaining({ withDeleted: true }),
    );
  });

  it('soft delete un litige dans remove', async () => {
    const litige = {
      id: 1,
      statut: LitigeStatut.OUVERT,
      type: LitigeType.AUTRE,
      priorite: LitigePriorite.NORMALE,
    } as Litige;

    jest.spyOn(service, 'findOne').mockResolvedValueOnce(litige);
    litigeRepository.softRemove.mockResolvedValueOnce(undefined);

    await service.remove(1);

    expect(litigeRepository.softRemove).toHaveBeenCalledWith(litige);
  });

  it('restaure un litige supprimé', async () => {
    litigeRepository.findOne.mockResolvedValueOnce({
      id: 7,
      deleted_at: new Date(),
    });
    litigeRepository.restore.mockResolvedValueOnce(undefined);
    jest.spyOn(service, 'findOne').mockResolvedValueOnce({ id: 7 } as Litige);

    const result = await service.restore(7);

    expect(litigeRepository.restore).toHaveBeenCalledWith(7);
    expect(result).toEqual(expect.objectContaining({ id: 7 }));
  });

  it('lève NotFound sur restore si introuvable', async () => {
    litigeRepository.findOne.mockResolvedValueOnce(null);

    await expect(service.restore(404)).rejects.toBeInstanceOf(
      NotFoundException,
    );
  });
});
