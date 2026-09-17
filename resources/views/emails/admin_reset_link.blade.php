<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $temple['name'] }} · Set Your Password</title>
</head>
<body style="background-color: #f5f3ef; margin: 0; padding: 20px; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2d2520;">
  <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2ddd3;">
    <!-- Header -->
    <div style="background-color: {{ $temple['dark_color'] }}; padding: 24px 30px; text-align: left; border-bottom: 3px solid {{ $temple['primary_color'] }};">
      <h1 style="margin: 0; color: #ffffff; font-family: 'DM Sans', sans-serif; font-size: 19px; font-weight: 700;">
        {{ $temple['name'] }}
      </h1>
      <p style="margin: 6px 0 0 0; color: #d9d4c9; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
        Set Your Password
      </p>
    </div>

    <!-- Body content -->
    <div style="padding: 35px 30px;">
      <p style="color: #2d2520; font-size: 16px; line-height: 1.6; margin: 0 0 18px 0;">
        Vanakkam, {{ $name ?? 'there' }}
      </p>
      <p style="color: #52473c; font-size: 15px; line-height: 1.6; margin-bottom: 26px;">
        Click the button below to set your password and access your account.
      </p>

      <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background-color: {{ $temple['primary_color'] }}; border-radius: 6px;">
            <a href="{{ $url }}" style="display: inline-block; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; padding: 13px 32px;">
              Set My Password
            </a>
          </td>
        </tr>
      </table>

      <p style="color: #7b6b5a; font-size: 13px; line-height: 1.6; margin: 22px 0 0 0;">
        This link is valid for <strong>60 minutes</strong> and can only be used once. If the button doesn't work, copy and paste this address into your browser:
      </p>
      <p style="color: #7b6b5a; font-size: 12px; word-break: break-all; margin: 6px 0 0 0;">
        {{ $url }}
      </p>

      <div style="border-top: 1px solid #e2ddd3; margin: 22px 0;"></div>

      <p style="color: #7b6b5a; font-size: 13px; line-height: 1.6; margin: 0;">
        If you did not expect this email, you can safely ignore it — your password will not change unless you click the link above.
      </p>
    </div>

    <!-- Footer -->
    <div style="background-color: #f5f3ef; color: #7b6b5a; text-align: left; padding: 16px 30px; font-size: 12px; border-top: 1px solid #e2ddd3;">
      © {{ date('Y') }} {{ $temple['name'] }}. All rights reserved.
    </div>
  </div>
</body>
</html>
