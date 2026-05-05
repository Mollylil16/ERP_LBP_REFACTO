import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { RhDocument, TypeDocumentRh } from './entities/rh-document.entity';
import * as path from 'path';
import * as fs from 'fs';

@Injectable()
export class DocumentRhService {
  constructor(
    @InjectRepository(RhDocument) private docRepo: Repository<RhDocument>,
  ) {}

  private getUploadPath(): string {
    const dir = path.join(process.cwd(), 'uploads', 'rh-documents');
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    return dir;
  }

  async getDocumentsEmploye(id_employe: number): Promise<RhDocument[]> {
    return this.docRepo.find({
      where: { id_employe },
      order: { created_at: 'DESC' },
      relations: ['uploader'],
    });
  }

  async saveDocument(
    id_employe: number,
    file: Express.Multer.File,
    type: TypeDocumentRh,
    description?: string,
    date_expiration?: string,
    uploaderId?: number,
  ): Promise<RhDocument> {
    const doc = this.docRepo.create({
      id_employe,
      type,
      nom_fichier: file.originalname,
      url_fichier: `/uploads/rh-documents/${file.filename}`,
      taille_octets: file.size,
      mime_type: file.mimetype,
      description: description ?? null,
      date_expiration: date_expiration ?? null,
      id_uploader: uploaderId ?? null,
    });
    return this.docRepo.save(doc);
  }

  async deleteDocument(id: number): Promise<void> {
    const doc = await this.docRepo.findOne({ where: { id } });
    if (!doc) throw new NotFoundException('Document introuvable');
    const filePath = path.join(process.cwd(), doc.url_fichier);
    if (fs.existsSync(filePath)) fs.unlinkSync(filePath);
    await this.docRepo.delete(id);
  }

  getFilePath(url: string): string {
    return path.join(process.cwd(), url);
  }

  async saveGeneratedPdf(
    id_employe: number,
    buffer: Buffer,
    nom: string,
    type: TypeDocumentRh,
  ): Promise<RhDocument> {
    const dir = this.getUploadPath();
    const filename = `${Date.now()}-${nom}.pdf`;
    fs.writeFileSync(path.join(dir, filename), buffer);

    const doc = this.docRepo.create({
      id_employe,
      type,
      nom_fichier: `${nom}.pdf`,
      url_fichier: `/uploads/rh-documents/${filename}`,
      taille_octets: buffer.length,
      mime_type: 'application/pdf',
    });
    return this.docRepo.save(doc);
  }
}
