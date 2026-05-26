#!/usr/bin/env bash
# Déploiement rapide vers OVH : rsync + Composer + migrations + cache (SSH).
# Prérequis : fichier .env.deploy (voir .env.deploy.example), accès SSH, Composer sur le serveur.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DRY_RUN=0
for arg in "$@"; do
  if [[ "$arg" == "--dry-run" ]]; then
    DRY_RUN=1
  fi
done

ENV_FILE="$ROOT/.env.deploy"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Fichier manquant : $ENV_FILE" >&2
  echo "Copiez .env.deploy.example vers .env.deploy et renseignez DEPLOY_*." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

: "${DEPLOY_SSH_HOST:?Variable DEPLOY_SSH_HOST manquante}"
: "${DEPLOY_SSH_USER:?Variable DEPLOY_SSH_USER manquante}"
: "${DEPLOY_REMOTE_PATH:?Variable DEPLOY_REMOTE_PATH manquante}"

DEPLOY_PHP_BIN="${DEPLOY_PHP_BIN:-php}"
DEPLOY_COMPOSER="${DEPLOY_COMPOSER:-composer}"

REMOTE="${DEPLOY_SSH_USER}@${DEPLOY_SSH_HOST}"
RPATH="${DEPLOY_REMOTE_PATH%/}"

# Mot de passe : BatchMode=yes interdit la saisie interactive → désactiver avec
# DEPLOY_SSH_PASSWORD_AUTH=1 dans .env.deploy (multiplexage = un seul mot de passe par run).
CTL_SOCK=""
cleanup_ssh_master() {
  if [[ -n "${CTL_SOCK}" ]] && [[ -S "${CTL_SOCK}" ]]; then
    ssh -S "${CTL_SOCK}" -O exit "${REMOTE}" 2>/dev/null || true
  fi
}

if [[ "${DEPLOY_SSH_PASSWORD_AUTH:-0}" == "1" ]]; then
  CTL_SOCK="${TMPDIR:-/tmp}/lpf26-ovh-ssh.${USER}.$$"
  trap cleanup_ssh_master EXIT INT TERM
  # Évite « Too many authentication failures » si beaucoup de clés dans ssh-agent
  SSH_EXTRA="-o StrictHostKeyChecking=accept-new -o PreferredAuthentications=password,keyboard-interactive -o PubkeyAuthentication=no -o ControlMaster=auto -o ControlPath=${CTL_SOCK} -o ControlPersist=600"
else
  SSH_EXTRA="-o BatchMode=yes -o StrictHostKeyChecking=accept-new"
fi
if [[ -n "${DEPLOY_SSH_KEY:-}" ]]; then
  SSH_EXTRA+=" -i ${DEPLOY_SSH_KEY}"
fi

RSYNC_SSH="ssh ${SSH_EXTRA}"

echo "==> Vérification SSH vers ${REMOTE} …"
ssh ${SSH_EXTRA} "${REMOTE}" "echo OK && cd '${RPATH}' && pwd"

RSYNC_FLAGS=(-avz)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(--dry-run)
else
  RSYNC_FLAGS+=(--delete)
  echo "==> Tailwind (sur ta machine : le CLI Tailwind v4 sur mutualisé OVH échoue souvent) …"
  mkdir -p "${ROOT}/var/tailwind"
  (cd "${ROOT}" && APP_ENV=prod APP_DEBUG=0 "${DEPLOY_PHP_BIN}" bin/console tailwind:build --no-interaction)
fi

echo "==> Rsync projet → ${REMOTE}:${RPATH}/"
# shellcheck disable=SC2086
rsync "${RSYNC_FLAGS[@]}" \
  -e "${RSYNC_SSH}" \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude 'var/' \
  --exclude 'node_modules/' \
  --exclude '.env.local' \
  --exclude '.env.*.local' \
  --exclude '.env.deploy' \
  --exclude '.env.deploy.*' \
  --exclude 'phpunit.xml' \
  --exclude '.phpunit.cache/' \
  --exclude 'tests/' \
  --exclude 'public/uploads/' \
  --exclude 'vendor/' \
  --exclude 'composer.phar' \
  --exclude 'public/assets/' \
  --exclude 'src/Command/JokerTestScenario*.php' \
  --exclude 'src/Controller/Admin/AdminJokerTestScenarioController.php' \
  --exclude 'src/Data/JokerTestScenarioDefinition.php' \
  --exclude 'src/Service/JokerTestScenario*.php' \
  --exclude 'templates/admin/joker_test_scenario.html.twig' \
  --exclude 'public/css/admin-joker-test-scenario.css' \
  ./ "${REMOTE}:${RPATH}/"

if [[ "$DRY_RUN" -eq 0 ]]; then
  echo "==> Rsync var/tailwind/*.built.css (CSS Tailwind compilé en local, sans le binaire) …"
  ssh ${SSH_EXTRA} "${REMOTE}" "mkdir -p '${RPATH}/var/tailwind'"
  shopt -s nullglob
  built=( "${ROOT}/var/tailwind"/*.built.css )
  shopt -u nullglob
  if [[ ${#built[@]} -eq 0 ]]; then
    echo "Aucun fichier *.built.css dans var/tailwind/. Lance d’abord : php bin/console tailwind:build" >&2
    exit 1
  fi
  rsync -avz -e "${RSYNC_SSH}" "${built[@]}" "${REMOTE}:${RPATH}/var/tailwind/"
else
  if [[ -f "${ROOT}/var/tailwind/app.built.css" ]]; then
    echo "==> Rsync var/tailwind/*.built.css (simulation) …"
    ssh ${SSH_EXTRA} "${REMOTE}" "mkdir -p '${RPATH}/var/tailwind'"
    rsync -avz --dry-run -e "${RSYNC_SSH}" "${ROOT}/var/tailwind"/*.built.css "${REMOTE}:${RPATH}/var/tailwind/"
  fi
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "Mode --dry-run : rien n’a été modifié sur le serveur."
  exit 0
fi

echo "==> Permissions favicons / images statiques (lecture web) …"
ssh ${SSH_EXTRA} "${REMOTE}" "F='${RPATH}/public/images/favicon-lpf'; if [[ -d \"\$F\" ]]; then find \"\$F\" -type d -exec chmod 755 {} +; find \"\$F\" -type f -exec chmod 644 {} +; fi; [[ -f '${RPATH}/public/favicon.ico' ]] && chmod 644 '${RPATH}/public/favicon.ico' || true"

echo "==> Composer (installateur sur le serveur si absent) …"
ssh ${SSH_EXTRA} "${REMOTE}" "cd '${RPATH}' && if [[ ! -f composer.phar ]]; then curl -sS https://getcomposer.org/installer | php; fi"

echo "==> Composer sur le serveur …"
ssh ${SSH_EXTRA} "${REMOTE}" "cd '${RPATH}' && ${DEPLOY_COMPOSER} install --no-interaction --prefer-dist --optimize-autoloader"

echo "==> Assets (prod) …"
ssh ${SSH_EXTRA} "${REMOTE}" "cd '${RPATH}' && APP_ENV=prod APP_DEBUG=0 ${DEPLOY_PHP_BIN} bin/console asset-map:compile --no-interaction"

echo "==> Migrations …"
ssh ${SSH_EXTRA} "${REMOTE}" "cd '${RPATH}' && APP_ENV=prod APP_DEBUG=0 ${DEPLOY_PHP_BIN} bin/console doctrine:migrations:migrate --no-interaction"

echo "==> Recalcul des points pronos (barème / cotes) …"
ssh ${SSH_EXTRA} "${REMOTE}" "cd '${RPATH}' && APP_ENV=prod APP_DEBUG=0 ${DEPLOY_PHP_BIN} bin/console app:match:rescore-all --no-interaction"

echo "==> Cache prod …"
# Sur mutualisé OVH, cache:clear seul échoue parfois (« Directory not empty » sur var/cache/prod).
# On force la suppression puis warmup (équivalent fonctionnel à clear + warmup).
ssh ${SSH_EXTRA} "${REMOTE}" "cd '${RPATH}' && (chmod -R u+w var/cache 2>/dev/null || true) && rm -rf var/cache/* && APP_ENV=prod APP_DEBUG=0 ${DEPLOY_PHP_BIN} bin/console cache:warmup --no-interaction"

echo "Terminé."
