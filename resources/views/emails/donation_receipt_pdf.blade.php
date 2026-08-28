<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body {
      font-family: 'DejaVu Sans', sans-serif;
      color: #2d2520;
      font-size: 12px;
      margin: 0;
      padding: 0;
    }
    .header {
      background-color: {{ $temple['dark_color'] }};
      color: #ffffff;
      padding: 20px 30px;
      border-bottom: 3px solid {{ $temple['primary_color'] }};
    }
    .header h1 {
      margin: 0;
      font-size: 18px;
    }
    .header p {
      margin: 4px 0 0 0;
      font-size: 11px;
      color: #d9d4c9;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .content {
      padding: 25px 30px;
    }
    .receipt-title {
      font-size: 14px;
      font-weight: bold;
      color: #17110a;
      margin: 0 0 4px 0;
    }
    .receipt-meta {
        color: #7b6b5a;
        font-size: 11px;
        margin: 0 0 20px 0;
    }
    table.details {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    table.details td {
      padding: 8px 10px;
      border-bottom: 1px solid #e2ddd3;
      font-size: 12px;
    }
    table.details td.label {
      color: #7b6b5a;
      width: 35%;
    }
    table.details td.value {
      color: #17110a;
    }
    .amount-value {
      font-weight: bold;
      font-size: 14px;
    }
    .footer {
      padding: 15px 30px;
      border-top: 1px solid #e2ddd3;
      color: #7b6b5a;
      font-size: 10px;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>{{ $temple['name'] }}</h1>
    <p>Donation Receipt</p>
  </div>

  <div class="content">
    <p class="receipt-title">Official Donation Receipt</p>
    <p class="receipt-meta">Issued {{ date('d M Y') }}</p>

    <table class="details">
      <tr>
        <td class="label">Donor Name</td>
        <td class="value">{{ $donorName }}</td>
      </tr>
      <tr>
        <td class="label">Amount</td>
        <td class="value amount-value">{{ $currency }} {{ number_format($amount, 2) }}</td>
      </tr>
      @if($eventName)
      <tr>
        <td class="label">Event</td>
        <td class="value">{{ $eventName }}</td>
      </tr>
      @elseif($purpose)
      <tr>
        <td class="label">Purpose</td>
        <td class="value">{{ $purpose }}</td>
      </tr>
      @endif
      <tr>
        <td class="label">Payment Method</td>
        <td class="value">{{ $paymentMethod }}</td>
      </tr>
      <tr>
        <td class="label">Donation Date</td>
        <td class="value">{{ date('d M Y', strtotime($donationDate)) }}</td>
      </tr>
      @if($transactionId)
      <tr>
        <td class="label">Reference</td>
        <td class="value">{{ $transactionId }}</td>
      </tr>
      @endif
    </table>

    <p style="color: #52473c; font-size: 11px; line-height: 1.6;">
      This receipt confirms a donation made to {{ $temple['name'] }}. Please retain this document for your records.
    </p>
  </div>

  <div class="footer">
    {{ $temple['name'] }}@if(!empty($temple['address'])), {{ $temple['address'] }}@endif<br>
    @if(!empty($temple['phone'])){{ $temple['phone'] }}@endif @if(!empty($temple['email'])) &middot; {{ $temple['email'] }}@endif
  </div>
</body>
</html>
