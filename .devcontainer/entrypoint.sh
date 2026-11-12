#!/bin/sh
set -eu
cd /workspaces/web-tune-handson/app
if [ ! -f vendor/autoload.php ]; then
  echo 'Composer 依存をインストールしています…'
  composer install --no-interaction --prefer-dist
fi
if [ ! -f .env ]; then
  cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi
mkdir -p /results /workspaces/web-tune-handson/results \
  storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
exec php artisan serve --host=0.0.0.0 --port=3000
