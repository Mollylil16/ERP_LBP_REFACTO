import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest
import os

# Liste des colonnes numériques utilisées pour l'entraînement
FEATURE_COLS = [
    'nb_colis', 'poids_moyen', 'valeur_moyenne', 'ratio_valeur_poids',
    'nb_factures', 'montant_total_facture', 'montant_total_encaisse',
    'ecart_caisse_abs_cumule', 'nb_ecarts_caisse', 'nb_actions_audit',
    'nb_audit_hors_horaires', 'nb_modifs_verrouillees'
]

class AnomalyDetector:
    def __init__(self):
        self.model = IsolationForest(n_estimators=100, contamination=0.08, random_state=42)
        self.medians = {}
        self.stds = {}
        self.model_path = os.path.join(os.path.dirname(__file__), 'saved_anomaly.pkl')
        
    def fit(self, df: pd.DataFrame):
        if df.empty or len(df) < 1:
            # Pas assez de données pour entraîner
            return
            
        X = df[FEATURE_COLS].fillna(0)
        
        # Enregistrer les statistiques de population pour l'explication locale (Z-score)
        for col in FEATURE_COLS:
            self.medians[col] = float(X[col].median())
            self.stds[col] = float(X[col].std()) if X[col].std() > 0 else 1.0
            
        self.model.fit(X)
        self.save()
        
    def predict_score(self, feature_dict: dict) -> tuple[float, dict]:
        """
        Calcule le score d'anomalie (0 à 100) et identifie les facteurs contributifs.
        Retourne (score_anomalie, top_facteurs).
        """
        # Convertir en vecteur 2D pour scikit-learn
        x_vec = np.array([[feature_dict.get(col, 0.0) for col in FEATURE_COLS]])
        
        # Isolation Forest decision_function : valeurs négatives = anomalies, positives = normales
        # typiquement dans range [-0.5, 0.5].
        raw_score = self.model.decision_function(x_vec)[0]
        
        # Normalisation en pourcentage (0 = parfaitement normal, 100 = anomalie extrême)
        # score = 0 correspond à la limite de contamination.
        score_pct = (0.5 - raw_score) * 100.0
        score_pct = max(0.0, min(100.0, score_pct))
        
        # Calcul des facteurs explicatifs locaux par Z-score d'écart à la population
        explications = {}
        for col in FEATURE_COLS:
            val = float(feature_dict.get(col, 0.0))
            med = self.medians.get(col, 0.0)
            std = self.stds.get(col, 1.0)
            
            # Écart par rapport à la médiane normalisé (Z-score robuste)
            z_score = abs(val - med) / std
            if z_score > 1.5:  # Écart significatif
                explications[col] = round(z_score, 1)
                
        # Trier par importance décroissante
        top_facteurs = dict(sorted(explications.items(), key=lambda item: item[1], reverse=True)[:3])
        
        return float(score_pct), top_facteurs
        
    def save(self):
        try:
            import pickle
            with open(self.model_path, 'wb') as f:
                pickle.dump({
                    'model': self.model,
                    'medians': self.medians,
                    'stds': self.stds
                }, f)
        except Exception as e:
            print(f"[ML Anomaly] Erreur sauvegarde modèle: {e}")
            
    def load(self) -> bool:
        if os.path.exists(self.model_path):
            try:
                import pickle
                with open(self.model_path, 'rb') as f:
                    data = pickle.load(f)
                    self.model = data['model']
                    self.medians = data['medians']
                    self.stds = data['stds']
                return True
            except Exception as e:
                print(f"[ML Anomaly] Erreur chargement modèle: {e}")
        return False
