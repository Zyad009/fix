<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <style>
        body { direction: rtl; font-family: sans-serif; font-size: 9.5pt; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .ltr { direction: ltr; }

        .head td { vertical-align: middle; }
        .logo { width: 20mm; }
        .center-name { font-size: 16pt; font-weight: bold; color: #111827; }
        .center-info { font-size: 8.5pt; color: #6b7280; }
        .doc { width: 58mm; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 3mm; padding: 2.5mm 4mm; }
        .doc-title { font-size: 11pt; font-weight: bold; color: #1e3a8a; }
        .doc-no { font-size: 15pt; font-weight: bold; color: #1d4ed8; }
        .doc-date { font-size: 8.5pt; color: #6b7280; }
        .rule { height: 1mm; background-color: #1d4ed8; margin: 4mm 0 4mm; }

        .card { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 3mm; padding: 3mm 4mm; margin-bottom: 4mm; }
        .fields td { width: 33.33%; padding: 1mm 0; }
        .label { font-size: 8pt; color: #6b7280; }
        .value { font-size: 10pt; font-weight: bold; }
        .plate { color: #1d4ed8; }

        .tiles td { width: 33.33%; text-align: center; padding: 3mm 1mm; border-radius: 3mm; }
        .tiles .gap { width: 3mm; padding: 0; }
        .tile-num { font-size: 20pt; font-weight: bold; }
        .tile-label { font-size: 9pt; font-weight: bold; }
        .tile-ok { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .tile-attention { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .tile-urgent { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .not-checked { font-size: 8pt; color: #6b7280; text-align: center; margin-top: 1.5mm; }

        .heading { font-size: 11.5pt; font-weight: bold; color: #111827; margin: 5mm 0 2mm; }
        .heading-note { font-size: 8.5pt; font-weight: normal; color: #6b7280; }

        .findings td { padding: 2mm 3mm; border-bottom: 1px solid #ffffff; }
        .finding-urgent { background-color: #fef2f2; border-right: 1.2mm solid #dc2626; }
        .finding-attention { background-color: #fffbeb; border-right: 1.2mm solid #f59e0b; }
        .finding-name { font-weight: bold; font-size: 10pt; }
        .finding-section { font-size: 8pt; color: #6b7280; }
        .finding-note { font-size: 9pt; color: #374151; }
        .finding-status { width: 26mm; text-align: center; font-weight: bold; vertical-align: middle; }

        .all-good { background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 3mm; padding: 3mm; text-align: center; color: #15803d; font-weight: bold; }

        .box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 3mm; padding: 3mm 4mm; color: #374151; }

        .grid > tbody > tr > td { width: 49%; padding-bottom: 2.5mm; }
        .grid .gap { width: 2%; }
        .section { border: 1px solid #e5e7eb; }
        .section th { background-color: #f3f4f6; text-align: right; font-size: 9.5pt; padding: 1.4mm 2.5mm; border-bottom: 1px solid #e5e7eb; }
        .section td { padding: 0.9mm 2.5mm; border-bottom: 1px solid #f3f4f6; font-size: 8.5pt; }
        .section .st { width: 22mm; text-align: center; font-weight: bold; font-size: 8pt; }
        .note { font-size: 7.5pt; color: #6b7280; }

        .ok { color: #15803d; }
        .attention { color: #b45309; }
        .urgent { color: #b91c1c; }
        .st.ok { background-color: #f0fdf4; }
        .st.attention { background-color: #fffbeb; }
        .st.urgent { background-color: #fef2f2; }

        .details-heading { margin-top: 0; }
        .signature { margin-top: 6mm; }
        .signature td { width: 45%; font-size: 8.5pt; color: #6b7280; padding-top: 1mm; }
        .signature .sign { height: 12mm; border-bottom: 1px solid #9ca3af; }
        .signature .gap { width: 10%; }
        .footer { font-size: 7.5pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @php
        $findings = [];
        foreach (['urgent', 'attention'] as $level) {
            foreach ($inspection['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    if ($item['status']['value'] === $level) {
                        $findings[] = [...$item, 'section' => $section['label']];
                    }
                }
            }
        }
        $vehicleName = trim(($vehicle['make'] ?? '').' '.($vehicle['model'] ?? '').' '.($vehicle['year'] ?? ''));
        $sectionRows = array_chunk($inspection['sections'], 2);
    @endphp

    <htmlpagefooter name="footer">
        <div class="footer">هذا التقرير يوضح حالة السيارة وقت الفحص — {{ $center['name'] }} — صفحة {PAGENO} من {nbpg}</div>
    </htmlpagefooter>
    <sethtmlpagefooter name="footer" value="on" />

    <table class="head">
        <tr>
            @if ($center['logo_path'])
                <td class="logo"><img src="{{ $center['logo_path'] }}" style="height: 18mm; width: auto;" alt=""></td>
            @endif
            <td>
                <div class="center-name">{{ $center['name'] }}</div>
                <div class="center-info">
                    {{ collect([$center['branch'], $center['address']])->filter()->implode(' — ') }}
                    @if ($center['phone'])<br>هاتف: <span class="ltr">{{ $center['phone'] }}</span>@endif
                </div>
            </td>
            <td class="doc">
                <div class="doc-title">تقرير فحص فني</div>
                <div class="doc-no"><span class="doc-date">فاتورة رقم</span> {{ $invoice['order_no'] }}</div>
                <div class="doc-date">{{ $inspection['inspected_at'] }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <div class="card">
        <table class="fields">
            <tr>
                <td><div class="label">العميل</div><div class="value">{{ $customer['name'] }}</div></td>
                <td><div class="label">التليفون</div><div class="value ltr">{{ $customer['phone'] }}</div></td>
                <td><div class="label">الفاحص</div><div class="value">{{ $inspection['inspected_by'] ?? '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">اللوحة</div><div class="value plate">{{ $vehicle['plate_number'] }}</div></td>
                <td><div class="label">السيارة</div><div class="value">{{ $vehicleName !== '' ? $vehicleName : '—' }}</div></td>
                <td><div class="label">العداد</div><div class="value">{{ $invoice['odometer'] ? number_format($invoice['odometer']).' كم' : '—' }}</div></td>
            </tr>
        </table>
    </div>

    <table class="tiles">
        <tr>
            <td class="tile-ok"><div class="tile-num">{{ $inspection['summary']['ok'] }}</div><div class="tile-label">سليم</div></td>
            <td class="gap"></td>
            <td class="tile-attention"><div class="tile-num">{{ $inspection['summary']['attention'] }}</div><div class="tile-label">يحتاج متابعة</div></td>
            <td class="gap"></td>
            <td class="tile-urgent"><div class="tile-num">{{ $inspection['summary']['urgent'] }}</div><div class="tile-label">لازم يتغير</div></td>
        </tr>
    </table>
    @if ($inspection['summary']['not_checked'] > 0)
        <div class="not-checked">{{ $inspection['summary']['not_checked'] }} بند لم يتم فحصه</div>
    @endif

    <div class="heading">ملاحظات الفحص <span class="heading-note">— البنود اللي محتاجة اهتمامك</span></div>
    @if ($findings === [])
        <div class="all-good">كل البنود اللي اتفحصت سليمة ✓</div>
    @else
        <table class="findings">
            @foreach ($findings as $finding)
                <tr class="finding-{{ $finding['status']['value'] }}">
                    <td>
                        <span class="finding-name">{{ $finding['label'] }}</span>
                        <span class="finding-section"> · {{ $finding['section'] }}</span>
                        @if ($finding['note'])
                            <div class="finding-note">{{ $finding['note'] }}</div>
                        @endif
                    </td>
                    <td class="finding-status {{ $finding['status']['value'] }}">{{ $finding['status']['label'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($inspection['diagnostic_result'])
        <div class="heading">نتيجة جهاز كشف الأعطال</div>
        <div class="box">{!! nl2br(e($inspection['diagnostic_result'])) !!}</div>
    @endif

    @if ($inspection['notes'])
        <div class="heading">ملاحظات الفاحص</div>
        <div class="box">{!! nl2br(e($inspection['notes'])) !!}</div>
    @endif

    <table class="signature" style="page-break-inside: avoid;">
        <tr>
            <td class="sign"></td>
            <td class="gap"></td>
            <td class="sign"></td>
        </tr>
        <tr>
            <td>توقيع الفاحص</td>
            <td class="gap"></td>
            <td>توقيع العميل</td>
        </tr>
    </table>

    <pagebreak />
    <div class="heading details-heading">تفاصيل الفحص <span class="heading-note">— فاتورة رقم {{ $invoice['order_no'] }}</span></div>
    <table class="grid">
        @foreach ($sectionRows as $pair)
            <tr>
                @foreach ([0, 1] as $index)
                    @if ($index === 1)
                        <td class="gap"></td>
                    @endif
                    <td>
                        @if (isset($pair[$index]))
                            <table class="section">
                                <tr><th colspan="2">{{ $pair[$index]['label'] }}</th></tr>
                                @foreach ($pair[$index]['items'] as $item)
                                    <tr>
                                        <td>
                                            {{ $item['label'] }}
                                            @if ($item['note'])
                                                <br><span class="note">{{ $item['note'] }}</span>
                                            @endif
                                        </td>
                                        <td class="st {{ $item['status']['value'] }}">{{ $item['status']['label'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

</body>
</html>
