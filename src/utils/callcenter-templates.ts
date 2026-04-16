/** Variables : {{nom_client}}, {{tel_client}}, {{ref_colis}}, {{num_facture}} */

export type CallcenterTemplateVars = {
  nom_client?: string
  tel_client?: string
  ref_colis?: string
  num_facture?: string
}

export const CALLCENTER_MESSAGE_TEMPLATES: {
  id: string
  label: string
  body: string
}[] = [
  {
    id: 'accuse',
    label: 'Accusé de réception',
    body: 'Bonjour {{nom_client}}, nous avons bien reçu votre message et traitons votre demande. Merci de votre patience.',
  },
  {
    id: 'infos',
    label: 'Demande d’informations',
    body: 'Bonjour {{nom_client}}, pour avancer sur votre dossier (réf. colis {{ref_colis}}), pourriez-vous nous préciser votre numéro de facture ou tout élément manquant ? Merci.',
  },
  {
    id: 'delai',
    label: 'Délai de traitement',
    body: 'Bonjour {{nom_client}}, le traitement de votre demande est en cours. Nous revenons vers vous dans les meilleurs délais.',
  },
  {
    id: 'agence',
    label: 'Passage en agence',
    body: 'Bonjour {{nom_client}}, si besoin vous pouvez vous rapprocher de votre agence habituelle avec une pièce d’identité. Bonne journée.',
  },
  {
    id: 'rdv',
    label: 'Proposition de RDV',
    body: 'Bonjour {{nom_client}}, souhaitez-vous que nous vous rappelions à un créneau précis ? Indiquez-nous vos disponibilités (joignable au {{tel_client}}).',
  },
  {
    id: 'relance_impaye',
    label: 'Relance facture impayée',
    body: 'Bonjour {{nom_client}}, nous vous informons qu’une facture reste due ({{num_facture}}, colis {{ref_colis}}). Merci de régulariser ou de nous contacter en cas de difficulté.',
  },
]

export function applyCallcenterTemplate(
  body: string,
  vars: CallcenterTemplateVars,
): string {
  let out = body
  const map: Record<string, string> = {
    nom_client: vars.nom_client?.trim() || 'client',
    tel_client: vars.tel_client?.trim() || '—',
    ref_colis: vars.ref_colis?.trim() || '—',
    num_facture: vars.num_facture?.trim() || '—',
  }
  for (const [k, v] of Object.entries(map)) {
    out = out.split(`{{${k}}}`).join(v)
  }
  return out
}
