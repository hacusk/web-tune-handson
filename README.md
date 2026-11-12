# 40分で挑戦！入門Webパフォーマンスチューニング

このハンズオンでは、架空のレビューサイト「ラーメンRunner」を題材に、パフォーマンスチューニングに挑戦します。
ベンチマークによる性能計測からスタートし、SQLログや実行計画を手がかりにボトルネックを調査します。そして、N+1問題の解消やインデックスの追加など、実際のコードやSQLを修正して再計測するところまでを体験します。

目指すのは、単にWebアプリを高速化することではありません。「なぜ遅かったのか」「なぜ速くなったのか」を、計測結果に基づいて説明できるようになることです。

**予定している内容**

- `scripts/bench`（k6）によるパフォーマンス計測
- SQLログを利用したN+1問題の発見と解消
- before / after の再計測と `compare` による比較
- SQL実行計画の確認
- インデックス追加によるパフォーマンス改善

この README は事前の環境構築用です。

事前にご質問等がある場合は [X(@hacusk)](https://x.com/hacusk) までご連絡ください。

## 参加者の皆様へ（事前準備）

**イメージの取得と初回起動は、事前に済ませておいてください。**

次のどちらかで起動できます。PHP・MySQL・k6 を自分のPCに入れる必要はありません。Windows では Docker Desktop ＋ WSL2 のシェルを使ってください。

### Dev Container（推奨）

Docker Desktop と Cursor / VS Code（Dev Containers 拡張）が必要です。

1. Docker Desktop を起動する
2. このフォルダを Cursor / VS Code で開く
3. 通知の「Reopen in Container」（またはコマンドパレット → `Dev Containers: Reopen in Container`）を選ぶ
4. 初回はイメージ取得・依存のインストール・DB投入で数分かかる
5. 完了後に http://localhost:8080 を開き、画面が出ることを確認する
6. 正しさチェックを実行して成功することを確認する
```sh
# Dev Container の場合
php tests/check.php
```
`正しさチェック成功` と出れば OK です。

### Docker Compose

```sh
docker compose up -d --build
docker pull grafana/k6:1.6.0@sha256:be2a9ceb1b6ffc573277af4157135882a6d6433968b41e858ab02c5fd5847532
```

起動後に http://localhost:8080 を開き、画面が出ることを確認してください。続けて正しさチェックを実行します。
```sh
# Docker Compose の場合
docker compose exec -T app php /work/tests/check.php
```
`正しさチェック成功` と出れば OK です。

状態は `docker compose ps`、ログは `docker compose logs db app` で見られます。8080番が使われているときは、`compose.yaml` の公開ポート左側を変えてください。

止めるときは `docker compose down` です。DBごと消すときは `docker compose down -v` です。

## ディレクトリ構成

```text
.
├── app/                 # Laravel アプリ
├── bench/               # k6 の負荷シナリオ
├── db/                  # 初期データ（init.sql）
├── scripts/             # 計測・比較などの補助スクリプト
├── tests/               # 正しさチェック
├── solutions/           # 解答例
├── .devcontainer/       # Dev Container 用定義
├── compose.yaml         # Docker Compose 定義
└── Dockerfile           # アプリ用イメージ
```

| パス | 内容 |
| --- | --- |
| `app/app/Queries/ShopQuery.php` | 主な課題 |
| `db/init.sql` | 固定シード（1000番店舗はレビュー0件） |
