import { apiService } from './api.service'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface RhDashboard {
  effectif_total: number
  effectif_actif: number
  cdd_actifs: number
  conges_en_attente: number
  alertes_cdd: Array<{ id: number; matricule: string; nom: string; prenoms: string; date_fin: string; jours_restants: number }>
  par_agence: Array<{ agence_nom: string; n: number }>
  par_statut: Record<string, number>
  par_type_contrat: Record<string, number>
}

export interface RhEmploye {
  id: number
  matricule: string
  nom: string
  prenoms: string
  date_naissance: string | null
  lieu_naissance: string | null
  nationalite: string | null
  sexe: 'M' | 'F' | null
  situation_familiale: string | null
  nb_enfants: number
  numero_cni: string | null
  numero_cnps: string | null
  adresse: string | null
  telephone: string | null
  email_pro: string | null
  email_perso: string | null
  date_embauche: string
  date_sortie: string | null
  intitule_poste: string | null
  categorie: string | null
  grade: string | null
  departement: string | null
  service: string | null
  type_contrat_actuel: 'CDI' | 'CDD' | 'STAGE' | 'INTERIM'
  statut: 'actif' | 'suspendu' | 'sorti'
  id_agence: number | null
  agence?: { id: number; nom: string } | null
}

export interface RhContrat {
  id: number
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  type_contrat: 'CDI' | 'CDD' | 'STAGE' | 'INTERIM'
  date_debut: string
  date_fin: string | null
  periode_essai_debut: string | null
  periode_essai_fin: string | null
  intitule_poste: string | null
  salaire_base: number | null
  statut: 'actif' | 'termine' | 'resilie' | 'essai'
  motif_fin: string | null
  notes: string | null
  alerte_envoyee_jours: number
  created_at: string
}

export interface RhCongeType {
  id: number
  code: string
  libelle: string
  jours_par_an: number
  est_paye: boolean
  necessite_justificatif: boolean
  description: string | null
  est_actif: boolean
}

export interface RhCongeRequest {
  id: number
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  id_conge_type: number
  type_conge?: RhCongeType
  date_debut: string
  date_fin: string
  nb_jours: number
  motif: string | null
  statut: 'en_attente' | 'approuve_manager' | 'approuve' | 'refuse' | 'annule'
  commentaire_manager: string | null
  commentaire_rh: string | null
  date_validation_rh: string | null
  created_at: string
}

export interface RhCongeBalance {
  id: number
  id_employe: number
  id_conge_type: number
  type_conge?: RhCongeType
  annee: number
  jours_acquis: number
  jours_pris: number
  jours_restants: number
}

// ── API functions ─────────────────────────────────────────────────────────────

export const rhService = {
  getDashboard: (): Promise<RhDashboard> =>
    apiService.get('/rh/dashboard'),

  // Employés
  getEmployes: (search?: string, statut?: string): Promise<RhEmploye[]> => {
    const params = new URLSearchParams()
    if (search) params.set('search', search)
    if (statut) params.set('statut', statut)
    return apiService.get(`/rh/employes${params.toString() ? '?' + params.toString() : ''}`)
  },

  getEmploye: (id: number): Promise<RhEmploye> =>
    apiService.get(`/rh/employes/${id}`),

  createEmploye: (data: Partial<RhEmploye>): Promise<RhEmploye> =>
    apiService.post('/rh/employes', data),

  updateEmploye: (id: number, data: Partial<RhEmploye>): Promise<RhEmploye> =>
    apiService.patch(`/rh/employes/${id}`, data),

  sortirEmploye: (id: number, date_sortie: string, motif?: string): Promise<RhEmploye> =>
    apiService.patch(`/rh/employes/${id}/sortie`, { date_sortie, motif }),

  // Contrats
  getContrats: (statut?: string, type?: string): Promise<RhContrat[]> => {
    const params = new URLSearchParams()
    if (statut) params.set('statut', statut)
    if (type) params.set('type', type)
    return apiService.get(`/rh/contrats${params.toString() ? '?' + params.toString() : ''}`)
  },

  getCddExpirants: (jours = 30): Promise<RhContrat[]> =>
    apiService.get(`/rh/contrats/alertes/cdd?jours=${jours}`),

  getContratsEmploye: (id: number): Promise<RhContrat[]> =>
    apiService.get(`/rh/employes/${id}/contrats`),

  createContrat: (data: Partial<RhContrat>): Promise<RhContrat> =>
    apiService.post('/rh/contrats', data),

  // Types de congé
  getCongeTypes: (): Promise<RhCongeType[]> =>
    apiService.get('/rh/conge-types'),

  // Demandes de congé
  getConges: (statut?: string): Promise<RhCongeRequest[]> => {
    const params = new URLSearchParams()
    if (statut) params.set('statut', statut)
    return apiService.get(`/rh/conges${params.toString() ? '?' + params.toString() : ''}`)
  },

  getCongesEmploye: (id: number): Promise<RhCongeRequest[]> =>
    apiService.get(`/rh/employes/${id}/conges`),

  getSoldesConges: (id: number, annee?: number): Promise<RhCongeBalance[]> => {
    const params = annee ? `?annee=${annee}` : ''
    return apiService.get(`/rh/employes/${id}/soldes-conges${params}`)
  },

  createConge: (data: Partial<RhCongeRequest>): Promise<RhCongeRequest> =>
    apiService.post('/rh/conges', data),

  validerConge: (id: number, approuve: boolean, commentaire?: string): Promise<RhCongeRequest> =>
    apiService.patch(`/rh/conges/${id}/valider?approuve=${approuve}`, { commentaire }),

  // ── Rapports ────────────────────────────────────────────────────────────────

  getBilanSocial: (annee: number): Promise<RhBilanSocial> =>
    apiService.get(`/rh/rapports/bilan-social?annee=${annee}`),

  getEtatCnps: (periode: string): Promise<RhEtatCnps> =>
    apiService.get(`/rh/rapports/cnps?periode=${periode}`),

  getEtatIts: (periode: string): Promise<RhEtatIts> =>
    apiService.get(`/rh/rapports/its?periode=${periode}`),

  getDeclarationMO: (annee: number): Promise<RhDeclarationMO> =>
    apiService.get(`/rh/rapports/declaration-mo?annee=${annee}`),

  getHeuresSup: (periode: string): Promise<RhRapportHeursSup> =>
    apiService.get(`/rh/rapports/heures-sup?periode=${periode}`),

  getPdfUrl: (path: string): string =>
    `/api${path}`,

  // ── Documents (coffre-fort) ──────────────────────────────────────────────

  getDocuments: (employeId: number): Promise<RhDocumentCoffre[]> =>
    apiService.get(`/rh/documents/${employeId}`),

  deleteDocument: (id: number): Promise<{ ok: boolean }> =>
    apiService.delete(`/rh/documents/${id}`),

  // ── Historique postes ────────────────────────────────────────────────────

  getHistoriquePoste: (employeId: number): Promise<RhHistoriquePoste[]> =>
    apiService.get(`/rh/employes/${employeId}/historique-postes`),

  // ── Paie ────────────────────────────────────────────────────────────────────

  getPaieConfig: (periode?: string): Promise<RhConfigPaie> => {
    const q = periode ? `?periode=${periode}` : ''
    return apiService.get(`/rh/paie/config${q}`)
  },

  upsertPaieConfig: (data: Partial<RhConfigPaie>): Promise<RhConfigPaie> =>
    apiService.post('/rh/paie/config', data),

  getPaieRuns: (): Promise<RhPaieRun[]> =>
    apiService.get('/rh/paie/runs'),

  createPaieRun: (periode: string): Promise<RhPaieRun> =>
    apiService.post('/rh/paie/runs', { periode }),

  calculerPaieRun: (id: number): Promise<RhPaieRun> =>
    apiService.post(`/rh/paie/runs/${id}/calculer`, {}),

  validerPaieRun: (id: number, role: 'rh' | 'daf'): Promise<RhPaieRun> =>
    apiService.patch(`/rh/paie/runs/${id}/valider?role=${role}`, {}),

  getPaieRunDetail: (id: number): Promise<RhPaieRun & { lignes: RhPaieLigne[] }> =>
    apiService.get(`/rh/paie/runs/${id}`),

  getBulletinsEmploye: (id: number): Promise<RhPaieLigne[]> =>
    apiService.get(`/rh/paie/bulletins/${id}`),

  getAvances: (employeId?: number): Promise<RhAvanceSalaire[]> => {
    const q = employeId ? `?employe_id=${employeId}` : ''
    return apiService.get(`/rh/paie/avances${q}`)
  },

  createAvance: (data: { id_employe: number; montant: number; mois_deduction: string; motif?: string }): Promise<RhAvanceSalaire> =>
    apiService.post('/rh/paie/avances', data),

  approuverAvance: (id: number, approuve: boolean): Promise<RhAvanceSalaire> =>
    apiService.patch(`/rh/paie/avances/${id}/approuver?approuve=${approuve}`, {}),

  getMasseSalariale: (): Promise<RhMasseSalariale[]> =>
    apiService.get('/rh/paie/masse-salariale'),

  // ── Présences ───────────────────────────────────────────────────────────────

  getPresences: (employeId?: number, dateDebut?: string, dateFin?: string): Promise<RhPresence[]> => {
    const params = new URLSearchParams()
    if (employeId) params.set('employe_id', String(employeId))
    if (dateDebut) params.set('date_debut', dateDebut)
    if (dateFin) params.set('date_fin', dateFin)
    return apiService.get(`/rh/presences${params.toString() ? '?' + params.toString() : ''}`)
  },

  saisirPresence: (data: Partial<RhPresence>): Promise<RhPresence> =>
    apiService.post('/rh/presences', data),

  validerPresence: (id: number): Promise<RhPresence> =>
    apiService.patch(`/rh/presences/${id}/valider`, {}),

  getStatsMensuellesEmploye: (employeId: number, periode: string): Promise<RhStatsMensuelles> =>
    apiService.get(`/rh/presences/stats/${employeId}?periode=${periode}`),

  getJoursFeries: (annee: number): Promise<RhJourFerie[]> =>
    apiService.get(`/rh/presences/feries?annee=${annee}`),

  seedJoursFeries: (annee: number): Promise<number> =>
    apiService.post('/rh/presences/feries/seed', { annee }),

  // ── Evaluations ─────────────────────────────────────────────────────────────

  getEvaluations: (employeId?: number, statut?: string): Promise<RhEvaluation[]> => {
    const params = new URLSearchParams()
    if (employeId) params.set('employe_id', String(employeId))
    if (statut) params.set('statut', statut)
    return apiService.get(`/rh/evaluations${params.toString() ? '?' + params.toString() : ''}`)
  },

  createEvaluation: (data: Partial<RhEvaluation>): Promise<RhEvaluation> =>
    apiService.post('/rh/evaluations', data),

  updateEvaluation: (id: number, data: Partial<RhEvaluation>): Promise<RhEvaluation> =>
    apiService.patch(`/rh/evaluations/${id}`, data),

  validerEvaluation: (id: number, etape: 'evalue' | 'evaluateur' | 'rh'): Promise<RhEvaluation> =>
    apiService.patch(`/rh/evaluations/${id}/valider?etape=${etape}`, {}),

  getDashboardEval: (): Promise<RhDashboardEval> =>
    apiService.get('/rh/evaluations/dashboard'),

  // ── Recrutement ─────────────────────────────────────────────────────────────

  getPostes: (statut?: string): Promise<RhPoste[]> => {
    const q = statut ? `?statut=${statut}` : ''
    return apiService.get(`/rh/recrutement/postes${q}`)
  },

  createPoste: (data: Partial<RhPoste>): Promise<RhPoste> =>
    apiService.post('/rh/recrutement/postes', data),

  updatePoste: (id: number, data: Partial<RhPoste>): Promise<RhPoste> =>
    apiService.patch(`/rh/recrutement/postes/${id}`, data),

  getCandidatures: (posteId?: number, statut?: string): Promise<RhCandidature[]> => {
    const params = new URLSearchParams()
    if (posteId) params.set('poste_id', String(posteId))
    if (statut) params.set('statut', statut)
    return apiService.get(`/rh/recrutement/candidatures${params.toString() ? '?' + params.toString() : ''}`)
  },

  createCandidature: (data: Partial<RhCandidature>): Promise<RhCandidature> =>
    apiService.post('/rh/recrutement/candidatures', data),

  updateStatutCandidature: (id: number, statut: string, notes?: string, note_entretien?: number, date_entretien?: string): Promise<RhCandidature> =>
    apiService.patch(`/rh/recrutement/candidatures/${id}/statut`, { statut, notes, note_entretien, date_entretien }),

  getDashboardRecrutement: (): Promise<RhDashboardRecrutement> =>
    apiService.get('/rh/recrutement/dashboard'),

  // ── Formation ───────────────────────────────────────────────────────────────

  getFormations: (annee?: number): Promise<RhFormation[]> => {
    const q = annee ? `?annee=${annee}` : ''
    return apiService.get(`/rh/formation${q}`)
  },

  createFormation: (data: Partial<RhFormation>): Promise<RhFormation> =>
    apiService.post('/rh/formation', data),

  updateFormation: (id: number, data: Partial<RhFormation>): Promise<RhFormation> =>
    apiService.patch(`/rh/formation/${id}`, data),

  getInscriptions: (formationId?: number, employeId?: number): Promise<RhInscription[]> => {
    const params = new URLSearchParams()
    if (formationId) params.set('formation_id', String(formationId))
    if (employeId) params.set('employe_id', String(employeId))
    return apiService.get(`/rh/formation/inscriptions${params.toString() ? '?' + params.toString() : ''}`)
  },

  inscrire: (data: { id_formation: number; id_employe: number }): Promise<RhInscription> =>
    apiService.post('/rh/formation/inscriptions', data),

  updateInscription: (id: number, data: Partial<RhInscription>): Promise<RhInscription> =>
    apiService.patch(`/rh/formation/inscriptions/${id}`, data),

  getDashboardFormation: (): Promise<RhDashboardFormation> =>
    apiService.get('/rh/formation/dashboard'),
}

// ── Interfaces nouvelles ───────────────────────────────────────────────────────

export interface RhConfigPaie {
  id: number
  annee_mois: string
  smig_mensuel: number
  cnps_retraite_salarial: number
  cnps_retraite_patronal: number
  cnps_retraite_plafond_annuel: number
  cnps_at_patronal: number
  cnps_famille_patronal: number
  cnps_famille_plafond_mensuel: number
  cmu_salarial: number
  cmu_patronal: number
  cn_taux: number
  its_tranches: Array<{ min: number; max: number | null; taux: number }> | null
}

export interface RhPaieRun {
  id: number
  periode: string
  statut: 'brouillon' | 'calcule' | 'valide_rh' | 'valide_daf' | 'cloture'
  total_brut: number
  total_net: number
  total_charges_salariales: number
  total_charges_patronales: number
  nb_employes: number
  notes: string | null
  created_at: string
}

export interface RhPaieLigne {
  id: number
  id_run: number
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  salaire_base: number
  prime_anciennete: number
  prime_transport: number
  heures_sup_montant: number
  autres_primes: number
  prime_performance: number
  salaire_brut: number
  cnps_retraite_salarial: number
  cmu_salarial: number
  its: number
  cn: number
  avances_deduites: number
  absences_deduites: number
  total_deductions_salariales: number
  salaire_net: number
  cnps_retraite_patronal: number
  cnps_at_patronal: number
  cnps_famille_patronal: number
  cmu_patronal: number
  total_charges_patronales: number
  cout_total_employeur: number
  alerte_smig: boolean
}

export interface RhAvanceSalaire {
  id: number
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  montant: number
  mois_deduction: string
  statut: 'en_attente' | 'approuve' | 'refuse' | 'remboursee'
  motif: string | null
  created_at: string
}

export interface RhMasseSalariale {
  periode: string
  total_brut: number
  total_net: number
  nb_employes: number
}

export interface RhPresence {
  id: number
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  date_presence: string
  heure_entree: string | null
  heure_sortie: string | null
  heures_travaillees: number
  heures_sup: number
  retard_minutes: number
  statut: 'present' | 'absent' | 'retard' | 'mission' | 'conge' | 'ferie'
  type_pointage: 'badgeuse' | 'mobile' | 'manuel' | 'biometrie'
  justificatif: string | null
  est_valide: boolean
  created_at: string
}

export interface RhJourFerie {
  id: number
  date: string
  libelle: string
  est_islamique: boolean
  annee: number
}

export interface RhStatsMensuelles {
  jours_travailles: number
  heures_totales: number
  heures_sup_totales: number
  retards: number
  absences: number
}

export interface RhEvaluation {
  id: number
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  id_evaluateur: number | null
  evaluateur?: { id: number; nom: string; prenoms: string } | null
  type: 'annuelle' | 'semestrielle' | 'trimestrielle' | 'fin_essai'
  periode: string
  statut: 'brouillon' | 'en_cours' | 'signe_evalue' | 'signe_evaluateur' | 'valide_rh' | 'cloture'
  score_resultats: number | null
  score_competences_metier: number | null
  score_comportement: number | null
  score_conformite: number | null
  score_developpement: number | null
  note_globale: number | null
  commentaire_evaluateur: string | null
  commentaire_employe: string | null
  plan_developpement: string | null
  metriques_auto: {
    colis_count?: number
    ca_total?: number
    periode?: string
    calcule_le?: string
  } | null
  created_at: string
}

export interface RhDashboardEval {
  en_cours: number
  clotures: number
  moyenne_globale: number
  par_type: Array<{ type: string; nb: number; moyenne: number }>
}

export interface RhPoste {
  id: number
  intitule: string
  departement: string | null
  description: string | null
  competences_requises: string | null
  nb_postes: number
  statut: 'ouvert' | 'en_cours' | 'pourvu' | 'annule'
  id_agence: number | null
  date_limite: string | null
  publication_interne: boolean
  created_at: string
}

export interface RhCandidature {
  id: number
  id_poste: number
  poste?: { id: number; intitule: string }
  nom: string
  prenoms: string
  email: string | null
  telephone: string | null
  statut: 'nouveau' | 'preselectionne' | 'entretien' | 'retenu' | 'refuse' | 'embauche'
  notes_recruteur: string | null
  note_entretien: number | null
  date_entretien: string | null
  created_at: string
}

export interface RhDashboardRecrutement {
  postes_ouverts: number
  candidatures_total: number
  par_statut: Array<{ statut: string; nb: number }>
  par_poste: Array<{ poste: string; nb: number }>
}

export interface RhFormation {
  id: number
  titre: string
  description: string | null
  type: 'presentiel' | 'distanciel' | 'elearning' | 'mixte'
  organisme: string | null
  date_debut: string | null
  date_fin: string | null
  duree_heures: number | null
  cout: number | null
  places_max: number
  est_actif: boolean
  annee_plan: number | null
  created_at: string
}

export interface RhInscription {
  id: number
  id_formation: number
  formation?: { id: number; titre: string }
  id_employe: number
  employe?: { id: number; matricule: string; nom: string; prenoms: string }
  statut: 'en_attente' | 'confirme' | 'termine' | 'annule'
  note_satisfaction: number | null
  commentaire: string | null
  created_at: string
}

export interface RhDashboardFormation {
  formations_planifiees: number
  inscriptions_total: number
  taux_realisation: number
  cout_total: number
}

// ── Interfaces rapports et documents ──────────────────────────────────────────

export interface RhBilanSocial {
  annee: number
  effectif_total: number
  effectif_actif: number
  effectif_sorti: number
  embauches_annee: number
  sorties_annee: number
  par_sexe: Array<{ sexe: string; nb: number }>
  par_type_contrat: Array<{ type: string; nb: number }>
  par_departement: Array<{ departement: string; nb: number }>
  par_agence: Array<{ agence: string; nb: number }>
  masse_salariale_brute: number
  masse_salariale_nette: number
  taux_absenteisme: number
  taux_turnover: number
}

export interface RhEtatCnps {
  periode: string
  run_statut: string | null
  lignes: Array<{
    matricule: string; nom: string; numero_cnps: string
    salaire_brut: number; cnps_retraite_salarial: number
    cnps_retraite_patronal: number; cnps_at_patronal: number
    cnps_famille_patronal: number; cmu_salarial: number
    cmu_patronal: number; total_cnps: number
  }>
  totaux: Record<string, number>
}

export interface RhEtatIts {
  periode: string
  lignes: Array<{ matricule: string; nom: string; salaire_brut: number; its: number; cn: number; total_its_cn: number }>
  total_its: number
  total_cn: number
  total_its_cn: number
}

export interface RhDeclarationMO {
  annee: number
  nb_employes: number
  employes: Array<{
    matricule: string; nom: string; prenoms: string; sexe: string | null
    nationalite: string | null; date_embauche: string; date_sortie: string | null
    type_contrat_actuel: string; intitule_poste: string | null
    categorie: string | null; departement: string | null
    numero_cnps: string | null; agence_nom: string | null
  }>
  alerte_delai: string | null
}

export interface RhRapportHeursSup {
  periode: string
  lignes: Array<{ matricule: string; nom: string; montant_hs: number }>
  total_montant: number
}

export interface RhDocumentCoffre {
  id: number
  id_employe: number
  type: string
  nom_fichier: string
  url_fichier: string
  taille_octets: number | null
  mime_type: string | null
  description: string | null
  date_expiration: string | null
  created_at: string
}

export interface RhHistoriquePoste {
  id: number
  id_employe: number
  ancien_poste: string | null
  nouveau_poste: string | null
  ancien_departement: string | null
  nouveau_departement: string | null
  ancien_salaire: number | null
  nouveau_salaire: number | null
  date_effet: string
  motif: string | null
  created_at: string
}
