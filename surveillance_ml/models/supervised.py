import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.utils.class_weight import compute_sample_weight
import os
import pickle
from config import ML_SETTINGS
from models.anomaly import FEATURE_COLS


class SupervisedClassifier:
    """
    Classifieur supervisé Random Forest pour la détection de fraude.

    Améliorations v2 :
    - Support des 3 nouvelles features (Phase 2)
    - Fusion labels DG + pseudo-labels (cold start)
    - Class weights automatiques (gestion des classes déséquilibrées)
    - Versioning des modèles sauvegardés
    """

    def __init__(self):
        self.model = RandomForestClassifier(
            n_estimators=200,
            max_depth=10,
            class_weight='balanced',   # Compense le déséquilibre fraude/normal
            random_state=42
        )
        self.is_active  = False
        self.n_samples  = 0
        self.n_fraud    = 0
        self.n_normal   = 0

    @property
    def model_path(self) -> str:
        return os.path.join(os.path.dirname(__file__), 'saved_supervised.pkl')

    def _versioned_path(self) -> str:
        import datetime
        ts = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
        return os.path.join(os.path.dirname(__file__), f'saved_supervised_{ts}.pkl')

    def _cleanup_old_versions(self):
        import glob
        pattern = os.path.join(os.path.dirname(__file__), 'saved_supervised_????????_??????.pkl')
        old_files = sorted(glob.glob(pattern))
        keep = ML_SETTINGS.get('model_versions_to_keep', 3)
        while len(old_files) > keep:
            try:
                os.remove(old_files.pop(0))
            except OSError:
                pass

    # ── Entraînement ─────────────────────────────────────────────────────────
    def fit(self, features_df: pd.DataFrame, labels_dict: dict,
            pseudo_labels_dict: dict | None = None) -> bool:
        """
        Entraîne le classifieur si le nombre de labels est suffisant.

        Stratégie de fusion des labels :
        1. Les labels DG qualifiés (gold truth) ont toujours priorité.
        2. Si ML_SETTINGS['pseudo_labels_enabled'], les pseudo-labels
           (depuis alertes PHP) sont utilisés pour combler les manques.
        """
        if features_df.empty or not labels_dict:
            self.is_active = False
            return False

        # ── Fusionner labels DG + pseudo-labels ───────────────────────────────
        merged_labels = {}
        if ML_SETTINGS.get('pseudo_labels_enabled') and pseudo_labels_dict:
            # D'abord les pseudo-labels (priorité basse)
            merged_labels.update(pseudo_labels_dict)
        # Puis les vrais labels DG (priorité haute → écrasent les pseudo)
        merged_labels.update(labels_dict)

        if not merged_labels:
            self.is_active = False
            return False

        # ── Aligner features et labels ────────────────────────────────────────
        X_list, y_list, is_pseudo = [], [], []
        for _, row in features_df.iterrows():
            key = (int(row['user_id']), str(row['semaine']))
            if key in merged_labels:
                vec = [row.get(col, 0.0) for col in FEATURE_COLS]
                X_list.append(vec)
                y_list.append(merged_labels[key])
                is_pseudo.append(key not in labels_dict)

        X = np.array(X_list, dtype=float)
        y = np.array(y_list, dtype=int)

        # Remplacement des NaN résiduels
        X = np.nan_to_num(X, nan=0.0)

        total_labels = len(y)
        positives    = int(np.sum(y == 1))
        negatives    = int(np.sum(y == 0))
        n_pseudo     = sum(is_pseudo)

        min_total = ML_SETTINGS['supervised_min_labels']
        min_pos   = ML_SETTINGS['supervised_min_positives']
        min_neg   = ML_SETTINGS['supervised_min_negatives']

        print(f"[SupervisedClassifier] Labels disponibles : {total_labels} total "
              f"({positives} fraudes, {negatives} normaux, {n_pseudo} pseudo-labels)")

        if total_labels < min_total or positives < min_pos or negatives < min_neg:
            print(f"[SupervisedClassifier] Seuils non atteints "
                  f"(min {min_total}/{min_pos}+/{min_neg}-). Modèle inactif.")
            self.is_active = False
            return False

        # ── Poids des samples (pseudo-labels pondérés à 0.5) ─────────────────
        sample_weights = np.array([0.5 if p else 1.0 for p in is_pseudo])

        try:
            self.model.fit(X, y, sample_weight=sample_weights)
            self.is_active = True
            self.n_samples = total_labels
            self.n_fraud   = positives
            self.n_normal  = negatives
            self.save()
            print(f"[SupervisedClassifier] Entraînement réussi sur {total_labels} "
                  f"échantillons ({n_pseudo} pseudo). Modèle actif.")
            return True
        except Exception as e:
            print(f"[SupervisedClassifier] Erreur d'entraînement: {e}")
            self.is_active = False
            return False

    # ── Prédiction ───────────────────────────────────────────────────────────
    def predict_probability(self, feature_dict: dict) -> tuple[float | None, dict]:
        """
        Prédit la probabilité de fraude (0.0 à 100.0) et les variables explicatives.
        Retourne (score_supervise, top_facteurs).
        """
        if not self.is_active:
            return None, {}

        x_vec = np.array([[feature_dict.get(col, 0.0) for col in FEATURE_COLS]])
        x_vec = np.nan_to_num(x_vec, nan=0.0)

        try:
            prob_fraud  = self.model.predict_proba(x_vec)[0][1]
            score_pct   = float(prob_fraud * 100.0)
            importances = self.model.feature_importances_
            feature_imp = {FEATURE_COLS[i]: float(importances[i]) for i in range(len(FEATURE_COLS))}
            top_facteurs = dict(sorted(feature_imp.items(), key=lambda x: x[1], reverse=True)[:3])
            return score_pct, top_facteurs
        except Exception as e:
            print(f"[SupervisedClassifier] Erreur de prédiction: {e}")
            return None, {}

    # ── Persistance ──────────────────────────────────────────────────────────
    def save(self):
        payload = {
            'model':     self.model,
            'is_active': self.is_active,
            'n_samples': self.n_samples,
            'n_fraud':   self.n_fraud,
            'n_normal':  self.n_normal,
        }
        try:
            with open(self.model_path, 'wb') as f:
                pickle.dump(payload, f)
        except Exception as e:
            print(f"[SupervisedClassifier] Erreur sauvegarde: {e}")

        try:
            with open(self._versioned_path(), 'wb') as f:
                pickle.dump(payload, f)
            self._cleanup_old_versions()
        except Exception as e:
            print(f"[SupervisedClassifier] Erreur versioning: {e}")

    def load(self) -> bool:
        if not os.path.exists(self.model_path):
            return False
        try:
            with open(self.model_path, 'rb') as f:
                data = pickle.load(f)
            self.model     = data['model']
            self.is_active = data['is_active']
            self.n_samples = data.get('n_samples', 0)
            self.n_fraud   = data.get('n_fraud', 0)
            self.n_normal  = data.get('n_normal', 0)
            return True
        except Exception as e:
            print(f"[SupervisedClassifier] Erreur chargement: {e}")
            return False
