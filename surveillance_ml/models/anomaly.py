import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest
import os
import pickle
import datetime

# ─── Colonnes features (12 originales + 3 nouvelles Phase 2) ─────────────────
FEATURE_COLS = [
    # Colis
    'nb_colis', 'poids_moyen', 'valeur_moyenne', 'ratio_valeur_poids',
    'nb_annulations_colis',
    # Facturation & caisse
    'nb_factures', 'montant_total_facture', 'montant_total_encaisse',
    'ecart_caisse_abs_cumule', 'nb_ecarts_caisse',
    # Audit système
    'nb_actions_audit', 'nb_audit_hors_horaires', 'nb_modifs_verrouillees',
    # Nouvelles features Phase 2
    'nb_saisies_rapides', 'nb_colis_sans_facture_7j', 'taux_factures_incompletes',
]

MODELS_DIR = os.path.dirname(__file__)


class AnomalyDetector:
    """
    Détecteur d'anomalies basé sur Isolation Forest.

    Améliorations v2 :
    - Auto-calibration du taux de contamination depuis les labels DG
    - Support des 3 nouvelles features (nb_annulations, vitesse, colis sans facture)
    - Versioning des modèles sauvegardés (conserve les N dernières versions)
    """

    def __init__(self):
        self.model = IsolationForest(n_estimators=150, contamination=0.08, random_state=42)
        self.medians: dict = {}
        self.stds: dict = {}
        self.contamination_used: float = 0.08
        self._versions_to_keep: int = 3

    # ── Propriété : chemin du modèle actif ───────────────────────────────────
    @property
    def model_path(self) -> str:
        return os.path.join(MODELS_DIR, 'saved_anomaly.pkl')

    def _versioned_path(self) -> str:
        ts = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
        return os.path.join(MODELS_DIR, f'saved_anomaly_{ts}.pkl')

    def _cleanup_old_versions(self):
        """Supprime les anciennes versions au-delà du quota."""
        import glob
        pattern = os.path.join(MODELS_DIR, 'saved_anomaly_????????_??????.pkl')
        old_files = sorted(glob.glob(pattern))
        while len(old_files) > self._versions_to_keep:
            try:
                os.remove(old_files.pop(0))
            except OSError:
                pass

    # ── Entraînement ─────────────────────────────────────────────────────────
    def fit(self, df: pd.DataFrame, labels: dict | None = None):
        """
        Entraîne l'Isolation Forest.

        - Si `labels` est fourni et contient des données, la contamination est
          auto-calibrée depuis le taux de fraude réel observé.
        - Sinon, utilise la valeur fixée dans ML_SETTINGS.
        """
        from config import ML_SETTINGS

        if df.empty:
            return

        X = df[FEATURE_COLS].fillna(0)

        # ── Auto-calibration de la contamination ─────────────────────────────
        if ML_SETTINGS['contamination_auto'] and labels and len(labels) >= 20:
            fraud_rate = sum(v == 1 for v in labels.values()) / len(labels)
            contamination = max(0.01, min(0.30, fraud_rate))
            print(f"[AnomalyDetector] Contamination auto-calibrée : {contamination:.3f} "
                  f"(depuis {len(labels)} labels, {sum(v==1 for v in labels.values())} fraudes)")
        else:
            contamination = ML_SETTINGS['contamination_fixed']
            print(f"[AnomalyDetector] Contamination fixe : {contamination}")

        self.contamination_used = contamination
        self.model = IsolationForest(
            n_estimators=150,
            contamination=contamination,
            random_state=42
        )

        # Statistiques population pour l'explainabilité (Z-score robuste)
        for col in FEATURE_COLS:
            self.medians[col] = float(X[col].median())
            self.stds[col]    = float(X[col].std()) if X[col].std() > 0 else 1.0

        self.model.fit(X)
        self.save()

    # ── Prédiction ───────────────────────────────────────────────────────────
    def predict_score(self, feature_dict: dict) -> tuple[float, dict]:
        """
        Calcule le score d'anomalie (0 à 100) et les facteurs explicatifs.
        Retourne (score_anomalie, top_facteurs).
        """
        x_vec = np.array([[feature_dict.get(col, 0.0) for col in FEATURE_COLS]])
        raw_score = self.model.decision_function(x_vec)[0]

        # Normalisation : 0 = parfaitement normal, 100 = anomalie extrême
        score_pct = max(0.0, min(100.0, (0.5 - raw_score) * 100.0))

        # Facteurs explicatifs par Z-score d'écart à la population
        explications = {}
        for col in FEATURE_COLS:
            val = float(feature_dict.get(col, 0.0))
            med = self.medians.get(col, 0.0)
            std = self.stds.get(col, 1.0)
            z = abs(val - med) / std
            if z > 1.5:
                explications[col] = round(z, 1)

        top_facteurs = dict(sorted(explications.items(), key=lambda x: x[1], reverse=True)[:3])
        return float(score_pct), top_facteurs

    # ── Persistance ──────────────────────────────────────────────────────────
    def save(self):
        payload = {
            'model':               self.model,
            'medians':             self.medians,
            'stds':                self.stds,
            'contamination_used':  self.contamination_used,
            'feature_cols':        FEATURE_COLS,
        }
        # Écriture version active
        try:
            with open(self.model_path, 'wb') as f:
                pickle.dump(payload, f)
        except Exception as e:
            print(f"[AnomalyDetector] Erreur sauvegarde modèle actif: {e}")

        # Copie versionnée + nettoyage
        try:
            versioned = self._versioned_path()
            with open(versioned, 'wb') as f:
                pickle.dump(payload, f)
            self._cleanup_old_versions()
        except Exception as e:
            print(f"[AnomalyDetector] Erreur versioning: {e}")

    def load(self) -> bool:
        if not os.path.exists(self.model_path):
            return False
        try:
            with open(self.model_path, 'rb') as f:
                data = pickle.load(f)
            self.model              = data['model']
            self.medians            = data['medians']
            self.stds               = data['stds']
            self.contamination_used = data.get('contamination_used', 0.08)
            return True
        except Exception as e:
            print(f"[AnomalyDetector] Erreur chargement: {e}")
            return False
