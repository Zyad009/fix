<div class="card">
    <div class="card-title">بيانات العميل والمركبة</div>
    <div class="fields">
        <div><div class="label">اسم العميل</div><div class="value">{{ $customer['name'] }}</div></div>
        <div><div class="label">رقم التليفون</div><div class="value ltr">{{ $customer['phone'] }}</div></div>
        <div><div class="label">نوع العربية</div><div class="value">{{ trim(($vehicle['make'] ?? '').' '.($vehicle['model'] ?? '').' '.($vehicle['year'] ?? '')) ?: '—' }}</div></div>
        <div><div class="label">رقم العربية (اللوحة)</div><div class="value plate">{{ $vehicle['plate_number'] }}</div></div>
        <div><div class="label">رقم الشاسيه</div><div class="value ltr">{{ $vehicle['vin'] ?: '—' }}</div></div>
        <div><div class="label">قراءة العداد</div><div class="value">{{ $invoice['odometer'] ? number_format($invoice['odometer']).' كم' : '—' }}</div></div>
    </div>
</div>

<table class="lines">
    <thead>
        <tr><th>#</th><th>البيان</th><th>النوع</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="name">{{ $item['name'] }}</td>
                <td class="kind">{{ $item['kind']['label'] }}</td>
                <td>{{ rtrim(rtrim(number_format($item['qty'], 2), '0'), '.') }}</td>
                <td>{{ number_format($item['unit_price'], 2) }} جنيه</td>
                <td class="value">{{ number_format($item['line_total'], 2) }} جنيه</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="bottom">
    <div class="notes">
        <div class="notes-title">ملاحظات الفاتورة</div>
        <div>شكراً لتعاملكم معنا، نتمنى لكم رحلة آمنة.</div>
        @if ($invoice['notes'])<div>{{ $invoice['notes'] }}</div>@endif
        @if ($invoice['next_service_date'] || $invoice['next_service_odometer'])
            <div>
                الصيانة القادمة:
                @if ($invoice['next_service_odometer']) عند {{ number_format($invoice['next_service_odometer']) }} كم @endif
                @if ($invoice['next_service_date']) بتاريخ <span class="ltr">{{ $invoice['next_service_date'] }}</span> @endif
            </div>
        @endif
    </div>

    <div class="totals">
        <div class="row"><span>إجمالي قطع الغيار</span><span>{{ number_format($totals['parts_total'], 2) }} جنيه</span></div>
        <div class="row"><span>إجمالي مصنعية الصيانة</span><span>{{ number_format($totals['services_total'], 2) }} جنيه</span></div>
        @if ($totals['discount'] > 0)
            <div class="row"><span>الإجمالي قبل الخصم</span><span>{{ number_format($totals['subtotal'], 2) }} جنيه</span></div>
            <div class="row discount"><span>الخصم الممنوح</span><span>- {{ number_format($totals['discount'], 2) }} جنيه</span></div>
        @endif
        <div class="row grand"><span>الإجمالي النهائي</span><span>{{ number_format($totals['total'], 2) }} جنيه</span></div>
        <div class="row paid"><span>المدفوع</span><span>{{ number_format($totals['paid'], 2) }} جنيه</span></div>
        @if ($totals['remaining'] > 0)
            <div class="row due"><span>المبلغ المتبقي</span><span>{{ number_format($totals['remaining'], 2) }} جنيه</span></div>
        @else
            <div class="row clear"><span>المبلغ المتبقي</span><span>مدفوعة بالكامل ✓</span></div>
        @endif
    </div>
</div>
