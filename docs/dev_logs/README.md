# 開発・技術学習ログ (Development & Learning Logs)

本ディレクトリは、本プロジェクトの実装過程で得られた**技術的知見、ハマりどころの言語仕様・フレームワークの挙動、および設計判断の思考プロセス**をフェーズ別に記録したナレッジベースです。

---

## ログ一覧 & トピック目次

| ファイル | 対象フェーズ | 主な学習トピック・キーワード |
| :--- | :--- | :--- |
| [01. ドメイン層の実装とTDD](01_domain_layer.md) | Phase 1 (Domain Layer) | ・Pest の `with()` データプロバイダとクラス評価ライフサイクル<br>・PHP 8 `match` 式の構文特性（文と式の違い・末尾セミコロン）<br>・PHP標準 `DomainException` を継承する設計的根拠<br>・PHPStan Level 8 における型付けの厳格性<br>・緩やかな比較 (`!=`) の排除と型推論<br>・PHP 8.4+ 非対称可視性 (`public private(set)`) |
| `02_infrastructure_db.md` | Phase 2 (Infrastructure) | *(準備中: DB設計・Eloquentマッピング・リポジトリ)* |
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
- **テスト (Pest PHP / TDD)**
  - `DatasetMissing` の発生メカニズム: [01_domain_layer.md#1-pest-の-with-データプロバイダと-datasetmissing-の正体](01_domain_layer.md#1-pest-の-with-データプロバイダと-datasetmissing-の正体)
  - `DatasetArgumentsMismatch` の引数マッピング規則: [01_domain_layer.md#7-pest-データセット-with-における引数マッピング仕様と-datasetargumentsmismatch](01_domain_layer.md#7-pest-データセット-with-における引数マッピング仕様と-datasetargumentsmismatch)
- **静的解析 (PHPStan / Larastan)**
  - Level 8 厳格ルールの適用: [01_domain_layer.md#4-phpstan-level-8-による厳格な品質担保](01_domain_layer.md#4-phpstan-level-8-による厳格な品質担保)
- **ドキュメンテーション (Markdown / Mermaid)**
  - Mermaid パーサーの記号エスケープ: [01_domain_layer.md#8-github-mermaid-パーサーにおける特殊記号括弧のエスケープ規則](01_domain_layer.md#8-github-mermaid-パーサーにおける特殊記号括弧のエスケープ規則)


