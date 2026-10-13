<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Pooja Booking Confirmed</title>
</head>
<body style="background-color: #f5f3ef; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2ddd3;">
    <!-- Header -->
    <div style="background-color: {{ $temple['dark_color'] }}; padding: 28px 30px; text-align: left; border-bottom: 3px solid {{ $temple['primary_color'] }};">
      <h1 style="margin: 0; color: #ffffff; font-family: 'DM Sans', sans-serif; font-size: 20px; font-weight: 700;">
        {{ $temple['name'] }}
      </h1>
      <p style="margin: 4px 0 0 0; color: #d9d4c9; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
        Pooja Booking Confirmed
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 35px 30px;">
      <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 18px 0;">
        Vanakkam, {{ $devoteeName }}
      </p>

      <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 22px;">
        Your pooja booking at {{ $temple['name'] }} has been confirmed. Here are the details:
      </p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px; width: 40%;">Booking ID</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #17110a; font-size: 15px; font-weight: 700;">#{{ $bookingId }}</td>
        </tr>
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Pooja</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $poojaName }}</td>
        </tr>
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Date &amp; Time</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $bookingDate }} at {{ date('h:i A', strtotime($bookingTime)) }}</td>
        </tr>
        @if($priestName)
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Priest</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $priestName }}</td>
        </tr>
        @endif
        <tr>
          <td style="padding: 12px 16px; color: #7b6b5a; font-size: 13px;">Total Cost</td>
          <td style="padding: 12px 16px; color: #17110a; font-size: 15px; font-weight: 700;">{{ $currency }} {{ number_format($totalAmount, 2) }}</td>
        </tr>
      </table>

      @if($paymentStatus !== 'Paid')
      <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 16px; font-weight: 700;">
        You can pay either by Bank Transfer, or in person at the temple (cash or card):
      </p>

      <p style="color: #7b6b5a; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; margin: 0 0 8px 0;">Option 1 — Bank Transfer</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2ddd3; border-collapse: collapse; margin: 0 0 22px 0;">
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px; width: 40%;">Account Name</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #17110a; font-size: 14px; font-weight: 700;">{{ $bankAccount['account_name'] }}</td>
        </tr>
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">Bank Name</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $bankAccount['bank_name'] }}</td>
        </tr>
        <tr>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #7b6b5a; font-size: 13px;">BSB</td>
          <td style="padding: 12px 16px; border-bottom: 1px solid #e2ddd3; color: #2d2520; font-size: 14px;">{{ $bankAccount['bsb'] }}</td>
        </tr>
        <tr>
          <td style="padding: 12px 16px; color: #7b6b5a; font-size: 13px;">Account Number</td>
          <td style="padding: 12px 16px; color: #2d2520; font-size: 14px;">{{ $bankAccount['account_number'] }}</td>
        </tr>
      </table>
      <p style="color: #52473c; font-size: 13px; line-height: 1.6; margin-bottom: 22px;">
        Please use booking #{{ $bookingId }} as your transfer reference so we can match your payment.
      </p>

      <p style="color: #7b6b5a; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; margin: 0 0 8px 0;">Option 2 — Pay at the Temple</p>
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
      <p style="color: #52473c; font-size: 13px; line-height: 1.6; margin-bottom: 22px;">
        Cash and card are both accepted at the counter — just mention booking #{{ $bookingId }}.
      </p>
      @else
      <p style="color: #2f6f4e; font-size: 14px; line-height: 1.6; margin-bottom: 22px; font-weight: 700;">
        Payment already received — no further action needed. See you at the temple!
      </p>
      @endif

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
