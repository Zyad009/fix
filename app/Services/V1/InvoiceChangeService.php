<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\WhatsappMessage;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * What the customer has not seen yet on a completed invoice: the edits made
 * since the last invoice image actually delivered to them (all edits when none
 * was). Read back from the audit trail, so nothing extra is stored.
 */
class InvoiceChangeService
{
    /** Header fields the customer sees; the technician is internal. */
    public const CUSTOMER_FIELDS = ['discount', 'odometer', 'next_service_odometer', 'next_service_date', 'notes'];

    private const LINE_EVENTS = [AuditEvent::ItemAdded, AuditEvent::ItemUpdated, AuditEvent::ItemRemoved];

    /**
     * @return array{
     *     lines: list<array{action: string, name: string, old_qty: ?float, new_qty: ?float, old_price: ?float, new_price: ?float}>,
     *     fields: list<array{field: string, old: ?string, new: ?string}>,
     *     previous_total: float,
     *     total: float,
     *     difference: float,
     * }
     */
    public function unsent(Order $order, ?int $exceptMessageId = null): array
    {
        $lines = [];
        $fields = [];
        $difference = Money::zero();

        foreach ($this->edits($order, $this->lastDeliveredAt($order, $exceptMessageId)) as $log) {
            if (in_array($log->event, self::LINE_EVENTS, true)) {
                [$line, $delta] = $this->line($log);

                if ($line !== null) {
                    $lines[] = $line;
                    $difference = $difference->plus($delta);
                }

                continue;
            }

            foreach ($this->changedFields($log->old_values ?? [], $log->new_values ?? []) as $field => [$old, $new]) {
                $fields[] = ['field' => $field, 'old' => $old, 'new' => $new];

                if ($field === 'discount') {
                    $difference = $difference->minus(Money::of($new)->minus($old));
                }
            }
        }

        return [
            'lines' => $lines,
            'fields' => $fields,
            'previous_total' => Money::of($order->total)->minus($difference)->toFloat(),
            'total' => formatNumber($order->total),
            'difference' => $difference->toFloat(),
        ];
    }

    /**
     * The customer-visible header fields whose value really changed.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{?string, ?string}>
     */
    public function changedFields(array $old, array $new): array
    {
        $changed = [];

        foreach (self::CUSTOMER_FIELDS as $field) {
            if (! array_key_exists($field, $old) && ! array_key_exists($field, $new)) {
                continue;
            }

            $before = $this->normalize($field, $old[$field] ?? null);
            $after = $this->normalize($field, $new[$field] ?? null);

            $same = $field === 'discount' ? Money::of($before)->isEqualTo($after) : $before === $after;

            if (! $same) {
                $changed[$field] = [$before, $after];
            }
        }

        return $changed;
    }

    private function lastDeliveredAt(Order $order, ?int $exceptMessageId): ?Carbon
    {
        $sentAt = WhatsappMessage::query()
            ->where('order_id', $order->id)
            ->whereIn('type', [WhatsappMessageType::Invoice, WhatsappMessageType::InvoiceUpdated])
            ->where('status', WhatsappMessageStatus::Sent)
            ->when($exceptMessageId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->max('sent_at');

        return $sentAt === null ? null : Carbon::parse($sentAt);
    }

    /**
     * @return iterable<AuditLog>
     */
    private function edits(Order $order, ?Carbon $since): iterable
    {
        return AuditLog::query()
            ->where('auditable_type', $order->getMorphClass())
            ->where('auditable_id', $order->id)
            ->whereIn('event', [AuditEvent::Updated, ...self::LINE_EVENTS])
            ->when($since, fn ($query, Carbon $since) => $query->where('created_at', '>', $since))
            ->orderBy('id')
            ->get(['id', 'event', 'old_values', 'new_values']);
    }

    /**
     * A cost-only change on a line is internal and is left out.
     *
     * @return array{0: ?array{action: string, name: string, old_qty: ?float, new_qty: ?float, old_price: ?float, new_price: ?float}, 1: Money}
     */
    private function line(AuditLog $log): array
    {
        $old = $log->event === AuditEvent::ItemAdded ? null : $log->old_values;
        $new = $log->event === AuditEvent::ItemRemoved ? null : $log->new_values;

        if ($old !== null && $new !== null && $this->sameLine($old, $new)) {
            return [null, Money::zero()];
        }

        $line = [
            'action' => match ($log->event) {
                AuditEvent::ItemAdded => 'added',
                AuditEvent::ItemRemoved => 'removed',
                default => 'updated',
            },
            'name' => (string) ($new['name'] ?? $old['name'] ?? ''),
            'old_qty' => $old === null ? null : formatNumber((string) $old['qty']),
            'new_qty' => $new === null ? null : formatNumber((string) $new['qty']),
            'old_price' => $old === null ? null : formatNumber((string) $old['unit_price']),
            'new_price' => $new === null ? null : formatNumber((string) $new['unit_price']),
        ];

        return [$line, $this->lineTotal($new)->minus($this->lineTotal($old))];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function sameLine(array $old, array $new): bool
    {
        return ($old['name'] ?? null) === ($new['name'] ?? null)
            && Money::of((string) $old['qty'])->isEqualTo((string) $new['qty'])
            && Money::of((string) $old['unit_price'])->isEqualTo((string) $new['unit_price']);
    }

    /**
     * @param  array<string, mixed>|null  $line
     */
    private function lineTotal(?array $line): Money
    {
        return $line === null ? Money::zero() : Money::of((string) $line['unit_price'])->multipliedBy((string) $line['qty']);
    }

    private function normalize(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'next_service_date' => substr((string) $value, 0, 10),
            'discount' => (string) Money::of((string) $value),
            default => (string) $value,
        };
    }
}
