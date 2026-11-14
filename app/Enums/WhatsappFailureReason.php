<?php

namespace App\Enums;

use App\Exceptions\DocumentImageException;
use App\Support\Traits\EnumTrait;
use Throwable;

/**
 * What users are told about a failed message; the raw error stays in the whatsapp log.
 */
enum WhatsappFailureReason: string
{
    use EnumTrait;

    case ImageFailed = 'image_failed';
    case SendFailed = 'send_failed';

    public const LABELS = [
        'image_failed' => ['ar' => 'تعذّر تجهيز صورة المستند. حاول مرة أخرى أو تواصل مع الدعم.', 'en' => 'The document image could not be prepared. Try again or contact support.'],
        'send_failed' => ['ar' => 'تعذّر إرسال الرسالة على واتساب. تأكد من الرقم واتصال واتساب ثم أعد الإرسال.', 'en' => 'The WhatsApp message could not be sent. Check the number and the WhatsApp connection, then resend.'],
    ];

    public static function fromException(Throwable $exception): self
    {
        return $exception instanceof DocumentImageException ? self::ImageFailed : self::SendFailed;
    }

    /**
     * Rows failed before reasons existed hold raw error text; they read as a send failure.
     */
    public static function fromStored(?string $error): ?self
    {
        return $error === null ? null : (self::tryFrom($error) ?? self::SendFailed);
    }
}
