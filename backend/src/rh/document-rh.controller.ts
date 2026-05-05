import {
  Body, Controller, Delete, Get, Param, ParseIntPipe,
  Post, Request, UploadedFile, UseGuards, UseInterceptors,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { ApiBearerAuth, ApiConsumes, ApiOperation, ApiTags } from '@nestjs/swagger';
import { diskStorage } from 'multer';
import { extname, join } from 'path';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { PermissionsGuard } from '../auth/guards/permissions.guard';
import { RequirePermission } from '../auth/decorators/permissions.decorator';
import { DocumentRhService } from './document-rh.service';
import { TypeDocumentRh } from './entities/rh-document.entity';
import { RhService } from './rh.service';
import { RhEmploye } from './entities/rh-employe.entity';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';

const storageConfig = diskStorage({
  destination: join(process.cwd(), 'uploads', 'rh-documents'),
  filename: (req, file, cb) => {
    const unique = Date.now() + '-' + Math.round(Math.random() * 1e6);
    cb(null, unique + extname(file.originalname));
  },
});

@ApiTags('rh-documents')
@ApiBearerAuth()
@UseGuards(JwtAuthGuard, PermissionsGuard)
@Controller('rh/documents')
export class DocumentRhController {
  constructor(
    private readonly documentService: DocumentRhService,
    @InjectRepository(RhEmploye) private employeRepo: Repository<RhEmploye>,
  ) {}

  @Get(':employeId')
  @RequirePermission('rh.documents.read')
  @ApiOperation({ summary: 'Documents d\'un employé (coffre-fort)' })
  getDocuments(@Param('employeId', ParseIntPipe) employeId: number) {
    return this.documentService.getDocumentsEmploye(employeId);
  }

  @Post(':employeId/upload')
  @RequirePermission('rh.documents.create')
  @ApiOperation({ summary: 'Téléverser un document dans le coffre-fort' })
  @ApiConsumes('multipart/form-data')
  @UseInterceptors(FileInterceptor('file', { storage: storageConfig }))
  async uploadDocument(
    @Param('employeId', ParseIntPipe) employeId: number,
    @UploadedFile() file: Express.Multer.File,
    @Body() body: { type?: TypeDocumentRh; description?: string; date_expiration?: string },
    @Request() req: { user: { id: number } },
  ) {
    return this.documentService.saveDocument(
      employeId,
      file,
      body.type ?? TypeDocumentRh.AUTRE,
      body.description,
      body.date_expiration,
      req.user.id,
    );
  }

  @Delete(':id')
  @RequirePermission('rh.documents.delete')
  @ApiOperation({ summary: 'Supprimer un document' })
  async deleteDocument(@Param('id', ParseIntPipe) id: number) {
    await this.documentService.deleteDocument(id);
    return { ok: true };
  }

  // Import CSV employés
  @Post('import/employes')
  @RequirePermission('rh.employes.create')
  @ApiOperation({ summary: 'Import CSV/Excel des employés' })
  @UseInterceptors(FileInterceptor('file', { storage: storageConfig }))
  async importEmployes(
    @UploadedFile() file: Express.Multer.File,
  ) {
    // Parse CSV simple (séparé par virgule ou point-virgule)
    const content = require('fs').readFileSync(file.path, 'utf-8');
    const lines = content.split('\n').map((l: string) => l.trim()).filter(Boolean);
    if (lines.length < 2) return { imported: 0, errors: ['Fichier vide'] };

    const separator = lines[0].includes(';') ? ';' : ',';
    const headers = lines[0].split(separator).map((h: string) => h.trim().replace(/"/g, ''));
    const results: { ok: number; errors: string[] } = { ok: 0, errors: [] };

    for (let i = 1; i < lines.length; i++) {
      const vals = lines[i].split(separator).map((v: string) => v.trim().replace(/"/g, ''));
      const row: Record<string, string> = {};
      headers.forEach((h: string, idx: number) => { row[h] = vals[idx] ?? ''; });

      const nom = (row['NOM'] ?? row['nom'] ?? '').trim();
      const prenoms = (row['PRENOMS'] ?? row['prenoms'] ?? '').trim();
      const date_embauche = row['DATE_EMBAUCHE'] ?? row['date_embauche'];

      if (!nom || !prenoms || !date_embauche) {
        results.errors.push(`Ligne ${i + 1} : NOM, PRENOMS, DATE_EMBAUCHE obligatoires`);
        continue;
      }

      try {
        const last = await this.employeRepo.createQueryBuilder('e').orderBy('e.id', 'DESC').getOne();
        const seq = (last?.id ?? 0) + 1;
        const matricule = `LBP-RH-${String(seq).padStart(4, '0')}`;

        await this.employeRepo.save(this.employeRepo.create({
          matricule,
          nom: nom.toUpperCase(),
          prenoms,
          date_embauche,
          intitule_poste: row['POSTE'] ?? row['intitule_poste'] ?? null,
          categorie: row['CATEGORIE'] ?? row['categorie'] ?? null,
          departement: row['DEPARTEMENT'] ?? row['departement'] ?? null,
          telephone: row['TELEPHONE'] ?? row['telephone'] ?? null,
          email_pro: row['EMAIL'] ?? row['email_pro'] ?? null,
          numero_cnps: row['CNPS'] ?? row['numero_cnps'] ?? null,
          numero_cni: row['CNI'] ?? row['numero_cni'] ?? null,
        }));
        results.ok++;
      } catch (e) {
        results.errors.push(`Ligne ${i + 1} : ${(e as Error).message}`);
      }
    }

    // Supprimer le fichier temporaire
    require('fs').unlinkSync(file.path);
    return results;
  }
}
