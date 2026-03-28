import { PartialType } from '@nestjs/mapped-types';
import { CreateProduitCatalogueDto } from './create-produit-catalogue.dto';

export class UpdateProduitCatalogueDto extends PartialType(
  CreateProduitCatalogueDto,
) {}
