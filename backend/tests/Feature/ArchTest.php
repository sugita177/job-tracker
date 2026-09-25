<?php

declare(strict_types=1);

// 1. デバッグ関数の残留防止
arch('コードベース全体でデバッグ関数が使われていないこと')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

// 2. strict_types 宣言の強制
arch('Domain層の全ファイルで declare(strict_types=1) が宣言されていること')
    ->expect('App\Domain')
    ->toUseStrictTypes();

// 3. ドメイン層の純粋性の保証（フレームワーク・インフラ層への依存禁止）
arch('Domain層は純粋なPHPであり、Laravel(Illuminate)や外部レイヤーに依存しないこと')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate',
        'App\Infrastructure',
        'App\Http',
        'App\Models',
    ]);

// 4. ValueObject のクラスはすべて final であること（継承による密結合を防止）
arch('ValueObjects のクラスはすべて final であること')
    ->expect('App\Domain\JobApplication\ValueObjects')
    ->classes()
    ->toBeFinal();

// 5. Entity の設計ルール（継承による密結合を防ぐため final を強制）
arch('Entities はすべて final class であること')
    ->expect([
        'App\Domain\JobApplication\Entities',
        'App\Domain\Company\Entities',
    ])
    ->classes()
    ->toBeFinal();

// 6. Repository はすべて interface であること
arch('Domain層の Repositories はすべて interface であること')
    ->expect([
        'App\Domain\Company\Repositories',
        'App\Domain\JobApplication\Repositories',
    ])
    ->toBeInterfaces();
