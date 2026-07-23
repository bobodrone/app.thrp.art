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

npm run build

rsync $test -avzh \
  --omit-dir-times \
  --no-o \
  --no-g \
  --exclude=".env" \
  --exclude=".env.*" \
  --exclude="bootstrap/cache" \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude="storage" \
  --exclude="public/.htaccess" \
  --exclude="public/hot" \
  --delete \
  ./ "${WEB_RELEASE_DIR}/"

ssh $WEB_USER_HOST "cd $WEB_BASE_PATH && php8.4-cli artisan migrate --force && php8.4-cli artisan view:clear && php8.4-cli artisan cache:clear" 
