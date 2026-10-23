<style>
    body { direction: rtl; font-family: sans-serif; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    .header td { vertical-align: top; }
    .center-name { font-size: {{ $size === 'thermal' ? '12pt' : '16pt' }}; font-weight: bold; }
    .logo { text-align: left; width: 30%; }
    .logo img { max-height: {{ $size === 'thermal' ? '15mm' : '25mm' }}; }
    .title { text-align: center; font-size: {{ $size === 'thermal' ? '11pt' : '14pt' }}; font-weight: bold; margin: 3mm 0; }
    .meta td { padding: 1mm 0; }
    .lines th, .lines td { border-bottom: 1px solid #ccc; padding: 1.5mm 1mm; text-align: right; }
    .lines th { background: #f2f2f2; }
    .num { text-align: left !important; white-space: nowrap; }
    .totals td { padding: 1mm; }
    .totals .label { text-align: right; }
    .grand { font-weight: bold; font-size: {{ $size === 'thermal' ? '10pt' : '12pt' }}; border-top: 1px solid #111; }
    .muted { color: #666; font-size: 8pt; }
    .voided { color: #b00; font-weight: bold; text-align: center; font-size: 14pt; }
    .footer { text-align: center; margin-top: 4mm; }
</style>
