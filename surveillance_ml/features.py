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


def extract_features_df(user_id=None, weeks_back=26, daily=False) -> pd.DataFrame:
    """
    Extrait l'historique d'activité des collaborateurs pour le ML.
    Retourne un DataFrame pandas indexé par (user_id, semaine).

    Paramètres :
    - user_id   : Si fourni, extrait uniquement cet utilisateur.
    - weeks_back: Fenêtre historique en semaines (défaut : 26 = 6 mois).
    - daily     : Si True, granularité journalière (plus sensible aux fraudes courtes).
    """
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # ── 1. Récupérer les utilisateurs actifs ─────────────────────────────────
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

    # Groupement par semaine ISO ou par jour
    if daily:
        date_expr = "DATE(created_at)"
        date_label = "jour"
    else:
        date_expr = "STR_TO_DATE(CONCAT(YEARWEEK(created_at, 1), ' Monday'), '%x%v %W')"
        date_label = "semaine"

    features_list = []

    for u in users:
        uid = u['id']

        # ── 2. Features Colis ─────────────────────────────────────────────────
        cursor.execute(f"""
            SELECT
                {date_expr} AS {date_label},
                COUNT(*) AS nb_colis,
                COALESCE(AVG(poids_total), 0) AS poids_moyen,
                COALESCE(AVG(valeur_declaree), 0) AS valeur_moyenne,
                COALESCE(AVG(valeur_declaree / NULLIF(poids_total, 0)), 0) AS ratio_valeur_poids,
                COUNT(CASE WHEN statut = 'annule' THEN 1 END) AS nb_annulations_colis
            FROM lbp_colis
            WHERE created_by = %s AND created_at >= %s
            GROUP BY {date_label}
        """, (uid, start_date_str))
        colis_data = _index_by_date(cursor.fetchall(), date_label)

        # ── 3. Features Factures & Paiements ──────────────────────────────────
        cursor.execute(f"""
            SELECT
                {date_expr.replace('created_at', 'date_emission')} AS {date_label},
                COUNT(*) AS nb_factures,
                COALESCE(SUM(montant_total), 0)    AS montant_total_facture,
                COALESCE(SUM(montant_encaisse), 0) AS montant_total_encaisse,
                COALESCE(SUM(CASE WHEN montant_total > 0
                    THEN (montant_total - montant_encaisse) / montant_total ELSE 0 END)
                    / NULLIF(COUNT(*), 0), 0) AS taux_colis_sans_facture_complete
            FROM lbp_factures
            WHERE caissiere_id = %s AND date_emission >= %s
            GROUP BY {date_label}
        """, (uid, start_date_str))
        factures_data = _index_by_date(cursor.fetchall(), date_label)

        # ── 4. Features Écarts de caisse ──────────────────────────────────────
        cursor.execute(f"""
            SELECT
                {date_expr.replace('created_at', 'date_jour')} AS {date_label},
                COALESCE(SUM(ABS(ecart_caisse)), 0) AS ecart_caisse_abs_cumule,
                COUNT(CASE WHEN ecart_caisse != 0 THEN 1 END) AS nb_ecarts_caisse
            FROM lbp_etats_journaliers
            WHERE chef_agence_id = %s AND date_jour >= %s
            GROUP BY {date_label}
        """, (uid, start_date_str))
        caisse_data = _index_by_date(cursor.fetchall(), date_label)

        # ── 5. Features Audit trail ───────────────────────────────────────────
        cursor.execute(f"""
            SELECT
                {date_expr} AS {date_label},
                COUNT(*) AS nb_actions_audit,
                COUNT(CASE WHEN HOUR(created_at) < 8
                           OR HOUR(created_at) >= 18
                           OR DAYOFWEEK(created_at) IN (1, 7) THEN 1 END)
                    AS nb_audit_hors_horaires,
                COUNT(CASE WHEN action = 'update_invoice_locked' THEN 1 END)
                    AS nb_modifs_verrouillees
            FROM lbp_audit_logs
            WHERE user_id = %s AND created_at >= %s
            GROUP BY {date_label}
        """, (uid, start_date_str))
        audit_data = _index_by_date(cursor.fetchall(), date_label)

        # ── 6. Feature : Vitesse de saisie suspecte (colis < 60s d'intervalle) ─
        cursor.execute("""
            SELECT
                DATE(created_at) AS jour,
                COUNT(*) AS nb_saisies_rapides
            FROM (
                SELECT
                    created_at,
                    LAG(created_at) OVER (PARTITION BY created_by ORDER BY created_at) AS prev_created
                FROM lbp_colis
                WHERE created_by = %s AND created_at >= %s
            ) sub
            WHERE TIMESTAMPDIFF(SECOND, prev_created, created_at) < 60
              AND prev_created IS NOT NULL
            GROUP BY jour
        """, (uid, start_date_str))
        speed_data = _index_by_date(cursor.fetchall(), 'jour')

        # ── 7. Feature : Colis sans facture dans les 7 jours ─────────────────
        cursor.execute(f"""
            SELECT
                {date_expr} AS {date_label},
                COUNT(CASE WHEN f.id IS NULL
                    AND DATEDIFF(NOW(), c.created_at) > 7 THEN 1 END)
                    AS nb_colis_sans_facture_7j
            FROM lbp_colis c
            LEFT JOIN lbp_factures f ON f.colis_id = c.id
            WHERE c.created_by = %s AND c.created_at >= %s
            GROUP BY {date_label}
        """, (uid, start_date_str))
        sans_facture_data = _index_by_date(cursor.fetchall(), date_label)

        # ── 8. Fusion de toutes les périodes ──────────────────────────────────
        toutes_periodes = set(
            list(colis_data.keys()) +
            list(factures_data.keys()) +
            list(caisse_data.keys()) +
            list(audit_data.keys())
        )

        for periode in toutes_periodes:
            if not periode:
                continue

            c_row  = colis_data.get(periode, {})
            f_row  = factures_data.get(periode, {})
            cs_row = caisse_data.get(periode, {})
            a_row  = audit_data.get(periode, {})
            sp_row = speed_data.get(periode, {})
            sf_row = sans_facture_data.get(periode, {})

            features_list.append({
                'user_id':  uid,
                'semaine':  periode,

                # Colis
                'nb_colis':                   c_row.get('nb_colis', 0),
                'poids_moyen':                float(c_row.get('poids_moyen', 0.0)),
                'valeur_moyenne':             float(c_row.get('valeur_moyenne', 0.0)),
                'ratio_valeur_poids':         float(c_row.get('ratio_valeur_poids', 0.0)),
                'nb_annulations_colis':       c_row.get('nb_annulations_colis', 0),

                # Facturation
                'nb_factures':                f_row.get('nb_factures', 0),
                'montant_total_facture':      float(f_row.get('montant_total_facture', 0.0)),
                'montant_total_encaisse':     float(f_row.get('montant_total_encaisse', 0.0)),

                # Caisse
                'ecart_caisse_abs_cumule':    float(cs_row.get('ecart_caisse_abs_cumule', 0.0)),
                'nb_ecarts_caisse':           cs_row.get('nb_ecarts_caisse', 0),

                # Audit
                'nb_actions_audit':           a_row.get('nb_actions_audit', 0),
                'nb_audit_hors_horaires':     a_row.get('nb_audit_hors_horaires', 0),
                'nb_modifs_verrouillees':     a_row.get('nb_modifs_verrouillees', 0),

                # Nouvelles features (Phase 2)
                'nb_saisies_rapides':         sp_row.get('nb_saisies_rapides', 0),
                'nb_colis_sans_facture_7j':   sf_row.get('nb_colis_sans_facture_7j', 0),
                'taux_factures_incompletes':  float(f_row.get('taux_colis_sans_facture_complete', 0.0)),
            })

    conn.close()

    if not features_list:
        return pd.DataFrame()

    return pd.DataFrame(features_list)


def get_labels() -> dict:
    """
    Récupère les labels qualifiés par le DG depuis lbp_alertes_integrite.
    Retourne un dictionnaire {(user_id, semaine): label_0_ou_1}.
    """
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

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
        labels[(r['user_id'], sem_str)] = 1 if r['statut'] == 'confirmee' else 0

    return labels


def get_pseudo_labels() -> dict:
    """
    Génère des pseudo-labels depuis les alertes PHP graves/très graves
    pour amorcer le modèle supervisé (cold start).

    Règle :
    - gravite='tres_grave' ET statut != 'faux_positif' → label = 1
    - statut='justifiee' (DG a validé comme normal)    → label = 0

    Ces pseudo-labels ont moins d'autorité que les vrais labels DG,
    mais permettent d'activer le modèle supervisé plus rapidement.
    """
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT
            user_id,
            STR_TO_DATE(CONCAT(YEARWEEK(created_at, 1), ' Monday'), '%x%v %W') AS semaine,
            gravite,
            statut
        FROM lbp_alertes_integrite
        WHERE (
            (gravite = 'tres_grave' AND statut NOT IN ('faux_positif', 'justifiee'))
            OR statut = 'justifiee'
        )
        AND created_at >= DATE_SUB(NOW(), INTERVAL 52 WEEK)
    """)
    rows = cursor.fetchall()
    conn.close()

    pseudo_labels = {}
    for r in rows:
        sem_str = r['semaine'].strftime('%Y-%m-%d') if r['semaine'] else None
        if not sem_str:
            continue
        key = (r['user_id'], sem_str)
        # Les labels DG confirmés ont priorité → ne pas écraser
        if key not in pseudo_labels:
            pseudo_labels[key] = 1 if r['gravite'] == 'tres_grave' and r['statut'] != 'justifiee' else 0

    return pseudo_labels


def get_active_user_ids() -> list[int]:
    """Retourne la liste des IDs de tous les utilisateurs actifs."""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT id FROM users WHERE status = 'active'")
    ids = [row[0] for row in cursor.fetchall()]
    conn.close()
    return ids


# ── Helpers internes ──────────────────────────────────────────────────────────

def _index_by_date(rows: list, date_key: str) -> dict:
    """Indexe une liste de rows par la clé de date (semaine ou jour)."""
    result = {}
    for row in rows:
        dt = row.get(date_key)
        if dt is None:
            continue
        key = dt.strftime('%Y-%m-%d') if hasattr(dt, 'strftime') else str(dt)
        result[key] = row
    return result
