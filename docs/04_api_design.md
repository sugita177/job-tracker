# 04. API設計書 (RESTful API Design)

## 1. API共通仕様 & 設計原則

### 1.1 基本原則
- **RESTful アーキテクチャ**: HTTPメソッド（`GET`, `POST`, `PUT`, `DELETE`）を意味論通りに使い、リソース（名詞）をURIで表現する。
- **User-scoped URL設計**: URLパスに `users/{userId}/...` を含めず、常にセッション認証されたユーザー（`$request->user()`）のコンテキストでリソースを解決する（例: `GET /api/job-applications` でログインユーザー自身の求人のみ取得）。
- **ステータスコード**:
  - `200 OK`: 取得・更新成功
  - `201 Created`: 新規作成成功（`Location` ヘッダーまたは作成リソースを返却）
  - `204 No Content`: 削除成功（レスポンスボディなし）
  - `400 Bad Request`: 不正なリクエスト構文
  - `401 Unauthorized`: 未ログイン / セッション切れ
  - `403 Forbidden`: 認可エラー（他ユーザーのリソースへのアクセス試行など）
  - `404 Not Found`: 指定リソースが存在しない
  - `422 Unprocessable Content`: バリデーションエラー、またはドメイン不変条件違反（例: 不正な状態遷移、ガード条件違反）

### 1.2 共通レスポンス構造

#### 成功時レスポンス (`JsonResource`)
```json
{
  "data": { ... }
}
```

#### エラー時レスポンス（RFC 7807 準拠のLaravel標準）
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "現在のステータス [INTERESTED] から [OFFERED] への遷移は許可されていません。"
    ]
  }
}
```

---

## 2. エンドポイント一覧 (Endpoint Matrix)

### 2.1 認証系 (Auth & Session)
※Laravel Sanctum SPA認証仕様

| Method | Endpoint | 概要 | 認証 |
| :--- | :--- | :--- | :---: |
| `GET` | `/sanctum/csrf-cookie` | CSRF保護Cookieの発行 | 不要 |
| `POST` | `/api/register` | 新規ユーザー登録 | 不要 |
| `POST` | `/api/login` | ログイン (Cookieセッション開始) | 不要 |
| `POST` | `/api/logout` | ログアウト (セッション破棄) | 要 |
| `GET` | `/api/me` | ログイン中ユーザー情報の取得 | 要 |

---

### 2.2 企業管理 (Companies)

| Method | Endpoint | 概要 |
| :--- | :--- | :--- |
| `GET` | `/api/companies` | 登録企業一覧の取得 |
| `POST` | `/api/companies` | 企業の新規登録 |
| `GET` | `/api/companies/{companyId}` | 企業詳細の取得 (紐づく応募一覧含む) |
| `PUT` | `/api/companies/{companyId}` | 企業情報の更新 |
| `DELETE` | `/api/companies/{companyId}` | 企業の削除 |

---

### 2.3 求人応募管理 (Job Applications - 集約ルート)

| Method | Endpoint | 概要 | クエリパラメータ / 補足 |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/job-applications` | 応募・検討中一覧取得 | `?status=...&priority=...` で絞り込み |
| `POST` | `/api/job-applications` | 新規登録（検討中 or 応募済） | 初期ステータス: `INTERESTED` or `DOCUMENT_SCREENING` |
| `GET` | `/api/job-applications/{id}` | 応募詳細取得 | 選考ステップ・履歴を含む |
| `PUT` | `/api/job-applications/{id}` | 基本情報の更新 | 募集URL、優先度、メモ等の更新 |
| `DELETE` | `/api/job-applications/{id}` | 応募の削除 | 論理削除または物理削除 |
| `POST` | `/api/job-applications/{id}/advance-status` | **通常のステータス進行** | 許可された遷移先への進捗 |
| `POST` | `/api/job-applications/{id}/correct-status` | **ステータス誤操作の訂正** | 訂正理由（`reason`）が必須 |

---

### 2.4 選考ステップ管理 (Selection Steps - 集約内子エンティティ)
※`JobApplication` 集約ルートを経由して操作

| Method | Endpoint | 概要 |
| :--- | :--- | :--- |
| `POST` | `/api/job-applications/{id}/selection-steps` | 選考ステップ（面接・面談）の追加 |
| `PUT` | `/api/job-applications/{id}/selection-steps/{stepId}` | 選考ステップの更新（振り返りメモ・合否等） |
| `DELETE` | `/api/job-applications/{id}/selection-steps/{stepId}` | 選考ステップの削除 |

---

## 3. 主要エンドポイントのリクエスト・レスポンス詳細

### 3.1 求人の新規登録 (`POST /api/job-applications`)

#### Request Body
```json
{
  "company_id": 1,
  "title": "バックエンドエンジニア (PHP/Laravel)",
  "job_url": "https://example.com/jobs/123",
  "priority": "HIGH",
  "status": "INTERESTED", // または DOCUMENT_SCREENING
  "channel": {            // status が DOCUMENT_SCREENING の場合は必須
    "type": "MEDIA",
    "detail_name": "転職サービス名"
  },
  "applied_at": null,     // status が DOCUMENT_SCREENING の場合は "2026-10-01" 必須
  "notes": "カジュアル面談で技術スタックを確認したい"
}
```

#### Response (201 Created)
```json
{
  "data": {
    "id": 10,
    "company": {
      "id": 1,
      "name": "株式会社サンプル"
    },
    "title": "バックエンドエンジニア (PHP/Laravel)",
    "job_url": "https://example.com/jobs/123",
    "priority": "HIGH",
    "current_status": "INTERESTED",
    "channel": null,
    "applied_at": null,
    "notes": "カジュアル面談で技術スタックを確認したい",
    "selection_steps": [],
    "created_at": "2026-10-01T10:00:00Z"
  }
}
```

---

### 3.2 通常のステータス進行 (`POST /api/job-applications/{id}/advance-status`)

ビジネスフローに沿ってステータスを進めるエンドポイント。不正な遷移（例: 検討中からいきなり内定）は 422 エラーとなる。

#### Request Body (例: 検討中 → 書類選考へ応募)
```json
{
  "to_status": "DOCUMENT_SCREENING",
  "applied_at": "2026-10-05",
  "channel": {
    "type": "DIRECT",
    "detail_name": "コーポレート採用サイト"
  }
}
```

#### Response (200 OK)
```json
{
  "data": {
    "id": 10,
    "current_status": "DOCUMENT_SCREENING",
    "applied_at": "2026-10-05",
    "channel": {
      "type": "DIRECT",
      "detail_name": "コーポレート採用サイト"
    },
    "updated_at": "2026-10-05T12:00:00Z"
  }
}
```

---

### 3.3 ステータス誤操作の訂正 (`POST /api/job-applications/{id}/correct-status`)

誤って変更したステータスを戻す、または修正するための専用エンドポイント。**訂正理由 (`reason`)** が必須。

#### Request Body
```json
{
  "to_status": "INTERVIEW_IN_PROGRESS",
  "reason": "操作ミスでお見送りを押してしまったため、2次面接進行中に差し戻し"
}
```

#### Response (200 OK)
```json
{
  "data": {
    "id": 10,
    "current_status": "INTERVIEW_IN_PROGRESS",
    "latest_history": {
      "from_status": "REJECTED",
      "to_status": "INTERVIEW_IN_PROGRESS",
      "type": "CORRECTION",
      "reason": "操作ミスでお見送りを押してしまったため、2次面接進行中に差し戻し",
      "changed_at": "2026-10-06T15:30:00Z"
    }
  }
}
```

---

### 3.4 選考ステップ（面談・面接）の登録 (`POST /api/job-applications/{id}/selection-steps`)

#### Request Body
```json
{
  "type": "FIRST_ROUND",
  "scheduled_at": "2026-10-12T19:00:00+09:00",
  "location_or_url": "https://meet.google.com/xxx-yyyy-zzz",
  "interviewer_info": "エンジニアマネージャー 田中様",
  "prep_memo": "自社サービスのDDD導入の背景について逆質問を用意しておく"
}
```

#### Response (201 Created)
```json
{
  "data": {
    "id": 101,
    "job_application_id": 10,
    "type": "FIRST_ROUND",
    "scheduled_at": "2026-10-12T19:00:00+09:00",
    "location_or_url": "https://meet.google.com/xxx-yyyy-zzz",
    "interviewer_info": "エンジニアマネージャー 田中様",
    "prep_memo": "自社サービスのDDD導入の背景について逆質問を用意しておく",
    "review_memo": null,
    "result": "PENDING"
  }
}
```

---

## 4. APIドキュメント自動生成 & スキーマ駆動連携方針 (Scribe)

本プロジェクトでは、API仕様の形骸化（コードと設計書の乖離）を防ぎ、フロントエンド開発の型安全性を最大化するため、**Scribe (knuckleswtf/scribe)** を導入する。

### 4.1 採用理由とパイプライン

```mermaid
flowchart LR
    Laravel[Laravel コード<br>Routes / FormRequests / PHPDoc] -->|php artisan scribe:generate| Scribe[Scribe エンジン]
    Scribe -->|生成 1| HTML[インタラクティブ HTML ドキュメント<br>/docs/api]
    Scribe -->|生成 2| OpenAPI[OpenAPI 3.0 仕様書<br>.scribe/endpoints.yaml]
    OpenAPI -->|npm run typegen<br>(openapi-typescript)| FrontendTypes[フロントエンド TypeScript型定義<br>frontend/src/types/api.ts]
```

1. **実装からの自動抽出**:
   - `FormRequest`（入力ルールやバリデーション）からリクエストパラメータの型・必須条件を自動抽出。
   - コントローラやルーティングからエンドポイント情報を自動抽出。
2. **OpenAPI 3.0 仕様書の同時出力**:
   - Scribe はHTMLドキュメントと同時に `OpenAPI (yaml/json)` を出力可能。
3. **フロントエンド（React/TypeScript）との型連携**:
   - 出力された OpenAPI スキーマを元に、フロントエンド側で `openapi-typescript` を実行し、API型定義を自動同期。
   - バックエンドのレスポンス変更がフロントエンドのコンパイルエラーとして即座に検知できる環境（**エンドツーエンド型安全性**）を構築する。

