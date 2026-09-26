# Phase 3: アプリケーション層・RESTful API・例外ハンドリング (Learning Log)

本ドキュメントは、Phase 3（ユースケース層、RESTful API、FormRequest バリデーション、JsonResource、および例外ハンドリング）の実装において得られた言語仕様・フレームワークの挙動、および設計判断の記録である。

---

## 1. 多重防御（Defense in Depth）によるテナント分離セキュリティ

### 背景
本システムは複数ユーザーが利用するマルチテナント型 Web アプリケーションであるため、他ユーザーの企業データや求人応募情報へのアクセス・改ざんは致命的なセキュリティ事故（情報漏洩）となる。
単一のレイヤー（例: コントローラーのチェックのみ）に依存せず、多層でデータを防衛するアーキテクチャを採用した。

```text
[ HTTP リクエスト ]
    │
    ▼ ① 第1防壁: 認証レイヤー (Laravel Sanctum)
       未認証リクエストを 401 Unauthorized で即時遮断
    │
    ▼ ② 第2防壁: バリデーションレイヤー (FormRequest)
       リクエスト内の外部キー（company_id）がログインユーザー所有か検証
       他人の会社IDや存在しないIDを 422 Unprocessable Content で遮断
    │
    ▼ ③ 第3防壁: アプリケーション層 / コントローラー
       currentUserId() により認証ユーザー ID を確実に DTO へ注入
    │
    ▼ ④ 第4防壁: インフラストラクチャ層 (Repository)
       すべての検索・更新・削除クエリに必ず `user_id = ?` 条件をバインド
       万が一 URL の {id} を他人のリソースに書き換えられても 404 Not Found で完全防御
```

### 設計の要点と得られた知見
1. **FormRequest での存在・所有権同時チェック**:
   `Rule::exists()` に `.where('user_id', $userId)` を連結することで、「その ID が DB に存在するか」だけでなく「**ログイン中のユーザーが作成したデータか**」をリクエストの入口で自動検証する。
   ```php
   // CreateJobApplicationRequest.php
   'company_id' => [
       'required',
       'integer',
       Rule::exists('companies', 'id')->where('user_id', $this->user()?->id),
   ],
   ```
2. **Repository での ID 隔離**:
   `findById(int $id, int $userId)` や `delete(int $id, int $userId)` のように、必ず `$userId` を第2引数に強制することで、コントローラーやユースケースの書き間違いによるテナント越境を構造的に排除した。

---

## 2. FormRequest のライフサイクルとデータ取得の設計判断

### 背景
コントローラー内で `$request->input()`, `$request->validated()`, `$request->filled()` のどれを用いるべきか、またどのタイミングでバリデーションが走るのかについて設計整理を行った。

### ライフサイクル（処理フロー）
Laravel のコントローラーメソッドで `CreateJobApplicationRequest $request` のように FormRequest をタイプヒントすると、以下の順序で自動実行される：

```text
1. Laravel DI コンテナが FormRequest インスタンスを生成
    ↓
2. authorize() メソッドを実行（false なら即座に 403 Forbidden）
    ↓
3. rules() に定義されたバリデーションルールを実行
    ├── 失敗: ValidationException が発生し、即座に 422 JSON を返して中断
    │         （※コントローラーのコードは 1 行も実行されない）
    └── 成功: コントローラーのアクションメソッドへ処理が進む
```

### データ取得メソッドの比較と選定
| メソッド | 挙動 | 特徴・使いどころ |
| :--- | :--- | :--- |
| **`$request->validated()`** | `rules()` に定義されたキーのみを配列または値で返す | ホワイトリスト形式で安全。ただし PHPStan から見ると戻り値型が `mixed` と推論されるため、細かな型キャストが必要 |
| **`$request->input()`** | リクエスト内の指定キーを取得する | 標準的。DTO コンストラクタで必要なキーのみを明示的に型キャストして詰め替える場合、十分安全で可読性が高い |
| **`$request->filled()`** | キーが存在し、かつ空文字・`null` でないことを判定 | `nullable` なオプショナル項目の判定に最適（三項演算子の冗長なネストを解消できる） |

### 改善判断
当初、PHPStan の型チェックを警戒して以下のような過剰に防衛的なコードを書いていた：
```php
// 改善前: 冗長で認知負荷が高い
channelType: $request->has('channel_type') && $request->validated('channel_type') !== null
    ? (string) $request->validated('channel_type')
    : null,
```
これを `filled()` と `input()` の組み合わせにリファクタリングすることで、CompanyController と流儀を揃え、シンプルで意図が明白なコードに統一した：
```php
// 改善後: 直感的でスッキリしたコード
channelType: $request->filled('channel_type') ? (string) $request->input('channel_type') : null,
```

---

## 3. ドメイン例外の透過的 HTTP マッピング（Laravel 11+ `bootstrap/app.php`）

### 背景
従来の Laravel（〜10）では `app/Exceptions/Handler.php` に例外ハンドリングを集約していたが、Laravel 11+ では `bootstrap/app.php` の関数型クロージャによる設定スタイルに刷新された。
DDD を実践する際、コントローラーごとに `try-catch` を乱立させるとボイラープレート化し、フレームワーク層が肥大化する問題があった。

### 解決策
ドメイン層・アプリケーション層で発生する例外を、`bootstrap/app.php` のグローバルハンドラで捕捉し、HTTP ステータスコード `422 Unprocessable Content` の JSON レスポンスへ自動変換するアーキテクチャを採用した。

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {
    // ドメイン層のビジネスルール違反（不変条件違反、不正遷移など）を 422 JSON に透過変換
    $exceptions->render(function (\DomainException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    });

    // 引数違反（タイトル空文字、検討中ステータスに応募日指定など）も同様に 422 JSON に変換
    $exceptions->render(function (\InvalidArgumentException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    });
})
```

### メリット
- コントローラーは正常系のみに集中でき、薄いアダプター（Pure Controller）を維持できる。
- ドメイン層は純粋な PHP 例外（`DomainException`, `InvalidArgumentException`）をスローするだけで、API クライアント（フロントエンド）に統一されたエラーフォーマットが返却される。

---

## 4. サイレントフォールバックの排除（フェイルファスト原則）

### 背景
実装の過程で、以下の2つの「暗黙のデフォルト値への倒し込み（サイレントフォールバック）」の誘惑に直面した：
1. 「検討中ステータスの求人に対して、update で応募日（`appliedAt`）が渡された場合、無視して `null` のまま保存すれば良いのではないか？」
2. 「検討中から書類選考へ進む際、応募媒体（`channel`）が指定されなかった場合、勝手に `direct`（直接応募）にして進めれば良いのではないか？」

### 設計判断（フェイルファストの徹底）
これらは一見「親切な自動補正」に見えるが、実際には**ユーザーの意図しない状態遷移やデータの不整合を静かに生み出すバグの温床**となる。
そのため、「間違った入力は即座に例外で弾く（Fail-Fast）」というドメインモデリング原則を徹底した。

1. **検討中への応募日設定の即時例外化**:
   ```php
   // JobApplication.php
   if ($appliedAt !== null) {
       if ($this->currentStatus === ApplicationStatus::INTERESTED) {
           throw new InvalidArgumentException('検討中ステータスの求人に応募日を設定することはできません。');
       }
       $this->appliedAt = $appliedAt;
   }
   ```
2. **媒体未指定時の例外送出**:
   勝手に `direct()` に倒すのではなく、`IncompleteApplicationException::missingChannelOrAppliedAt()` をスローしてクライアントに必須入力であることを明示する。

---

## 5. API レスポンスの日時表現と標準規格（`DateTimeImmutable::ATOM`）

### 背景
API のレスポンスフォーマットにおいて、日時をどのような文字列形式で返却すべきかが議論となった。

### 採用規格と理由
PHP の組み込み定数 **`DateTimeImmutable::ATOM`**（`"Y-m-d\TH:i:sP"`）を採用した。
例: `2026-09-26T22:30:00+09:00`

- **国際標準規格（ISO 8601 / RFC 3339）準拠**:
  末尾に `+09:00` や `Z` などのタイムゾーンオフセット情報が必ず付与される。
- **フロントエンド（React / TypeScript）との完全な親和性**:
  JavaScript の `new Date("2026-09-26T22:30:00+09:00")` で 100% 正確にパースでき、閲覧環境のローカルタイムゾーンとの変換トラブルを防止する。

---

## 6. PHPStan Level 8 における `??` と `?->` の重複検証

### 事象
JsonResource で会社名を取り出す際、PHPStan から以下の Dead Code 警告が報告された：
```text
Left side of && is always true / Null coalescing operator is redundant.
```

### 原因と技術的背景
以下のコードを記述していた：
```php
'company_name' => $company?->name ?? '',
```
PHP の仕様上、`??`（Null合体演算子）は左辺の変数が未定義であったり `null` であってもエラーを出さず、安全に右辺のデフォルト値を評価する。
そのため、`$company?->name` のように Nullsafe 演算子（`?->`）を重ねて使うのは文法的に冗長（二重防衛）であり、PHPStan はこれを「無駄な Nullsafe 呼び出し」と検出する。

### 解決策
```php
// 正しい記述: Null合体演算子のみで安全に評価される
'company_name' => $company->name ?? '',
```
静的解析ツールを最高レベル（Level 8）で運用することにより、PHP の細かな演算子仕様への理解を深めることができた。

---

## 7. 未使用 public メソッド（デッドコード）の検出ツールと静的解析運用設計

> [!NOTE]
> **【将来導入検討 / リファクタリング課題】**
> 本ツール（`tomasvotruba/unused-public`）は、現在の開発フェーズ（Phase 3）では**未導入**です。
> 実装途中の開発リズムを最優先するため、Phase 3 完了後のリファクタリング期間、または CI 構築時に導入を検討します。

### 背景
TDD や DDD でエンティティを設計する際、「ビジネスルールを表現するメソッドを定義したが、現行の Controller や UseCase からは未呼び出し」という状態が一時的に発生する。
コードベースの健全性を保つために「どこからも使われていない public メソッド」を検出したいが、常時チェックすると実装途中のテンポが損なわれる課題がある。

### 検出ツールの選定（`tomasvotruba/unused-public`）
PHPStan の拡張プラグインである `tomasvotruba/unused-public` は、クラス全体を走査して「定義されているが一度も呼び出されていない public メソッド・プロパティ」を静的に検出できる。

### 運用設計上のベストプラクティス（導入時の計画）
- **普段の開発時（`make analyze`）**:
  `unused-public` は無効化しておく（TDD 先行実装中の誤検知による作業中断を防ぎ、開発リズムを維持するため）。
- **リファクタリング・リリース前（`make check-unused`）**:
  専用設定 `phpstan-unused.neon` を用意し、不要なコードを一掃したいフェーズでのみ別コマンド（CI パイプライン等）で明示的に実行する。

---

## 8. 集約配下の子エンティティ（SelectionStep）の整合性保護と今後の課題 (Phase 2 バックログ)

> [!IMPORTANT]
> **【未実装 / Phase 2 バックログ】**
> 以下の整合性ガード（合否変更制限・中間ステップ削除禁止）は、**MVP の現行フェーズでは意図的に実装を見送り、ユーザーの入力自由度を優先**しています。
> 実際の運用データやユースケースの複雑化に合わせて、Phase 2 以降で追加実装を検討するバックログ項目です。

### 現行（MVP）での設計判断
- **メモや面接官情報の編集自由度を最優先**:
  本アプリケーションは「個人の転職活動ナレッジ蓄積ツール」としての性格が強く、「二次面接の対策中に、一次面接で聞かれた技術質問を思い出して追記する」「名刺を見て後から面接官名をメモする」というユースケースが頻繁に発生する。
  そのため、監査ログとして改ざん不可にした `StatusHistory` とは対照的に、`SelectionStep` の事前メモ・振り返りメモ・面接官情報は過去ステップであっても自由に更新可能とした。

### 今後のロードマップ（Phase 2 追加検討リスト）
- [ ] **先行ステップの「結果（result: 合否）」変更制限ガード**:
  - **現状の課題**: 「すでに二次面接に進んでいる（＝一次通過）のに、過去の一次面接の結果を『お見送り（FAILED）』に変更できてしまう」という論理矛盾が生じうる。
  - **将来の対応案**: 後続のステップが存在する場合、先行ステップの `recordStepReview()` による合否変更を制限する（または管理者・訂正専用メソッドを設ける）。
- [ ] **中間ステップ削除の禁止ガード**:
  - **現状の課題**: 一次面接と二次面接がある状態で、中間の一次面接だけが削除できてしまうと、選考のタイムラインが破綻する。
  - **将来の対応案**: `removeSelectionStep()` において、「最新（末尾）のステップのみ削除可能」とするバリデーションガードを集約ルートに設ける。


