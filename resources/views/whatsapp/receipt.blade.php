<!doctype html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    @include('whatsapp.partials.styles')
</head>
<body>
    <div class="head">
        @include('whatsapp.partials.header')
        <div class="doc">
            <div>إيصال سداد</div>
            <div class="doc-no ltr">#{{ $receipt['receipt_no'] }}</div>
            <div>التاريخ: <span class="ltr">{{ substr($receipt['received_at'], 0, 10) }}</span></div>
        </div>
    </div>

    <div class="amount">
        <div>تم استلام مبلغ</div>
        <div class="amount-value">{{ number_format($receipt['amount'], 2) }} جنيه</div>
        <div>{{ $receipt['method']['label'] }}</div>
    </div>

    <div class="card">
        <div class="card-title">بيانات العميل</div>
        <div class="fields">
            <div><div class="label">اسم العميل</div><div class="value">{{ $customer['name'] }}</div></div>
            <div><div class="label">رقم التليفون</div><div class="value ltr">{{ $customer['phone'] }}</div></div>
            @if ($vehicle['plate_number'])
                <div><div class="label">رقم العربية (اللوحة)</div><div class="value plate">{{ $vehicle['plate_number'] }}</div></div>
            @endif
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr><th>رقم الفاتورة</th><th>المبلغ المسدد</th><th>المتبقي على الفاتورة</th></tr>
        </thead>
        <tbody>
            @foreach ($allocations as $allocation)
                <tr>
                    <td><span class="ltr">#{{ $allocation['order_no'] }}</span></td>
                    <td class="value">{{ number_format($allocation['amount'], 2) }} جنيه</td>
                    <td>{{ number_format($allocation['order_remaining'] ?? 0, 2) }} جنيه</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bottom">
        <div class="notes">
            <div class="notes-title">شكراً لتعاملكم معنا</div>
            @if ($receipt['notes'])<div>{{ $receipt['notes'] }}</div>@endif
            @if ($receipt['received_by'])<div>المستلم: {{ $receipt['received_by'] }}</div>@endif
        </div>

        <div class="totals">
            @if ($customer_debt > 0)
                <div class="row due"><span>إجمالي المديونية المتبقية</span><span>{{ number_format($customer_debt, 2) }} جنيه</span></div>
            @else
                <div class="row clear"><span>إجمالي المديونية</span><span>لا توجد مديونية ✓</span></div>
            @endif
        </div>
    </div>
</body>
</html>
