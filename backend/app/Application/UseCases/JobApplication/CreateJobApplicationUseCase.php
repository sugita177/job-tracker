<?php

declare(strict_types=1);

namespace App\Application\UseCases\JobApplication;

use App\Application\UseCases\JobApplication\Dto\CreateJobApplicationInput;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Domain\JobApplication\Entities\JobApplication;
use App\Domain\JobApplication\Repositories\JobApplicationRepositoryInterface;
use App\Domain\JobApplication\ValueObjects\ApplicationChannel;
use App\Domain\JobApplication\ValueObjects\ApplicationStatus;
use App\Domain\JobApplication\ValueObjects\ChannelType;
use App\Domain\JobApplication\ValueObjects\Priority;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateJobApplicationUseCase
{
    public function __construct(
        private JobApplicationRepositoryInterface $jobApplicationRepository,
        private CompanyRepositoryInterface $companyRepository,
    ) {}

    public function execute(CreateJobApplicationInput $input): JobApplication
    {
        // 他人の会社、または存在しない会社への紐付けを遮断（マルチテナント多重防御）
        $company = $this->companyRepository->findById($input->companyId, $input->userId);
        if ($company === null) {
            throw new InvalidArgumentException('指定された企業が存在しません。');
        }

        $priority = Priority::from($input->priority);
        $status = ApplicationStatus::from($input->status);

        // 新規登録時の初期ステータスを厳格に制限
        if (! in_array($status, [ApplicationStatus::INTERESTED, ApplicationStatus::DOCUMENT_SCREENING], true)) {
            throw new InvalidArgumentException("新規登録時の初期ステータスは「検討中」または「書類選考中」のみ指定可能です。指定された値: {$status->value}");
        }

        $channel = $input->channelType !== null
            ? new ApplicationChannel(ChannelType::from($input->channelType), $input->channelDetailName)
            : null;

        if ($status === ApplicationStatus::DOCUMENT_SCREENING) {
            if ($channel === null) {
                throw new InvalidArgumentException('応募済みとして登録する場合は、応募媒体の指定が必須です。');
            }

            $application = JobApplication::createApplied(
                userId: $input->userId,
                companyId: $input->companyId,
                title: $input->title,
                priority: $priority,
                channel: $channel,
                appliedAt: $input->appliedAt ?? new DateTimeImmutable(),
                jobUrl: $input->jobUrl,
                notes: $input->notes,
            );
        } else {
            $application = JobApplication::createInterested(
                userId: $input->userId,
                companyId: $input->companyId,
                title: $input->title,
                priority: $priority,
                channel: $channel,
                jobUrl: $input->jobUrl,
                notes: $input->notes,
            );
        }

        return $this->jobApplicationRepository->save($application);
    }
}
