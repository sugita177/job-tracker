# Phase 2: インフラストラクチャ層・DB設計・リポジトリ実装 (Learning Log)

本ドキュメントは、Phase 2（データベース設計、Eloquent モデル、リポジトリ具象クラス、および Pest による統合テストの実装）において得られた言語仕様・フレームワークの挙動、および設計判断の記録である。

---

## 1. Eloquent とドメインエンティティの完全分離と相互マッピング設計

### 背景
軽量DDD（Lightweight DDD）を採用するにあたり、ドメイン層の純粋性（Laravel非依存）を守りつつ、インフラストラクチャ層で Eloquent ORM の強力なクエリ機能・リレーション機能を活用する設計とした。

```text
[ ドメイン層 ]
  JobApplication (集約ルート)
   ├── SelectionStep (Entity)
   └── StatusHistory (Entity)
        ▲
        │ 相互変換 (toDomain / save)
        ▼
[ インフラ層 (リポジトリ) ]
  JobApplicationRepository
        ▲
        │ Eloquent 操作
        ▼
[ DBモデル & テーブル ]
  JobApplicationModel ──(hasMany)── SelectionStepModel
                      └──(hasMany)── StatusHistoryModel
```

### 設計の要点と得られた知見
1. **ドメイン層の完全な独立**:
   - `JobApplication`, `SelectionStep`, `StatusHistory`, `Company` は Eloquent（`Illuminate\Database\Eloquent\Model`）を一切継承せず、純粋な PHP オブジェクトとして設計。
   - `ArchTest` により、Domain 層から Illuminate への依存を永久に禁止。
2. **リポジトリによる責務の隔離**:
   - DB からのデータ復元（再構築）はリポジトリの `toDomain()` メソッドに集約。
   - Eloquent のマジックプロパティやリレーション結果を、型安全に Value Object（Enum）やドメインエンティティに変換して返す。
   - 変更の保存は、ドメイン集約の状態を取り出して Eloquent モデルに詰め替え、DB に永続化する。

---

## 2. 型ナローイング（Type Narrowing）と `assert()` の活用（PHPStan Level 8 対策）

### 事象
テストコードやリポジトリにおいて、`$company->id` や `$jobApplication->id` を `findById($id, $userId)` などの `int` 型引数に渡そうとすると、PHPStan Level 8 から以下のエラーが報告された：

```text
Parameter #1 $id of method ...::findById() expects int, int|null given.
```

### 原因と技術的背景
- ドメインエンティティ（`Company`, `JobApplication`）は新規作成前（DB保存前）にもインスタンス化されるため、`id` プロパティの型は `?int`（`int|null`）と定義されている。
- しかし、テストコードや保存後ロジックの文脈では、すでに `save()` されて DB に格納された後であるため、**論理的（ビジネスルール上）には絶対に `id` は `int` であり `null` にはなり得ない**。
- PHPStan は静的な文脈のみを解析するため、「保存後だから null ではない」という暗黙の前提を推論できない。

### 解決策（型ナローイングの選択肢と判断）
型を `int|null` から `int` に絞り込む（Narrowing する）には主に3つのアプローチがある：

| アプローチ | 実装コード | メリット | デメリット / 採用判断 |
| :--- | :--- | :--- | :--- |
| **`if` + 例外** | `if ($entity->id === null) throw ...` | 実行時安全性が高い | 既に不変条件として保証されている文脈では冗長（ボイラープレート化） |
| **`is_int()` 三項演算** | `$id = is_int($e->id) ? $e->id : 0;` | 簡潔 | 偽りのデフォルト値（0等）を生み出し、不具合を見落とす危険 |
| **`assert()` (★採用)** | `assert($entity->id !== null);` | **コードの意図（不変条件の表明）が最も明確**。PHPStan が以降の行で `int` として認識する。実行時オーバーヘッドなし | **プロダクションの業務ロジックでは過信禁物だが、保存後保証の文脈やテストコードには最適解** |

```php
// 例: JobApplicationRepositoryTest
$saved = $repository->save($jobApplication);

// この時点で保存済みのため id は null ではない不変条件を表明（型ナローイング）
assert($saved->id !== null);

// PHPStan は $saved->id を int と確定し、型エラーが解消する
$found = $repository->findById($saved->id, $user->id);
```

---

## 3. Eloquent 日時キャスト（`immutable_datetime`）と PHPDoc 共変・反変の制約

### 事象
`JobApplicationModel::$applied_at` などのプロパティに、ドメイン層の `DateTimeImmutable` を代入しようとした際、PHPStan から型不一致エラーが発生した：

```text
Property App\Infrastructure\Persistence\Eloquent\JobApplicationModel::$applied_at
(Carbon\CarbonImmutable|null) does not accept DateTimeImmutable|null.
```

さらに、`StatusHistoryModel` に PHPDoc アノテーションが存在しなかった際、`createFromInterface()` で「string given」と怒られる事象も発生。

### 原因と技術的背景
1. **CarbonImmutable と DateTimeImmutable の親子関係**:
   - `Carbon\CarbonImmutable` は `\DateTimeImmutable` の子クラス（サブタイプ）である。
   - モデルの PHPDoc に `@property \Carbon\CarbonImmutable|null $applied_at` と書くと、PHPStan から見れば **「子クラス（Carbon）を要求しているプロパティに親クラス（DateTimeImmutable）を渡している」** とみなされ、型の安全性が破壊される（反変性エラー）。
2. **モデルのアノテーション欠落**:
   - Eloquent モデルはマジックプロパティ（`__get` / `__set`）を多用するため、PHPDoc に `@property` が書かれていないと、PHPStan はプロパティの型を `mixed` や `string` と推論してしまう。

### 解決策
1. **PHPDoc で両方の型を受け入れ可能にする**:
   ```php
   /**
    * @property \DateTimeImmutable|\Carbon\CarbonImmutable|null $applied_at
    */
   final class JobApplicationModel extends Model
   ```
2. **NOT NULL 制約に対するインフラ層でのフォールバック**:
   - `status_histories.changed_at` は DB 上 NOT NULL カラム。
   - ドメインの `StatusHistory` エンティティは新規作成時の利便性のため `?DateTimeImmutable $changedAt = null`（nullable）としている。
   - そのため、リポジトリ保存時に `?? new DateTimeImmutable()` を添えて現在時刻を補完することで、DBの NOT NULL 制約違反を防ぎ、PHPStan の non-null 型とも完全一致させる。
   ```php
   $historyModel->changed_at = $history->changedAt ?? new DateTimeImmutable();
   ```

---

## 4. Pest による DB 統合テスト設計と関数スタイル（`assertDatabaseHas`）

### 事象
Pest のテストケース内で `$this->assertDatabaseHas(...)` や `$this->assertDatabaseMissing(...)` を呼び出すと、PHPStan / Pest が「Undefined method」あるいはコンテキストエラーを出力した。

### 解決策（Pest の関数スタイル）
Pest では、Laravel のアサーションを関数として直接インポートして利用するスタイルが推奨される。

```php
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

// 関数として直接呼び出し（PHPStan Level 8 でも完全な型推論が効く）
assertDatabaseHas('job_applications', [
    'id' => $saved->id,
    'title' => 'バックエンドエンジニア',
]);
```

### CASCADE 削除の検証
`JobApplication` の削除時に、子テーブル（`selection_steps`, `status_histories`）が DB の外部キー制約（`ON DELETE CASCADE`）によって自動的・確実に物理削除されることを統合テストで検証。

```php
$repository->delete($saved->id, $user->id);

// 親テーブルだけでなく、子テーブルのレコードも消えていることを明示的に検証
assertDatabaseMissing('job_applications', ['id' => $saved->id]);
assertDatabaseMissing('selection_steps', ['job_application_id' => $saved->id]);
assertDatabaseMissing('status_histories', ['job_application_id' => $saved->id]);
```

---

## 5. 集約の整合性とトランザクション戦略（単一テーブル vs 複数テーブル集約）

### 設計判断の背景
- **Company 集約**: 単一テーブル（`companies`）に完結。MySQL の Auto-commit 機能により、単一の `INSERT` / `UPDATE` 文は自動的に不可分（Atomic）に実行されるため、明示的な `DB::transaction()` は不要。
- **JobApplication 集約**: 複数テーブル（`job_applications`, `selection_steps`, `status_histories`）を跨ぐ集約ルート。
  - 親レコードの作成・更新
  - 選考ステップの作成・更新
  - 変更履歴の追記
  これらの一部だけが成功して途中でエラーが発生した場合、データの不整合（親のない子レコード、あるいはステータスと履歴の不一致）が生じる。

### 実装
集約ルート単位で `DB::transaction()` を適用し、All-or-Nothing（原子性）を担保：

```php
public function save(JobApplication $jobApplication): JobApplication
{
    assert($jobApplication->userId > 0);
    assert($jobApplication->companyId > 0);

    return DB::transaction(function () use ($jobApplication): JobApplication {
        // 1. 親モデルの保存
        // 2. 子モデル (SelectionStep) のループ保存
        // 3. 子モデル (StatusHistory) の追記保存
        // 4. 最新状態の完全再取得
        return $this->toDomain($refreshedModel);
    });
}
```

### 排他ロック（悲観的ロック）についての考察
- 本システムは「個人専用の転職活動トラッカー」であり、同一ユーザーが複数ブラウザ・端末から同時に同一レコードを衝突更新する確率は実質的にゼロである。
- したがって、現段階で `lockForUpdate()` などの悲観的排他ロックを導入するのは YAGNI（過剰設計）と判断し、シンプルなトランザクションのみで十分な堅牢性を確保した。

---

## 6. Laravel Collection の `list<T>` 型推論と `array_values()` による解決

### 事象
`JobApplicationRepository::listByUserId` メソッドの戻り値型を `list<JobApplication>` と定義した際、以下のエラーが発生した：

```text
Method JobApplicationRepository::listByUserId() should return list<JobApplication>
but returns array<int, JobApplication>.
```

### 原因と技術的背景
- `list<T>` 型（PHPStan / Psalm 固有の型）は、**「添字が 0 から始まる連続した整数（0, 1, 2, ...）である配列」** を指す。
- Laravel Collection の `$models->map(...)->all()` は、内部的に配列のキーを保持する可能性があるため、PHPStan は「キーが飛び番かもしれない `array<int, T>`」と推論する。

### 解決策
PHP標準関数の `array_values()` でラップすることで、キーが確実に 0 始まりの連続した整数であることを保証し、型安全に `list<T>` を返却する。

```php
/** @var list<JobApplication> */
return array_values($models->map(fn (JobApplicationModel $model): JobApplication => $this->toDomain($model))->all());
```

---

## 7. `ServiceProvider::$bindings` による宣言的 DI バインディング

### 実装
Laravel のサービスプロバイダで、インターフェースと具象クラスのバインドを `register()` 内で手続き的に記述する代わりに、`$bindings` プロパティを活用した宣言的バインドを採用。

```php
final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * リポジトリのインターフェースと具象クラスのバインド定義
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CompanyRepositoryInterface::class => CompanyRepository::class,
        JobApplicationRepositoryInterface::class => JobApplicationRepository::class,
    ];
}
```

### メリット
- 手続き的な `$this->app->bind(...)` の記述が不要になり、バインド一覧が一目で把握できる。
- Laravel のフレームワークが最適化された形でシングルトン/通常インスタンスを解決できる。
- `bootstrap/providers.php` に登録するだけで、新アーキテクチャ（Laravel 11+）の作法に完全準拠。
