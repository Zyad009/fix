<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.header')

    <div class="title">إيصال استلام نقدية رقم {{ $receipt['receipt_no'] }}</div>

    @if ($receipt['is_voided'])
        <div class="voided">إيصال ملغي</div>
    @endif

    <table class="meta">
        <tr><td>استلمنا من: {{ $customer['name'] }}</td></tr>
        <tr><td>التليفون: {{ $customer['phone'] }}</td></tr>
        @if ($vehicle['plate_number'])
            <tr><td>اللوحة: {{ $vehicle['plate_number'] }}</td></tr>
        @endif
        <tr><td>مبلغ: <strong>{{ number_format($receipt['amount'], 2) }} ج.م</strong> ({{ $receipt['method']['label'] }})</td></tr>
        <tr><td>التاريخ: {{ $receipt['received_at'] }}</td></tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>فاتورة رقم</th>
                <th class="num">المسدد</th>
                <th class="num">المتبقي عليها</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allocations as $allocation)
                <tr>
                    <td>{{ $allocation['order_no'] }}</td>
                    <td class="num">{{ number_format($allocation['amount'], 2) }}</td>
                    <td class="num">{{ number_format($allocation['order_remaining'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($receipt['received_by'])
        <p class="muted">المستلم: {{ $receipt['received_by'] }}</p>
    @endif

    <div class="footer muted">شكراً لكم</div>
</body>
</html>
