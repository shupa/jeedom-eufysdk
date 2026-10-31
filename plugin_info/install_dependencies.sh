#!/bin/bash
set -euo pipefail
BASE="$(cd "$(dirname "$0")/.." && pwd)"
python3 -m venv "$BASE/core/python/.venv"
"$BASE/core/python/.venv/bin/python" -m pip install -r "$BASE/core/python/requirements.txt"
