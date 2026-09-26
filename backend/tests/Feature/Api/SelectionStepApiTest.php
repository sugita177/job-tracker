<?php

declare(strict_types=1);

use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepResult;
use App\Domain\JobApplication\ValueObjects\StepType;
use App\Domain\Company\Entities\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

describe('SelectionStep API (選考ステップ管理)', function () {
    test('POST /api/job-applications/{id}/selection-steps で選考ステップを追加できること', function () {
        $user = User::factory()->create();

        $companyRepo = app(CompanyRepositoryInterface::class);
        $company = $companyRepo->save(new Company(userId: $user->id, name: 'テスト企業'));
        assert($company->id !== null);

        $repository = app(JobApplicationRepositoryInterface::class);
        $app = JobApplication::createApplied(
            userId: $user->id,
            companyId: $company->id,
            title: 'バックエンドエンジニア',
            priority: Priority::HIGH,
            channel: ApplicationChannel::direct(),
            appliedAt: new DateTimeImmutable('2026-03-01 10:00:00'),
        );
        $saved = $repository->save($app);
        assert($saved->id !== null);

        $payload = [
            'type' => StepType::FIRST_ROUND->value,
            'scheduled_at' => '2026-03-10T14:00:00+09:00',
            'location_or_url' => 'https://zoom.us/j/12345',
            'interviewer_info' => '開発部長 / 人事',
            'prep_memo' => '逆質問を3つ用意する',
        ];

        $response = actingAs($user)->postJson("/api/job-applications/{$saved->id}/selection-steps", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.type', StepType::FIRST_ROUND->value)
            ->assertJsonPath('data.location_or_url', 'https://zoom.us/j/12345')
            ->assertJsonPath('data.interviewer_info', '開発部長 / 人事')
            ->assertJsonPath('data.prep_memo', '逆質問を3つ用意する')
            ->assertJsonPath('data.result', StepResult::PENDING->value);

        assertDatabaseHas('selection_steps', [
            'job_application_id' => $saved->id,
            'type' => StepType::FIRST_ROUND->value,
            'interviewer_info' => '開発部長 / 人事',
        ]);
    });

    test('POST /api/job-applications/{id}/selection-steps で検討中(INTERESTED)の求人には追加できず 422 エラーになること', function () {
        $user = User::factory()->create();
        $companyRepo = app(CompanyRepositoryInterface::class);
        $company = $companyRepo->save(new Company(userId: $user->id, name: 'テスト企業'));
        assert($company->id !== null);

        $repository = app(JobApplicationRepositoryInterface::class);
        $app = JobApplication::createInterested(
            userId: $user->id,
            companyId: $company->id,
            title: '検討中求人',
        );
        $saved = $repository->save($app);
        assert($saved->id !== null);

        $payload = [
            'type' => StepType::FIRST_ROUND->value,
        ];

        // 検討中ステータスにはステップ追加できないドメイン例外が 422 にマッピングされる
        $response = actingAs($user)->postJson("/api/job-applications/{$saved->id}/selection-steps", $payload);

        $response->assertUnprocessable();
    });

    test('POST /api/job-applications/{id}/selection-steps で他人の求人を指定した場合は 404 になること', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $companyRepo = app(CompanyRepositoryInterface::class);
        $company2 = $companyRepo->save(new Company(userId: $user2->id, name: 'テスト企業'));
        assert($company2->id !== null);

        $repository = app(JobApplicationRepositoryInterface::class);
        $app2 = JobApplication::createApplied(
            userId: $user2->id,
            companyId: $company2->id,
            title: '他人の求人',
            priority: Priority::HIGH,
            channel: ApplicationChannel::direct(),
            appliedAt: new DateTimeImmutable('2026-03-01 10:00:00'),
        );
        $saved2 = $repository->save($app2);
        assert($saved2->id !== null);

        $payload = ['type' => StepType::FIRST_ROUND->value];

        // user1 が user2 の求人にステップ追加を試みる
        $response = actingAs($user1)->postJson("/api/job-applications/{$saved2->id}/selection-steps", $payload);

        $response->assertNotFound();
    });

    test('PUT /api/job-applications/{id}/selection-steps/{stepId} でステップ情報を更新できること', function () {
        $user = User::factory()->create();
        $companyRepo = app(CompanyRepositoryInterface::class);
        $company = $companyRepo->save(new Company(userId: $user->id, name: 'テスト企業'));
        assert($company->id !== null);

        $repository = app(JobApplicationRepositoryInterface::class);
        $app = JobApplication::createApplied(
            userId: $user->id,
            companyId: $company->id,
            title: 'バックエンドエンジニア',
            priority: Priority::HIGH,
            channel: ApplicationChannel::direct(),
            appliedAt: new DateTimeImmutable('2026-03-01 10:00:00'),
        );
        $app->addSelectionStep(new SelectionStep(
            type: StepType::FIRST_ROUND,
            scheduledAt: new DateTimeImmutable('2026-03-10 10:00:00'),
        ));
        $saved = $repository->save($app);
        $stepId = $saved->steps[0]->id;
        assert($saved->id !== null && $stepId !== null);

        $updatePayload = [
            'type' => StepType::FIRST_ROUND->value,
            'scheduled_at' => '2026-03-12T15:00:00+09:00',
            'location_or_url' => 'https://zoom.us/j/updated',
            'interviewer_info' => 'CTO',
            'prep_memo' => '技術スタックについて質問',
            'review_memo' => 'アーキテクチャの議論が盛り上がった',
            'result' => StepResult::PASSED->value,
        ];

        $response = actingAs($user)->putJson("/api/job-applications/{$saved->id}/selection-steps/{$stepId}", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('data.location_or_url', 'https://zoom.us/j/updated')
            ->assertJsonPath('data.interviewer_info', 'CTO')
            ->assertJsonPath('data.review_memo', 'アーキテクチャの議論が盛り上がった')
            ->assertJsonPath('data.result', StepResult::PASSED->value);

        assertDatabaseHas('selection_steps', [
            'id' => $stepId,
            'review_memo' => 'アーキテクチャの議論が盛り上がった',
            'result' => StepResult::PASSED->value,
        ]);
    });

    test('DELETE /api/job-applications/{id}/selection-steps/{stepId} でステップを削除できること', function () {
        $user = User::factory()->create();
        $companyRepo = app(CompanyRepositoryInterface::class);
        $company = $companyRepo->save(new Company(userId: $user->id, name: 'テスト企業'));
        assert($company->id !== null);

        $repository = app(JobApplicationRepositoryInterface::class);
        $app = JobApplication::createApplied(
            userId: $user->id,
            companyId: $company->id,
            title: 'バックエンドエンジニア',
            priority: Priority::HIGH,
            channel: ApplicationChannel::direct(),
            appliedAt: new DateTimeImmutable('2026-03-01 10:00:00'),
        );
        $app->addSelectionStep(new SelectionStep(type: StepType::FIRST_ROUND, scheduledAt: null));
        $saved = $repository->save($app);
        $stepId = $saved->steps[0]->id;
        assert($saved->id !== null && $stepId !== null);

        $response = actingAs($user)->deleteJson("/api/job-applications/{$saved->id}/selection-steps/{$stepId}");

        $response->assertNoContent();

        assertDatabaseMissing('selection_steps', [
            'id' => $stepId,
        ]);
    });
});
