# 開発・技術学習ログ (Development & Learning Logs)

本ディレクトリは、本プロジェクトの実装過程で得られた**技術的知見、ハマりどころの言語仕様・フレームワークの挙動、および設計判断の思考プロセス**をフェーズ別に記録したナレッジベースです。

---

## ログ一覧 & トピック目次

| ファイル | 対象フェーズ | 主な学習トピック・キーワード |
| :--- | :--- | :--- |
| [01. ドメイン層の実装とTDD](01_domain_layer.md) | Phase 1 (Domain Layer) | ・Pest の `with()` データプロバイダとクラス評価ライフサイクル<br>・PHP 8 `match` 式の構文特性（文と式の違い・末尾セミコロン）<br>・PHP標準 `DomainException` を継承する設計的根拠<br>・PHPStan Level 8 における型付けの厳格性<br>・緩やかな比較 (`!=`) の排除と型推論<br>・PHP 8.4+ 非対称可視性 (`public private(set)`) |
| [02. インフラ層・DB設計・リポジトリ](02_infrastructure_db.md) | Phase 2 (Infrastructure) | ・Eloquent とドメインエンティティの相互マッピングと責務分離<br>・型ナローイング（Type Narrowing）と `assert()` の活用<br>・Eloquent 日時キャストと PHPDoc 共変・反変制約<br>・Pest による DB 統合テスト設計（`assertDatabaseHas` 関数スタイル）<br>・集約の整合性とトランザクション戦略（単一 vs 複数テーブル）<br>・Laravel Collection の `list<T>` 型推論と `array_values()`<br>・`ServiceProvider::$bindings` による宣言的 DI バインディング |
| `03_api_usecases.md` | Phase 3 (API & UseCase) | *(準備中: ユースケース・RESTful API・Scribe自動ドキュメント)* |
| `04_auth_security.md` | Phase 4 (Auth & Security) | *(準備中: Sanctum SPA Cookie認証・Gate認可)* |
| `05_frontend_react.md` | Phase 5 (Frontend SPA) | *(準備中: React 18+・TypeScript・Vite・型安全APIクライアント)* |

---

## トピック逆引きインデックス (Keywords Index)

- **言語仕様 (PHP 8.x)**
  - `match` 式のセミコロン規則: [01_domain_layer.md#2-php-8-match-式の構文特性](01_domain_layer.md#2-php-8-match-式の構文特性文と式の違い)
  - `DomainException` (SPLビルトイン例外): [01_domain_layer.md#3-spl標準-domainexception-を継承する設計的根拠](01_domain_layer.md#3-spl標準-domainexception-を継承する設計的根拠)
  - 緩やかな比較（`!=` / `==`）の罠: [01_domain_layer.md#5-緩やかな比較-loose-comparison---の排除と-phpstan-の型推論](01_domain_layer.md#5-緩やかな比較-loose-comparison---の排除と-phpstan-の型推論)
  - 非対称可視性 (`public private(set)`): [01_domain_layer.md#6-php-84-非対称可視性-public-privateset-によるカプセル化](01_domain_layer.md#6-php-84-非対称可視性-public-privateset-によるカプセル化)
  - 型ナローイング（Type Narrowing）と `assert()`: [02_infrastructure_db.md#2-型ナローイングtype-narrowingと-assert-の活用phpstan-level-8-対策](02_infrastructure_db.md#2-型ナローイングtype-narrowingと-assert-の活用phpstan-level-8-対策)
  - `list<T>` 型と `array_values()`: [02_infrastructure_db.md#6-laravel-collection-の-listt-型推論と-array_values-による解決](02_infrastructure_db.md#6-laravel-collection-の-listt-型推論と-array_values-による解決)
- **テスト (Pest PHP / TDD)**
  - `DatasetMissing` の発生メカニズム: [01_domain_layer.md#1-pest-の-with-データプロバイダと-datasetmissing-の正体](01_domain_layer.md#1-pest-の-with-データプロバイダと-datasetmissing-の正体)
  - `DatasetArgumentsMismatch` の引数マッピング規則: [01_domain_layer.md#7-pest-データセット-with-における引数マッピング仕様と-datasetargumentsmismatch](01_domain_layer.md#7-pest-データセット-with-における引数マッピング仕様と-datasetargumentsmismatch)
  - Arch Testing と PHPStan の共存: [01_domain_layer.md#11-pest-arch-testing-によるアーキテクチャ自動検証と-phpstan-の共存](01_domain_layer.md#11-pest-arch-testing-によるアーキテクチャ自動検証と-phpstan-の共存)
  - Pest DB 統合テスト（`assertDatabaseHas` 関数スタイル）: [02_infrastructure_db.md#4-pest-による-db-統合テスト設計と関数スタイルassertdatabasehas](02_infrastructure_db.md#4-pest-による-db-統合テスト設計と関数スタイルassertdatabasehas)
  - CASCADE 削除のテスト検証: [02_infrastructure_db.md#cascade-削除の検証](02_infrastructure_db.md#cascade-削除の検証)
- **静的解析 (PHPStan / Larastan)**
  - Level 8 厳格ルールの適用: [01_domain_layer.md#4-phpstan-level-8-による厳格な品質担保](01_domain_layer.md#4-phpstan-level-8-による厳格な品質担保)
  - `trim($str ?? '')` の型ナローイングとデッドコード回避: [01_domain_layer.md#10-phpstan-level-8-における-trimstr---の型ナローイングと-notidenticalalwaystrue](01_domain_layer.md#10-phpstan-level-8-における-trimstr---の型ナローイングと-notidenticalalwaystrue)
  - Eloquent 日時キャストと PHPDoc 共変・反変制約: [02_infrastructure_db.md#3-eloquent-日時キャストimmutable_datetime-と-phpdoc-共変反変の制約](02_infrastructure_db.md#3-eloquent-日時キャストimmutable_datetime-と-phpdoc-共変反変の制約)
- **設計思想・アーキテクチャ**
  - 強い例外保証（Strong Exception Guarantee）: [01_domain_layer.md#9-強い例外保証strong-exception-guaranteeと副作用の順序](01_domain_layer.md#9-強い例外保証strong-exception-guaranteeと副作用の順序)
  - Userエンティティの非作成とコアドメイン集中: [01_domain_layer.md#12-軽量dddにおける-user-エンティティの非作成とコアドメイン集中](01_domain_layer.md#12-軽量dddにおける-user-エンティティの非作成とコアドメイン集中)
  - Eloquent とドメインモデルの完全分離: [02_infrastructure_db.md#1-eloquent-とドメインエンティティの完全分離と相互マッピング設計](02_infrastructure_db.md#1-eloquent-とドメインエンティティの完全分離と相互マッピング設計)
  - 集約の整合性とトランザクション戦略（単一 vs 複数テーブル）: [02_infrastructure_db.md#5-集約の整合性とトランザクション戦略単一テーブル-vs-複数テーブル集約](02_infrastructure_db.md#5-集約の整合性とトランザクション戦略単一テーブル-vs-複数テーブル集約)
  - ServiceProvider による宣言的 DI バインディング: [02_infrastructure_db.md#7-serviceproviderbindings-による宣言的-di-バインディング](02_infrastructure_db.md#7-serviceproviderbindings-による宣言的-di-バインディング)
- **ドキュメンテーション (Markdown / Mermaid)**
  - Mermaid パーサーの記号エスケープ: [01_domain_layer.md#8-github-mermaid-パーサーにおける特殊記号括弧のエスケープ規則](01_domain_layer.md#8-github-mermaid-パーサーにおける特殊記号括弧のエスケープ規則)



