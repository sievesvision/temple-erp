<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Donation Receipt</title>
</head>
<body style="background-color: #f5f3ef; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2ddd3;">
    <!-- Header -->
    <div style="background-color: {{ $temple['dark_color'] }}; padding: 28px 30px; text-align: left; border-bottom: 3px solid {{ $temple['primary_color'] }};">
      <h1 style="margin: 0; color: #ffffff; font-family: 'DM Sans', sans-serif; font-size: 20px; font-weight: 700;">
        {{ $temple['name'] }}
      </h1>
      <p style="margin: 4px 0 0 0; color: #d9d4c9; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
        Donation Receipt
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 35px 30px;">
      @if($isDonorCopy)
        <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 18px 0;">
          Vanakkam, {{ $donorName }}
        </p>

        <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 22px;">
          We gratefully acknowledge your donation. Your generosity supports {{ $temple['name'] }} and the community it serves. A copy of this receipt is attached as a PDF for your records.
        </p>
      @else
        <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 22px 0;">
          A new donation has been recorded for {{ $temple['name'] }} from <strong>{{ $donorName }}</strong>. A copy of the receipt is attached as a PDF.
        </p>
      @endif

      <!-- Donation details -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px; width: 40%;">Amount</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #17110a; font-size: 15px; font-weight: 700;">{{ $currency }} {{ number_format($amount, 2) }}</td>
        </tr>
        @if($eventName)
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Event</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $eventName }}</td>
        </tr>
        @elseif($purpose)
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Purpose</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $purpose }}</td>
        </tr>
        @endif
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Payment Method</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $paymentMethod }}</td>
        </tr>
        <tr>
          <td style="padding: 12px 16px; {{ $transactionId ? 'border-bottom: 1px solid #e2ddd3;' : '' }} color: #7b6b5a; font-size: 13px;">Donation Date</td>
          <td style="padding: 12px 16px; {{ $transactionId ? 'border-bottom: 1px solid #e2ddd3;' : '' }} color: #2d2520; font-size: 14px;">{{ date('d M Y', strtotime($donationDate)) }}</td>
        </tr>
        @if($transactionId)
        <tr>
          <td style="padding: 12px 16px; color: #7b6b5a; font-size: 13px;">Reference</td>
          <td style="padding: 12px 16px; color: #2d2520; font-size: 14px; font-family: monospace;">{{ $transactionId }}</td>
        </tr>
        @endif
      </table>

      <p style="color: #52473c; font-size: 14px; line-height: 1.6; margin: 0;">
        Regards,<br>
        {{ $temple['name'] }}
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #f5f3ef; color: #7b6b5a; text-align: left; padding: 16px 30px; font-size: 12px; border-top: 1px solid #e2ddd3;">
      © {{ date('Y') }} {{ $temple['name'] }}. All rights reserved.
    </div>
  </div>
</body>
</html>
