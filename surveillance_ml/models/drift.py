import numpy as np
import pandas as pd
import ruptures as rpt
from config import ML_SETTINGS

class DriftDetector:
    @staticmethod
    def detect_drift(employee_history_df: pd.DataFrame) -> tuple[float, str | None, list[str]]:
        """
        Détecte une dérive comportementale sur l'historique d'un employé.
        Retourne (score_derive, date_rupture, metriques_concernees).
        """
        min_weeks = ML_SETTINGS['drift_min_weeks']
        
        if employee_history_df.empty or len(employee_history_df) < min_weeks:
            return 0.0, None, []
            
        # Trier chronologiquement par semaine
        df_sorted = employee_history_df.sort_values('semaine').reset_index(drop=True)
        N = len(df_sorted)
        
        # Sélectionner les signaux clés de dérive
        signals_cols = ['ratio_valeur_poids', 'ecart_caisse_abs_cumule', 'nb_audit_hors_horaires', 'nb_modifs_verrouillees']
        signals = df_sorted[signals_cols].fillna(0.0).values
        
        # Normaliser les signaux pour équilibrer leur poids dans le calcul L2
        stds = np.std(signals, axis=0)
        stds[stds == 0] = 1.0
        signals_norm = (signals - np.mean(signals, axis=0)) / stds
        
        try:
            # Change Point Detection (Binary Segmentation de ruptures)
            # Recherche d'un unique point de rupture (n_bkps=1)
            algo = rpt.Binseg(model="l2").fit(signals_norm)
            result = algo.predict(n_bkps=1)
            
            # ruptures renvoie les indices de fin de segments. Le dernier élément est toujours N.
            if len(result) < 2 or result[0] >= N or result[0] <= 1:
                return 0.0, None, []
                
            break_point = result[0]
            date_rupture = df_sorted.loc[break_point, 'semaine']
            
            # Analyser la dérive : comparer "avant" vs "après" la rupture
            before = df_sorted.iloc[:break_point]
            after = df_sorted.iloc[break_point:]
            
            score_derive = 0.0
            metriques_concernees = []
            
            # Indicateurs de dégradation :
            # 1. Baisse du ratio valeur/poids (sous-déclaration suspecte)
            ratio_diff = before['ratio_valeur_poids'].mean() - after['ratio_valeur_poids'].mean()
            if ratio_diff > 0 and before['ratio_valeur_poids'].mean() > 0:
                p_deg = (ratio_diff / before['ratio_valeur_poids'].mean()) * 100.0
                score_derive += p_deg * 0.4
                metriques_concernees.append('ratio_valeur_poids')
                
            # 2. Augmentation des écarts de caisse
            ecart_diff = after['ecart_caisse_abs_cumule'].mean() - before['ecart_caisse_abs_cumule'].mean()
            if ecart_diff > 0:
                score_derive += min(ecart_diff / 5000.0, 1.0) * 30.0  # Capé à 30pts
                metriques_concernees.append('ecart_caisse_abs_cumule')
                
            # 3. Augmentation de l'activité hors horaires
            audit_diff = after['nb_audit_hors_horaires'].mean() - before['nb_audit_hors_horaires'].mean()
            if audit_diff > 0:
                score_derive += min(audit_diff, 5.0) * 4.0  # Capé à 20pts
                metriques_concernees.append('nb_audit_hors_horaires')
                
            # 4. Augmentation des modifications de factures verrouillées
            modifs_diff = after['nb_modifs_verrouillees'].mean() - before['nb_modifs_verrouillees'].mean()
            if modifs_diff > 0:
                score_derive += min(modifs_diff, 3.0) * 10.0  # Capé à 30pts
                metriques_concernees.append('nb_modifs_verrouillees')
                
            # Normaliser le score de dérive final à [0, 100]
            score_final = min(100.0, max(0.0, score_derive))
            
            # Si aucune métrique n'est dégradée, le score est nul (changement positif)
            if not metriques_concernees:
                return 0.0, None, []
                
            return float(score_final), date_rupture, metriques_concernees
            
        except Exception as e:
            print(f"[ML Drift] Erreur lors de la détection de dérive: {e}")
            return 0.0, None, []
