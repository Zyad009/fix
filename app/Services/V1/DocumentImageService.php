<?php

namespace App\Services\V1;

use App\Exceptions\DocumentImageException;
use App\Models\Order;
use App\Models\PaymentReceipt;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Invoice and receipt pictures for WhatsApp, rendered by headless Chrome. The
 * pages embed their fonts and logo, so rendering needs no network. Returns the
 * path of a temporary PNG; the caller deletes it.
 */
class DocumentImageService
{
    private const WIDTH = 800;

    /** @var list<array{weight: int, subset: string}> */
    private const FONTS = [
        ['weight' => 400, 'subset' => 'arabic'],
        ['weight' => 400, 'subset' => 'latin'],
        ['weight' => 700, 'subset' => 'arabic'],
        ['weight' => 700, 'subset' => 'latin'],
    ];

    public function __construct(
        private readonly PrintService $print,
        private readonly BalanceService $balances,
    ) {}

    public function invoice(Order $order): string
    {
        return $this->render('whatsapp.invoice', $this->print->invoiceData($order));
    }

    /**
     * @param  array<string, mixed>  $changes  {@see InvoiceChangeService::unsent()}
     */
    public function invoiceUpdate(Order $order, array $changes): string
    {
        return $this->render('whatsapp.invoice-update', [
            ...$this->print->invoiceData($order),
            'changes' => $changes,
            'updated_at' => now()->setTimezone(maintenanceCenterContext()->maintenanceCenter()->timezone)->format('Y-m-d H:i'),
        ]);
    }

    public function receipt(PaymentReceipt $receipt): string
    {
        return $this->render('whatsapp.receipt', $this->print->receiptData($receipt));
    }

    public function customerDebt(PaymentReceipt $receipt): float
    {
        return $this->balances->customerBalance($receipt->customer_id)->toFloat();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data): string
    {
        $directory = storage_path('app/whatsapp-temp');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.'/'.Str::random(32).'.png';

        $html = view($view, [...$data, 'fonts' => $this->fonts(), 'logo' => $this->logo($data['center']['logo_path'] ?? null)])->render();

        try {
            $this->browser($html)->save($path);
        } catch (Throwable $th) {
            throw new DocumentImageException($th);
        }

        return $path;
    }

    private function browser(string $html): Browsershot
    {
        $browser = Browsershot::html($html)
            ->windowSize(self::WIDTH, 600)
            ->deviceScaleFactor(2)
            ->fullPage()
            ->setNodeModulePath(base_path('node_modules'))
            ->timeout(90);

        foreach (['node' => 'setNodeBinary', 'npm' => 'setNpmBinary', 'chrome' => 'setChromePath'] as $key => $setter) {
            if ($binary = config('services.browsershot.'.$key)) {
                $browser->{$setter}($binary);
            }
        }

        return config('services.browsershot.no_sandbox') ? $browser->noSandbox() : $browser;
    }

    /**
     * @return list<array{weight: int, data: string}>
     */
    private function fonts(): array
    {
        return array_map(fn (array $font): array => [
            'weight' => $font['weight'],
            'data' => base64_encode((string) file_get_contents(resource_path("fonts/cairo-{$font['subset']}-{$font['weight']}.woff2"))),
        ], self::FONTS);
    }

    private function logo(?string $path): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        return 'data:'.mime_content_type($path).';base64,'.base64_encode((string) file_get_contents($path));
    }
}
