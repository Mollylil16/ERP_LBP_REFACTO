from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from orchestrator import MLOrchestrator
from features import get_db_connection
from config import ML_SETTINGS

app = FastAPI(
    title="LBP Surveillance ML API",
    description="Micro-service de détection comportementale et d'anomalies anti-fraude pour l'ERP LBP",
    version=ML_SETTINGS['version_modele']
)

# Initialiser l'orchestrateur
orchestrator = MLOrchestrator()

class PredictRequest(BaseModel):
    user_id: int

@app.get("/health")
def health_check():
    """
    Diagnostic de l'état de l'API, de la connexion à la BDD et des modèles ML.
    """
    db_status = "ok"
    try:
        conn = get_db_connection()
        conn.close()
    except Exception as e:
        db_status = f"error: {str(e)}"
        
    return {
        "status": "healthy",
        "database": db_status,
        "models": {
            "anomaly_detector_loaded": len(orchestrator.anomaly_detector.medians) > 0,
            "supervised_classifier_active": orchestrator.supervised_classifier.is_active
        },
        "version_modele": ML_SETTINGS['version_modele']
    }

@app.post("/predict")
def predict_risk(req: PredictRequest):
    """
    Calcule et enregistre les scores ML d'un employé.
    """
    try:
        result = orchestrator.predict_employee_risk(req.user_id)
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors de la prédiction: {str(e)}")

@app.post("/train")
def train_models():
    """
    Ré-entraîne l'ensemble des modèles à partir de l'historique de la BDD.
    """
    try:
        result = orchestrator.train_models()
        if result.get("status") == "error":
            raise HTTPException(status_code=400, detail=result.get("message"))
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors du réentraînement: {str(e)}")
