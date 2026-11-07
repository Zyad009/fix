<?php

namespace App\Support;

/**
 * Stores phone numbers as plain digits (Eastern-Arabic digits converted),
 * so "٠١٠ ١٢٣-٤٥٦٧٨" and "01012345678" are the same customer.
 */
final class PhoneNormalizer
{
    /**
     * An Egyptian mobile after normalize(): 11 digits starting 010/011/012/015.
     */
    public const EGYPT_MOBILE_REGEX = '/^01[0125]\d{8}$/';

    /**
     * Validation rule for a customer-facing mobile number (input already normalized).
     */
    public static function rule(): string
    {
        return 'regex:'.self::EGYPT_MOBILE_REGEX;
    }

    public static function normalize(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        $phone = strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * International digits for WhatsApp (01012345678 → 201012345678), or null
     * when the number is unusable. A leading "+" or "00" already carries the
     * country code.
     */
    public static function toWhatsapp(?string $phone): ?string
    {
        $digits = self::normalize($phone);
        $countryCode = (string) config('services.gowa.country_code');

        $digits = match (true) {
            $digits === '' => '',
            str_starts_with(trim((string) $phone), '+') => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '0') => $countryCode.substr($digits, 1),
            str_starts_with($digits, $countryCode) => $digits,
            default => $countryCode.$digits,
        };

        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * Partly hidden for logs (201551****52).
     */
    public static function mask(string $phone): string
    {
        return strlen($phone) <= 8
            ? str_repeat('*', strlen($phone))
            : substr($phone, 0, 6).str_repeat('*', strlen($phone) - 8).substr($phone, -2);
    }
}
