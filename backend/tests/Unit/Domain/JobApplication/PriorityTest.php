<?php

use App\Domain\JobApplication\ValueObjects\Priority;

it('returns correct label for each priority', function (Priority $priority, string $expectedLabel) {
    expect($priority->getLabel())->toBe($expectedLabel);
})->with([
    'HIGH'   => [Priority::HIGH, '高 ★★★'],
    'MEDIUM' => [Priority::MEDIUM, '中 ★★☆'],
    'LOW'    => [Priority::LOW, '低 ★☆☆'],
]);

it('returns correct weight for sorting', function(Priority $priority, int $expectedWeight) {
    expect($priority->getWeight())->toBe($expectedWeight);
})->with([
    'HIGH is 3'   => [Priority::HIGH, 3],
    'MEDIUM is 2' => [Priority::MEDIUM, 2],
    'LOW is 1'    => [Priority::LOW, 1],
]);
