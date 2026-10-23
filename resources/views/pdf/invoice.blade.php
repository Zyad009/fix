<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.header')

    <div class="title">
        {{ $invoice['is_opening_balance'] ? 'رصيد افتتاحي' : 'فاتورة' }} رقم {{ $invoice['order_no'] }}
    </div>

    @if ($invoice['status']['value'] === 'cancelled')
        <div class="voided">ملغاة</div>
    @endif

    <table class="meta">
        <tr>
            <td>العميل: {{ $customer['name'] }}</td>
            <td>التليفون: {{ $customer['phone'] }}</td>
        </tr>
        <tr>
            <td>اللوحة: {{ $vehicle['plate_number'] }}</td>
            <td>{{ trim(($vehicle['make'] ?? '').' '.($vehicle['model'] ?? '').' '.($vehicle['year'] ?? '')) }}</td>
        </tr>
        <tr>
            <td>التاريخ: {{ $invoice['completed_at'] ?? $invoice['created_at'] }}</td>
            <td>@if ($invoice['odometer'])العداد: {{ number_format($invoice['odometer']) }} كم@endif</td>
        </tr>
        @if ($invoice['technician'])
            <tr><td colspan="2">الفني: {{ $invoice['technician'] }}</td></tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>البند</th>
                <th class="num">الكمية</th>
                @if ($size !== 'thermal')
                    <th class="num">السعر</th>
                @endif
                <th class="num">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item['qty'], 2), '0'), '.') }}</td>
                    @if ($size !== 'thermal')
                        <td class="num">{{ number_format($item['unit_price'], 2) }}</td>
                    @endif
                    <td class="num">{{ number_format($item['line_total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">الإجمالي</td><td class="num">{{ number_format($totals['subtotal'], 2) }}</td></tr>
        @if ($totals['discount'] > 0)
            <tr><td class="label">الخصم</td><td class="num">{{ number_format($totals['discount'], 2) }}</td></tr>
        @endif
        <tr class="grand"><td class="label">الصافي</td><td class="num">{{ number_format($totals['total'], 2) }} ج.م</td></tr>
        <tr><td class="label">المدفوع</td><td class="num">{{ number_format($totals['paid'], 2) }}</td></tr>
        <tr><td class="label">المتبقي</td><td class="num">{{ number_format($totals['remaining'], 2) }}</td></tr>
    </table>

    @if ($invoice['next_service_date'] || $invoice['next_service_odometer'])
        <p>
            الصيانة القادمة:
            @if ($invoice['next_service_odometer']) عند {{ number_format($invoice['next_service_odometer']) }} كم @endif
            @if ($invoice['next_service_date']) بتاريخ {{ $invoice['next_service_date'] }} @endif
        </p>
    @endif

    @if ($invoice['notes'])
        <p class="muted">{{ $invoice['notes'] }}</p>
    @endif

    <div class="footer muted">شكراً لزيارتكم</div>
</body>
</html>
