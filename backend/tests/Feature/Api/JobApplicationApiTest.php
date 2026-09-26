<?php

declare(strict_types=1);

use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\ChannelType;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepType;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;



uses(RefreshDatabase::class);

test('GET /api/job-applications でログインユーザーの求人一覧を取得でき、ステータス絞り込みができること', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '株式会社A'));
    $otherCompany = $companyRepo->save(new Company(userId: $otherUser->id, name: '他人の会社'));
    assert($company->id !== null && $otherCompany->id !== null);

    // ユーザー自身の求人2件（ステータス違い）
    $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: 'バックエンドエンジニア',
        priority: Priority::HIGH,
        currentStatus: ApplicationStatus::INTERESTED,
    ));
    $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: 'テックリード',
        priority: Priority::MEDIUM,
        currentStatus: ApplicationStatus::DOCUMENT_SCREENING,
        channel: new ApplicationChannel(ChannelType::DIRECT),
        appliedAt: new DateTimeImmutable('2026-10-01'),
    ));

    // 他人の求人1件
    $appRepo->save(new JobApplication(
        userId: $otherUser->id,
        companyId: $otherCompany->id,
        title: '他人の求人',
        priority: Priority::LOW,
    ));

    // 全件取得
    $response = actingAs($user)->getJson('/api/job-applications');
    $response->assertOk()
        ->assertJsonCount(2, 'data');

    // ステータス絞り込み（INTERESTED のみ）
    $filteredResponse = actingAs($user)->getJson('/api/job-applications?status=INTERESTED');
    $filteredResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'バックエンドエンジニア');
});

test('POST /api/job-applications で検討中(INTERESTED)として新規登録できること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $company = $companyRepo->save(new Company(userId: $user->id, name: 'ターゲット企業'));
    assert($company->id !== null);

    $payload = [
        'company_id' => $company->id,
        'title' => 'Laravelエンジニア',
        'priority' => 'HIGH',
        'status' => 'INTERESTED',
        'job_url' => 'https://example.com/jobs/1',
        'notes' => 'カジュアル面談でDDDについて聞く',
    ];

    $response = actingAs($user)->postJson('/api/job-applications', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Laravelエンジニア')
        ->assertJsonPath('data.priority', 'HIGH')
        ->assertJsonPath('data.current_status', 'INTERESTED')
        ->assertJsonPath('data.company.name', 'ターゲット企業');

    assertDatabaseHas('job_applications', [
        'user_id' => $user->id,
        'company_id' => $company->id,
        'title' => 'Laravelエンジニア',
        'current_status' => 'INTERESTED',
    ]);
});

test('POST /api/job-applications で応募済(DOCUMENT_SCREENING)として登録時は媒体と応募日が記録されること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $company = $companyRepo->save(new Company(userId: $user->id, name: '応募企業'));
    assert($company->id !== null);

    $payload = [
        'company_id' => $company->id,
        'title' => 'リードエンジニア',
        'priority' => 'HIGH',
        'status' => 'DOCUMENT_SCREENING',
        'channel_type' => 'AGENT',
        'channel_detail_name' => '転職エージェントA',
        'applied_at' => '2026-10-05',
    ];

    $response = actingAs($user)->postJson('/api/job-applications', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.current_status', 'DOCUMENT_SCREENING')
        ->assertJsonPath('data.channel.type', 'AGENT')
        ->assertJsonPath('data.channel.detail_name', '転職エージェントA')
        ->assertJsonPath('data.applied_at', '2026-10-05T00:00:00+00:00');
});

test('POST /api/job-applications で他人の会社を指定した場合は 422 エラーになること', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $otherCompany = $companyRepo->save(new Company(userId: $otherUser->id, name: '他人の会社'));
    assert($otherCompany->id !== null);

    $payload = [
        'company_id' => $otherCompany->id,
        'title' => '不正な求人',
        'priority' => 'HIGH',
        'status' => 'INTERESTED',
    ];

    $response = actingAs($user)->postJson('/api/job-applications', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);
});

test('GET /api/job-applications/{id} で求人詳細を取得できること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '詳細企業'));
    assert($company->id !== null);

    $application = $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '詳細テスト求人',
        priority: Priority::HIGH,
    ));
    assert($application->id !== null);

    $response = actingAs($user)->getJson("/api/job-applications/{$application->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $application->id)
        ->assertJsonPath('data.title', '詳細テスト求人')
        ->assertJsonPath('data.selection_steps', [])
        ->assertJsonPath('data.status_histories', []);
});

test('PUT /api/job-applications/{id} で求人の基本情報を更新できること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '更新企業'));
    assert($company->id !== null);

    $application = $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '変更前タイトル',
        priority: Priority::LOW,
    ));
    assert($application->id !== null);

    $response = actingAs($user)->putJson("/api/job-applications/{$application->id}", [
        'title' => '変更後タイトル',
        'priority' => 'HIGH',
        'job_url' => 'https://example.com/updated',
        'notes' => '更新メモ',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', '変更後タイトル')
        ->assertJsonPath('data.priority', 'HIGH')
        ->assertJsonPath('data.job_url', 'https://example.com/updated');

    assertDatabaseHas('job_applications', [
        'id' => $application->id,
        'title' => '変更後タイトル',
        'priority' => 'HIGH',
    ]);
});

test('DELETE /api/job-applications/{id} で求人を削除でき、子要素(step, history)もCASCADE削除されること (204 No Content)', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '削除企業'));
    assert($company->id !== null);

    // 検討中からスタートし、ステップと履歴を生成
    $application = new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '削除対象求人',
        priority: Priority::LOW,
        currentStatus: ApplicationStatus::CASUAL_INTERVIEW,
    );
    $application->addSelectionStep(new SelectionStep(
        type: StepType::CASUAL_INTERVIEW,
        scheduledAt: new DateTimeImmutable('2026-10-10 14:00:00'),
    ));
    // 応募を実行（これで DOCUMENT_SCREENING になり StatusHistory が作られる）
    $application->apply(
        new ApplicationChannel(ChannelType::DIRECT),
        new DateTimeImmutable('2026-10-01')
    );

    $saved = $appRepo->save($application);
    assert($saved->id !== null);

    // 保存時点で子テーブルにレコードが存在することを確認
    assertDatabaseHas('selection_steps', ['job_application_id' => $saved->id]);
    assertDatabaseHas('status_histories', ['job_application_id' => $saved->id]);

    // API経由で削除実行
    $response = actingAs($user)->deleteJson("/api/job-applications/{$saved->id}");

    $response->assertNoContent();

    // 1. 求人本体が削除されていること
    assertDatabaseMissing('job_applications', [
        'id' => $saved->id,
    ]);

    // 2. 配下のステップと履歴がCASCADE削除されていること
    assertDatabaseMissing('selection_steps', [
        'job_application_id' => $saved->id,
    ]);
    assertDatabaseMissing('status_histories', [
        'job_application_id' => $saved->id,
    ]);

    // 3. 別集約の企業情報は削除されずに残っていること
    assertDatabaseHas('companies', [
        'id' => $company->id,
    ]);
});


test('POST /api/job-applications/{id}/advance-status で正常なステータス進行ができ、履歴が記録されること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '進行企業'));
    assert($company->id !== null);

    // 初期状態: 検討中
    $application = $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '進行求人',
        priority: Priority::HIGH,
        currentStatus: ApplicationStatus::INTERESTED,
    ));
    assert($application->id !== null);

    // 検討中 → 書類選考中 へ進行（応募日・媒体を指定）
    $response = actingAs($user)->postJson("/api/job-applications/{$application->id}/advance-status", [
        'to_status' => 'DOCUMENT_SCREENING',
        'channel_type' => 'DIRECT',
        'channel_detail_name' => '企業HP',
        'applied_at' => '2026-10-06',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.current_status', 'DOCUMENT_SCREENING');

    assertDatabaseHas('job_applications', [
        'id' => $application->id,
        'current_status' => 'DOCUMENT_SCREENING',
    ]);

    // 履歴が自動記録されていること
    assertDatabaseHas('status_histories', [
        'job_application_id' => $application->id,
        'from_status' => 'INTERESTED',
        'to_status' => 'DOCUMENT_SCREENING',
        'type' => 'TRANSITION',
    ]);
});

test('POST /api/job-applications/{id}/advance-status で不正な飛び級遷移は 422 エラーになること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '不正遷移企業'));
    assert($company->id !== null);

    // 検討中
    $application = $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '飛び級求人',
        priority: Priority::HIGH,
        currentStatus: ApplicationStatus::INTERESTED,
    ));
    assert($application->id !== null);

    // 検討中 → いきなり内定（ACCEPTED）への不正遷移
    $response = actingAs($user)->postJson("/api/job-applications/{$application->id}/advance-status", [
        'to_status' => 'ACCEPTED',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.domain.0', fn ($message) => is_string($message) && str_contains($message, '許可されていません'));
});

test('POST /api/job-applications/{id}/correct-status で理由付きでステータス訂正できること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '訂正企業'));
    assert($company->id !== null);

    // 初期状態: お見送り（REJECTED）
    $application = $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '訂正求人',
        priority: Priority::HIGH,
        currentStatus: ApplicationStatus::REJECTED,
    ));
    assert($application->id !== null);

    // 理由付きで 面接進行中 に訂正
    $response = actingAs($user)->postJson("/api/job-applications/{$application->id}/correct-status", [
        'to_status' => 'INTERVIEW_IN_PROGRESS',
        'reason' => '操作ミスでお見送りにしてしまったため差し戻し',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.current_status', 'INTERVIEW_IN_PROGRESS');

    assertDatabaseHas('job_applications', [
        'id' => $application->id,
        'current_status' => 'INTERVIEW_IN_PROGRESS',
    ]);

    // 訂正理由付きの履歴が記録されていること
    assertDatabaseHas('status_histories', [
        'job_application_id' => $application->id,
        'from_status' => 'REJECTED',
        'to_status' => 'INTERVIEW_IN_PROGRESS',
        'type' => 'CORRECTION',
        'reason' => '操作ミスでお見送りにしてしまったため差し戻し',
    ]);
});

test('POST /api/job-applications/{id}/correct-status で理由が空の場合は 422 エラーになること', function () {
    $user = User::factory()->create();
    $companyRepo = app(CompanyRepositoryInterface::class);
    $appRepo = app(JobApplicationRepositoryInterface::class);

    $company = $companyRepo->save(new Company(userId: $user->id, name: '訂正理由なし企業'));
    assert($company->id !== null);

    $application = $appRepo->save(new JobApplication(
        userId: $user->id,
        companyId: $company->id,
        title: '訂正求人',
        priority: Priority::HIGH,
        currentStatus: ApplicationStatus::REJECTED,
    ));
    assert($application->id !== null);

    $response = actingAs($user)->postJson("/api/job-applications/{$application->id}/correct-status", [
        'to_status' => 'INTERVIEW_IN_PROGRESS',
        'reason' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});
