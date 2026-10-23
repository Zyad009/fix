<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use JsonSerializable;
use Stringable;

/**
 * Immutable EGP amount with exactly two decimal places.
 *
 * All money arithmetic in PHP goes through this class — never float. Values
 * coming from the database (`decimal:2` casts) are strings and are accepted
 * as-is; floats are rejected on purpose.
 */
final class Money implements JsonSerializable, Stringable
{
    public const SCALE = 2;

    private function __construct(private readonly BigDecimal $amount) {}

    public static function of(self|BigNumber|int|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return self::zero();
        }

        return new self(BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    /**
     * @param  iterable<self|BigNumber|int|string|null>  $values
     */
    public static function sum(iterable $values): self
    {
        $total = self::zero();

        foreach ($values as $value) {
            $total = $total->plus($value);
        }

        return $total;
    }

    public function plus(self|BigNumber|int|string|null $other): self
    {
        return new self($this->amount->plus(self::of($other)->amount));
    }

    public function minus(self|BigNumber|int|string|null $other): self
    {
        return new self($this->amount->minus(self::of($other)->amount));
    }

    /**
     * Multiply by a (possibly fractional) quantity, rounding half-up to piasters.
     */
    public function multipliedBy(BigNumber|int|string $factor): self
    {
        return new self($this->amount->multipliedBy($factor)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public function compareTo(self|BigNumber|int|string|null $other): int
    {
        return $this->amount->compareTo(self::of($other)->amount);
    }

    public function isEqualTo(self|BigNumber|int|string|null $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isGreaterThan(self|BigNumber|int|string|null $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self|BigNumber|int|string|null $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function min(self|BigNumber|int|string|null $other): self
    {
        return $this->isGreaterThan($other) ? self::of($other) : $this;
    }

    /**
     * Float for JSON output only — never feed the result back into arithmetic.
     */
    public function toFloat(): float
    {
        return $this->amount->toFloat();
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    public function jsonSerialize(): float
    {
        return $this->toFloat();
    }
}
