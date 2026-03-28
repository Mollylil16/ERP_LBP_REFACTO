import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Between } from 'typeorm';
import { Colis } from '../colis/entities/colis.entity';

@Injectable()
export class RapportsService {
  constructor(
    @InjectRepository(Colis)
    private colisRepository: Repository<Colis>,
  ) {}

  async generateRapportColis(params: any): Promise<Colis[]> {
    const {
      start_date,
      end_date,
      trafic_envoi,
      mode_envoi,
      forme_envoi,
      client_id,
    } = params;
    const where: any = {};

    if (start_date && end_date) {
      where.date_envoi = Between(new Date(start_date), new Date(end_date));
    }
    if (trafic_envoi) where.trafic_envoi = trafic_envoi;
    if (mode_envoi) where.mode_envoi = mode_envoi;
    if (forme_envoi) where.forme_envoi = forme_envoi;
    if (client_id) where.client = { id: client_id };

    return this.colisRepository.find({
      where,
      relations: ['client', 'marchandises'],
      order: { date_envoi: 'DESC' },
    });
  }

  async exportExcel(params: any): Promise<Buffer> {
    // Mocking Excel export
    return Buffer.from(
      'Mock Excel Content for period ' +
        params.start_date +
        ' to ' +
        params.end_date,
    );
  }

  async exportPDF(params: any): Promise<Buffer> {
    // Mocking PDF export
    return Buffer.from(
      'Mock PDF Content for period ' +
        params.start_date +
        ' to ' +
        params.end_date,
    );
  }

  async getFinancesParTarif(): Promise<any[]> {
    const result = await this.colisRepository
      .createQueryBuilder('colis')
      .leftJoin('colis.marchandises', 'm')
      .leftJoin('m.tarif', 't')
      .select([
        'm.prix_unit as tarif',
        't.nom as nom_tarif',
        'SUM(m.nbre_colis * m.prix_unit) as revenu_total',
        'SUM(m.nbre_colis * m.cout_reel) as cout_total',
        'SUM(m.nbre_colis * m.charges_reelles) as charges_totales',
        'SUM(m.nbre_colis * (m.prix_unit - m.cout_reel - m.charges_reelles)) as benefice_total',
        'SUM(m.poids_total) as poids_total',
      ])
      .groupBy('m.prix_unit')
      .addGroupBy('t.nom')
      .getRawMany();

    return result.map((item) => ({
      tarif: parseFloat(item.tarif),
      nom_tarif: item.nom_tarif || `Tarif ${item.tarif}`,
      revenu_total: parseFloat(item.revenu_total) || 0,
      cout_total: parseFloat(item.cout_total) || 0,
      charges_totales: parseFloat(item.charges_totales) || 0,
      benefice_total: parseFloat(item.benefice_total) || 0,
      poids_total: parseFloat(item.poids_total) || 0,
    }));
  }
}
