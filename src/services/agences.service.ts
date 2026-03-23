import { apiService } from './api.service';
import { Agency } from '@types';

export interface AgenceStats {
    id: number;
    name: string;
    total_colis: number;
    total_montant: number;
}

class AgencesService {
    async getAll(): Promise<Agency[]> {
        return apiService.get('/agences');
    }

    async getOne(id: number): Promise<Agency> {
        return apiService.get(`/agences/${id}`);
    }

    async create(data: any): Promise<Agency> {
        return apiService.post('/agences', data);
    }

    async update(id: number, data: any): Promise<Agency> {
        return apiService.patch(`/agences/${id}`, data);
    }

    async delete(id: number): Promise<void> {
        return apiService.delete(`/agences/${id}`);
    }

    async getStats(): Promise<AgenceStats[]> {
        return apiService.get('/agences/stats');
    }
}

export const agencesService = new AgencesService();
