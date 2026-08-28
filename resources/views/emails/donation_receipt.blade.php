<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Donation Receipt</title>
</head>
<body style="background-color: #fbf8f1; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ebdcc5; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <!-- Header banner -->
    <div style="background: linear-gradient(135deg, {{ $temple['accent_color'] }} 0%, {{ $temple['primary_color'] }} 50%, {{ $temple['dark_color'] }} 100%); padding: 30px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-family: 'Playfair Display', 'Georgia', serif; font-size: 24px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
        🛕 {{ $temple['name'] }}
      </h1>
      <p style="margin: 5px 0 0 0; color: #ffd8bd; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">
        Donation Receipt
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 40px 30px;">
      @if($isDonorCopy)
        <h2 style="margin-top: 0; color: #17110a; font-size: 20px; font-weight: 600; text-align: center;">
          Thank You for Your Donation
        </h2>

        <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
          Vanakkam, {{ $donorName }}
        </p>

        <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
          We gratefully acknowledge your donation. Your generosity supports {{ $temple['name'] }} and the community it serves. Please find the details of your donation below.
        </p>
      @else
        <h2 style="margin-top: 0; color: #17110a; font-size: 20px; font-weight: 600; text-align: center;">
          New Donation Received
        </h2>

        <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
          A new donation has been recorded for {{ $temple['name'] }} from <strong>{{ $donorName }}</strong>. Details below.
        </p>
      @endif

      <!-- Donation details Box -->
      <div style="background-color: rgba(255, 111, 0, 0.03); border: 1px solid #ebdcc5; border-radius: 12px; padding: 20px; margin: 25px 0;">
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Amount:</strong> <span style="color: {{ $temple['primary_color'] }}; font-weight: 700; font-size: 17px;">{{ $currency }} {{ number_format($amount, 2) }}</span>
        </div>
        @if($eventName)
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Event:</strong> <span style="color: #52473c;">{{ $eventName }}</span>
        </div>
        @elseif($purpose)
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Purpose:</strong> <span style="color: #52473c;">{{ $purpose }}</span>
        </div>
        @endif
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Payment Method:</strong> <span style="color: #52473c;">{{ $paymentMethod }}</span>
        </div>
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Donation Date:</strong> <span style="color: #52473c;">{{ date('d M Y', strtotime($donationDate)) }}</span>
        </div>
        @if($transactionId)
        <div style="font-size: 15px;">
          <strong style="color: #17110a;">Reference:</strong> <span style="color: #52473c; font-family: monospace;">{{ $transactionId }}</span>
        </div>
        @endif
      </div>

      <div style="margin: 30px 0; border-top: 1px solid #f3ede2;"></div>

      <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin: 0;">
        Warm Regards,<br>
        <strong>{{ $temple['name'] }} Team</strong>
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #17110a; color: #ffd8bd; text-align: center; padding: 20px; font-size: 12px;">
      <p style="margin: 0 0 5px 0; font-weight: 500;">
        © {{ date('Y') }} {{ $temple['name'] }}. All rights reserved.
      </p>
      <p style="margin: 0; color: rgba(255, 255, 255, 0.45);">
        Secure Vedic Administration System
      </p>
    </div>
  </div>
</body>
</html>
