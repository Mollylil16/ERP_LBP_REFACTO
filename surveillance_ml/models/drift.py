import numpy as np
import pandas as pd
import ruptures as rpt
from config import ML_SETTINGS

# Signaux de dérive étendus (Phase 2 : inclut les nouvelles features)
DRIFT_SIGNAL_COLS = [
    'ratio_valeur_poids',
    'ecart_caisse_abs_cumule',
    'nb_audit_hors_horaires',
    'nb_modifs_verrouillees',
    'nb_annulations_colis',     # Nouveau : spike d'annulations suspect
    'nb_saisies_rapides',       # Nouveau : accélération saisie suspecte
]


class DriftDetector:

    @staticmethod
    def detect_drift(employee_history_df: pd.DataFrame) -> tuple[float, str | None, list[str]]:
        """
        Détecte une dérive comportementale sur l'historique d'un employé.
        Retourne (score_derive, date_rupture, metriques_concernees).

        Améliorations v2 :
        - 6 signaux surveillés (au lieu de 4)
        - Ajout de nb_annulations_colis et nb_saisies_rapides
        - Score de dérive normalisé plus précis
        """
        min_weeks = ML_SETTINGS['drift_min_weeks']

        if employee_history_df.empty or len(employee_history_df) < min_weeks:
            return 0.0, None, []

        df_sorted = employee_history_df.sort_values('semaine').reset_index(drop=True)
        N = len(df_sorted)

        # Sélectionner uniquement les colonnes disponibles dans le DataFrame
        available_cols = [c for c in DRIFT_SIGNAL_COLS if c in df_sorted.columns]
        if not available_cols:
            return 0.0, None, []

        signals = df_sorted[available_cols].fillna(0.0).values

        # Normalisation des signaux (Z-score robuste)
        stds = np.std(signals, axis=0)
        stds[stds == 0] = 1.0
        signals_norm = (signals - np.mean(signals, axis=0)) / stds

        try:
            # Change Point Detection — Binary Segmentation (ruptures)
            algo   = rpt.Binseg(model='l2').fit(signals_norm)
            result = algo.predict(n_bkps=1)

            if len(result) < 2 or result[0] >= N or result[0] <= 1:
                return 0.0, None, []

            break_point  = result[0]
            date_rupture = df_sorted.loc[break_point, 'semaine']

            before = df_sorted.iloc[:break_point]
            after  = df_sorted.iloc[break_point:]

            score_derive        = 0.0
            metriques_concernees = []

            # ── Indicateur 1 : Baisse ratio valeur/poids (sous-déclaration) ──
            if 'ratio_valeur_poids' in df_sorted.columns:
                ratio_diff = before['ratio_valeur_poids'].mean() - after['ratio_valeur_poids'].mean()
                if ratio_diff > 0 and before['ratio_valeur_poids'].mean() > 0:
                    pct_deg = (ratio_diff / before['ratio_valeur_poids'].mean()) * 100.0
                    score_derive += pct_deg * 0.35
                    metriques_concernees.append('ratio_valeur_poids')

            # ── Indicateur 2 : Augmentation écarts de caisse ─────────────────
            if 'ecart_caisse_abs_cumule' in df_sorted.columns:
                ecart_diff = after['ecart_caisse_abs_cumule'].mean() - before['ecart_caisse_abs_cumule'].mean()
                if ecart_diff > 0:
                    score_derive += min(ecart_diff / 5000.0, 1.0) * 25.0
                    metriques_concernees.append('ecart_caisse_abs_cumule')

            # ── Indicateur 3 : Activité hors horaires ────────────────────────
            if 'nb_audit_hors_horaires' in df_sorted.columns:
                audit_diff = after['nb_audit_hors_horaires'].mean() - before['nb_audit_hors_horaires'].mean()
                if audit_diff > 0:
                    score_derive += min(audit_diff, 5.0) * 3.5
                    metriques_concernees.append('nb_audit_hors_horaires')

            # ── Indicateur 4 : Modifications factures verrouillées ───────────
            if 'nb_modifs_verrouillees' in df_sorted.columns:
                modifs_diff = after['nb_modifs_verrouillees'].mean() - before['nb_modifs_verrouillees'].mean()
                if modifs_diff > 0:
                    score_derive += min(modifs_diff, 3.0) * 9.0
                    metriques_concernees.append('nb_modifs_verrouillees')

            # ── Indicateur 5 : Spike d'annulations de colis (nouveau) ────────
            if 'nb_annulations_colis' in df_sorted.columns:
                annul_diff = after['nb_annulations_colis'].mean() - before['nb_annulations_colis'].mean()
                if annul_diff > 0:
                    score_derive += min(annul_diff, 5.0) * 4.0
                    metriques_concernees.append('nb_annulations_colis')

            # ── Indicateur 6 : Saisies trop rapides (nouveau) ────────────────
            if 'nb_saisies_rapides' in df_sorted.columns:
                speed_diff = after['nb_saisies_rapides'].mean() - before['nb_saisies_rapides'].mean()
                if speed_diff > 0:
                    score_derive += min(speed_diff, 10.0) * 2.0
                    metriques_concernees.append('nb_saisies_rapides')

            score_final = min(100.0, max(0.0, score_derive))

            if not metriques_concernees:
                return 0.0, None, []

            return float(score_final), date_rupture, metriques_concernees

        except Exception as e:
            print(f"[DriftDetector] Erreur: {e}")
            return 0.0, None, []
