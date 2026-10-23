<?php

namespace App\Support\Traits;

/**
 * Shared helpers for string-backed enums that declare a
 * `LABELS = [value => ['ar' => ..., 'en' => ...]]` constant.
 */
trait EnumTrait
{
    public function label(?string $lang = null): string
    {
        $lang ??= app()->getLocale();

        return static::LABELS[$this->value][$lang]
            ?? static::LABELS[$this->value]['ar'];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array{label: string, value: string}
     */
    public function response(?string $lang = null): array
    {
        return [
            'label' => $this->label($lang),
            'value' => $this->value,
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function options(?string $lang = null): array
    {
        return array_map(fn (self $item): array => $item->response($lang), self::cases());
    }
}
