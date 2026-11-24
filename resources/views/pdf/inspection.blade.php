<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.styles')
    <style>
        .summary td { text-align: center; padding: 2mm; font-weight: bold; border: 1px solid #ddd; }
        .section { font-size: 12pt; font-weight: bold; margin: 4mm 0 1mm; padding: 1mm 2mm; background: #f2f2f2; }
        .status { font-weight: bold; white-space: nowrap; width: 28mm; }
        .ok { color: #15803d; }
        .attention { color: #b45309; }
        .urgent { color: #b91c1c; }
        .box { border: 1px solid #ccc; padding: 2mm; margin-top: 1mm; }
        .signature td { padding-top: 12mm; }
    </style>
</head>
<body>
    @include('pdf.partials.header')

    <div class="title">تقرير الفحص الفني - فاتورة رقم {{ $invoice['order_no'] }}</div>

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
            <td>تاريخ الفحص: {{ $inspection['inspected_at'] }}</td>
            <td>@if ($invoice['odometer'])العداد: {{ number_format($invoice['odometer']) }} كم@endif</td>
        </tr>
        @if ($inspection['inspected_by'])
            <tr><td colspan="2">الفاحص: {{ $inspection['inspected_by'] }}</td></tr>
        @endif
    </table>

    <table class="summary">
        <tr>
            <td class="ok">سليم: {{ $inspection['summary']['ok'] }}</td>
            <td class="attention">يحتاج متابعة: {{ $inspection['summary']['attention'] }}</td>
            <td class="urgent">لازم يتغير: {{ $inspection['summary']['urgent'] }}</td>
        </tr>
    </table>

    @foreach ($inspection['sections'] as $section)
        <div class="section">{{ $section['label'] }}</div>
        <table class="lines">
            <tbody>
                @foreach ($section['items'] as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td class="status {{ $item['status']['value'] }}">{{ $item['status']['label'] }}</td>
                        <td class="muted">{{ $item['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($inspection['diagnostic_result'])
        <div class="section">جهاز كشف الأعطال</div>
        <div class="box">{!! nl2br(e($inspection['diagnostic_result'])) !!}</div>
    @endif

    @if ($inspection['notes'])
        <div class="section">ملاحظات</div>
        <div class="box">{!! nl2br(e($inspection['notes'])) !!}</div>
    @endif

    <table class="signature">
        <tr>
            <td>توقيع الفاحص: ....................</td>
            <td>توقيع العميل: ....................</td>
        </tr>
    </table>

    <div class="footer muted">هذا التقرير يوضح حالة السيارة وقت الفحص</div>
</body>
</html>
