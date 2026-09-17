<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; color: #2d2520; }

    .bg { position: absolute; top: 0; left: 0; width: 1102pt; height: 1427pt; }

    .content { position: absolute; top: 448pt; left: 78pt; width: 946pt; }

    .title {
      text-align: center;
      font-family: 'DejaVu Serif', serif;
      font-size: 30pt;
      font-weight: bold;
      color: #6B0F1A;
      letter-spacing: 1.5pt;
      margin: 0 0 12pt 0;
    }
    .subtitle-row { text-align: center; margin-bottom: 32pt; }
    .subtitle-row .line { display: inline-block; width: 70pt; border-top: 1pt solid #C89B3C; vertical-align: middle; }
    .subtitle-row .label { display: inline-block; font-size: 11pt; letter-spacing: 4pt; color: #A67C2B; font-weight: bold; padding: 0 12pt; vertical-align: middle; }

    table.meta-row { width: 100%; border-collapse: collapse; margin-bottom: 26pt; }
    table.meta-row td { vertical-align: top; }
    .meta-fields .field-row { margin-bottom: 10pt; font-size: 12pt; }
    .meta-fields .label { display: inline-block; width: 150pt; color: #6B0F1A; font-weight: bold; font-size: 10pt; letter-spacing: 0.5pt; text-transform: uppercase; }
    .meta-fields .value { font-size: 12pt; color: #2d2520; }
    .receipt-box { border: 1pt solid #C89B3C; background-color: #FDF6EA; text-align: center; padding: 14pt 10pt; }
    .receipt-box .label { font-size: 9pt; letter-spacing: 1pt; text-transform: uppercase; color: #8a6d2f; margin-bottom: 6pt; }
    .receipt-box .number { font-size: 18pt; font-weight: bold; color: #6B0F1A; }

    table.items { width: 100%; border-collapse: collapse; margin-bottom: 28pt; }
    table.items th { background-color: #F3E6CE; border: 1pt solid #C89B3C; padding: 10pt 14pt; font-size: 10pt; text-transform: uppercase; letter-spacing: 0.5pt; text-align: left; color: #6B0F1A; }
    table.items th.amount-col { text-align: right; width: 160pt; }
    table.items td { border: 1pt solid #e6dcc6; padding: 12pt 14pt; font-size: 12pt; }
    table.items td.amount { text-align: right; }
    table.items tr.total td { font-weight: bold; background-color: #FDF6EA; border-top: 2pt solid #C89B3C; font-size: 13pt; color: #6B0F1A; }

    .thankyou { text-align: center; font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 22pt; color: #A67C2B; margin: 6pt 0 14pt 0; }
    .thankyou-sub { text-align: center; font-size: 11pt; color: #4a4038; line-height: 16pt; margin-bottom: 36pt; }

    .footer-note { font-size: 9pt; color: #7b6b5a; font-style: italic; line-height: 14pt; }
  </style>
</head>
<body>
  <img class="bg" src="{{ $bgImagePath }}">

  <div class="content">
    <div class="title">DONATION RECEIPT</div>
    <div class="subtitle-row"><span class="line"></span><span class="label">WITH GRATITUDE</span><span class="line"></span></div>

    <table class="meta-row">
      <tr>
        <td style="width:62%;">
          <div class="meta-fields">
            <div class="field-row"><span class="label">Received From</span><span class="value">{{ $donorName }}</span></div>
            <div class="field-row"><span class="label">Date</span><span class="value">{{ date('d/m/Y', strtotime($donationDate)) }}</span></div>
            <div class="field-row"><span class="label">Payment Method</span><span class="value">{{ $paymentMethod }}</span></div>
            @if(!empty($donorMobile))
            <div class="field-row"><span class="label">Telephone</span><span class="value">{{ $donorMobile }}</span></div>
            @endif
            @if(!empty($transactionId))
            <div class="field-row"><span class="label">Reference</span><span class="value">{{ $transactionId }}</span></div>
            @endif
          </div>
        </td>
        <td style="width:38%;">
          <div class="receipt-box">
            <div class="label">Receipt No.</div>
            <div class="number">{{ $receiptNumber }}</div>
          </div>
        </td>
      </tr>
    </table>

    <table class="items">
      <thead>
        <tr>
          <th>Description</th>
          <th class="amount-col">Amount ({{ $currency }})</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $eventName ? $eventName . ($purpose && $purpose !== 'Event Donation' ? ' — ' . $purpose : '') : ($purpose ?: 'General Donation') }}</td>
          <td class="amount">{{ number_format($amount, 2) }}</td>
        </tr>
        <tr class="total">
          <td>TOTAL</td>
          <td class="amount">{{ $currency }} {{ number_format($amount, 2) }}</td>
        </tr>
      </tbody>
    </table>

    <div class="thankyou">Thank you for your generous contribution.</div>
    <div class="thankyou-sub">
      Your support helps us continue our spiritual, cultural and community services.<br>
      May Lord Ganesha bless you and your family with health, happiness and prosperity.
    </div>

    <div class="footer-note">
      This receipt confirms a donation made to {{ rtrim($temple['legal_name'] ?: $temple['name'], '.') }}.<br>
      Please retain it for your records.
    </div>
  </div>
</body>
</html>
