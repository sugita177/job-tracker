# Phase 4: 認証基盤 & セキュリティ 開発・技術学習ログ

本ドキュメントは、Phase 4（Laravel Sanctum SPA 認証基盤・セッション管理・認可設計）の実装過程で直面した技術的課題、言語・フレームワーク仕様、エラー解決知見、および設計判断のトレードオフを記録したものである。

---

## 逆引きインデックス (Keywords)

| キーワード | 概要・該当セクション |
| :--- | :--- |
| **Sanctum SPA 認証** | [1. Bearer Token ではなく SPA Cookie 認証を採用した理由](#1-bearer-token-ではなく-spa-cookie-認証を採用した理由) |
| **アカウント列挙攻撃** | [2. ValidationException でエラーキーを email に統一する理由](#2-validationexception-でエラーキーを-email-に統一する理由) |
| **`Session store not set`** | [3. テスト課題①: Sanctum の Stateful 判定と Referer ヘッダー](#3-テスト課題①-sanctum-の-stateful-判定と-referer-ヘッダー) |
| **ガードキャッシュと状態リーク** | [4. テスト課題②: assertGuest('web') と Auth::forgetGuards()](#4-テスト課題②-assertguestweb-と-authforgetguards) |
| **Gate vs Policy vs 404隠匿** | [5. 認可設計のトレードオフと将来TODO](#5-認可設計のトレードオフと将来todo) |

---

## 1. Bearer Token ではなく SPA Cookie 認証を採用した理由

### 背景とセキュリティ課題
SPA（React）と Web API（Laravel）を分離した構成において、最も単純な認証方式は「ログイン時に API トークン（Bearer Token）を発行し、フロントエンドの `localStorage` に保持して毎回リクエストヘッダーに乗せる」方式である。

しかし、この方式には致命的なセキュリティリスクが存在する：
- **XSS（クロスサイトスクリプティング）への脆弱性**: フロントエンドに悪意ある JavaScript が混入・実行された場合、`localStorage.getItem('token')` によりトークンが即座に外部へ盗難され、セッションハイジャックされる。

### 解決策: httpOnly Cookie + SameSite=Lax + CSRF 保護
Laravel Sanctum の SPA モードを採用：
1. **`httpOnly` 属性**: JavaScript から Cookie へのアクセスをブラウザレベルで遮断し、XSS によるトークン奪取を防止。
2. **CSRF 保護**: 事前に `GET /sanctum/csrf-cookie` で発行される `XSRF-TOKEN` を `X-XSRF-TOKEN` ヘッダーに付与させることで、クロスサイトリクエストフォージェリを防御。
3. **セッション固定攻撃防止**: ログイン成功時および登録成功時に必ず `$request->session()->regenerate()` を実行し、新しいセッションIDを再発行。

---

## 2. ValidationException でエラーキーを email に統一する理由

### 疑問の背景
ログイン失敗時、コントローラーで以下のように記述する：

```php
if (! Auth::attempt($credentials)) {
    throw ValidationException::withMessages([
        'email' => [trans('auth.failed')],
    ]);
}
```
「パスワードが違うかもしれないのに、なぜキーが `email` 固定なのか？」

### 理由①: UI/UX（フォーム項目への自然な紐付け）
Laravel のバリデーションエラーは `{"errors": {"email": ["..."]}}` の JSON 構造を返す。フロントエンド（React）ではフィールド名キーを参照して入力欄の直下に赤字表示するため、ログインフォームの代表入力欄である `email` に紐付けるのが最も自然である。

### 理由②: アカウント列挙攻撃（User Enumeration Attack）の防止
「メールアドレスが存在しません」「パスワードが間違っています」とエラーを分けてしまうと、攻撃者に「どのメールアドレスが実際に登録されているか」を特定されてしまう。
実在有無にかかわらず一律で「認証情報が記録と一致しません（auth.failed）」を `email` キーで返すのが、Web セキュリティの標準的なベストプラクティスである。

---

## 3. テスト課題①: Sanctum の Stateful 判定と Referer ヘッダー

### 直面したエラー
```text
RuntimeException: Session store not set on request.
  at tests/Feature/Api/AuthApiTest.php
```

### 原因の究明
1. Laravel Sanctum の `EnsureFrontendRequestsAreStateful` ミドルウェア（`$middleware->statefulApi()`）は、**「フロントエンド（SPA）からのリクエスト」と判定された場合のみ、セッションミドルウェア（`StartSession` 等）を動的にパイプラインへ注入する**。
2. その判定メソッド `fromFrontend($request)` は、リクエストヘッダーの `Referer` または `Origin` を検査し、環境変数 `SANCTUM_STATEFUL_DOMAINS`（例: `localhost:5173`）と一致するかをチェックする。
3. テスト環境（`postJson(...)`）ではデフォルトで `Referer` ヘッダーが付与されないため、Sanctum が「外部ステートレス API クライアント」と判断してセッションミドルウェアをスキップ。その結果、コントローラー内の `$request->session()->regenerate()` でセッションストア未設定の例外がスローされた。

### 解決策
テストの `beforeEach` で `Referer` ヘッダーを付与し、SPA からのリクエストとして認識させる。

```php
use function Pest\Laravel\withHeader;

beforeEach(function () {
    withHeader('referer', 'http://localhost:5173');
});
```

---

## 4. テスト課題②: assertGuest('web') と Auth::forgetGuards()

### 直面したエラー
1. ログアウトテストで `assertGuest()` を呼ぶと `The user is authenticated` と失敗。
2. ログアウト後に `getJson('/api/auth/user')` を呼ぶと、期待する `401 Unauthorized` ではなく `200 OK` が返る。

### 原因の究明: 本番環境（PHP-FPM）とテスト環境（Pest）のライフサイクルの乖離
* **本番環境**:
  リクエスト終了時に **PHP プロセスが終了し、メモリ空間がリセット** される。次のリクエストはまっさらな状態で届くため、Cookie が無ければ確実に 401 になる。
* **テスト環境**:
  同一の PHP プロセス内でテストが連続実行される。Laravel の `RequestGuard`（Sanctum ガード）は、パフォーマンス向上のため **「一度解決したユーザーオブジェクトを Guard インスタンスの内部プロパティ（`$this->user`）にキャッシュ」** している。
  コントローラーで `web` ガードのセッションを破棄しても、同一メモリ上に残った Sanctum ガードのキャッシュが古いユーザーオブジェクトを返してしまう（状態リーク）。

### 解決策
1. ログアウト検証は、セッションCookieを司る `web` ガードを明示して検証する：
   ```php
   assertGuest('web');
   ```
2. ログアウト後に別の API を呼ぶ結合テストを行う際は、テスト環境のインメモリキャッシュをクリアして本番の「新しいリクエスト」状態を再現する：
   ```php
   Auth::forgetGuards();
   $afterResponse = getJson('/api/auth/user');
   $afterResponse->assertUnauthorized();
   ```

---

## 5. 認可設計のトレードオフと将来TODO

### Gate vs Policy vs 404隠匿

| 方式 | 挙動 | 評価・本プロジェクトでの採用理由 |
| :--- | :--- | :--- |
| **Gate** | クロージャベースの権限チェック | 管理者権限などグローバル操作向け。MVPには管理者ロールがないため不適合。 |
| **Policy** | モデル単位のクラスベース認可 | Laravel の王道パターンだが、他人のリソースアクセス時に標準で `403 Forbidden` を返す。 |
| **リポジトリ層での404隠匿** | SQL条件に `user_id` を強制し、他人のデータは `404 Not Found` | **★MVP採用**。攻撃者に他人のリソースIDの存在有無を一切悟らせない（推測攻撃・情報漏洩の防止）。 |

### 将来TODO (Phase 2)
- **Laravel Policy の導入**:
  将来的に「チーム機能（複数人での選考管理・共有）」や「企業エージェント・管理者ロール」などの多重ロールが必要になった段階で、`CompanyPolicy` や `JobApplicationPolicy` による宣言的認可レイヤーを導入する。
  MVP（シングルユーザー向け完全隔離）では、リポジトリ層での強制スコープ（404隠匿）が最も堅牢かつシンプルであると判断。
