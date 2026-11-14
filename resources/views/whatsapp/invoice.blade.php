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
            <div>فاتورة صيانة</div>
            <div class="doc-no ltr">#{{ $invoice['order_no'] }}</div>
            <div>التاريخ: <span class="ltr">{{ substr($invoice['completed_at'] ?? $invoice['created_at'], 0, 10) }}</span></div>
        </div>
    </div>

    @if ($invoice['status']['value'] === 'cancelled')
        <div class="cancelled">فاتورة ملغاة</div>
    @endif

    @include('whatsapp.partials.invoice-body')
</body>
</html>
