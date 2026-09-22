# 00. 開発環境セットアップガイド (Development Setup Guide)

本ドキュメントは、本プロジェクト（バックエンド: Laravel 13 + Sail / フロントエンド: React + Vite + TypeScript）のローカル開発環境構築手順および初期化コマンドの記録です。

---

## 1. 前提条件 (Prerequisites)

ローカルマシンに以下のツールがインストール・稼働している必要があります。

- **OS**: macOS / Linux
- **コンテナ環境**: Docker Desktop または **OrbStack**（推奨: メモリ消費が少なく高速）
  - `docker info` でデーモンが稼働していることを確認
- **Node.js**: v20.x 以上（LTS推奨）
  - バージョン管理ツール（**fnm**, mise, asdf 等）経由での導入を推奨
  - `node -v` および `npm -v` で確認
- **Git**: v2.x 以上

---

## 2. 初回プロジェクト初期化手順（構築ログ）

本プロジェクトをスクラッチから構築した際の手順です。

### 2.1 Gitリポジトリ初期化
```bash
# プロジェクトルートでGitを初期化
git init
```

### 2.2 バックエンド初期化 (Laravel 13 + Sail)
```bash
# Docker (OrbStack) が起動していることを確認
docker info

# Laravel 13 公式スクリプトで backend/ を作成 (MySQL, Mailpit 同梱)
curl -s "https://laravel.build/backend?with=mysql,mailpit" | bash

# 子リポジトリの.gitが作られた場合は削除してルートGitに統合 (念のため確認)
rm -rf backend/.git
```

### 2.3 フロントエンド初期化 (React + Vite + TypeScript)
```bash
# ルート直下で frontend/ を作成
npx create-vite@latest frontend --template react-ts
# (Linter には業界標準の ESLint を選択)

# 依存ライブラリのインストール
cd frontend && npm install && cd ..
```

---

## 3. リポジトリ取得後の開発環境セットアップ（再現手順）

新規マシンやクローン直後の環境で開発を開始する手順です。

### 3.1 リポジトリのクローン
```bash
git clone <repository-url>
cd job-tracker
```

### 3.2 バックエンドの起動 (Laravel Sail)
```bash
cd backend

# .env の作成 (存在しない場合)
cp .env.example .env

# Sail コンテナのビルド & バックグラウンド起動
./vendor/bin/sail up -d

# アプリケーションキーの生成
./vendor/bin/sail artisan key:generate

# マイグレーションの実行
./vendor/bin/sail artisan migrate

cd ..
```

### 3.3 フロントエンドの起動 (Vite)
```bash
cd frontend

# 依存パッケージのインストール
npm install

# 開発サーバーの起動 (HMR)
npm run dev
```

ブラウザで `http://localhost:5173` にアクセスし、画面が表示されれば成功です。

---

## 4. テストの実行

### バックエンドテスト (Pest)
```bash
cd backend
./vendor/bin/sail pest
```

### フロントエンド静的チェック (ESLint & TypeScript)
```bash
cd frontend
npm run lint
npx tsc --noEmit
```

---

## 5. トラブルシューティング

1. **`Docker is not running.` と表示される**:
   - Docker Desktop または OrbStack アプリが起動しているか確認してください。
2. **ポート衝突 (`address already in use` 3306 / 80)**:
   - ホストマシン側で別の MySQL や Apache/Nginx が動いていないか確認し、停止するか `backend/.env` の `APP_PORT` / `FORWARD_DB_PORT` を変更してください。
