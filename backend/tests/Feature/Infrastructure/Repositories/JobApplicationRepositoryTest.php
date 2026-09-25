<?php

declare(strict_types=1);

use App\Domain\Company\Entities\Company;
use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Entities\SelectionStep;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\Priority;
use App\Domain\JobApplication\ValueObjects\StepType;
use App\Infrastructure\Persistence\Repositories\CompanyRepository;
use App\Infrastructure\Persistence\Repositories\JobApplicationRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

describe('JobApplicationRepository (統合テスト)', function () {
    test('新規の JobApplication を保存でき、子エンティティ steps や statusHistories も連動して永続化される', function () {
        $user = User::factory()->create();
        $companyRepo = new CompanyRepository();
        $jobRepo = new JobApplicationRepository();

        $company = $companyRepo->save(new Company(userId: $user->id, name: 'テスト企業'));
        assert($company->id !== null);

        // 1. 集約ルートの作成
        $jobApp = new JobApplication(
            userId: $user->id,
            companyId: $company->id,
            title: 'バックエンドエンジニア',
            priority: Priority::HIGH,
        );

        // 2. 正式応募を実行（DOCUMENT_SCREENING になり、StatusHistory が1件追加される）
        $appliedAt = new DateTimeImmutable('2026-03-01 10:00:00');
        $jobApp->apply(ApplicationChannel::direct(), $appliedAt);

        // 3. 面談調整へ進め、面接ステップを追加
        $jobApp->advanceStatus(ApplicationStatus::INTERVIEW_ADJUSTING);
        $step = new SelectionStep(
            type: StepType::FIRST_ROUND,
            scheduledAt: new DateTimeImmutable('2026-03-10 14:00:00'),
            locationOrUrl: 'Google Meet',
        );
        $jobApp->addSelectionStep($step);

        // 4. 集約ルートを一括保存
        $saved = $jobRepo->save($jobApp);
        assert($saved->id !== null);

        // 5. DBに親・子のすべてのレコードが存在することを検証
        assertDatabaseHas('job_applications', [
            'id' => $saved->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'title' => 'バックエンドエンジニア',
            'current_status' => ApplicationStatus::INTERVIEW_ADJUSTING->value,
            'channel_type' => 'DIRECT',
        ]);

        assertDatabaseHas('selection_steps', [
            'job_application_id' => $saved->id,
            'type' => StepType::FIRST_ROUND->value,
            'location_or_url' => 'Google Meet',
        ]);

        // 通常遷移が2回（applyでの書類選考、面接調整中への遷移）発生しているので履歴は2件
        expect($saved->statusHistories)->toHaveCount(2)
            ->and($saved->steps)->toHaveCount(1);
    });

    test('findById で子エンティティを含めて集約が完全復元される（他ユーザーには取得不可）', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $companyRepo = new CompanyRepository();
        $jobRepo = new JobApplicationRepository();

        $companyA = $companyRepo->save(new Company(userId: $userA->id, name: '企業A'));
        assert($companyA->id !== null);

        $jobApp = new JobApplication(
            userId: $userA->id,
            companyId: $companyA->id,
            title: 'テックリード候補',
            channel: ApplicationChannel::agent('エージェントX'),
            currentStatus: ApplicationStatus::CASUAL_INTERVIEW,
        );
        $jobApp->addSelectionStep(new SelectionStep(
            type: StepType::CASUAL_INTERVIEW,
            scheduledAt: new DateTimeImmutable('2026-03-05 19:00:00'),
        ));

        $saved = $jobRepo->save($jobApp);
        assert($saved->id !== null);

        // User A として取得 -> 復元成功
        $found = $jobRepo->findById($saved->id, $userA->id);
        expect($found)->not->toBeNull();
        assert($found !== null);
        expect($found->title)->toBe('テックリード候補')
            ->and($found->channel?->detailName)->toBe('エージェントX')
            ->and($found->currentStatus)->toBe(ApplicationStatus::CASUAL_INTERVIEW)
            ->and($found->steps)->toHaveCount(1)
            ->and($found->steps[0]->type)->toBe(StepType::CASUAL_INTERVIEW);

        // User B として取得 -> 他人のデータなので null
        $notFound = $jobRepo->findById($saved->id, $userB->id);
        expect($notFound)->toBeNull();
    });

    test('listByUserId でステータス絞り込みができる', function () {
        $user = User::factory()->create();
        $companyRepo = new CompanyRepository();
        $jobRepo = new JobApplicationRepository();

        $company = $companyRepo->save(new Company(userId: $user->id, name: '企業'));
        assert($company->id !== null);

        $job1 = $jobRepo->save(new JobApplication(
            userId: $user->id,
            companyId: $company->id,
            title: '求人1',
            currentStatus: ApplicationStatus::INTERESTED,
        ));
        $job2 = $jobRepo->save(new JobApplication(
            userId: $user->id,
            companyId: $company->id,
            title: '求人2',
            currentStatus: ApplicationStatus::REJECTED,
        ));

        // 全件取得
        $all = $jobRepo->listByUserId($user->id);
        expect($all)->toHaveCount(2);

        // ステータス絞り込み
        $interestedOnly = $jobRepo->listByUserId($user->id, ApplicationStatus::INTERESTED);
        expect($interestedOnly)->toHaveCount(1)
            ->and($interestedOnly[0]->title)->toBe('求人1');
    });

        test('delete で求人応募を削除すると、CASCADE制約により配下の面談日程や履歴も連動削除される', function () {
        $user = User::factory()->create();
        $companyRepo = new CompanyRepository();
        $jobRepo = new JobApplicationRepository();

        $company = $companyRepo->save(new Company(userId: $user->id, name: '企業'));
        assert($company->id !== null);

        $jobApp = new JobApplication(
            userId: $user->id,
            companyId: $company->id,
            title: '削除テスト',
            currentStatus: ApplicationStatus::INTERESTED,
        );

        // 1. カジュアル面談に進める（StatusHistory が生成される）
        $jobApp->advanceStatus(ApplicationStatus::CASUAL_INTERVIEW);

        // 2. 面談ステップを追加（SelectionStep が生成される）
        $jobApp->addSelectionStep(new SelectionStep(
            type: StepType::CASUAL_INTERVIEW,
            scheduledAt: new DateTimeImmutable(),
        ));

        // 3. 集約ルートを保存（親・steps・histories がDBに入る）
        $saved = $jobRepo->save($jobApp);
        assert($saved->id !== null);

        // 4. 削除実行
        $jobRepo->delete($saved->id, $user->id);

        // 5. 親だけでなく、子（steps）も孫（status_histories）もすべてCASCADE消去されていることを検証
        assertDatabaseMissing('job_applications', ['id' => $saved->id]);
        assertDatabaseMissing('selection_steps', ['job_application_id' => $saved->id]);
        assertDatabaseMissing('status_histories', ['job_application_id' => $saved->id]);
    });
});
