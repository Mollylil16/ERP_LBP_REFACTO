import mysql.connector
import pandas as pd
from datetime import datetime, timedelta
from config import DB_SETTINGS

def get_db_connection():
    return mysql.connector.connect(
        host=DB_SETTINGS['host'],
        port=DB_SETTINGS['port'],
        database=DB_SETTINGS['dbname'],
        user=DB_SETTINGS['username'],
        password=DB_SETTINGS['password'],
        charset='utf8mb4'
    )

def extract_features_df(user_id=None, weeks_back=26) -> pd.DataFrame:
    """
    Extrait l'historique d'activité hebdomadaire des collaborateurs pour le ML.
    Retourne un DataFrame pandas indexé par (user_id, date_semaine).
    """
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    # 1. Récupérer tous les collaborateurs actifs
    user_query = "SELECT id, full_name FROM users WHERE status = 'active'"
    if user_id is not None:
        user_query += f" AND id = {int(user_id)}"
    
    cursor.execute(user_query)
    users = cursor.fetchall()
    
    if not users:
        conn.close()
        return pd.DataFrame()
        
    start_date = datetime.now() - timedelta(weeks=weeks_back)
    start_date_str = start_date.strftime('%Y-%m-%d')
    
    # Nous allons agréger l'historique par semaine (du Lundi au Dimanche)
    # Les features agrégées :
    # - Colis créés, poids, valeur, ratio
    # - Factures, paiements
    # - Actions d'audit, actions hors-horaires, modifications factures verrouillées
    # - Écarts de caisse
    
    features_list = []
    
    for u in users:
        uid = u['id']
        
        # Requête Colis
        cursor.execute("""
            SELECT 
                STR_TO_DATE(CONCAT(YEARWEEK(created_at, 1), ' Monday'), '%x%v %W') AS semaine,
                COUNT(*) AS nb_colis,
                COALESCE(AVG(poids_total), 0) AS poids_moyen,
                COALESCE(AVG(valeur_declaree), 0) AS valeur_moyenne,
                COALESCE(AVG(valeur_declaree / NULLIF(poids_total, 0)), 0) AS ratio_valeur_poids
            FROM lbp_colis
            WHERE created_by = %s AND created_at >= %s
            GROUP BY semani
        """.replace("semani", "semaine"), (uid, start_date_str))
        colis_data = {row['semaine'].strftime('%Y-%m-%d') if row['semaine'] else None: row for row in cursor.fetchall() if row['semaine']}
        
        # Requête Factures & Paiements
        cursor.execute("""
            SELECT 
                STR_TO_DATE(CONCAT(YEARWEEK(date_emission, 1), ' Monday'), '%x%v %W') AS semaine,
                COUNT(*) AS nb_factures,
                COALESCE(SUM(montant_total), 0) AS montant_total_facture,
                COALESCE(SUM(montant_encaisse), 0) AS montant_total_encaisse
            FROM lbp_factures
            WHERE caissiere_id = %s AND date_emission >= %s
            GROUP BY semani
        """.replace("semani", "semaine"), (uid, start_date_str))
        factures_data = {row['semaine'].strftime('%Y-%m-%d') if row['semaine'] else None: row for row in cursor.fetchall() if row['semaine']}
        
        # Requête Écarts de caisse (lbp_etats_journaliers)
        cursor.execute("""
            SELECT 
                STR_TO_DATE(CONCAT(YEARWEEK(date_jour, 1), ' Monday'), '%x%v %W') AS semaine,
                COALESCE(SUM(ABS(ecart_caisse)), 0) AS ecart_caisse_abs_cumule,
                COUNT(CASE WHEN ecart_caisse != 0 THEN 1 END) AS nb_ecarts_caisse
            FROM lbp_etats_journaliers
            WHERE chef_agence_id = %s AND date_jour >= %s
            GROUP BY semani
        """.replace("semani", "semaine"), (uid, start_date_str))
        caisse_data = {row['semaine'].strftime('%Y-%m-%d') if row['semaine'] else None: row for row in cursor.fetchall() if row['semaine']}
        
        # Requête Audit trail (lbp_audit_logs)
        cursor.execute("""
            SELECT 
                STR_TO_DATE(CONCAT(YEARWEEK(created_at, 1), ' Monday'), '%x%v %W') AS semaine,
                COUNT(*) AS nb_actions_audit,
                COUNT(CASE WHEN HOUR(created_at) < 8 OR HOUR(created_at) >= 18 OR DAYOFWEEK(created_at) IN (1, 7) THEN 1 END) AS nb_audit_hors_horaires,
                COUNT(CASE WHEN action = 'update_invoice_locked' THEN 1 END) AS nb_modifs_verrouillees
            FROM lbp_audit_logs
            WHERE user_id = %s AND created_at >= %s
            GROUP BY semani
        """.replace("semani", "semaine"), (uid, start_date_str))
        audit_data = {row['semaine'].strftime('%Y-%m-%d') if row['semaine'] else None: row for row in cursor.fetchall() if row['semaine']}
        
        # Fusionner toutes les semaines uniques
        toutes_semaines = set(list(colis_data.keys()) + list(factures_data.keys()) + list(caisse_data.keys()) + list(audit_data.keys()))
        
        for sem in toutes_semaines:
            if not sem:
                continue
            
            c_row = colis_data.get(sem, {})
            f_row = factures_data.get(sem, {})
            cs_row = caisse_data.get(sem, {})
            a_row = audit_data.get(sem, {})
            
            features_list.append({
                'user_id': uid,
                'semaine': sem,
                'nb_colis': c_row.get('nb_colis', 0),
                'poids_moyen': float(c_row.get('poids_moyen', 0.0)),
                'valeur_moyenne': float(c_row.get('valeur_moyenne', 0.0)),
                'ratio_valeur_poids': float(c_row.get('ratio_valeur_poids', 0.0)),
                'nb_factures': f_row.get('nb_factures', 0),
                'montant_total_facture': float(f_row.get('montant_total_facture', 0.0)),
                'montant_total_encaisse': float(f_row.get('montant_total_encaisse', 0.0)),
                'ecart_caisse_abs_cumule': float(cs_row.get('ecart_caisse_abs_cumule', 0.0)),
                'nb_ecarts_caisse': cs_row.get('nb_ecarts_caisse', 0),
                'nb_actions_audit': a_row.get('nb_actions_audit', 0),
                'nb_audit_hors_horaires': a_row.get('nb_audit_hors_horaires', 0),
                'nb_modifs_verrouillees': a_row.get('nb_modifs_verrouillees', 0)
            })
            
    conn.close()
    
    if not features_list:
        return pd.DataFrame()
        
    df = pd.DataFrame(features_list)
    return df

def get_labels() -> dict:
    """
    Récupère les labels qualifiés par le DG depuis la table lbp_alertes_integrite.
    Retourne un dictionnaire {(user_id, semaine_de_l_alerte): label_0_ou_1}
    """
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    # 1=Fraude confirmée, 0=Alerte justifiée / rejetée
    cursor.execute("""
        SELECT user_id, 
               STR_TO_DATE(CONCAT(YEARWEEK(created_at, 1), ' Monday'), '%x%v %W') AS semaine,
               statut
        FROM lbp_alertes_integrite
        WHERE statut IN ('justifiee', 'confirmee')
    """)
    rows = cursor.fetchall()
    conn.close()
    
    labels = {}
    for r in rows:
        sem_str = r['semaine'].strftime('%Y-%m-%d') if r['semaine'] else None
        if not sem_str:
            continue
        # confirmee = 1, justifiee = 0
        labels[(r['user_id'], sem_str)] = 1 if r['statut'] == 'confirmee' else 0
        
    return labels
