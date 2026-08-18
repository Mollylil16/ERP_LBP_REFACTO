import pandas as pd
import json
from config import ML_SETTINGS
from features import extract_features_df, get_labels, get_db_connection
from models.anomaly import AnomalyDetector
from models.drift import DriftDetector
from models.supervised import SupervisedClassifier

class MLOrchestrator:
    def __init__(self):
        self.anomaly_detector = AnomalyDetector()
        self.supervised_classifier = SupervisedClassifier()
        self.load_models()
        
    def load_models(self):
        self.anomaly_detector.load()
        self.supervised_classifier.load()
        
    def train_models(self) -> dict:
        """
        Ré-entraîne l'ensemble de la chaîne de modèles à partir de la base MySQL.
        """
        print("[Orchestrator] Démarrage du réentraînement...")
        
        # 1. Extraire les features
        df_all = extract_features_df(weeks_back=26)
        if df_all.empty:
            return {"status": "error", "message": "Aucune donnée extraite pour l'entraînement"}
            
        # 2. Entraîner le détecteur d'anomalies
        self.anomaly_detector.fit(df_all)
        
        # 3. Entraîner le classifieur supervisé
        labels = get_labels()
        self.supervised_classifier.fit(df_all, labels)
        
        return {
            "status": "success",
            "samples_trained": len(df_all),
            "supervised_active": self.supervised_classifier.is_active,
            "version_modele": ML_SETTINGS['version_modele']
        }
        
    def predict_employee_risk(self, user_id: int) -> dict:
        """
        Exécute la prédiction multi-couche pour un employé et enregistre le score.
        """
        # 1. Récupérer l'historique complet de l'employé
        df_emp = extract_features_df(user_id=user_id, weeks_back=26)
        if df_emp.empty:
            return {
                "user_id": user_id,
                "score_final": 0.0,
                "score_anomalie": 0.0,
                "score_derive": 0.0,
                "score_supervise": None,
                "top_facteurs": {},
                "recommandations": []
            }
            
        # Récupérer le vecteur de caractéristiques de la semaine la plus récente
        latest_row = df_emp.sort_values('semaine', ascending=False).iloc[0]
        latest_features = latest_row.to_dict()
        
        # 2. Inférence Modèle 1 : Anomalie
        score_anomaly, anomaly_factors = self.anomaly_detector.predict_score(latest_features)
        
        # 3. Inférence Modèle 2 : Dérive (CPD)
        score_drift, date_rupture, drift_factors = DriftDetector.detect_drift(df_emp)
        
        # 4. Inférence Modèle 3 : Classifieur supervisé
        score_supervised, supervised_factors = self.supervised_classifier.predict_probability(latest_features)
        
        # 5. Fusion des scores (Orchestration)
        weights = ML_SETTINGS['weights']
        
        if score_supervised is None:
            # Modèle supervisé inactif (pas assez de labels) -> redistribuer le poids
            total_w = weights['anomaly'] + weights['drift']
            w_anomaly = weights['anomaly'] / total_w
            w_drift = weights['drift'] / total_w
            
            score_final = (w_anomaly * score_anomaly) + (w_drift * score_drift)
        else:
            score_final = (weights['anomaly'] * score_anomaly) + \
                          (weights['drift'] * score_drift) + \
                          (weights['supervised'] * score_supervised)
                          
        # 6. Combiner les explications (Top facteurs)
        combined_factors = {}
        # Fusionner les importances des variables
        for k, v in anomaly_factors.items():
            combined_factors[k] = combined_factors.get(k, 0.0) + v * 1.5
        for col in drift_factors:
            combined_factors[col] = combined_factors.get(col, 0.0) + 2.0
        for k, v in supervised_factors.items():
            combined_factors[k] = combined_factors.get(k, 0.0) + v * 10.0
            
        # Garder les 3 plus importants et traduire en libellé lisible
        translation = {
            'nb_colis': 'Volume colis inhabituel',
            'poids_moyen': 'Poids moyen des colis hors normes',
            'valeur_moyenne': 'Valeur déclarée colis inhabituelle',
            'ratio_valeur_poids': 'Sous-déclaration valeur/poids suspecte',
            'nb_factures': 'Volume de factures anormal',
            'montant_total_facture': 'Montant total facturé atypique',
            'montant_total_encaisse': 'Volume d\'encaissements inhabituel',
            'ecart_caisse_abs_cumule': 'Écarts de caisse cumulés critiques',
            'nb_ecarts_caisse': 'Fréquence élevée d\'écarts de caisse',
            'nb_actions_audit': 'Activité générale système suspecte',
            'nb_audit_hors_horaires': 'Activité système critique hors horaires',
            'nb_modifs_verrouillees': 'Modifications répétées de factures verrouillées'
        }
        
        sorted_factors = sorted(combined_factors.items(), key=lambda x: x[1], reverse=True)[:3]
        top_facteurs = {translation.get(k, k): round(v, 1) for k, v in sorted_factors}
        
        # 7. Formuler les recommandations de gouvernance (IA en mode aide à la décision)
        recommandations = []
        if score_final >= 75.0:
            recommandations.append({
                "action": "suspendre_compte",
                "explication": f"Le score de risque critique de {score_final:.1f}% indique une dérive comportementale sévère, notamment marquée par : {', '.join(top_facteurs.keys())}. Une suspension à titre conservatoire est vivement recommandée."
            })
        elif score_final >= 50.0:
            recommandations.append({
                "action": "qualifier_fraude",
                "explication": f"Activité anormale détectée ({score_final:.1f}%). Le comportement de l'employé requiert une revue manuelle immédiate des écarts enregistrés."
            })
            
        # 8. Sauvegarder en base de données
        self.save_scores_to_db(user_id, score_anomaly, score_drift, score_supervised, score_final, top_facteurs, recommandations)
        
        return {
            "user_id": user_id,
            "score_final": round(score_final, 1),
            "score_anomalie": round(score_anomaly, 1),
            "score_derive": round(score_drift, 1),
            "score_supervise": round(score_supervised, 1) if score_supervised is not None else None,
            "top_facteurs": top_facteurs,
            "version_modele": ML_SETTINGS['version_modele'],
            "recommandations": recommandations
        }
        
    def save_scores_to_db(self, user_id: int, s_anomaly: float, s_drift: float, s_supervised: float | None, s_final: float, top_facteurs: dict, recommandations: list):
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Écriture dans lbp_scores_ml_employes
        cursor.execute("""
            INSERT INTO lbp_scores_ml_employes (
                user_id, score_anomalie, score_derive, score_supervise, score_final, top_facteurs, version_modele
            ) VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (
            user_id, s_anomaly, s_drift, s_supervised, s_final, 
            json.dumps(top_facteurs, ensure_ascii=False), ML_SETTINGS['version_modele']
        ))
        
        # Écriture dans lbp_recommandations_ia pour chaque recommandation majeure
        for rec in recommandations:
            # Vérifier si une recommandation identique est déjà en attente pour cet utilisateur
            cursor.execute("""
                SELECT COUNT(*) FROM lbp_recommandations_ia
                WHERE user_id = %s AND action_recommandee = %s AND statut = 'en_attente'
            """, (user_id, rec['action']))
            
            if cursor.fetchone()[0] == 0:
                # Créer la recommandation en attente
                cursor.execute("""
                    INSERT INTO lbp_recommandations_ia (
                        user_id, action_recommandee, statut, explication, origine_decision
                    ) VALUES (%s, %s, 'en_attente', %s, 'recommandation_ia_en_attente')
                """, (user_id, rec['action'], rec['explication']))
                
        conn.commit()
        conn.close()
