<?php

declare(strict_types=1);

namespace App\Domain\Company\Entities;

use InvalidArgumentException;

final class Company
{
    public function __construct(
        public readonly int $userId,
        public private(set) string $name,
        public private(set) ?string $url = null,
        public private(set) ?string $memo = null,
        public readonly ?int $id = null,
    ) {
        $this->update($name, $url, $memo);
    }

    /**
     * 企業情報を更新する
     */
    public function update(string $name, ?string $url = null, ?string $memo = null): void
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('企業名は必須です。');
        }
        $trimmedUrl = trim($url ?? '');
        $trimmedMemo = trim($memo ?? '');
        $this->name = $trimmedName;
        $this->url = $trimmedUrl === '' ? null : $trimmedUrl;
        $this->memo = $trimmedMemo === '' ? null : $trimmedMemo;
    }
}
