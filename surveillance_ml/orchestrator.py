import pandas as pd
import json
import datetime
from config import ML_SETTINGS
from features import (
    extract_features_df, get_labels, get_pseudo_labels,
    get_active_user_ids, get_db_connection
)
from models.anomaly import AnomalyDetector
from models.drift import DriftDetector
from models.supervised import SupervisedClassifier


class MLOrchestrator:
    """
    Orchestrateur principal du système IA anti-fraude LBP.

    Améliorations v2 :
    - Fusion labels DG + pseudo-labels (cold start du modèle supervisé)
    - Auto-calibration contamination transmise au détecteur d'anomalies
    - Nouveau endpoint predict_all_employees() pour le scheduler
    - Dictionnaire de traduction étendu aux 3 nouvelles features
    """

    def __init__(self):
        self.anomaly_detector     = AnomalyDetector()
        self.supervised_classifier = SupervisedClassifier()
        self.load_models()

    def load_models(self):
        self.anomaly_detector.load()
        self.supervised_classifier.load()

    # ── Entraînement ─────────────────────────────────────────────────────────
    def train_models(self) -> dict:
        """
        Ré-entraîne l'ensemble de la chaîne de modèles depuis la BDD MySQL.

        Étapes :
        1. Extraire les features (26 semaines)
        2. Récupérer labels DG + pseudo-labels
        3. Entraîner AnomalyDetector (avec auto-calibration contamination)
        4. Entraîner SupervisedClassifier (labels DG + pseudo si activés)
        """
        print("[Orchestrator] Démarrage du réentraînement v2...")

        df_all = extract_features_df(weeks_back=26)
        if df_all.empty:
            return {"status": "error", "message": "Aucune donnée extraite pour l'entraînement."}

        # Labels DG (gold truth)
        labels_dg = get_labels()
        print(f"[Orchestrator] Labels DG : {len(labels_dg)} ({sum(v==1 for v in labels_dg.values())} fraudes)")

        # Pseudo-labels (cold start)
        pseudo_labels = {}
        if ML_SETTINGS.get('pseudo_labels_enabled'):
            pseudo_labels = get_pseudo_labels()
            print(f"[Orchestrator] Pseudo-labels : {len(pseudo_labels)} "
                  f"({sum(v==1 for v in pseudo_labels.values())} fraudes)")

        # ── Entraîner AnomalyDetector avec auto-calibration ──────────────────
        merged_labels = {**pseudo_labels, **labels_dg}
        self.anomaly_detector.fit(df_all, labels=merged_labels)

        # ── Entraîner SupervisedClassifier ────────────────────────────────────
        self.supervised_classifier.fit(
            df_all,
            labels_dict=labels_dg,
            pseudo_labels_dict=pseudo_labels if ML_SETTINGS.get('pseudo_labels_enabled') else None
        )

        return {
            "status":              "success",
            "samples_trained":     len(df_all),
            "labels_dg":           len(labels_dg),
            "pseudo_labels":       len(pseudo_labels),
            "supervised_active":   self.supervised_classifier.is_active,
            "contamination_used":  self.anomaly_detector.contamination_used,
            "version_modele":      ML_SETTINGS['version_modele'],
            "trained_at":          datetime.datetime.now().isoformat(),
        }

    # ── Prédiction individuelle ───────────────────────────────────────────────
    def predict_employee_risk(self, user_id: int) -> dict:
        """
        Calcule et enregistre les scores ML d'un employé.
        Pipeline : Anomalie → Dérive → Supervisé → Fusion → Sauvegarde BDD.
        """
        df_emp = extract_features_df(user_id=user_id, weeks_back=26)
        if df_emp.empty:
            return self._empty_result(user_id)

        latest_row      = df_emp.sort_values('semaine', ascending=False).iloc[0]
        latest_features = latest_row.to_dict()

        # ── Couche 1 : Anomalie ───────────────────────────────────────────────
        score_anomaly, anomaly_factors = self.anomaly_detector.predict_score(latest_features)

        # ── Couche 2 : Dérive comportementale ────────────────────────────────
        score_drift, date_rupture, drift_factors = DriftDetector.detect_drift(df_emp)

        # ── Couche 3 : Classifieur supervisé ─────────────────────────────────
        score_supervised, supervised_factors = self.supervised_classifier.predict_probability(latest_features)

        # ── Fusion des scores ─────────────────────────────────────────────────
        weights = ML_SETTINGS['weights']
        if score_supervised is None:
            total_w    = weights['anomaly'] + weights['drift']
            w_anomaly  = weights['anomaly'] / total_w
            w_drift    = weights['drift'] / total_w
            score_final = (w_anomaly * score_anomaly) + (w_drift * score_drift)
        else:
            score_final = (
                weights['anomaly']    * score_anomaly +
                weights['drift']      * score_drift +
                weights['supervised'] * score_supervised
            )

        # ── Agrégation des facteurs explicatifs ───────────────────────────────
        combined_factors: dict[str, float] = {}
        for k, v in anomaly_factors.items():
            combined_factors[k] = combined_factors.get(k, 0.0) + v * 1.5
        for col in drift_factors:
            combined_factors[col] = combined_factors.get(col, 0.0) + 2.0
        for k, v in supervised_factors.items():
            combined_factors[k] = combined_factors.get(k, 0.0) + v * 10.0

        sorted_factors = sorted(combined_factors.items(), key=lambda x: x[1], reverse=True)[:3]
        top_facteurs   = {_TRANSLATION.get(k, k): round(v, 1) for k, v in sorted_factors}

        # ── Recommandations de gouvernance ────────────────────────────────────
        recommandations = _build_recommandations(score_final, top_facteurs, date_rupture)

        # ── Sauvegarde en base ────────────────────────────────────────────────
        self._save_scores_to_db(
            user_id, score_anomaly, score_drift, score_supervised,
            score_final, top_facteurs, recommandations
        )

        return {
            "user_id":         user_id,
            "score_final":     round(score_final, 1),
            "score_anomalie":  round(score_anomaly, 1),
            "score_derive":    round(score_drift, 1),
            "score_supervise": round(score_supervised, 1) if score_supervised is not None else None,
            "top_facteurs":    top_facteurs,
            "date_rupture":    str(date_rupture) if date_rupture else None,
            "version_modele":  ML_SETTINGS['version_modele'],
            "recommandations": recommandations,
        }

    # ── Prédiction groupée (toute la liste d'employés) ───────────────────────
    def predict_all_employees(self) -> dict:
        """
        Recalcule les scores ML de TOUS les employés actifs.
        Utilisé par le scheduler nightly.
        """
        user_ids = get_active_user_ids()
        if not user_ids:
            return {"status": "ok", "processed": 0, "message": "Aucun employé actif trouvé."}

        results     = []
        errors      = []
        high_risk   = []

        print(f"[Orchestrator] Démarrage prédiction groupée — {len(user_ids)} employés...")
        for uid in user_ids:
            try:
                result = self.predict_employee_risk(uid)
                results.append(result)
                if result['score_final'] >= 75.0:
                    high_risk.append(uid)
                print(f"  [OK] user_id={uid} → score={result['score_final']}%")
            except Exception as e:
                errors.append({"user_id": uid, "error": str(e)})
                print(f"  [ERR] user_id={uid} → {e}")

        summary = {
            "status":       "success" if not errors else "partial",
            "processed":    len(results),
            "errors":       len(errors),
            "high_risk":    high_risk,
            "completed_at": datetime.datetime.now().isoformat(),
        }
        print(f"[Orchestrator] Terminé — {summary['processed']} OK, "
              f"{summary['errors']} erreurs, {len(high_risk)} alertes critiques.")
        return summary

    # ── Métriques globales (endpoint /metrics) ────────────────────────────────
    def get_metrics(self) -> dict:
        """Retourne les métriques globales du système IA pour le dashboard DG."""
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        # Score moyen global (dernière semaine)
        cursor.execute("""
            SELECT
                ROUND(AVG(score_final), 1)          AS avg_score,
                MAX(score_final)                     AS max_score,
                COUNT(*)                             AS nb_employes_evalues,
                COUNT(CASE WHEN score_final >= 75 THEN 1 END) AS nb_critiques,
                COUNT(CASE WHEN score_final >= 50
                           AND score_final < 75 THEN 1 END) AS nb_a_surveiller
            FROM lbp_scores_ml_employes
            WHERE scored_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        """)
        scores_row = cursor.fetchone() or {}

        # Alertes actives
        cursor.execute("""
            SELECT COUNT(*) AS nb_alertes_actives
            FROM lbp_alertes_integrite
            WHERE statut IN ('nouvelle', 'en_cours')
        """)
        alertes_row = cursor.fetchone() or {}

        # Recommandations IA en attente
        cursor.execute("""
            SELECT COUNT(*) AS nb_reco_en_attente
            FROM lbp_recommandations_ia
            WHERE statut = 'en_attente'
        """)
        reco_row = cursor.fetchone() or {}

        # Dernier entraînement
        cursor.execute("""
            SELECT MAX(scored_at) AS dernier_score
            FROM lbp_scores_ml_employes
        """)
        last_train_row = cursor.fetchone() or {}

        conn.close()

        return {
            "avg_score_global":        scores_row.get('avg_score', 0.0),
            "max_score":               scores_row.get('max_score', 0.0),
            "nb_employes_evalues_7j":  scores_row.get('nb_employes_evalues', 0),
            "nb_critiques":            scores_row.get('nb_critiques', 0),
            "nb_a_surveiller":         scores_row.get('nb_a_surveiller', 0),
            "nb_alertes_actives":      alertes_row.get('nb_alertes_actives', 0),
            "nb_recommandations":      reco_row.get('nb_reco_en_attente', 0),
            "dernier_score":           str(last_train_row.get('dernier_score', '')),
            "supervised_active":       self.supervised_classifier.is_active,
            "supervised_n_samples":    self.supervised_classifier.n_samples,
            "contamination_used":      self.anomaly_detector.contamination_used,
            "version_modele":          ML_SETTINGS['version_modele'],
        }

    # ── Persistance en BDD ────────────────────────────────────────────────────
    def _save_scores_to_db(self, user_id, s_anomaly, s_drift, s_supervised,
                           s_final, top_facteurs, recommandations):
        conn   = get_db_connection()
        cursor = conn.cursor()

        cursor.execute("""
            INSERT INTO lbp_scores_ml_employes (
                user_id, score_anomalie, score_derive, score_supervise,
                score_final, top_facteurs, version_modele
            ) VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (
            user_id, s_anomaly, s_drift, s_supervised, s_final,
            json.dumps(top_facteurs, ensure_ascii=False),
            ML_SETTINGS['version_modele']
        ))

        for rec in recommandations:
            cursor.execute("""
                SELECT COUNT(*) FROM lbp_recommandations_ia
                WHERE user_id = %s AND action_recommandee = %s AND statut = 'en_attente'
            """, (user_id, rec['action']))
            if cursor.fetchone()[0] == 0:
                cursor.execute("""
                    INSERT INTO lbp_recommandations_ia (
                        user_id, action_recommandee, statut, explication, origine_decision
                    ) VALUES (%s, %s, 'en_attente', %s, 'recommandation_ia_en_attente')
                """, (user_id, rec['action'], rec['explication']))

        conn.commit()
        conn.close()

    # ── Helpers ──────────────────────────────────────────────────────────────
    @staticmethod
    def _empty_result(user_id: int) -> dict:
        return {
            "user_id":         user_id,
            "score_final":     0.0,
            "score_anomalie":  0.0,
            "score_derive":    0.0,
            "score_supervise": None,
            "top_facteurs":    {},
            "date_rupture":    None,
            "recommandations": [],
        }


# ─── Dictionnaire de traduction étendu ───────────────────────────────────────
_TRANSLATION = {
    'nb_colis':                   'Volume colis inhabituel',
    'poids_moyen':                'Poids moyen des colis hors normes',
    'valeur_moyenne':             'Valeur déclarée colis inhabituelle',
    'ratio_valeur_poids':         'Sous-déclaration valeur/poids suspecte',
    'nb_annulations_colis':       'Nombre élevé d\'annulations de colis',
    'nb_factures':                'Volume de factures anormal',
    'montant_total_facture':      'Montant total facturé atypique',
    'montant_total_encaisse':     'Volume d\'encaissements inhabituel',
    'ecart_caisse_abs_cumule':    'Écarts de caisse cumulés critiques',
    'nb_ecarts_caisse':           'Fréquence élevée d\'écarts de caisse',
    'nb_actions_audit':           'Activité générale système suspecte',
    'nb_audit_hors_horaires':     'Activité système critique hors horaires',
    'nb_modifs_verrouillees':     'Modifications répétées de factures verrouillées',
    'nb_saisies_rapides':         'Vitesse de saisie anormalement rapide',
    'nb_colis_sans_facture_7j':   'Colis non facturés (> 7 jours)',
    'taux_factures_incompletes':  'Taux élevé de factures non soldées',
}


def _build_recommandations(score_final: float, top_facteurs: dict,
                            date_rupture=None) -> list[dict]:
    recommandations = []
    facteurs_str = ', '.join(top_facteurs.keys())

    if score_final >= 75.0:
        rupture_info = f" Dérive détectée à partir du {date_rupture}." if date_rupture else ""
        recommandations.append({
            "action":     "suspendre_compte",
            "explication": (
                f"Score de risque critique : {score_final:.1f}%. "
                f"Facteurs dominants : {facteurs_str}.{rupture_info} "
                "Une suspension à titre conservatoire est vivement recommandée."
            )
        })
    elif score_final >= 50.0:
        recommandations.append({
            "action":     "qualifier_fraude",
            "explication": (
                f"Activité anormale détectée : {score_final:.1f}%. "
                f"Facteurs : {facteurs_str}. "
                "Revue manuelle immédiate des écarts recommandée."
            )
        })
    elif score_final >= 30.0:
        recommandations.append({
            "action":     "surveillance_renforcee",
            "explication": (
                f"Score de vigilance modéré : {score_final:.1f}%. "
                f"Surveiller l'évolution de : {facteurs_str}."
            )
        })

    return recommandations
