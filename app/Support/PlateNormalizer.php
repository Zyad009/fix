<?php

namespace App\Support;

/**
 * Canonical form of an Egyptian licence plate, used for search and uniqueness.
 *
 * "س ص ع ١٢٣", "سصع-123" and "123 س ص ع" all normalize to "سصع123":
 * separators and diacritics are dropped, Eastern-Arabic digits become Western,
 * look-alike Arabic letters are unified, and letters are placed before digits
 * (people type the two halves in either order).
 */
final class PlateNormalizer
{
    /** @var array<string, string> */
    private const CHARACTER_MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ة' => 'ه',
    ];

    public static function normalize(?string $plate): string
    {
        if ($plate === null) {
            return '';
        }

        $plate = strtr($plate, self::CHARACTER_MAP);

        // Drop tatweel, Arabic diacritics and anything that is not a letter or digit.
        $plate = preg_replace('/[\x{0640}\x{064B}-\x{0652}]|[^\p{L}\p{N}]/u', '', $plate) ?? '';
        $plate = mb_strtoupper($plate);

        preg_match_all('/\p{N}/u', $plate, $digits);
        preg_match_all('/\p{L}/u', $plate, $letters);

        return implode('', $letters[0]).implode('', $digits[0]);
    }
}
