#!/bin/sh
set -eu
cd /workspaces/web-tune-handson
mkdir -p results /results
echo 'アプリの準備を待っています（初回は Composer install と DB 投入で数分かかることがあります）…'
i=0
while [ "$i" -lt 180 ]; do
  if [ -f app/vendor/autoload.php ] && curl -sf "http://127.0.0.1:3000/health" >/dev/null; then
    echo '準備完了: http://localhost:8080 を開いてください'
    exit 0
  fi
  i=$((i + 1))
  sleep 2
done
echo 'タイムアウトしました。コンテナログを確認してください。' >&2
exit 1
