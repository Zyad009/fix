<style>
    @foreach ($fonts as $font)
        @font-face {
            font-family: 'Cairo';
            font-weight: {{ $font['weight'] }};
            src: url(data:font/woff2;base64,{!! $font['data'] !!}) format('woff2');
        }
    @endforeach

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { background: #fff; }
    body { width: 800px; padding: 32px; font-family: 'Cairo', sans-serif; font-size: 14px; line-height: 1.5; color: #111827; }
    .ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }

    .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; }
    .center-name { display: flex; align-items: center; gap: 10px; font-size: 26px; font-weight: 700; }
    .mark { width: 44px; height: 44px; border-radius: 10px; background: #1d4ed8; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .logo { width: 56px; height: 56px; border-radius: 10px; object-fit: contain; flex-shrink: 0; }
    .center-info { color: #6b7280; margin-top: 6px; }
    .doc { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 18px; min-width: 190px; color: #6b7280; }
    .doc-no { font-size: 20px; font-weight: 700; color: #1d4ed8; }

    .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
    .card-title { font-size: 15px; font-weight: 700; color: #374151; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; margin-bottom: 14px; }
    .fields { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 20px; }
    .label { font-size: 12px; color: #6b7280; }
    .value { font-weight: 700; }
    .plate { color: #1d4ed8; }

    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .lines th { background: #f3f4f6; font-weight: 700; padding: 10px; border: 1px solid #e5e7eb; text-align: center; }
    .lines td { padding: 10px; border: 1px solid #e5e7eb; text-align: center; }
    .lines .name { text-align: right; }
    .lines .kind { color: #6b7280; font-size: 12px; }

    .bottom { display: flex; gap: 24px; align-items: flex-start; }
    .notes { flex: 1; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; color: #6b7280; }
    .notes-title { font-weight: 700; color: #1f2937; margin-bottom: 6px; }
    .totals { width: 320px; flex-shrink: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .row { display: flex; justify-content: space-between; padding: 8px 16px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    .row:last-child { border-bottom: 0; }
    .row.discount { background: #fef2f2; color: #b91c1c; }
    .row.grand { background: #eff6ff; color: #1e3a8a; font-weight: 700; font-size: 18px; padding: 12px 16px; }
    .row.paid { background: #f0fdf4; color: #166534; }
    .row.due { background: #f3f4f6; color: #dc2626; font-weight: 700; font-size: 16px; }
    .row.clear { background: #f0fdf4; color: #166534; font-weight: 700; font-size: 16px; }

    .amount { text-align: center; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 24px; color: #166534; }
    .amount-value { font-size: 32px; font-weight: 700; }
    .cancelled { text-align: center; background: #fef2f2; color: #b91c1c; font-weight: 700; border-radius: 12px; padding: 8px; margin-bottom: 24px; }
</style>
