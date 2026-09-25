<?php

declare(strict_types=1);

use App\Domain\Company\Entities\Company;

describe('Company 集約エンティティ', function () {
    test('有効な情報で企業インスタンスを作成できる（URL・メモはnull許容）', function () {
        $company = new Company(
            userId: 1,
            name: ' 株式会社ABC ',
            url: ' https://example.com ',
            memo: ' 自社開発のSaaS企業 ',
        );

        expect($company->userId)->toBe(1)
            ->and($company->name)->toBe('株式会社ABC') // 前後の空白トリム
            ->and($company->url)->toBe('https://example.com')
            ->and($company->memo)->toBe('自社開発のSaaS企業')
            ->and($company->id)->toBeNull();
    });

    test('企業名が空文字または空白のみの場合は InvalidArgumentException がスローされる', function () {
        expect(fn () => new Company(userId: 1, name: '   '))
            ->toThrow(InvalidArgumentException::class, '企業名は必須です。');
    });

    test('空文字のURLやメモは null に正規化される', function () {
        $company = new Company(
            userId: 1,
            name: '株式会社ABC',
            url: '   ',
            memo: '',
        );

        expect($company->url)->toBeNull()
            ->and($company->memo)->toBeNull();
    });

    test('update メソッドで企業情報を正しく更新できる', function () {
        $company = new Company(
            userId: 1,
            name: '株式会社ABC',
        );

        $company->update(
            name: '株式会社XYZ',
            url: 'https://xyz.example.com',
            memo: 'リブランディング後の社名',
        );

        expect($company->name)->toBe('株式会社XYZ')
            ->and($company->url)->toBe('https://xyz.example.com')
            ->and($company->memo)->toBe('リブランディング後の社名');
    });
});
