import sys
import os

# Ajouter le dossier actuel au path
sys.path.append(os.path.dirname(__file__))

from orchestrator import MLOrchestrator

def main():
    print("=== ENTRAÎNEMENT INITIAL DES MODÈLES ML LBP ===")
    orchestrator = MLOrchestrator()
    result = orchestrator.train_models()
    print("Résultat :", result)

if __name__ == "__main__":
    main()
