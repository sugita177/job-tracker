# Job Tracker (求人応募・選考ステータストラッカー)

複数媒体（転職サイト・エージェント・各社採用ページ）にまたがる企業・ポジション情報、面談/面接日程、選考ステータスを一元管理するためのWebアプリケーションです。

モダンWeb開発（PHP 8.5 / Laravel 13、React、TypeScript、軽量DDD、TDD）のベストプラクティスを取り入れ、保守性・テスタビリティ・堅牢なセキュリティを追求した設計・実装を行っています。

---

## 1. 解決する課題と主な機能

エンジニアの転職活動において頻発する「情報の散乱」「優先順位の形骸化」「選考メモの分散」を解決します。

- **応募前検討 (Interested) & 優先度管理**:
  - 気になる企業・求人URLをストックし、志望度（★★★ / ★★☆ / ★☆☆）や下調べメモを記録。
  - スカウト受諾後の「カジュアル面談」フェーズから柔軟にトラッキング可能。
- **選考プロセス & 面接ログの一元化**:
  - 面談・面接（1次〜最終面接）の日時、URL、担当面接官、事前準備メモ、事後振り返りをタイムラインで集約。
- **ヒューマンエラー防止（ステータス誤操作の訂正機能）**:
  - 通常の選考ステップ進行とは別に、理由を伴う「ステータス訂正（Correction）」機能を備え、監査証跡とデータ整合性を両立。
- **完全なマルチテナント隔離 (User-scoped)**:
  - ユーザーごとのデータを4重の防壁（認証・認可Policy・UseCase注入・DBスコープ）で安全に隔離。

---

## 2. 技術スタック & アーキテクチャ

### 2.1 テクノロジースタック

| 分野 | 採用技術 | 選定理由・特徴 |
| :--- | :--- | :--- |
| **Backend** | **PHP 8.5 / Laravel 13** | 最新のPHP/Laravel機能を活用。Laravel Sail (Docker) による開発環境。 |
| **Testing** | **Pest PHP** | 自然言語に近い記法による高速TDD。DDDレイヤー原則を検証する **Arch Testing** を導入。 |
| **Frontend** | **React / Vite / TypeScript** | 高速なHMR開発環境と静的型付けによる堅牢なフロントエンド開発。 |
| **API Docs & Types** | **Scribe** + **openapi-typescript** | コントローラ/FormRequestからAPI仕様・OpenAPI 3.0を自動抽出。フロントエンドのTypeScript型と自動同期。 |
| **Auth & Security** | **Laravel Sanctum** | SPA推奨の httpOnly Cookie 認証 + CSRF保護トークンによる高セキュアなセッション管理。 |
| **Database** | **MySQL 8.x** | Eloquent ORM（データ永続化）とドメインエンティティの相互マッピング。 |

### 2.2 アーキテクチャ設計 (Lightweight DDD)
Laravel標準の利便性（FormRequest, Resource, Policy）を活かしつつ、ビジネスロジックをフレームワーク非依存の純粋なPHPクラス（ドメイン層）にカプセル化する **「軽量ドメイン駆動設計」** を採用しています。

- **Presentation Layer**: HTTPリクエスト受付、入力バリデーション、JSON変換
- **Application Layer**: UseCase（業務シナリオの調整・トランザクション制御）
- **Domain Layer**: エンティティ（`JobApplication` を集約ルートとする境界づけ）、値オブジェクト、状態遷移ガード、ドメイン例外
- **Infrastructure Layer**: Eloquentモデル（永続化データ構造）、リポジトリ実装

---

## 3. 設計・環境構築ドキュメント (`docs/`)

開発着手前に作成した詳細な設計書およびセットアップ手順書を公開しています。

- [00. 開発環境セットアップガイド (Development Setup Guide)](docs/00_setup_guide.md)
  - 前提環境（OrbStack, Node.js）、初期構築ログ、クローン後の再現手順
- [01. 要件定義書 (Requirements Specification)](docs/01_requirements.md)
  - ペルソナ、応募前から結果までの業務フロー、MVPスコープ定義
- [02. ドメインモデル設計 (Domain Model & Ubiquitous Language)](docs/02_domain_model.md)
  - ユビキタス言語辞書、集約の境界、ステータス順遷移マトリクス、不変条件
- [03. アーキテクチャ設計書 (Architecture Design)](docs/03_architecture.md)
  - 4層レイヤー責務、Eloquentマッピング方針、User-scoped多重防御、Pestテスト戦略
- [04. API設計書 (RESTful API Design)](docs/04_api_design.md)
  - RESTfulエンドポイント仕様、進行/訂正API、Scribeによるスキーマ駆動開発パイプライン
- [05. 画面設計書 (Screen Design & UI Specifications)](docs/05_screen_design.md)
  - 画面一覧、Mermaid画面遷移図、ダッシュボード/詳細/訂正モーダルのワイヤーフレーム
- [Architecture Decision Records (ADR)](docs/adr/0001-use-lightweight-ddd.md)
  - ADR 0001: 軽量ドメイン駆動設計 (Lightweight DDD) の採用
- [開発・技術学習ログ (Development & Learning Logs)](docs/dev_logs/README.md)
  - 実装過程で得られた言語仕様（PHP 8 match式、SPL例外）、Pestの挙動、静的解析の知見ナレッジベース


---

## 4. プロジェクト構成

バックエンドとフロントエンドを1つのリポジトリで管理しつつ、関心事を明確に分離したモノレポ構成を採用しています。

```text
job-tracker/
├── Makefile                    # 統合開発コマンド (make dev, make test 等)
├── docs/                       # 設計ドキュメント・環境構築ガイド・ADR群
├── backend/                    # Laravel 13 API (Laravel Sail / Docker)
└── frontend/                   # React + TypeScript SPA (Vite)
```

---

## 5. ローカル開発環境の起動方法

詳細な手順や前提ツールの導入は [00. 開発環境セットアップガイド](docs/00_setup_guide.md) をご覧ください。

```bash
# 1. リポジトリのクローン
git clone <repository-url>
cd job-tracker

# 2. 開発環境の起動 (バックエンド + フロントエンド)
make dev

# 3. バックエンドテストの実行 (Pest)
make test
```


---

## 6. ライセンス & セキュリティについて
- 本リポジトリはポートフォリオおよび技術検証目的で公開されています。
- リポジトリ内のデータ・企業名・求人情報はすべて検証用の架空データであり、実在の個人・組織の機密情報は含まれておりません。
