<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Temple ERP Welcome</title>
</head>
<body style="background-color: #fdfbf7; margin: 0; padding: 20px; font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #ebdcc5; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
    <!-- Header banner -->
    <div style="background: linear-gradient(135deg, #ff9e00 0%, #ff6f00 50%, #e65100 100%); padding: 30px; text-align: center;">
      <h1 style="margin: 0; color: #ffffff; font-family: 'Cinzel', 'Georgia', serif; font-size: 24px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">
        🛕 SHREE MANDIR ERP
      </h1>
      <p style="margin: 5px 0 0 0; color: #ffd8bd; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">
        Account Created
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 40px 30px;">
      <h2 style="margin-top: 0; color: #17110a; font-size: 20px; font-weight: 600; text-align: center;">
        Temple ERP Welcome
      </h2>
      
      <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
        Hello {{ $name }},
      </p>

      <p style="color: #52473c; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
        Your account has been created.
      </p>

      <!-- Credentials Box -->
      <div style="background-color: rgba(255, 111, 0, 0.03); border: 1px solid #ebdcc5; border-radius: 12px; padding: 20px; margin: 25px 0;">
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Role:</strong> <span style="color: #ff6f00; font-weight: 600;">{{ $role }}</span>
        </div>
        <div style="margin-bottom: 12px; font-size: 15px;">
          <strong style="color: #17110a;">Email:</strong> <span style="color: #52473c;">{{ $email }}</span>
        </div>
        <div style="font-size: 15px;">
          <strong style="color: #17110a;">Temporary Password:</strong> <code style="background-color: #f3ede2; padding: 3px 8px; border-radius: 4px; font-weight: 700; color: #d01919; font-size: 16px; font-family: monospace;">{{ $password }}</code>
        </div>
      </div>

      <p style="color: #d01919; font-size: 15px; font-weight: 600; text-align: center; margin-bottom: 30px;">
        ⚠️ Please login and change your password immediately.
      </p>
      
      <div style="margin: 30px 0; border-top: 1px solid #f3ede2;"></div>

      <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin: 0;">
        Warm Regards,<br>
        <strong>Temple ERP Team</strong>
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
