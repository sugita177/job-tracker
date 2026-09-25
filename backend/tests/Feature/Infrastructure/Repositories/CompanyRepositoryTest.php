<?php

declare(strict_types=1);

use App\Domain\Company\Entities\Company;
use App\Infrastructure\Persistence\Repositories\CompanyRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

describe('CompanyRepository (統合テスト)', function () {
    test('企業エンティティを新規保存でき、自動採番されたID付きで返される', function () {
        $user = User::factory()->create();
        $repository = new CompanyRepository();

        $company = new Company(
            userId: $user->id,
            name: '株式会社テスト',
            url: 'https://example.com',
            memo: 'テスト用のメモ',
        );

        $savedCompany = $repository->save($company);

        expect($savedCompany->id)->not->toBeNull()
            ->and($savedCompany->name)->toBe('株式会社テスト')
            ->and($savedCompany->userId)->toBe($user->id);

        // DBに実際に保存されていることを確認
        assertDatabaseHas('companies', [
            'id' => $savedCompany->id,
            'user_id' => $user->id,
            'name' => '株式会社テスト',
        ]);
    });

    test('既存の企業エンティティを更新できる', function () {
        $user = User::factory()->create();
        $repository = new CompanyRepository();

        $company = $repository->save(new Company(
            userId: $user->id,
            name: '変更前株式会社',
        ));

        // ドメインメソッドで更新
        $company->update('変更後株式会社', 'https://updated.com', '更新メモ');
        $updatedCompany = $repository->save($company);

        expect($updatedCompany->id)->toBe($company->id)
            ->and($updatedCompany->name)->toBe('変更後株式会社')
            ->and($updatedCompany->url)->toBe('https://updated.com');

        assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => '変更後株式会社',
        ]);
    });

    test('findById で自分自身の企業は取得できるが、他ユーザーの企業は null が返る（マルチテナント保護）', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $repository = new CompanyRepository();

        $companyA = $repository->save(new Company(
            userId: $userA->id,
            name: 'A社の企業',
        ));
        assert($companyA->id !== null);

        // User A として取得 -> 成功
        $found = $repository->findById($companyA->id, $userA->id);
        expect($found)->not->toBeNull();
        assert($found !== null);
        expect($found->name)->toBe('A社の企業');

        // User B として取得 -> 他人のデータなので null
        $notFound = $repository->findById($companyA->id, $userB->id);
        expect($notFound)->toBeNull();
    });

    test('listByUserId で指定ユーザーの企業一覧のみを取得できる', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $repository = new CompanyRepository();

        $repository->save(new Company(userId: $userA->id, name: 'A-1社'));
        $repository->save(new Company(userId: $userA->id, name: 'A-2社'));
        $repository->save(new Company(userId: $userB->id, name: 'B-1社'));

        $listA = $repository->listByUserId($userA->id);
        expect($listA)->toHaveCount(2)
            ->and($listA[0]->name)->toBe('A-1社')
            ->and($listA[1]->name)->toBe('A-2社');
    });

    test('delete で企業を削除できる（他ユーザーの企業は削除できない）', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $repository = new CompanyRepository();

        $companyA = $repository->save(new Company(userId: $userA->id, name: '削除対象社'));
        assert($companyA->id !== null);

        // 他ユーザーBが削除を試みても削除されない
        $repository->delete($companyA->id, $userB->id);
        assertDatabaseHas('companies', ['id' => $companyA->id]);

        // 所有者Aが削除実行 -> 正常削除
        $repository->delete($companyA->id, $userA->id);
        assertDatabaseMissing('companies', ['id' => $companyA->id]);
    });
});
