"""
=============================================================================
  SCRIPT D'ÉVALUATION DU SYSTÈME IA - ERP LBP TRANSIT
=============================================================================
  Objectif : Comparer les prédictions des modèles ML avec les labels qualifiés
             par le DG, et produire un rapport complet d'analyse.

  Usage    : python evaluate.py [--export-html] [--threshold 50]
  Output   : Résumé console + rapport HTML optionnel (report_evaluation.html)
=============================================================================
"""

import sys
import os
import argparse
import json
from datetime import datetime

import numpy as np
import pandas as pd

# Assurer que le dossier courant est dans le path Python
sys.path.insert(0, os.path.dirname(__file__))

from features import extract_features_df, get_labels, get_db_connection
from orchestrator import MLOrchestrator
from models.anomaly import AnomalyDetector, FEATURE_COLS
from models.drift import DriftDetector
from config import ML_SETTINGS

# ============================================================
# COULEURS CONSOLE
# ============================================================

GREEN  = "\033[92m"
RED    = "\033[91m"
YELLOW = "\033[93m"
CYAN   = "\033[96m"
BOLD   = "\033[1m"
RESET  = "\033[0m"
GRAY   = "\033[90m"

def c(text: str, color: str) -> str:
    return f"{color}{text}{RESET}"


# ============================================================
# 1. EXTRACTION DES DONNÉES
# ============================================================

def load_evaluation_data() -> tuple:
    print(c("\n[1/5] Extraction des donnees depuis MySQL...", CYAN))

    features_df = extract_features_df(weeks_back=26)
    if features_df.empty:
        print(c("  Aucune donnee de features extraite. Verifiez la connexion BDD.", RED))
        sys.exit(1)

    print(f"  {len(features_df)} lignes (user x semaine) extraites, {features_df['user_id'].nunique()} employes uniques")

    labels = get_labels()
    n_fraud   = sum(v == 1 for v in labels.values())
    n_normal  = sum(v == 0 for v in labels.values())
    print(f"  {len(labels)} labels DG qualifies ({n_fraud} fraudes confirmees, {n_normal} alertes justifiees)")

    if not labels:
        print(c("  Aucun label DG disponible. Le DG doit d'abord qualifier des alertes.", YELLOW))
        sys.exit(0)

    labeled_rows = []
    for _, row in features_df.iterrows():
        key = (int(row['user_id']), str(row['semaine']))
        if key in labels:
            labeled_rows.append({**row.to_dict(), 'label': labels[key]})

    labeled_df = pd.DataFrame(labeled_rows)
    print(f"  {len(labeled_df)} lignes avec label DG alignees pour l'evaluation")
    return features_df, labels, labeled_df


# ============================================================
# 2. PREDICTIONS ML
# ============================================================

def run_predictions(labeled_df: pd.DataFrame, threshold: float) -> pd.DataFrame:
    print(c(f"\n[2/5] Calcul des predictions ML (seuil = {threshold}%)...", CYAN))
    orchestrator = MLOrchestrator()
    records = []

    for i, (_, row) in enumerate(labeled_df.iterrows()):
        feature_dict = row[FEATURE_COLS].fillna(0).to_dict()

        score_anomaly, _ = orchestrator.anomaly_detector.predict_score(feature_dict)
        score_supervised, _ = orchestrator.supervised_classifier.predict_probability(feature_dict)

        emp_history = labeled_df[labeled_df['user_id'] == row['user_id']]
        score_drift, _, _ = DriftDetector.detect_drift(emp_history)

        weights = ML_SETTINGS['weights']
        if score_supervised is None:
            total_w = weights['anomaly'] + weights['drift']
            score_final = (weights['anomaly'] / total_w * score_anomaly) + (weights['drift'] / total_w * score_drift)
        else:
            score_final = (
                weights['anomaly']    * score_anomaly +
                weights['drift']      * score_drift +
                weights['supervised'] * score_supervised
            )

        records.append({
            'user_id'         : int(row['user_id']),
            'semaine'         : str(row['semaine']),
            'label_reel'      : int(row['label']),
            'score_anomalie'  : round(score_anomaly, 1),
            'score_derive'    : round(score_drift, 1),
            'score_supervise' : round(score_supervised, 1) if score_supervised is not None else None,
            'score_final'     : round(score_final, 1),
            'decision_ia'     : 1 if score_final >= threshold else 0,
        })
        sys.stdout.write(f"\r  Progression : {i+1}/{len(labeled_df)} lignes...")
        sys.stdout.flush()

    print(f"\r  {len(records)} predictions generees.{' ' * 20}")
    return pd.DataFrame(records)


# ============================================================
# 3. MATRICE DE CONFUSION ET METRIQUES
# ============================================================

def compute_metrics(results_df: pd.DataFrame) -> dict:
    y_true = results_df['label_reel'].values
    y_pred = results_df['decision_ia'].values

    tp = int(np.sum((y_true == 1) & (y_pred == 1)))
    fp = int(np.sum((y_true == 0) & (y_pred == 1)))
    fn = int(np.sum((y_true == 1) & (y_pred == 0)))
    tn = int(np.sum((y_true == 0) & (y_pred == 0)))

    precision   = tp / (tp + fp)   if (tp + fp) > 0   else 0.0
    recall      = tp / (tp + fn)   if (tp + fn) > 0   else 0.0
    f1          = 2 * precision * recall / (precision + recall) if (precision + recall) > 0 else 0.0
    accuracy    = (tp + tn) / len(y_true) if len(y_true) > 0 else 0.0
    specificity = tn / (tn + fp)   if (tn + fp) > 0   else 0.0

    fraud_scores     = results_df[results_df['label_reel'] == 1]['score_final'].values
    non_fraud_scores = results_df[results_df['label_reel'] == 0]['score_final'].values
    auc_approx = None
    if len(fraud_scores) > 0 and len(non_fraud_scores) > 0:
        auc_approx = float(np.mean([float(f > n) for f in fraud_scores for n in non_fraud_scores]))

    return {
        'tp': tp, 'fp': fp, 'fn': fn, 'tn': tn,
        'precision': precision, 'recall': recall,
        'f1': f1, 'accuracy': accuracy,
        'specificity': specificity, 'auc_approx': auc_approx,
        'total': len(y_true),
        'n_fraud_real': int(np.sum(y_true == 1)),
        'n_normal_real': int(np.sum(y_true == 0)),
    }


def print_confusion_matrix(m: dict):
    print(c("\n[3/5] Matrice de Confusion", CYAN))
    print(f"\n                          {'Predit Fraude':^20} {'Predit Normal':^20}")
    print(f"  {'─' * 46}")
    print(f"  {'Reel Fraude (1)':^22} | {c(str(m['tp']).center(18), GREEN)} | {c(str(m['fn']).center(18), RED)}")
    print(f"  {'─' * 46}")
    print(f"  {'Reel Normal (0)':^22} | {c(str(m['fp']).center(18), YELLOW)} | {c(str(m['tn']).center(18), GREEN)}")
    print(f"  {'─' * 46}")
    print()
    print(f"  {c('TP', GREEN)} Vrais Positifs  (fraudes detectees)        = {m['tp']}")
    print(f"  {c('FP', YELLOW)} Faux Positifs   (innocents accuses)        = {m['fp']}")
    print(f"  {c('FN', RED)} Faux Negatifs   (fraudes manquees)          = {m['fn']}")
    print(f"  {c('TN', GREEN)} Vrais Negatifs  (normaux classes OK)        = {m['tn']}")


def print_metrics(m: dict, threshold: float):
    print(c("\n[4/5] Metriques de Performance", CYAN))
    print(f"  Seuil de decision  : {c(f'{threshold:.0f}%', BOLD)}")
    print(f"  Echantillons total : {m['total']} ({m['n_fraud_real']} fraudes reelles, {m['n_normal_real']} normaux)")
    print()

    def bar(val: float, width: int = 25) -> str:
        filled = int(val * width)
        col = GREEN if val >= 0.75 else (YELLOW if val >= 0.5 else RED)
        return c("█" * filled + "░" * (width - filled), col) + f" {val*100:.1f}%"

    print(f"  Precision          : {bar(m['precision'])}")
    print(f"    Sur {m['tp']+m['fp']} alertes levees, {m['tp']} etaient de vraies fraudes.")
    print()
    print(f"  Rappel (Recall)    : {bar(m['recall'])}")
    print(f"    Sur {m['n_fraud_real']} fraudes reelles, {m['tp']} detectees, {m['fn']} manquees.")
    print()
    print(f"  Score F1           : {bar(m['f1'])}")
    print()
    print(f"  Exactitude         : {bar(m['accuracy'])}")
    print()
    print(f"  Specificite        : {bar(m['specificity'])}")
    print(f"    Sur {m['n_normal_real']} employes normaux, {m['tn']} correctement classes.")
    if m['auc_approx'] is not None:
        print()
        print(f"  AUC-ROC (approx.)  : {bar(m['auc_approx'])}")
        print("    Capacite de separation globale (0.5 = aleatoire, 1.0 = parfait).")


# ============================================================
# 4. ANALYSE DES FEATURES
# ============================================================

def analyze_feature_importance(results_df: pd.DataFrame) -> dict:
    print(c("\n[5/5] Analyse des Variables (Fraudeurs vs Normaux)", CYAN))

    fraud_df  = results_df[results_df['label_reel'] == 1]
    normal_df = results_df[results_df['label_reel'] == 0]

    if fraud_df.empty or normal_df.empty:
        print(c("  Pas assez de donnees pour comparer les groupes.", YELLOW))
        return {}

    labels_fr = {
        'nb_colis'              : 'Volume de colis crees',
        'poids_moyen'           : 'Poids moyen des colis',
        'valeur_moyenne'        : 'Valeur declaree moyenne',
        'ratio_valeur_poids'    : 'Ratio valeur/poids',
        'nb_factures'           : 'Nombre de factures',
        'montant_total_facture' : 'Montant total facture',
        'montant_total_encaisse': 'Montant encaisse',
        'ecart_caisse_abs_cumule': 'Ecarts de caisse cumules',
        'nb_ecarts_caisse'      : 'Frequence ecarts caisse',
        'nb_actions_audit'      : 'Actions systeme totales',
        'nb_audit_hors_horaires': 'Actions hors horaires',
        'nb_modifs_verrouillees': 'Modifs factures verrouillee',
    }

    feature_analysis = {}
    rows_to_print = []

    for col in FEATURE_COLS:
        if col not in results_df.columns:
            continue
        mf = fraud_df[col].mean()
        mn = normal_df[col].mean()
        diff_pct = ((mf - mn) / (abs(mn) + 1e-6)) * 100.0
        label = labels_fr.get(col, col)
        feature_analysis[col] = {'mean_fraud': round(mf, 2), 'mean_normal': round(mn, 2), 'diff_pct': round(diff_pct, 1)}

        alert = ""
        if abs(diff_pct) > 50:
            alert = c("  <- SIGNAL FORT", RED if diff_pct > 0 else YELLOW)

        rows_to_print.append((
            abs(diff_pct),
            f"  {label:<36} Fraudeurs: {mf:>10.2f}   Normaux: {mn:>10.2f}   Diff: {diff_pct:>+8.1f}%{alert}"
        ))

    for _, line in sorted(rows_to_print, key=lambda x: x[0], reverse=True):
        print(line)

    return feature_analysis


# ============================================================
# 5. RECOMMANDATIONS
# ============================================================

def print_recommendations(m: dict, threshold: float, feature_analysis: dict):
    print(c("\n── Recommandations d'Optimisation ──", BOLD))
    issues = 0

    if m['recall'] < 0.7:
        print(c("  [CRITIQUE] Taux de rappel faible -> le modele manque trop de fraudes.", RED))
        print(f"    Baisser le seuil de decision (actuellement {threshold}%) a environ {threshold - 10:.0f}%")
        issues += 1

    if m['precision'] < 0.6:
        print(c("  [ATTENTION] Precision faible -> trop de fausses accusations.", YELLOW))
        print(f"    Monter le seuil de decision a environ {threshold + 10:.0f}%")
        issues += 1

    if m['auc_approx'] and m['auc_approx'] < 0.7:
        print(c("  [ATTENTION] AUC-ROC faible -> separation fraude/innocent insuffisante.", YELLOW))
        print("    Envisager de nouvelles features metier ou d'augmenter la fenetre historique (52 semaines).")
        issues += 1

    contamination_estimate = m['n_fraud_real'] / m['total'] if m['total'] > 0 else 0.08
    if abs(contamination_estimate - 0.08) > 0.04:
        print(c(f"  [INFO] Taux de fraude reel ({contamination_estimate*100:.1f}%) different de contamination IsolationForest (8%).", YELLOW))
        print(f"    Modifier anomaly.py ligne 16 : contamination={contamination_estimate:.2f}")
        issues += 1

    high_signal = [k for k, v in feature_analysis.items() if abs(v.get('diff_pct', 0)) > 80]
    if high_signal:
        print(c(f"  [OPPORTUNITE] Variables tres discriminantes : {', '.join(high_signal)}", GREEN))
        print("    Ces variables sont les plus fiables. Assurez-vous qu'elles sont bien alimentees en production.")

    if issues == 0:
        print(c("  Bonnes performances globales.", GREEN))
        print("  Continuez a enrichir les labels DG pour activer le classifieur supervise.")

    print()
    print(c("── Resume Final ──", BOLD))
    emoji_f1 = "EXCELLENT" if m['f1'] >= 0.75 else ("CORRECT" if m['f1'] >= 0.5 else "INSUFFISANT")
    print(f"  Score F1 global     : {m['f1']*100:.1f}%  [{emoji_f1}]")
    print(f"  Fraudes manquees    : {m['fn']}/{m['n_fraud_real']}")
    print(f"  Fausses alertes     : {m['fp']}")


# ============================================================
# 6. EXPORT RAPPORT HTML
# ============================================================

def export_html_report(m: dict, results_df: pd.DataFrame, feature_analysis: dict, threshold: float) -> str:
    report_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "report_evaluation.html")

    rows_html = ""
    for _, row in results_df.iterrows():
        correct = row['decision_ia'] == row['label_reel']
        bg = "#e8f5e9" if correct else "#ffebee"
        label_text = "Fraude" if row['label_reel'] == 1 else "Normal"
        pred_text  = "Fraude" if row['decision_ia'] == 1 else "Normal"
        score_color = "color:#c62828" if row['score_final'] >= threshold else "color:#2e7d32"
        sup = row['score_supervise'] if row['score_supervise'] is not None else "N/A"
        ok = "OK" if correct else "ERREUR"
        rows_html += (
            f"<tr style='background:{bg}'>"
            f"<td>{row['user_id']}</td><td>{row['semaine']}</td>"
            f"<td>{'[F]' if row['label_reel']==1 else '[N]'} {label_text}</td>"
            f"<td style='{score_color};font-weight:bold'>{row['score_final']}%</td>"
            f"<td>{row['score_anomalie']}%</td>"
            f"<td>{row['score_derive']}%</td>"
            f"<td>{sup}</td>"
            f"<td>{'[F]' if row['decision_ia']==1 else '[N]'} {pred_text}</td>"
            f"<td>{ok}</td></tr>"
        )

    feat_rows = ""
    for col, vals in sorted(feature_analysis.items(), key=lambda x: abs(x[1].get('diff_pct', 0)), reverse=True):
        diff = vals['diff_pct']
        col_style = "color:#c62828;font-weight:bold" if diff > 50 else ("color:#e65100;font-weight:bold" if diff > 20 else "color:#388e3c")
        feat_rows += (
            f"<tr><td>{col}</td><td>{vals['mean_fraud']:.2f}</td>"
            f"<td>{vals['mean_normal']:.2f}</td>"
            f"<td style='{col_style}'>{diff:+.1f}%</td></tr>"
        )

    auc_card = ""
    if m['auc_approx'] is not None:
        col = "green" if m['auc_approx'] >= 0.75 else ("yellow" if m['auc_approx'] >= 0.5 else "red")
        auc_card = f"<div class='card'><div class='val {col}'>{m['auc_approx']*100:.1f}%</div><div class='lbl'>AUC-ROC (approx.)</div></div>"

    def kpi_color(val: float) -> str:
        return "green" if val >= 0.75 else ("yellow" if val >= 0.5 else "red")

    html = f"""<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Evaluation IA - ERP LBP Transit</title>
<style>
  body{{font-family:'Segoe UI',sans-serif;background:#f5f7fa;margin:0;padding:24px;color:#1a1a2e}}
  h1{{color:#1a237e;border-bottom:3px solid #3949ab;padding-bottom:12px}}
  h2{{color:#283593;margin-top:32px}}
  .meta{{color:#666;font-size:.9em;margin-bottom:24px}}
  .grid{{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin:20px 0}}
  .card{{background:white;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.1);text-align:center}}
  .card .val{{font-size:2.2em;font-weight:bold;margin:8px 0}}
  .card .lbl{{font-size:.85em;color:#666}}
  .green{{color:#2e7d32}}.red{{color:#c62828}}.yellow{{color:#e65100}}.blue{{color:#1565c0}}
  .confusion{{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:480px;margin:20px 0}}
  .cm-cell{{border-radius:10px;padding:20px;text-align:center;font-size:1.5em;font-weight:bold}}
  .cm-cell small{{display:block;font-size:.5em;font-weight:normal;margin-top:4px;color:#555}}
  .tp{{background:#e8f5e9;color:#2e7d32}}.fp{{background:#fff8e1;color:#e65100}}
  .fn{{background:#ffebee;color:#c62828}}.tn{{background:#e8f5e9;color:#2e7d32}}
  table{{width:100%;border-collapse:collapse;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-top:12px}}
  th{{background:#3949ab;color:white;padding:10px 14px;text-align:left;font-size:.85em}}
  td{{padding:8px 14px;border-bottom:1px solid #f0f0f0;font-size:.85em}}
  footer{{margin-top:40px;color:#888;font-size:.8em}}
</style>
</head>
<body>
<h1>Rapport d'Evaluation du Systeme IA</h1>
<p class="meta">ERP LBP Transit | Genere le {datetime.now().strftime('%d/%m/%Y a %H:%M')} | Modele {ML_SETTINGS['version_modele']} | Seuil {threshold}%</p>

<h2>Vue d'ensemble</h2>
<div class="grid">
  <div class="card"><div class="val blue">{m['total']}</div><div class="lbl">Echantillons</div></div>
  <div class="card"><div class="val {kpi_color(m['f1'])}">{m['f1']*100:.1f}%</div><div class="lbl">Score F1</div></div>
  <div class="card"><div class="val {kpi_color(m['precision'])}">{m['precision']*100:.1f}%</div><div class="lbl">Precision</div></div>
  <div class="card"><div class="val {kpi_color(m['recall'])}">{m['recall']*100:.1f}%</div><div class="lbl">Rappel</div></div>
  <div class="card"><div class="val {kpi_color(m['accuracy'])}">{m['accuracy']*100:.1f}%</div><div class="lbl">Exactitude</div></div>
  {auc_card}
</div>

<h2>Matrice de Confusion</h2>
<div class="confusion">
  <div class="cm-cell tp">{m['tp']}<small>Vrais Positifs<br>(fraudes detectees)</small></div>
  <div class="cm-cell fp">{m['fp']}<small>Faux Positifs<br>(innocents accuses)</small></div>
  <div class="cm-cell fn">{m['fn']}<small>Faux Negatifs<br>(fraudes manquees)</small></div>
  <div class="cm-cell tn">{m['tn']}<small>Vrais Negatifs<br>(normaux classes OK)</small></div>
</div>

<h2>Analyse des Variables (Fraudeurs vs Normaux)</h2>
<table>
  <tr><th>Variable</th><th>Moy. Fraudeurs</th><th>Moy. Normaux</th><th>Ecart %</th></tr>
  {feat_rows}
</table>

<h2>Detail des Predictions</h2>
<table>
  <tr><th>User</th><th>Semaine</th><th>Label Reel</th><th>Score Final</th><th>Anomalie</th><th>Derive</th><th>Supervise</th><th>Decision IA</th><th>Resultat</th></tr>
  {rows_html}
</table>

<footer>
  Rapport genere par <code>surveillance_ml/evaluate.py</code><br>
  Modeles : Isolation Forest + Change Point Detection + Random Forest Supervise<br>
  Poids : Anomalie {ML_SETTINGS['weights']['anomaly']*100:.0f}% | Derive {ML_SETTINGS['weights']['drift']*100:.0f}% | Supervise {ML_SETTINGS['weights']['supervised']*100:.0f}%
</footer>
</body>
</html>"""

    with open(report_path, 'w', encoding='utf-8') as f:
        f.write(html)

    print(c(f"\n  Rapport HTML exporte : {report_path}", GREEN))
    return report_path


# ============================================================
# POINT D'ENTREE
# ============================================================

def main():
    parser = argparse.ArgumentParser(description="Evaluation du systeme IA - ERP LBP Transit")
    parser.add_argument('--threshold',   type=float, default=50.0, help="Seuil de decision en %% (defaut: 50)")
    parser.add_argument('--export-html', action='store_true',      help="Exporter rapport HTML")
    args = parser.parse_args()

    print(c(f"\n{'=' * 60}", BOLD))
    print(c("  EVALUATION DU SYSTEME IA - ERP LBP TRANSIT", BOLD))
    print(c(f"  Seuil de decision : {args.threshold}%", BOLD))
    print(c(f"{'=' * 60}", BOLD))

    features_df, labels, labeled_df = load_evaluation_data()
    results_df      = run_predictions(labeled_df, threshold=args.threshold)
    metrics         = compute_metrics(results_df)

    print_confusion_matrix(metrics)
    print_metrics(metrics, threshold=args.threshold)
    feature_analysis = analyze_feature_importance(results_df)
    print_recommendations(metrics, threshold=args.threshold, feature_analysis=feature_analysis)

    if args.export_html:
        report_path = export_html_report(metrics, results_df, feature_analysis, threshold=args.threshold)
        try:
            import webbrowser
            webbrowser.open(f"file:///{report_path}")
        except Exception:
            pass

    print(c(f"\n{'=' * 60}", BOLD))
    print(c("  Evaluation terminee.", BOLD))
    print(c(f"{'=' * 60}\n", BOLD))


if __name__ == '__main__':
    main()
