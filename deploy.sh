#!/bin/bash

WEB_HOST="home527810786.1and1-data.host"
WEB_BASE_PATH="/kunden/homepages/34/d527810786/htdocs/thrp-app"
WEB_USER="u77242903"
WEB_BASE_PATH="/kunden/homepages/34/d527810786/htdocs/thrp-app"
WEB_BASE_PATH_TEST="/kunden/homepages/34/d527810786/htdocs/thrp-app-test"
WEB_USER_HOST="$WEB_USER@$WEB_HOST"

#rsync -ahvz --exclude=".env" --exclude "." $WEB_USER@$WEB_HOST:$WEB_BASE_PATH"
#WEB_RELEASE_DIR="$WEB_USER@$WEB_HOST:$WEB_BASE_PATH_TEST"
WEB_RELEASE_DIR="$WEB_USER_HOST:$WEB_BASE_PATH"

#test="--dry-run"

# Non-interactive login: read DEPLOY_PASSWORD from .env (never committed) and let
# sshpass feed it to both the rsync and the ssh below. SSHPASS is used instead of
# `sshpass -p` so the password never shows up in the process list.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_CMD=(ssh)
if [ -f "$SCRIPT_DIR/.env" ]; then
  SSHPASS="$(sed -n 's/^[[:space:]]*DEPLOY_PASSWORD[[:space:]]*=[[:space:]]*//p' "$SCRIPT_DIR/.env" | head -n1)"
  # strip a single layer of surrounding quotes, if present
  SSHPASS="${SSHPASS%\"}"; SSHPASS="${SSHPASS#\"}"
  SSHPASS="${SSHPASS%\'}"; SSHPASS="${SSHPASS#\'}"
fi

if [ -n "$SSHPASS" ]; then
  if command -v sshpass >/dev/null 2>&1; then
    export SSHPASS
    SSH_CMD=(sshpass -e ssh)
  else
    echo "DEPLOY_PASSWORD is set but sshpass is not installed (sudo apt install sshpass) - falling back to manual password entry." >&2
  fi
else
  echo "No DEPLOY_PASSWORD found in .env - falling back to manual password entry." >&2
fi

npm run build

rsync $test -avzh \
  -e "${SSH_CMD[*]}" \
  --omit-dir-times \
  --no-o \
  --no-g \
  --exclude=".env" \
  --exclude=".env.*" \
  --exclude="bootstrap/cache" \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude="storage" \
  --exclude="public/storage" \
  --exclude="public/.htaccess" \
  --exclude="public/hot" \
  --delete \
  ./ "${WEB_RELEASE_DIR}/"

# config:clear runs first: bootstrap/cache is never rsynced, so a config cache
# left there would hide newly added config files (e.g. config/uploads.php).
"${SSH_CMD[@]}" $WEB_USER_HOST "cd $WEB_BASE_PATH && php8.4-cli artisan config:clear && php8.4-cli artisan migrate --force && php8.4-cli artisan storage:link && php8.4-cli artisan view:clear && php8.4-cli artisan cache:clear"
