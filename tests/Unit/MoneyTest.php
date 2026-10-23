<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_normalizes_values_to_two_decimals(): void
    {
        $this->assertSame('150.00', (string) Money::of('150'));
        $this->assertSame('0.00', (string) Money::of(null));
        $this->assertSame('10.13', (string) Money::of('10.125'));
    }

    public function test_addition_is_exact_where_float_is_not(): void
    {
        $this->assertSame('0.30', (string) Money::of('0.10')->plus('0.20'));
        $this->assertTrue(Money::of('0.10')->plus('0.20')->isEqualTo('0.3'));
    }

    public function test_it_sums_and_subtracts(): void
    {
        $total = Money::sum(['300.50', '400.25', 0, null]);

        $this->assertSame('700.75', (string) $total);
        $this->assertSame('200.75', (string) $total->minus('500'));
    }

    public function test_multiplying_by_fractional_quantity_rounds_half_up(): void
    {
        $this->assertSame('3.38', (string) Money::of('2.25')->multipliedBy('1.5'));
        $this->assertSame('600.00', (string) Money::of('150')->multipliedBy(4));
    }

    public function test_comparisons(): void
    {
        $amount = Money::of('300');

        $this->assertTrue($amount->isGreaterThan('299.99'));
        $this->assertTrue($amount->isLessThan('300.01'));
        $this->assertTrue($amount->minus('300')->isZero());
        $this->assertTrue($amount->minus('301')->isNegative());
        $this->assertSame('250.00', (string) $amount->min('250'));
    }

    public function test_it_serializes_to_a_json_number(): void
    {
        $this->assertSame('{"total":1250.5}', json_encode(['total' => Money::of('1250.50')]));
    }
}
