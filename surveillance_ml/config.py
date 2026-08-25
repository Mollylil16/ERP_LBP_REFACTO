import os
import re
from pathlib import Path

# ─── Chargement du fichier .env local ────────────────────────────────────────
def _load_dotenv():
    """Charge le fichier .env s'il existe dans le dossier surveillance_ml/."""
    env_path = Path(__file__).parent / '.env'
    if env_path.exists():
        with open(env_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, _, value = line.partition('=')
                    os.environ.setdefault(key.strip(), value.strip())

_load_dotenv()

# ─── Helpers ─────────────────────────────────────────────────────────────────
def _env_str(key: str, default: str) -> str:
    return os.environ.get(key, default)

def _env_int(key: str, default: int) -> int:
    try:
        return int(os.environ.get(key, default))
    except (ValueError, TypeError):
        return default

def _env_float(key: str, default: float) -> float:
    try:
        return float(os.environ.get(key, default))
    except (ValueError, TypeError):
        return default

def _env_bool(key: str, default: bool) -> bool:
    val = os.environ.get(key, str(default)).lower()
    return val in ('true', '1', 'yes', 'on')

# ─── Connexion Base de données ────────────────────────────────────────────────
def _parse_php_db_config() -> dict:
    """
    Parse dynamiquement config/database.php pour extraire les credentials MySQL.
    Utilisé comme FALLBACK si les variables d'environnement ne sont pas définies.
    """
    db_config = {
        'host': 'localhost',
        'port': 3306,
        'dbname': 'lbp_db',
        'username': 'admin',
        'password': '',
    }

    base_dir = Path(__file__).parent.parent
    config_path = base_dir / 'config' / 'database.php'

    if config_path.exists():
        try:
            content = config_path.read_text(encoding='utf-8')
            patterns = {
                'host':     r"'host'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'",
                'port':     r"'port'\s*=>\s*.*?\?\s*.*?\s*:\s*(\d+)",
                'dbname':   r"'dbname'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'",
                'username': r"'username'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'",
                'password': r"'password'\s*=>\s*.*?\?\s*.*?\s*:\s*'([^']+)'",
            }
            for key, pattern in patterns.items():
                m = re.search(pattern, content)
                if m:
                    db_config[key] = int(m.group(1)) if key == 'port' else m.group(1)
        except Exception as e:
            print(f"[config] Erreur parsing database.php: {e}")

    return db_config

def _build_db_settings() -> dict:
    """
    Construit les paramètres DB en priorisant les variables d'environnement,
    puis le fichier database.php, et enfin les valeurs par défaut.
    """
    php_defaults = _parse_php_db_config()

    return {
        'host':     _env_str('DB_HOST',     php_defaults['host']),
        'port':     _env_int('DB_PORT',     php_defaults['port']),
        'dbname':   _env_str('DB_DATABASE', php_defaults['dbname']),
        'username': _env_str('DB_USERNAME', php_defaults['username']),
        'password': _env_str('DB_PASSWORD', php_defaults['password']),
    }

# ─── Paramètres exportés ─────────────────────────────────────────────────────
DB_SETTINGS = _build_db_settings()

ML_SETTINGS = {
    # Identification du modèle
    'version_modele': 'v2.0.0_2026',

    # Pondération de fusion des scores (doit totaliser 1.0)
    'weights': {
        'anomaly':    _env_float('ML_WEIGHTS_ANOMALY',    0.40),
        'drift':      _env_float('ML_WEIGHTS_DRIFT',      0.30),
        'supervised': _env_float('ML_WEIGHTS_SUPERVISED', 0.30),
    },

    # Modèle supervisé
    'supervised_min_labels':    _env_int('ML_SUPERVISED_MIN_LABELS',   50),
    'supervised_min_positives': _env_int('ML_SUPERVISED_MIN_POSITIVES', 10),
    'supervised_min_negatives': _env_int('ML_SUPERVISED_MIN_NEGATIVES', 10),

    # Dérive comportementale (Change Point Detection)
    'drift_min_weeks': _env_int('ML_DRIFT_MIN_WEEKS', 6),

    # Isolation Forest
    'contamination_auto':  _env_bool('ML_CONTAMINATION_AUTO',  True),   # True = depuis labels DG
    'contamination_fixed': _env_float('ML_CONTAMINATION_FIXED', 0.08),  # Fallback

    # Pseudo-labels (cold start du modèle supervisé via alertes PHP)
    'pseudo_labels_enabled': _env_bool('ML_PSEUDO_LABELS_ENABLED', True),

    # Versioning des modèles sauvegardés
    'model_versions_to_keep': _env_int('ML_MODEL_VERSIONS_TO_KEEP', 3),

    # Scheduler
    'schedule_hour':    _env_int('ML_SCHEDULE_HOUR',    23),
    'schedule_enabled': _env_bool('ML_SCHEDULE_ENABLED', True),
}
