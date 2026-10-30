<?php

namespace App\Services\V1;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client of the GOWA WhatsApp gateway. Both sends return the provider's
 * message id and throw when the gateway did not confirm the message, so a
 * queue retry only ever follows a real failure.
 */
class GowaWhatsappService
{
    public function sendText(string $phone, string $message, ?string $deviceId = null): string
    {
        return $this->messageId($this->request($deviceId)->post($this->url('send/message'), [
            'phone' => $this->toJid($phone),
            'message' => $message,
        ]));
    }

    /**
     * Uploads the local file directly: no public URL is needed.
     */
    public function sendImage(string $phone, string $imagePath, string $caption = '', ?string $deviceId = null): string
    {
        return $this->messageId($this->request($deviceId)
            ->attach('image', (string) file_get_contents($imagePath), basename($imagePath))
            ->post($this->url('send/image'), [
                'phone' => $this->toJid($phone),
                'caption' => $caption,
                'compress' => 'false',
            ]));
    }

    private function request(?string $deviceId): PendingRequest
    {
        $request = Http::withBasicAuth((string) config('services.gowa.username'), (string) config('services.gowa.password'))
            ->acceptJson()
            ->timeout(30);

        return $deviceId ? $request->withHeaders(['X-Device-Id' => $deviceId]) : $request;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.gowa.url'), '/').'/'.$path;
    }

    /**
     * GOWA answers {"code": "SUCCESS", "results": {"message_id": "..."}}. The
     * message id is the one reliable success signal.
     */
    private function messageId(Response $response): string
    {
        $messageId = $response->json('results.message_id');

        if (! $response->successful() || blank($messageId)) {
            throw new RuntimeException((string) ($response->json('message') ?? 'Unknown error from GOWA (HTTP '.$response->status().')'));
        }

        return (string) $messageId;
    }

    private function toJid(string $phone): string
    {
        return preg_replace('/\D/', '', $phone).'@s.whatsapp.net';
    }
}
