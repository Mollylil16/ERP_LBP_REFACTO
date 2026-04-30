import { Test, TestingModule } from '@nestjs/testing';
import { FacturesService } from './factures.service';
import { getRepositoryToken } from '@nestjs/typeorm';
import { Facture } from './entities/facture.entity';
import { Colis } from '../colis/entities/colis.entity';
import { LienPaiement } from '../paiements/entities/lien-paiement.entity';
import { DataSource } from 'typeorm';
import { NotFoundException, BadRequestException } from '@nestjs/common';
import { BusinessAuditService } from '../audit/business-audit.service';
import { CreditsColisService } from '../exploitation/credits-colis.service';

describe('FacturesService', () => {
  let service: FacturesService;
  let factureRepo;
  let colisRepo;

  const mockFactureRepo = {
    create: jest.fn(),
    save: jest.fn(),
    find: jest.fn(),
    findOne: jest.fn(),
    createQueryBuilder: jest.fn(),
  };

  const mockColisRepo = {
    findOne: jest.fn(),
  };

  const mockLienRepo = {
    findOne: jest.fn(),
  };

  const mockBusinessAudit = {
    logEvent: jest.fn(),
  };

  const mockCreditsColisService = {
    ensureCreditAfterFactureValidated: jest.fn().mockResolvedValue(null),
  };

  const mockDataSource = {
    // Mock datasource methods if needed
  };

  beforeEach(async () => {
    jest.clearAllMocks();
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        FacturesService,
        { provide: getRepositoryToken(Facture), useValue: mockFactureRepo },
        { provide: getRepositoryToken(Colis), useValue: mockColisRepo },
        { provide: getRepositoryToken(LienPaiement), useValue: mockLienRepo },
        { provide: DataSource, useValue: mockDataSource },
        { provide: BusinessAuditService, useValue: mockBusinessAudit },
        {
          provide: CreditsColisService,
          useValue: mockCreditsColisService,
        },
      ],
    }).compile();

    service = module.get<FacturesService>(FacturesService);
    factureRepo = module.get(getRepositoryToken(Facture));
    colisRepo = module.get(getRepositoryToken(Colis));
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('createProforma', () => {
    it('should calculate montant_ht correctly from marchandises', async () => {
      const mockColis = {
        id: 1,
        marchandises: [
          {
            nbre_colis: 2,
            poids_total: 2,
            prix_unit: 1000,
            prix_emballage: 50,
            prix_assurance: 20,
            prix_agence: 30,
          },
          {
            nbre_colis: 1,
            poids_total: 1,
            prix_unit: 500,
            prix_emballage: 0,
            prix_assurance: 10,
            prix_agence: 0,
          },
        ],
      } as any;

      // Total Item 1: 2 * 1000 + 50 + 20 = 2070 (prix_agence exclu du total)
      // Total Item 2: 1 * 500 + 0 + 10 = 510
      // Expected Total Sum = 2580

      // Mock generateReference (used inside createProforma)
      mockFactureRepo.createQueryBuilder.mockReturnValue({
        where: jest.fn().mockReturnThis(),
        orderBy: jest.fn().mockReturnThis(),
        getOne: jest.fn().mockResolvedValue(null),
      });

      mockFactureRepo.create.mockImplementation((dto) => dto);
      mockFactureRepo.save.mockImplementation((facture) =>
        Promise.resolve({ id: 100, ...facture }),
      );

      const result = await service.createProforma(mockColis, 'user-1');

      expect(result.montant_ht).toBe(2580);
      expect(result.etat).toBe(0); // Proforma
      expect(result.num_facture).toMatch(/^FCO-\d{4}-001$/);
    });
  });

  describe('validateProforma', () => {
    it('should update etat to 1 (Définitive)', async () => {
      const mockFacture = { id: 1, etat: 0 } as Facture;
      mockFactureRepo.findOne.mockResolvedValue(mockFacture);
      mockFactureRepo.save.mockImplementation((f) => Promise.resolve(f));

      const result = await service.validateProforma(1);
      expect(result.etat).toBe(1);
      expect(mockFactureRepo.save).toHaveBeenCalled();
      expect(
        mockCreditsColisService.ensureCreditAfterFactureValidated,
      ).toHaveBeenCalled();
    });

    it('should throw NotFoundException if facture does not exist', async () => {
      mockFactureRepo.findOne.mockResolvedValue(null);
      await expect(service.validateProforma(999)).rejects.toThrow(
        NotFoundException,
      );
    });
  });

  describe('generateFromColis', () => {
    it('should throw BadRequestException if facture already exists for colis', async () => {
      mockColisRepo.findOne.mockResolvedValue({ id: 1 });
      mockFactureRepo.findOne.mockResolvedValue({
        id: 10,
        num_facture: 'EXISTS',
      });

      await expect(service.generateFromColis(1, 'user-1')).rejects.toThrow(
        BadRequestException,
      );
    });

    it('should throw NotFoundException if colis does not exist', async () => {
      mockColisRepo.findOne.mockResolvedValue(null);
      await expect(service.generateFromColis(999, 'user-1')).rejects.toThrow(
        NotFoundException,
      );
    });
  });
});
