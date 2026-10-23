<?php

namespace Tests\Unit;

use App\Support\PlateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlateNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function equivalentSpellings(): array
    {
        return [
            'spaced letters, western digits' => ['س ص ع 123'],
            'compact' => ['سصع123'],
            'eastern arabic digits' => ['س ص ع ١٢٣'],
            'persian digits' => ['س ص ع ۱۲۳'],
            'digits first' => ['123 س ص ع'],
            'dashes and pipes' => ['س-ص-ع | 1-2-3'],
            'tatweel and diacritics' => ['سـصَـع 123'],
            'surrounding whitespace' => ["  س ص ع\t123  "],
        ];
    }

    #[DataProvider('equivalentSpellings')]
    public function test_equivalent_spellings_normalize_to_the_same_value(string $plate): void
    {
        $this->assertSame('سصع123', PlateNormalizer::normalize($plate));
    }

    public function test_look_alike_letters_are_unified(): void
    {
        $this->assertSame('ايه45', PlateNormalizer::normalize('أ ى ة ٤٥'));
        $this->assertSame('ايه45', PlateNormalizer::normalize('إ ي ه 45'));
    }

    public function test_latin_letters_are_upper_cased(): void
    {
        $this->assertSame('ABC123', PlateNormalizer::normalize('abc 123'));
    }

    public function test_empty_input(): void
    {
        $this->assertSame('', PlateNormalizer::normalize(null));
        $this->assertSame('', PlateNormalizer::normalize(' - '));
    }
}
