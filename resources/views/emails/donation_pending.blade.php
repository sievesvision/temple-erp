<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · {{ $paymentMethod === 'Bank' ? 'Complete Your Bank Transfer' : 'Donation Pledge Received' }}</title>
</head>
<body style="background-color: #f5f3ef; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2ddd3;">
    <!-- Header -->
    <div style="background-color: {{ $temple['dark_color'] }}; padding: 28px 30px; text-align: left; border-bottom: 3px solid {{ $temple['primary_color'] }};">
      <h1 style="margin: 0; color: #ffffff; font-family: 'DM Sans', sans-serif; font-size: 20px; font-weight: 700;">
        {{ $temple['name'] }}
      </h1>
      <p style="margin: 4px 0 0 0; color: #d9d4c9; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
        {{ $paymentMethod === 'Bank' ? 'Complete Your Bank Transfer' : 'Donation Pledge Received' }}
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 35px 30px;">
      @if($isDonorCopy)
        <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 18px 0;">
          Vanakkam, {{ $donorName }}
        </p>

        @if($paymentMethod === 'Bank')
          <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 22px;">
            Thank you for pledging a donation to {{ $temple['name'] }}. Please complete your transfer using the bank details below. Once we confirm the funds have arrived, we'll email you an official receipt.
          </p>

          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
            <tr>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px; width: 40%;">Account Name</td>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #17110a; font-size: 14px; font-weight: 700;">{{ $temple['donation_account_name'] }}</td>
            </tr>
            <tr>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Bank Name</td>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $temple['donation_bank_name'] }}</td>
            </tr>
            <tr>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">BSB</td>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $temple['donation_bsb'] }}</td>
            </tr>
            <tr>
              <td style="padding: 12px 16px; color: #7b6b5a; font-size: 13px;">Account Number</td>
              <td style="padding: 12px 16px; color: #2d2520; font-size: 14px;">{{ $temple['donation_account_number'] }}</td>
            </tr>
          </table>

          <p style="color: #52473c; font-size: 14px; line-height: 1.6; margin-bottom: 22px;">
            Please use your name as the transfer reference so we can match your payment, and send a copy of your transfer receipt to <a href="mailto:{{ $temple['donation_receipt_email'] }}" style="color: {{ $temple['primary_color'] }};">{{ $temple['donation_receipt_email'] }}</a>.
          </p>
        @else
          <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 22px;">
            Thank you for pledging a donation to {{ $temple['name'] }}. We've recorded your pledge — please bring your offering to the temple counter during opening hours. Once we confirm it's received, we'll email you an official receipt.
          </p>

          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
            <tr>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px; width: 40%;">Weekdays</td>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $temple['hours_weekday_morning'] }} &amp; {{ $temple['hours_weekday_evening'] }}</td>
            </tr>
            <tr>
              <td style="padding: 12px 16px; color: #7b6b5a; font-size: 13px;">Weekends</td>
              <td style="padding: 12px 16px; color: #2d2520; font-size: 14px;">{{ $temple['hours_weekend'] }}</td>
            </tr>
          </table>
        @endif
      @else
        <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 22px 0;">
          A new {{ strtolower($paymentMethod) === 'bank' ? 'Bank Transfer' : 'Cash at Temple' }} donation pledge has been submitted for {{ $temple['name'] }} by <strong>{{ $donorName }}</strong>, awaiting confirmation.
        </p>
      @endif

      <!-- Donation details -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px; width: 40%;">Amount Pledged</td>
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
          <td style="padding: 12px 16px; {{ $transactionId ? 'border-bottom: 1px solid #e2ddd3;' : '' }} color: #7b6b5a; font-size: 13px;">Payment Method</td>
          <td style="padding: 12px 16px; {{ $transactionId ? 'border-bottom: 1px solid #e2ddd3;' : '' }} color: #2d2520; font-size: 14px;">{{ $paymentMethod === 'Bank' ? 'Bank Transfer' : 'Cash at Temple' }}</td>
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
