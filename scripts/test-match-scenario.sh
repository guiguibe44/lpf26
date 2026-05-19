#!/usr/bin/env bash
# Scénario match test manuel — enchaîne une étape via Symfony CLI.
# Usage : source scripts/test-match-scenario.env && ./scripts/test-match-scenario.sh kickoff

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

STEP="${1:-info}"

if [[ -z "${MATCH_ID:-}" || "${MATCH_ID}" == "0" ]]; then
  echo "Définir MATCH_ID dans scripts/test-match-scenario.env" >&2
  exit 1
fi

run() {
  php bin/console app:test-match:step --match-id="$MATCH_ID" "$@"
}

case "$STEP" in
  info)
    run --step=info
    ;;
  reset-reminder)
    run --step=reset-reminder
    ;;
  reminder)
    run --step=reminder
    ;;
  kickoff)
    run --step=kickoff
    ;;
  goal-fr1)
    run --step=goal --buteur-id="${BUTEUR_FR_1}" --minute=23
    ;;
  goal-de1)
    run --step=goal --buteur-id="${BUTEUR_DE_1}" --minute=45
    ;;
  goal-fr2)
    run --step=goal --buteur-id="${BUTEUR_FR_2}" --minute=67
    ;;
  finish)
    run --step=finish
    ;;
  *)
    echo "Étapes : info | reset-reminder | reminder | kickoff | goal-fr1 | goal-de1 | goal-fr2 | finish" >&2
    exit 1
    ;;
esac
