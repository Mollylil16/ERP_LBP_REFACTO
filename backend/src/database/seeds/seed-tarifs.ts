import { DataSource } from 'typeorm';
import { Tarif } from '../../tarifs/entities/tarif.entity';

export async function seedTarifs(dataSource: DataSource) {
  const tarifRepository = dataSource.getRepository(Tarif);

  // Check if tariffs already exist
  const count = await tarifRepository.count();
  if (count > 0) {
    console.log('✅ Tarifs already seeded');
    return;
  }

  const tarifs = [
    {
      nom: 'Aérien Express',
      prix_vente_conseille: 6000,
      cout_transport_kg: 4500,
      charges_fixes_unit: 500,
    },
    {
      nom: 'Aérien Classique',
      prix_vente_conseille: 4500,
      cout_transport_kg: 3000,
      charges_fixes_unit: 500,
    },
    {
      nom: 'Maritime Groupage',
      prix_vente_conseille: 2500,
      cout_transport_kg: 1200,
      charges_fixes_unit: 300,
    },
  ];

  await tarifRepository.save(tarifs);
  console.log(`✅ ${tarifs.length} tarifs insérés avec succès`);
}
