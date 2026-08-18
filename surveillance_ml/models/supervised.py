import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
import os
from config import ML_SETTINGS

FEATURE_COLS = [
    'nb_colis', 'poids_moyen', 'valeur_moyenne', 'ratio_valeur_poids',
    'nb_factures', 'montant_total_facture', 'montant_total_encaisse',
    'ecart_caisse_abs_cumule', 'nb_ecarts_caisse', 'nb_actions_audit',
    'nb_audit_hors_horaires', 'nb_modifs_verrouillees'
]

class SupervisedClassifier:
    def __init__(self):
        self.model = RandomForestClassifier(n_estimators=100, random_state=42)
        self.is_active = False
        self.model_path = os.path.join(os.path.dirname(__file__), 'saved_supervised.pkl')
        
    def fit(self, features_df: pd.DataFrame, labels_dict: dict) -> bool:
        """
        Entraîne le classifieur si le nombre de labels est suffisant.
        """
        if features_df.empty or not labels_dict:
            self.is_active = False
            return False
            
        # Aligner les features et les labels
        X_list = []
        y_list = []
        
        for _, row in features_df.iterrows():
            key = (int(row['user_id']), str(row['semaine']))
            if key in labels_dict:
                X_list.append(row[FEATURE_COLS].fillna(0.0).values)
                y_list.append(labels_dict[key])
                
        X = np.array(X_list)
        y = np.array(y_list)
        
        # Vérification des seuils de validation
        total_labels = len(y)
        positives = int(np.sum(y == 1))
        negatives = int(np.sum(y == 0))
        
        min_total = ML_SETTINGS['supervised_min_labels']
        min_pos = ML_SETTINGS['supervised_min_positives']
        min_neg = ML_SETTINGS['supervised_min_negatives']
        
        if total_labels < min_total or positives < min_pos or negatives < min_neg:
            print(f"[ML Supervised] Seuils non atteints (Total: {total_labels}/{min_total}, Pos: {positives}/{min_pos}, Neg: {negatives}/{min_neg}). Modèle inactif.")
            self.is_active = False
            return False
            
        try:
            self.model.fit(X, y)
            self.is_active = True
            self.save()
            print(f"[ML Supervised] Entraînement réussi sur {total_labels} échantillons. Modèle actif.")
            return True
        except Exception as e:
            print(f"[ML Supervised] Erreur d'entraînement: {e}")
            self.is_active = False
            return False
            
    def predict_probability(self, feature_dict: dict) -> tuple[float | None, dict]:
        """
        Prédit la probabilité de fraude (0.0 à 100.0) et extrait les variables explicatives.
        Retourne (score_supervise, top_facteurs_globaux).
        """
        if not self.is_active:
            return None, {}
            
        x_vec = np.array([[feature_dict.get(col, 0.0) for col in FEATURE_COLS]])
        
        try:
            # predict_proba renvoie [[prob_classe_0, prob_classe_1]]
            prob_fraud = self.model.predict_proba(x_vec)[0][1]
            score_pct = float(prob_fraud * 100.0)
            
            # Récupérer les features les plus importantes globalement pour le modèle
            importances = self.model.feature_importances_
            feature_imp = {FEATURE_COLS[i]: float(importances[i]) for i in range(len(FEATURE_COLS))}
            top_facteurs = dict(sorted(feature_imp.items(), key=lambda x: x[1], reverse=True)[:3])
            
            return score_pct, top_facteurs
        except Exception as e:
            print(f"[ML Supervised] Erreur de prédiction: {e}")
            return None, {}
            
    def save(self):
        try:
            import pickle
            with open(self.model_path, 'wb') as f:
                pickle.dump({
                    'model': self.model,
                    'is_active': self.is_active
                }, f)
        except Exception as e:
            print(f"[ML Supervised] Erreur sauvegarde modèle: {e}")
            
    def load(self) -> bool:
        if os.path.exists(self.model_path):
            try:
                import pickle
                with open(self.model_path, 'rb') as f:
                    data = pickle.load(f)
                    self.model = data['model']
                    self.is_active = data['is_active']
                return True
            except Exception as e:
                print(f"[ML Supervised] Erreur chargement modèle: {e}")
        return False
