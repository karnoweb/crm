<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Exceptions\InvalidIdempotencyKeyException;
use Karnoweb\Crm\Exceptions\InvalidWeightException;
use Karnoweb\Crm\Support\InteractionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InteractionValidatorTest extends TestCase
{
    private InteractionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InteractionValidator;
    }

    public function test_numeric_non_negative_finite_weight_is_accepted(): void
    {
        $this->assertSame(0.0, $this->validator->validateWeight(0));
        $this->assertSame(2.5, $this->validator->validateWeight(2.5));
        $this->assertSame(3.0, $this->validator->validateWeight('3'));
    }

    #[DataProvider('invalidWeights')]
    public function test_invalid_weights_are_rejected(mixed $weight): void
    {
        $this->expectException(InvalidWeightException::class);
        $this->validator->validateWeight($weight);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidWeights(): array
    {
        return [
            'negative' => [-0.1],
            'nan' => [NAN],
            'inf' => [INF],
            'negative-inf' => [-INF],
            'string' => ['heavy'],
            'null' => [null],
        ];
    }

    public function test_empty_idempotency_key_is_rejected(): void
    {
        $this->expectException(InvalidIdempotencyKeyException::class);
        $this->validator->validateIdempotencyKey('');
    }
}
