/**
 * Utilitaires de calculs (factures, paiements)
 */

/**
 * Calcule le total d'une ligne marchandise
 */
/**
 * Formule LBP : Total ligne = Poids (kg) × Prix Unitaire (FCFA/kg) + frais annexes
 */
export function calculerTotalLigneMarchandise(
  prixUnit: any,
  poids: any,
  prixEmballage: any = 0,
  prixAssurance: any = 0,
  prixAgence: any = 0
): number {
  const pUnit = Number(prixUnit || 0)
  const pds = Number(poids || 0)
  const pEmb = Number(prixEmballage || 0)
  const pAss = Number(prixAssurance || 0)
  const pAg = Number(prixAgence || 0)

  const totalUnitaire = pUnit * pds
  return totalUnitaire + pEmb + pAss
}

/**
 * Calcule le total d'un tableau de marchandises
 */
export function calculerTotalMarchandises(
  marchandises: Array<{
    prix_unit: number
    poids_total: number
    prix_emballage?: number
    prix_assurance?: number
    prix_agence?: number
  }>
): number {
  return marchandises.reduce((total, marchandise) => {
    return (
      total +
      calculerTotalLigneMarchandise(
        marchandise.prix_unit,
        marchandise.poids_total,
        marchandise.prix_emballage || 0,
        marchandise.prix_assurance || 0,
        marchandise.prix_agence || 0
      )
    )
  }, 0)
}

/**
 * Calcule la TVA sur un montant HT
 */
export function calculerTVA(montantHT: number, tauxTVA: number = 0): number {
  return montantHT * (tauxTVA / 100)
}

/**
 * Calcule le montant TTC à partir du HT et de la TVA
 */
export function calculerTTC(montantHT: number, tauxTVA: number = 0): number {
  return montantHT + calculerTVA(montantHT, tauxTVA)
}

/**
 * Calcule le montant restant à payer
 */
export function calculerRestantAPayer(montantTotal: number, montantPaye: number): number {
  const restant = montantTotal - montantPaye
  return restant > 0 ? restant : 0
}

/**
 * Calcule la monnaie rendue pour un paiement comptant
 */
export function calculerMonnaieRendue(montantDu: number, montantRecu: number): number {
  const monnaie = montantRecu - montantDu
  return monnaie > 0 ? monnaie : 0
}

/**
 * Arrondit un montant (au supérieur si nécessaire)
 */
export function arrondirMontant(montant: number, decimales: number = 0): number {
  return Math.round(montant * Math.pow(10, decimales)) / Math.pow(10, decimales)
}

/**
 * Vérifie si un paiement est complet
 */
export function isPaiementComplet(montantTotal: number, montantPaye: number, tolerance: number = 0.01): boolean {
  return montantPaye >= montantTotal - tolerance
}
