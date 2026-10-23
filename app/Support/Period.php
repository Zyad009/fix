<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * A reporting window expressed in the tenant's local days and converted to
 * UTC bounds for querying stored (UTC) timestamps.
 */
final class Period
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $timezone,
    ) {}

    /**
     * Build from `from` / `to` (Y-m-d, local) filters; both default to today.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilters(array $filters): self
    {
        $timezone = tenantContext()->check() ? tenantContext()->tenant()->timezone : config('app.timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();

        $from = CarbonImmutable::parse($filters['from'] ?? $today, $timezone)->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? $filters['from'] ?? $today, $timezone)->endOfDay();

        return new self($from->utc(), $to->utc(), $timezone);
    }

    public static function today(): self
    {
        return self::fromFilters([]);
    }

    /**
     * Local calendar date of a stored UTC timestamp.
     */
    public function localDate(mixed $timestamp): string
    {
        return CarbonImmutable::parse($timestamp, 'UTC')->setTimezone($this->timezone)->toDateString();
    }

    public function localFrom(): string
    {
        return $this->start->setTimezone($this->timezone)->toDateString();
    }

    public function localTo(): string
    {
        return $this->end->setTimezone($this->timezone)->toDateString();
    }

    /**
     * @return list<string>
     */
    public function localDays(): array
    {
        $days = [];

        foreach (CarbonPeriod::create($this->localFrom(), $this->localTo()) as $day) {
            $days[] = $day->toDateString();
        }

        return $days;
    }
}
