import os
import re

def parse_php_db_config():
    """
    Parse dynamiquement le fichier config/database.php de l'ERP pour extraire
    les identifiants de connexion MySQL. Permet d'éviter la duplication de config.
    """
    db_config = {
        'host': 'localhost',
        'port': 3306,
        'dbname': 'lbp_db',
        'username': 'admin',
        'password': '@Succes2019',
    }
    
    # Résoudre le chemin de config/database.php
    base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
    config_path = os.path.join(base_dir, 'config', 'database.php')
    
    if os.path.exists(config_path):
        try:
            with open(config_path, 'r', encoding='utf-8') as f:
                content = f.read()
                
            # Regex pour extraire les valeurs par défaut
            host_match = re.search(r"'host'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'", content)
            port_match = re.search(r"'port'\s*=>\s*.*?\?\s*.*?\s*:\s*(\d+)", content)
            dbname_match = re.search(r"'dbname'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'", content)
            user_match = re.search(r"'username'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'", content)
            pass_match = re.search(r"'password'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'", content)
            
            if host_match:
                db_config['host'] = host_match.group(1)
            if port_match:
                db_config['port'] = int(port_match.group(1))
            if dbname_match:
                db_config['dbname'] = dbname_match.group(1)
            if user_match:
                db_config['username'] = user_match.group(1)
            if pass_match:
                db_config['password'] = pass_match.group(1)
                
        except Exception as e:
            print(f"[config] Erreur lors du parsing de database.php: {e}")
            
    return db_config

# Charger les paramètres
DB_SETTINGS = parse_php_db_config()

# Configuration du Moteur ML
ML_SETTINGS = {
    'version_modele': 'v1.0.0_2026',
    'weights': {
        'anomaly': 0.40,
        'drift': 0.30,
        'supervised': 0.30
    },
    'supervised_min_labels': 50,      # Seuil d'activation du modèle supervisé
    'supervised_min_positives': 10,
    'supervised_min_negatives': 10,
    'drift_min_weeks': 6,             # Historique min requis pour change point detection
}
