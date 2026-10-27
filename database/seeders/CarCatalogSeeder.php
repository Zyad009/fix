<?php

namespace Database\Seeders;

use App\Models\CarMake;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Makes and models common on Egyptian roads, with their logos. Idempotent:
 * safe to re-run (fills a missing logo, never overwrites one the admin set).
 *
 * Logos come from the open-source car-logos-dataset on jsDelivr, pinned to a
 * commit so the URLs never change under the frontend.
 */
class CarCatalogSeeder extends Seeder
{
    private const LOGO_BASE = 'https://cdn.jsdelivr.net/gh/filippofilip95/car-logos-dataset@e334a4d2e8742108ba164c629dff5636576d3808/logos/optimized/';

    /** @var array<string, array{0: string, 1: list<string>}> name_ar => [name_en, models] */
    private const CATALOG = [
        'هيونداي' => ['Hyundai', ['فيرنا', 'إلنترا', 'أكسنت', 'توسان', 'كريتا', 'i10', 'i20', 'ماتريكس']],
        'كيا' => ['Kia', ['سيراتو', 'ريو', 'بيكانتو', 'سبورتاج', 'سول', 'سيلتوس']],
        'تويوتا' => ['Toyota', ['كورولا', 'ياريس', 'فورتشنر', 'لاند كروزر', 'هايلكس', 'راف 4', 'C-HR']],
        'نيسان' => ['Nissan', ['صني', 'سنترا', 'قشقاي', 'جوك', 'إكس تريل']],
        'شيفروليه' => ['Chevrolet', ['أوبترا', 'لانوس', 'أفيو', 'كروز', 'كابتيفا', 'سبارك']],
        'ميتسوبيشي' => ['Mitsubishi', ['لانسر', 'باجيرو', 'أتراج', 'إكليبس كروس', 'إكسباندر']],
        'رينو' => ['Renault', ['لوجان', 'ميجان', 'داستر', 'كادجار', 'سانديرو']],
        'بيجو' => ['Peugeot', ['301', '3008', '5008', '2008', '508']],
        'سكودا' => ['Skoda', ['أوكتافيا', 'فابيا', 'كودياك', 'كاروك', 'سوبرب']],
        'فيات' => ['Fiat', ['تيبو', '128', '500', 'شاهين']],
        'فولكس فاجن' => ['Volkswagen', ['باسات', 'جولف', 'جيتا', 'تيجوان', 'بولو']],
        'بي إم دبليو' => ['BMW', ['الفئة الثالثة', 'الفئة الخامسة', 'X1', 'X3', 'X5']],
        'مرسيدس' => ['Mercedes-Benz', ['C-Class', 'E-Class', 'GLA', 'GLC', 'S-Class']],
        'إم جي' => ['MG', ['MG5', 'MG6', 'ZS', 'RX5', 'HS']],
        'شيري' => ['Chery', ['تيجو 3', 'تيجو 4', 'تيجو 7', 'أريزو 5', 'إنفي']],
        'سوزوكي' => ['Suzuki', ['سويفت', 'ديزاير', 'إرتيجا', 'ألتو', 'فيتارا']],
        'أوبل' => ['Opel', ['أسترا', 'كورسا', 'جراند لاند', 'كروس لاند']],
        'دايو' => ['Daewoo', ['لانوس', 'نوبيرا']],
        'هوندا' => ['Honda', ['سيفيك', 'أكورد', 'سيتي', 'CR-V']],
        'لادا' => ['Lada', ['جرانتا', 'فيستا', 'نيفا', '2107']],
        'سيات' => ['Seat', ['ليون', 'إبيزا', 'أرونا', 'أتيكا']],
        'بي واي دي' => ['BYD', ['F3', 'L3']],
        'جيلي' => ['Geely', ['إمجراند', 'كولراي', 'جي إكس 3']],
        'بروتون' => ['Proton', ['ساجا', 'بيرسونا']],
        'جيب' => ['Jeep', ['رانجلر', 'شيروكي', 'كومباس', 'رينيجيد']],
        'فورد' => ['Ford', ['فوكس', 'فييستا', 'إكسبلورر', 'كوجا']],
        'مازدا' => ['Mazda', ['3', '6', 'CX-5']],
        'سوبارو' => ['Subaru', ['إمبريزا', 'فورستر', 'XV']],
        'هافال' => ['Haval', ['H6', 'جوليان', 'H2']],
        'شانجان' => ['Changan', ['CS35', 'إيدو', 'CS75']],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $nameAr => [$nameEn, $models]) {
            $make = CarMake::query()->firstOrCreate(['name_ar' => $nameAr], ['name_en' => $nameEn]);
            $make->logo_url ??= self::LOGO_BASE.Str::slug($nameEn).'.png';
            $make->save();

            foreach ($models as $model) {
                $make->models()->firstOrCreate(['name_ar' => $model]);
            }
        }
    }
}
