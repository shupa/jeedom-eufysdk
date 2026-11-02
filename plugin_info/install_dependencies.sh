#!/bin/bash
set -euo pipefail
BASE="$(cd "$(dirname "$0")/.." && pwd)"
python3 -m venv "$BASE/core/python/.venv"
"$BASE/core/python/.venv/bin/python" -m pip install -r "$BASE/core/python/requirements.txt"

# Pré-construit l'image Docker du bridge (fork patché, voir docker/custom-bridge/) pendant
# l'installation des dépendances (asynchrone, pas de limite de temps PHP) plutôt que de laisser le
# premier "démarrer le démon" (appel AJAX synchrone) déclencher la construction et risquer un timeout.
php -r "require '$BASE/core/class/eufysdk.class.php'; eufysdk::dockerCompose('build');" || true
