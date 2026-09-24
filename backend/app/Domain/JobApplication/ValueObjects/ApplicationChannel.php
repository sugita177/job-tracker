<?php

declare(strict_types=1);

namespace App\Domain\JobApplication\ValueObjects;

final class ApplicationChannel
{
    public readonly ChannelType $type;
    public readonly ?string $detailName;

    public function __construct(ChannelType $type, ?string $detailName = null)
    {
        $this->type = $type;
        // 空白文字のみの場合は null に正規化し、前後の空白をトリム
        $trimmed = $detailName !== null ? trim($detailName) : null;
        $this->detailName = ($trimmed !== null && $trimmed !== '') ? $trimmed : null;
    }

    public static function direct(): self
    {
        return new self(ChannelType::DIRECT);
    }

    public static function agent(?string $agentName = null): self
    {
        return new self(ChannelType::AGENT, $agentName);
    }

    /**
     * 値オブジェクトの等価性（同一プロパティを持つか）を検証する
     */
    public function equals(self $other): bool
    {
        return $this->type === $other->type 
            && $this->detailName === $other->detailName;
    }

    /**
     * 表示用の文字列を取得する
     */
    public function getDisplayName(): string
    {
        if ($this->detailName !== null) {
            return sprintf('%s (%s)', $this->type->getLabel(), $this->detailName);
        }

        return $this->type->getLabel();
    }
}
