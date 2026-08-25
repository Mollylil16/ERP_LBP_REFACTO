"""
=============================================================================
  SCHEDULER NIGHTLY — ERP LBP TRANSIT
=============================================================================
  Lance automatiquement chaque nuit :
    1. Ré-entraînement de tous les modèles ML (données fraîches)
    2. Recalcul des scores pour tous les employés actifs

  Usage :
    python scheduler.py                   # Lance maintenant et tourne en boucle
    python scheduler.py --run-once        # Exécute une seule fois et quitte
    python scheduler.py --train-only      # Entraîne uniquement sans prédire
    python scheduler.py --predict-only    # Prédit uniquement sans entraîner

  En production, ce script peut être lancé via :
    - Windows Task Scheduler (schtasks)
    - Cron Linux (0 23 * * * python scheduler.py --run-once)
    - Un service Windows (avec pywin32)
=============================================================================
"""

import sys
import os
import time
import argparse
import datetime
import traceback

# Assurer que le dossier courant est dans le path Python
sys.path.insert(0, os.path.dirname(__file__))

from config import ML_SETTINGS
from orchestrator import MLOrchestrator


# ─── Couleurs console ─────────────────────────────────────────────────────────
GREEN  = "\033[92m"
RED    = "\033[91m"
YELLOW = "\033[93m"
CYAN   = "\033[96m"
BOLD   = "\033[1m"
RESET  = "\033[0m"

def log(msg: str, color: str = ""):
    ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"{color}[{ts}] {msg}{RESET}")


# ─── Tâche principale ─────────────────────────────────────────────────────────
def run_nightly_job(train: bool = True, predict: bool = True):
    """Exécute le cycle complet : entraînement + prédiction."""
    log("=" * 60, BOLD)
    log("  SCHEDULER NIGHTLY — LBP ML", BOLD)
    log("=" * 60, BOLD)

    orc = MLOrchestrator()

    # ── 1. Ré-entraînement ───────────────────────────────────────────────────
    if train:
        log("Démarrage du réentraînement des modèles...", CYAN)
        try:
            result = orc.train_models()
            if result.get("status") == "success":
                log(f"Entraînement OK — {result['samples_trained']} lignes, "
                    f"contamination={result['contamination_used']:.3f}, "
                    f"supervisé actif={result['supervised_active']}", GREEN)
            else:
                log(f"Entraînement échoué : {result.get('message', '?')}", RED)
        except Exception:
            log("Erreur critique lors de l'entraînement :", RED)
            traceback.print_exc()

    # ── 2. Prédiction groupée ────────────────────────────────────────────────
    if predict:
        log("Recalcul des scores ML pour tous les employés actifs...", CYAN)
        try:
            summary = orc.predict_all_employees()
            log(f"Prédiction OK — {summary['processed']} employés traités, "
                f"{summary['errors']} erreurs", GREEN)
            if summary['high_risk']:
                log(f"ALERTE CRITIQUE : {len(summary['high_risk'])} employé(s) score >= 75% "
                    f"(IDs: {summary['high_risk']})", RED)
        except Exception:
            log("Erreur critique lors de la prédiction groupée :", RED)
            traceback.print_exc()

    log("=" * 60, BOLD)
    log("  Job nightly terminé.", BOLD)
    log("=" * 60, BOLD)


# ─── Boucle de planification ─────────────────────────────────────────────────
def scheduler_loop(target_hour: int, train: bool, predict: bool):
    """
    Boucle infinie qui exécute run_nightly_job() chaque jour à target_hour.
    Vérifie chaque minute si l'heure cible est atteinte.
    """
    log(f"Scheduler démarré. Job planifié chaque jour à {target_hour:02d}h00.", CYAN)
    last_run_date = None

    while True:
        now  = datetime.datetime.now()
        today = now.date()

        if now.hour == target_hour and last_run_date != today:
            last_run_date = today
            run_nightly_job(train=train, predict=predict)

        # Calculer le temps avant la prochaine vérification (60 secondes)
        time.sleep(60)


# ─── Point d'entrée ───────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(
        description="Scheduler nightly — ERP LBP Surveillance ML"
    )
    parser.add_argument(
        '--run-once', action='store_true',
        help="Exécuter le job une seule fois immédiatement et quitter."
    )
    parser.add_argument(
        '--train-only', action='store_true',
        help="Entraîner uniquement (sans prédiction)."
    )
    parser.add_argument(
        '--predict-only', action='store_true',
        help="Prédire uniquement (sans entraînement)."
    )
    parser.add_argument(
        '--hour', type=int, default=ML_SETTINGS['schedule_hour'],
        help=f"Heure d'exécution 0-23 (défaut: {ML_SETTINGS['schedule_hour']})."
    )
    args = parser.parse_args()

    do_train   = not args.predict_only
    do_predict = not args.train_only

    if args.run_once:
        run_nightly_job(train=do_train, predict=do_predict)
    else:
        scheduler_loop(target_hour=args.hour, train=do_train, predict=do_predict)


if __name__ == '__main__':
    main()
