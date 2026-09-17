<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2a37; font-size: 12px; margin: 0; padding: 0; }
    .receipt-box { border: 2px solid #1f2a37; padding: 24px; margin: 20px; }
    .letterhead { text-align: center; border-bottom: 1px solid #1f2a37; padding-bottom: 14px; margin-bottom: 16px; }
    .letterhead .legal-name { font-size: 18px; font-weight: bold; margin: 0 0 4px 0; }
    .letterhead .temple-name { font-size: 13px; margin: 0 0 6px 0; color: #444444; }
    .letterhead .contact-line { font-size: 10px; color: #555555; margin: 2px 0; }
    .title-row td { padding-bottom: 12px; }
    .title-row .title { font-size: 16px; font-weight: bold; }
    .title-row .number { text-align: right; font-size: 12px; }
    table.meta { width: 100%; margin-bottom: 14px; font-size: 11px; }
    table.meta td { padding: 3px 0; }
    table.meta td.label { color: #555555; width: 110px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.items th { background-color: #f0e5d6; border: 1px solid #cccccc; padding: 7px 10px; font-size: 10px; text-transform: uppercase; text-align: left; }
    table.items th.amount-col { text-align: right; width: 90px; }
    table.items td { border: 1px solid #cccccc; padding: 9px 10px; font-size: 11px; }
    table.items td.amount { text-align: right; }
    table.items tr.total td { font-weight: bold; background-color: #faf5eb; }
    table.footer-meta { width: 100%; font-size: 10px; color: #555555; margin-bottom: 20px; }
    .thank-you { text-align: center; font-style: italic; font-size: 13px; margin-bottom: 6px; }
    .thank-you-sub { text-align: center; font-size: 9px; color: #777777; }
  </style>
</head>
<body>
  <div class="receipt-box">
    <div class="letterhead">
      <div class="legal-name">{{ $temple['legal_name'] ?: $temple['name'] }}</div>
      @if(!empty($temple['legal_name']) && $temple['legal_name'] !== $temple['name'])
      <div class="temple-name">{{ $temple['name'] }}</div>
      @endif
      @if(!empty($temple['abn']))<div class="contact-line">ABN {{ $temple['abn'] }}</div>@endif
      @if(!empty($temple['address']))<div class="contact-line">{{ $temple['address'] }}</div>@endif
      <div class="contact-line">
        @if(!empty($temple['phone']))Phone: {{ $temple['phone'] }}@endif
        @if(!empty($temple['phone']) && !empty($temple['website']))&nbsp;&middot;&nbsp;@endif
        @if(!empty($temple['website'])){{ $temple['website'] }}@endif
      </div>
    </div>

    <table style="width:100%;" class="title-row">
      <tr>
        <td class="title">RECEIPT</td>
        <td class="number">No. {{ $receiptNumber }}</td>
      </tr>
    </table>

    <table class="meta">
      <tr>
        <td class="label">Received from</td>
        <td>{{ $donorName }}</td>
      </tr>
      @if(!empty($donorMobile))
      <tr>
        <td class="label">Telephone</td>
        <td>{{ $donorMobile }}</td>
      </tr>
      @endif
      <tr>
        <td class="label">Date</td>
        <td>{{ date('d/m/Y', strtotime($donationDate)) }}</td>
      </tr>
    </table>

    <table class="items">
      <thead>
        <tr>
          <th>Being</th>
          <th class="amount-col">Amount</th>
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

    <table class="footer-meta">
      <tr>
        <td>Payment Method: {{ $paymentMethod }}</td>
        @if($transactionId)<td style="text-align:right;">Ref: {{ $transactionId }}</td>@endif
      </tr>
    </table>

    <div class="thank-you">Thank you for your generous contribution.</div>
    <div class="thank-you-sub">This receipt confirms a donation made to {{ $temple['legal_name'] ?: $temple['name'] }}. Please retain it for your records.</div>
  </div>
</body>
</html>
