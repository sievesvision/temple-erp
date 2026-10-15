<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; color: #111111; }

    .bg { position: absolute; top: 0; left: 0; width: 1102pt; height: 1427pt; }

    .content { position: absolute; top: 370pt; left: 78pt; width: 946pt; }

    {{-- Sized for this PDF's own oversized custom page (1102x1427pt — roughly 1.8x a
         normal Letter/A4 page, matching the letterhead background image's real pixel
         dimensions exactly) — point sizes that would read as normal body text on a
         standard page come out visibly tiny here, so everything below runs noticeably
         larger than it would on a normal document. --}}
    .title {
      text-align: center;
      font-family: 'DejaVu Serif', serif;
      font-size: 42pt;
      font-weight: bold;
      color: #6B0F1A;
      letter-spacing: 1.5pt;
      margin: 0 0 14pt 0;
    }
    .subtitle-row { text-align: center; margin-bottom: 20pt; }
    .subtitle-row .line { display: inline-block; width: 80pt; border-top: 1.5pt solid #C89B3C; vertical-align: middle; }
    .subtitle-row .label { display: inline-block; font-size: 16pt; letter-spacing: 4pt; color: #A67C2B; font-weight: bold; padding: 0 14pt; vertical-align: middle; }

    table.meta-row { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
    table.meta-row td { vertical-align: top; }
    .meta-fields .field-row { margin-bottom: 9pt; font-size: 18pt; }
    .meta-fields .label { display: inline-block; width: 190pt; color: #111111; font-weight: bold; font-size: 15pt; letter-spacing: 0.5pt; text-transform: uppercase; }
    .meta-fields .value { font-size: 18pt; color: #111111; }
    .receipt-box { border: 1.5pt solid #C89B3C; background-color: #FDF6EA; text-align: center; padding: 16pt 12pt; }
    .receipt-box .label { font-size: 13pt; letter-spacing: 1pt; text-transform: uppercase; color: #444444; margin-bottom: 8pt; }
    .receipt-box .number { font-size: 30pt; font-weight: bold; color: #111111; }

    .description-box { border: 1pt solid #e6dcc6; background-color: #FAF8F5; padding: 11pt 16pt; font-size: 15pt; line-height: 19pt; color: #333333; margin-bottom: 14pt; }
    .description-box .label { font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; font-size: 13pt; color: #8A6A1E; display: block; margin-bottom: 5pt; }

    table.items { width: 100%; border-collapse: collapse; margin-bottom: 18pt; }
    table.items th { background-color: #F3E6CE; border: 1.5pt solid #C89B3C; padding: 11pt 16pt; font-size: 16pt; text-transform: uppercase; letter-spacing: 0.5pt; text-align: left; color: #111111; }
    table.items th.amount-col { text-align: right; width: 200pt; }
    table.items td { border: 1pt solid #e6dcc6; padding: 11pt 16pt; font-size: 17pt; }
    table.items td.amount { text-align: right; }
    table.items tr.paid-row td { color: #1f9d6a; font-weight: bold; }
    table.items tr.due-row td { color: #b8442f; font-weight: bold; }
    table.items tr.total td { font-weight: bold; background-color: #FDF6EA; border-top: 2.5pt solid #C89B3C; font-size: 20pt; color: #111111; }

    .thankyou { text-align: center; font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 26pt; color: #A67C2B; margin: 4pt 0 8pt 0; }
    .thankyou-sub { text-align: center; font-size: 15pt; color: #111111; line-height: 20pt; margin-bottom: 16pt; }

    .footer-note { font-size: 12pt; color: #333333; font-style: italic; line-height: 17pt; }

    {{-- Same reasoning as donation_receipt_pdf.blade.php's identical mark — test.hasq.org's
         database mirrors real production data, so this stays unmistakably a test artefact. --}}
    .test-watermark {
      position: absolute; top: 560pt; left: -180pt; width: 1460pt;
      text-align: center; font-family: 'DejaVu Sans', sans-serif; font-weight: bold;
      font-size: 150pt; letter-spacing: 10pt; color: rgba(185, 28, 28, 0.32);
      transform: rotate(-35deg);
    }
  </style>
</head>
<body>
  <img class="bg" src="{{ $bgImagePath }}">

  <div class="content">
    <div class="title">{{ $isPaid ? 'POOJA BOOKING RECEIPT' : 'POOJA BOOKING CONFIRMATION' }}</div>
    <div class="subtitle-row"><span class="line"></span><span class="label">{{ $isPaid ? 'WITH GRATITUDE' : 'BOOKING DETAILS' }}</span><span class="line"></span></div>

    <table class="meta-row">
      <tr>
        <td style="width:62%;">
          <div class="meta-fields">
            <div class="field-row"><span class="label">Booking ID</span><span class="value">#{{ $bookingId }}</span></div>
            <div class="field-row"><span class="label">Devotee</span><span class="value">{{ $devoteeName }}</span></div>
            <div class="field-row"><span class="label">Date &amp; Time</span><span class="value">{{ date('d/m/Y', strtotime($bookingDate)) }} at {{ date('h:i A', strtotime($bookingTime)) }}</span></div>
            @if(!empty($priestName))
            <div class="field-row"><span class="label">Priest</span><span class="value">{{ $priestName }}</span></div>
            @endif
            <div class="field-row"><span class="label">Payment Method</span><span class="value">{{ $paymentMethod }}</span></div>
          </div>
        </td>
        <td style="width:38%;">
          <div class="receipt-box">
            <div class="label">Booking No.</div>
            <div class="number">{{ $receiptNumber }}</div>
          </div>
        </td>
      </tr>
    </table>

    @if(!empty($poojaDescription))
    <div class="description-box"><span class="label">About this Pooja</span>{{ $poojaDescription }}</div>
    @endif

    @if(!empty($participantDetails))
    <div class="description-box"><span class="label">Special Requirements</span>{{ $participantDetails }}</div>
    @endif

    <table class="items">
      <thead>
        <tr>
          <th>Description</th>
          <th class="amount-col">Amount ({{ $currency }})</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $poojaName }}</td>
          <td class="amount">{{ number_format($totalAmount, 2) }}</td>
        </tr>
        <tr class="paid-row">
          <td>Paid</td>
          <td class="amount">{{ number_format($paidAmount, 2) }}</td>
        </tr>
        @if($dueAmount > 0)
        <tr class="due-row">
          <td>Due</td>
          <td class="amount">{{ number_format($dueAmount, 2) }}</td>
        </tr>
        @endif
        <tr class="total">
          <td>TOTAL</td>
          <td class="amount">{{ $currency }} {{ number_format($totalAmount, 2) }}</td>
        </tr>
      </tbody>
    </table>

    <div class="thankyou">Thank you for your devotion.</div>
    <div class="thankyou-sub">
      May Lord Ganesha bless you and your family with health, happiness and prosperity.
    </div>

    <div class="footer-note">
      This {{ $isPaid ? 'receipt confirms a pooja booking paid to' : 'confirms a pooja booking with' }} {{ rtrim($templeLegalName, '.') }}.<br>
      Please retain it for your records.
    </div>
  </div>

  @if(config('app.show_test_banner'))
  <div class="test-watermark">TEST ONLY</div>
  @endif
</body>
</html>
