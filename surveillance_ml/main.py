from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from orchestrator import MLOrchestrator
from features import get_db_connection
from config import ML_SETTINGS

app = FastAPI(
    title="LBP Surveillance ML API",
    description=(
        "Micro-service de détection comportementale et d'anomalies anti-fraude "
        "pour l'ERP LBP Transit. Version 2.0 : pseudo-labels, auto-calibration, "
        "predict-all, métriques DG."
    ),
    version=ML_SETTINGS['version_modele'],
    docs_url="/docs",
    redoc_url="/redoc",
)

# CORS pour autoriser les appels depuis le PHP/ERP
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

# ── Initialiser l'orchestrateur au démarrage ──────────────────────────────────
orchestrator = MLOrchestrator()


# ── Modèles de requêtes ───────────────────────────────────────────────────────
class PredictRequest(BaseModel):
    user_id: int


# ═══════════════════════════════════════════════════════════════════════════════
# ENDPOINTS
# ═══════════════════════════════════════════════════════════════════════════════

@app.get("/health", tags=["Monitoring"])
def health_check():
    """
    Diagnostic complet de l'API :
    - Connexion BDD
    - État des 2 modèles chargés
    - Version du modèle actif
    """
    db_status = "ok"
    try:
        conn = get_db_connection()
        conn.close()
    except Exception as e:
        db_status = f"error: {str(e)}"

    return {
        "status":   "healthy",
        "database": db_status,
        "models": {
            "anomaly_detector_loaded":       len(orchestrator.anomaly_detector.medians) > 0,
            "anomaly_contamination_used":    orchestrator.anomaly_detector.contamination_used,
            "supervised_classifier_active":  orchestrator.supervised_classifier.is_active,
            "supervised_n_samples":          orchestrator.supervised_classifier.n_samples,
        },
        "version_modele":    ML_SETTINGS['version_modele'],
        "pseudo_labels_mode": ML_SETTINGS.get('pseudo_labels_enabled', False),
    }


@app.get("/metrics", tags=["Dashboard DG"])
def get_metrics():
    """
    Métriques globales du système IA pour le Centre de Pilotage DG.
    Retourne : scores moyens, alertes actives, recommandations en attente,
    état du modèle supervisé.
    """
    try:
        return orchestrator.get_metrics()
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur métriques: {str(e)}")


@app.post("/predict", tags=["Prédiction"])
def predict_risk(req: PredictRequest):
    """
    Calcule et enregistre les scores ML d'un employé spécifique.
    Retourne : score_final, score_anomalie, score_derive, score_supervise,
    top_facteurs, date_rupture, recommandations.
    """
    try:
        result = orchestrator.predict_employee_risk(req.user_id)
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur prédiction: {str(e)}")


@app.post("/predict-all", tags=["Prédiction"])
def predict_all(background_tasks: BackgroundTasks):
    """
    Lance le recalcul des scores ML pour TOUS les employés actifs.
    Exécuté en tâche de fond (non bloquant).
    Utilisé par le scheduler nightly.
    """
    background_tasks.add_task(orchestrator.predict_all_employees)
    return {
        "status":  "accepted",
        "message": "Recalcul groupé lancé en arrière-plan pour tous les employés actifs.",
    }


@app.post("/train", tags=["Entraînement"])
def train_models():
    """
    Ré-entraîne l'ensemble des modèles depuis l'historique BDD.
    Inclut : labels DG + pseudo-labels + auto-calibration contamination.
    """
    try:
        result = orchestrator.train_models()
        if result.get("status") == "error":
            raise HTTPException(status_code=400, detail=result.get("message"))
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur entraînement: {str(e)}")
