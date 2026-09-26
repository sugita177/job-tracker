<?php

declare(strict_types=1);

use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

test('GET /api/companies でログインユーザーの企業一覧のみ取得できること', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $repository = app(CompanyRepositoryInterface::class);

    $repository->save(new Company(userId: $user->id, name: '自社ターゲットA'));
    $repository->save(new Company(userId: $user->id, name: '自社ターゲットB'));
    $repository->save(new Company(userId: $otherUser->id, name: '他人の企業C'));

    $response = actingAs($user)
        ->getJson('/api/companies');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', '自社ターゲットA')
        ->assertJsonPath('data.1.name', '自社ターゲットB');
});

test('POST /api/companies で企業を新規登録できること', function () {
    $user = User::factory()->create();

    $payload = [
        'name' => '株式会社テスト',
        'url' => 'https://example.com',
        'notes' => '気になる自社開発企業',
    ];

    $response = actingAs($user)
        ->postJson('/api/companies', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', '株式会社テスト')
        ->assertJsonPath('data.url', 'https://example.com')
        ->assertJsonPath('data.notes', '気になる自社開発企業');

    assertDatabaseHas('companies', [
        'user_id' => $user->id,
        'name' => '株式会社テスト',
    ]);
});

test('POST /api/companies で企業名が空の場合は 422 バリデーションエラーになること', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->postJson('/api/companies', [
            'name' => '',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('GET /api/companies/{id} で企業詳細を取得できること', function () {
    $user = User::factory()->create();
    $repository = app(CompanyRepositoryInterface::class);

    $company = $repository->save(new Company(userId: $user->id, name: '株式会社詳細テスト'));
    assert($company->id !== null);

    $response = actingAs($user)
        ->getJson("/api/companies/{$company->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', '株式会社詳細テスト');
});

test('GET /api/companies/{id} で他人の企業を指定した場合は 404 になること', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $repository = app(CompanyRepositoryInterface::class);

    $otherCompany = $repository->save(new Company(userId: $otherUser->id, name: '他人の企業'));
    assert($otherCompany->id !== null);

    $response = actingAs($user)
        ->getJson("/api/companies/{$otherCompany->id}");

    $response->assertNotFound();
});

test('PUT /api/companies/{id} で企業情報を更新できること', function () {
    $user = User::factory()->create();
    $repository = app(CompanyRepositoryInterface::class);

    $company = $repository->save(new Company(userId: $user->id, name: '変更前企業名'));
    assert($company->id !== null);

    $response = actingAs($user)
        ->putJson("/api/companies/{$company->id}", [
            'name' => '変更後企業名',
            'url' => 'https://updated.example.com',
            'notes' => '更新メモ',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', '変更後企業名')
        ->assertJsonPath('data.url', 'https://updated.example.com');

    assertDatabaseHas('companies', [
        'id' => $company->id,
        'name' => '変更後企業名',
    ]);
});

test('DELETE /api/companies/{id} で企業を削除できること (204 No Content)', function () {
    $user = User::factory()->create();
    $repository = app(CompanyRepositoryInterface::class);

    $company = $repository->save(new Company(userId: $user->id, name: '削除対象企業'));
    assert($company->id !== null);

    $response = actingAs($user)
        ->deleteJson("/api/companies/{$company->id}");

    $response->assertNoContent();

    assertDatabaseMissing('companies', [
        'id' => $company->id,
    ]);
});
