#!/usr/bin/env bash
# Affiche les URLs GET /cron/test-match-step à coller dans cron-job.org (scénario match test).
# Ne mettez pas CRON_SECRET dans un fichier versionné : exportez-le dans le shell.
#
# Usage :
#   source scripts/test-match-scenario.env
#   export CRON_SECRET='…'   # même valeur que CRON_SECRET sur le serveur
#   export BASE_URL='https://26.lotopotofoot.fr'
#   ./scripts/print-test-match-cron-urls.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT}/scripts/test-match-scenario.env"

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  set -a
  source "${ENV_FILE}"
  set +a
fi

: "${BASE_URL:=https://26.lotopotofoot.fr}"
BASE_URL="${BASE_URL%/}"

if [[ -z "${CRON_SECRET:-}" ]]; then
  echo "Définir CRON_SECRET dans l’environnement (export CRON_SECRET='…')." >&2
  exit 1
fi

if [[ -z "${MATCH_ID:-}" || "${MATCH_ID}" == "0" ]]; then
  echo "Définir MATCH_ID dans scripts/test-match-scenario.env (ou l’environnement)." >&2
  exit 1
fi

# Token openssl rand -hex : pas de caractères réservés URL en général.
step_url() {
  local extra="$1"
  printf '%s/cron/test-match-step?token=%s&match_id=%s%s\n' "${BASE_URL}" "${CRON_SECRET}" "${MATCH_ID}" "${extra}"
}

echo "=== Coller dans cron-job.org (GET), fuseau Europe/Paris recommandé ==="
echo ""
echo "# 1 — Relance pronos (ex. 14:00 le jour J)"
step_url "&step=reminder"
echo ""
echo "# 2 — Coup d’envoi (ex. 15:00)"
step_url "&step=kickoff"
echo ""

if [[ -z "${BUTEUR_FR_1:-}" || "${BUTEUR_FR_1}" == "0" ]]; then
  echo "# 3–5 — Renseigner BUTEUR_FR_1, BUTEUR_DE_1, BUTEUR_FR_2 dans test-match-scenario.env pour les URLs des buts." >&2
else
  echo "# 3 — 1er but France (ex. 15:23, minute 23)"
  step_url "&step=goal&buteur_id=${BUTEUR_FR_1}&minute=23"
  echo ""
  if [[ -n "${BUTEUR_DE_1:-}" && "${BUTEUR_DE_1}" != "0" ]]; then
    echo "# 4 — But extérieur (ex. 15:45, minute 45)"
    step_url "&step=goal&buteur_id=${BUTEUR_DE_1}&minute=45"
    echo ""
  fi
  if [[ -n "${BUTEUR_FR_2:-}" && "${BUTEUR_FR_2}" != "0" ]]; then
    echo "# 5 — 2e but France (ex. 16:07, minute 67)"
    step_url "&step=goal&buteur_id=${BUTEUR_FR_2}&minute=67"
    echo ""
  fi
fi

echo "# 6 — Fin de match (ex. 17:05)"
step_url "&step=finish"
echo ""
echo "=== Titres suggérés dans cron-job.org ==="
echo "LPF26 test — reminder | kickoff | goal FR1 | goal DE | goal FR2 | finish"
echo ""
echo "Après le test : désactiver ou supprimer ces tâches (sinon elles se réexécutent selon le planning)."
