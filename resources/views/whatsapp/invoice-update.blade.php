@php
    $money = fn ($value) => number_format((float) $value, 2).' جنيه';
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $fieldLabels = [
        'discount' => 'الخصم',
        'odometer' => 'قراءة العداد',
        'next_service_odometer' => 'الصيانة القادمة (كم)',
        'next_service_date' => 'تاريخ الصيانة القادمة',
        'notes' => 'ملاحظات الفاتورة',
    ];
    $fieldValue = fn (string $field, $value) => $value === null ? '—' : match ($field) {
        'discount' => $money($value),
        'odometer', 'next_service_odometer' => number_format((float) $value).' كم',
        default => $value,
    };
    $actionLabels = ['added' => 'إضافة', 'removed' => 'حذف', 'updated' => 'تعديل'];
    $difference = $changes['difference'];
@endphp
<!doctype html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    @include('whatsapp.partials.styles')
    <style>
        .notice { display: flex; align-items: center; gap: 12px; background: #fffbeb; border: 1px solid #fde68a; border-inline-start: 5px solid #f59e0b; border-radius: 12px; padding: 12px 16px; margin-bottom: 28px; }
        .notice-icon { width: 38px; height: 38px; border-radius: 50%; background: #f59e0b; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notice-title { font-size: 17px; font-weight: 700; color: #92400e; }
        .notice-text { color: #6b7280; font-size: 13px; }

        .doc-tag { display: inline-block; background: #fef3c7; color: #92400e; font-weight: 700; font-size: 12px; border-radius: 999px; padding: 1px 12px; margin-bottom: 4px; }

        .changes { border-inline-start: 4px solid #f59e0b; }
        .change { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px dashed #e5e7eb; }
        .change:last-child { border-bottom: 0; }
        .badge { font-size: 12px; font-weight: 700; border-radius: 999px; padding: 2px 12px; flex-shrink: 0; min-width: 56px; text-align: center; }
        .badge.added { background: #dcfce7; color: #166534; }
        .badge.removed { background: #fee2e2; color: #b91c1c; }
        .badge.updated, .badge.field { background: #fef3c7; color: #92400e; }
        .change-name { font-weight: 700; }
        .change-detail { color: #6b7280; margin-inline-start: auto; }
        .old { text-decoration: line-through; color: #9ca3af; }
        .arrow { color: #6b7280; font-weight: 700; padding: 0 4px; }
        .removed-name { text-decoration: line-through; color: #6b7280; }

        .compare { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
        .compare > div { border-radius: 12px; padding: 14px; text-align: center; border: 1px solid #e5e7eb; background: #f9fafb; }
        .compare .label { margin-bottom: 4px; }
        .compare .figure { font-size: 20px; font-weight: 700; }
        .compare .before .figure { color: #6b7280; text-decoration: line-through; }
        .compare .after { background: #eff6ff; border-color: #bfdbfe; }
        .compare .after .figure { color: #1e3a8a; }
        .compare .up .figure { color: #b91c1c; }
        .compare .down .figure { color: #166534; }
    </style>
</head>
<body>
    <div class="notice">
        <div class="notice-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        </div>
        <div>
            <div class="notice-title">تم تعديل الفاتورة رقم <span class="ltr">#{{ $invoice['order_no'] }}</span></div>
            <div class="notice-text">هذه هي النسخة المحدثة من فاتورتكم وتحل محل النسخة السابقة.</div>
        </div>
    </div>

    <div class="head">
        @include('whatsapp.partials.header')
        <div class="doc">
            <div class="doc-tag">فاتورة معدلة</div>
            <div class="doc-no ltr">#{{ $invoice['order_no'] }}</div>
            <div>تاريخ الفاتورة: <span class="ltr">{{ substr($invoice['completed_at'] ?? $invoice['created_at'], 0, 10) }}</span></div>
            <div>تاريخ التعديل: <span class="ltr">{{ $updated_at }}</span></div>
        </div>
    </div>

    <div class="card changes">
        <div class="card-title">التعديلات على الفاتورة</div>
        @foreach ($changes['lines'] as $line)
            <div class="change">
                <span class="badge {{ $line['action'] }}">{{ $actionLabels[$line['action']] }}</span>
                <span class="change-name {{ $line['action'] === 'removed' ? 'removed-name' : '' }}">{{ $line['name'] }}</span>
                <span class="change-detail">
                    @if ($line['action'] === 'added')
                        {{ $qty($line['new_qty']) }} × {{ $money($line['new_price']) }}
                    @elseif ($line['action'] === 'removed')
                        {{ $qty($line['old_qty']) }} × {{ $money($line['old_price']) }}
                    @else
                        @if ($line['old_qty'] != $line['new_qty'])
                            الكمية: <span class="old">{{ $qty($line['old_qty']) }}</span><span class="arrow">←</span>{{ $qty($line['new_qty']) }}
                        @endif
                        @if ($line['old_price'] != $line['new_price'])
                            &nbsp; السعر: <span class="old">{{ $money($line['old_price']) }}</span><span class="arrow">←</span>{{ $money($line['new_price']) }}
                        @endif
                    @endif
                </span>
            </div>
        @endforeach
        @foreach ($changes['fields'] as $field)
            <div class="change">
                <span class="badge field">تعديل</span>
                <span class="change-name">{{ $fieldLabels[$field['field']] ?? $field['field'] }}</span>
                <span class="change-detail">
                    <span class="{{ $field['old'] === null ? '' : 'old' }}">{{ $fieldValue($field['field'], $field['old']) }}</span><span class="arrow">←</span>{{ $fieldValue($field['field'], $field['new']) }}
                </span>
            </div>
        @endforeach
        @if ($changes['lines'] === [] && $changes['fields'] === [])
            <div class="change"><span class="badge field">تحديث</span><span>تم تحديث بيانات الفاتورة.</span></div>
        @endif
    </div>

    <div class="compare">
        <div class="before"><div class="label">الإجمالي قبل التعديل</div><div class="figure">{{ $money($changes['previous_total']) }}</div></div>
        <div class="after"><div class="label">الإجمالي بعد التعديل</div><div class="figure">{{ $money($changes['total']) }}</div></div>
        <div class="{{ $difference > 0 ? 'up' : ($difference < 0 ? 'down' : '') }}">
            <div class="label">الفرق</div>
            <div class="figure">
                @if ($difference > 0)
                    + {{ $money($difference) }}
                @elseif ($difference < 0)
                    - {{ $money(abs($difference)) }}
                @else
                    بدون تغيير
                @endif
            </div>
        </div>
    </div>

    @include('whatsapp.partials.invoice-body')
</body>
</html>
