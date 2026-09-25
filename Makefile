.PHONY: help up down restart dev test lint migrate fresh bash typegen

help: ## ヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## バックエンド (Laravel Sail) をバックグラウンド起動
	cd backend && ./vendor/bin/sail up -d

down: ## バックエンド (Laravel Sail) を停止
	cd backend && ./vendor/bin/sail down

restart: down up ## バックエンド (Laravel Sail) を再起動

dev: ## フロントエンド開発サーバー (Vite) を起動
	cd frontend && npm run dev

test: ## バックエンドのテスト (Pest) を実行
	cd backend && ./vendor/bin/sail pest

analyze: ## バックエンドの静的解析 (PHPStan Level 8) を実行
	cd backend && ./vendor/bin/sail bin phpstan analyse

pint: ## バックエンドのコード整形 (Laravel Pint) を実行
	cd backend && ./vendor/bin/sail bin pint

lint: ## フロントエンドのコード検証 (ESLint)
	cd frontend && npm run lint

migrate: ## バックエンドのDBマイグレーションを実行
	cd backend && ./vendor/bin/sail artisan migrate

fresh: ## DBをリフレッシュして再マイグレーション
	cd backend && ./vendor/bin/sail artisan migrate:fresh

bash: ## Laravel Sail コンテナ内の bash に入る
	cd backend && ./vendor/bin/sail bash

typegen: ## OpenAPI仕様からフロントエンドのTypeScript型定義を自動生成 (Scribe導入後)
	cd frontend && npm run typegen
