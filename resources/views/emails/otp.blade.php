<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Temple ERP OTP Verification</title>
</head>
<body style="background-color: #fdfbf7; margin: 0; padding: 20px; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ebdcc5; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <!-- Header banner -->
    <div style="background: linear-gradient(135deg, #ff9e00 0%, #ff6f00 50%, #e65100 100%); padding: 30px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-family: 'Cinzel', 'Georgia', serif; font-size: 24px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
        🛕 SHREE MANDIR ERP
      </h1>
      <p style="margin: 5px 0 0 0; color: #ffd8bd; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">
        Email Verification
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 40px 30px; text-align: center;">
      <h2 style="margin-top: 0; color: #17110a; font-size: 20px; font-weight: 600;">
        Namaste,
      </h2>
      <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 30px;">
        Thank you for registering at Shree Mandir Devotee Portal. To complete your registration and activate your account, please use the following One-Time Password (OTP):
      </p>

      <!-- OTP code box -->
      <div style="background-color: rgba(255, 111, 0, 0.05); border: 2px dashed #ff6f00; border-radius: 12px; padding: 20px; margin: 30px auto; max-width: 250px; text-align: center;">
        <span style="font-family: monospace; font-size: 36px; font-weight: 800; color: #ff6f00; letter-spacing: 6px; display: block; line-height: 1;">
          {{ $otp }}
        </span>
      </div>

      <p style="color: #7a6e63; font-size: 14px; margin-top: 30px;">
        This OTP is valid for <strong>{{ $expiry }}</strong>.
      </p>
      
      <div style="margin: 30px 0; border-top: 1px solid #f3ede2;"></div>

      <p style="color: #8a7b6e; font-size: 13px; line-height: 1.5; margin: 0; text-align: left;">
        ⚠️ <strong>Security Notice:</strong> Do not share this OTP with anyone. Temple officials will never ask for your password or OTP. If you did not request this code, please ignore this email.
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #17110a; color: #ffd8bd; text-align: center; padding: 20px; font-size: 12px;">
      <p style="margin: 0 0 5px 0; font-weight: 500;">
        © 2026 Shree Mandir Trust. All rights reserved.
      </p>
      <p style="margin: 0; color: rgba(255, 255, 255, 0.45);">
        Secure Vedic Administration System
      </p>
    </div>
  </div>
</body>
</html>
