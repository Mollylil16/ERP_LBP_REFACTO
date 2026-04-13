import { IsInt, Min } from 'class-validator';

export class AjustStockArticleDto {
  /** Nouveau stock absolu (inventaire / saisie agent exploitation). */
  @IsInt()
  @Min(0)
  quantite_stock: number;
}
