<?php

declare(strict_types=1);

use App\Domain\Dawah\Services\BudgetVariance;

it('measures variance against the approved amount, or the estimate before approval', function (string $estimated, ?string $approved, ?string $actual, string $variance): void {
    expect(BudgetVariance::amount($estimated, $approved, $actual))->toBe($variance);
})->with([
    'over approved' => ['100.00', '120.00', '150.00', '30.00'],
    'under approved' => ['100.00', '120.00', '90.50', '-29.50'],
    'not approved yet' => ['100.00', null, '110.00', '10.00'],
    'no actual yet' => ['100.00', '80.00', null, '-80.00'],
]);

it('gives the variance share of the baseline, none for a zero baseline', function (): void {
    expect(BudgetVariance::ratio('100.00', '200.00', '230.00'))->toBe(0.15)
        ->and(BudgetVariance::ratio('0.00', null, '10.00'))->toBeNull();
});

it('keeps halalas exact', function (): void {
    expect(BudgetVariance::cents('0.10') + BudgetVariance::cents('0.20'))->toBe(30)
        ->and(BudgetVariance::format(-5))->toBe('-0.05');
});
