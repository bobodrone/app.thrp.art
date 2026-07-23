<!DOCTYPE html>
<html lang="en">
<body style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:40px 24px;color:#111;">
  <p style="font-size:13px;color:#888;margin:0 0 32px;">The Human Response Project</p>
  <h1 style="font-size:22px;font-weight:700;margin:0 0 12px;">Reset your password</h1>
  <p style="color:#444;line-height:1.6;">Hi {{ $nickname }},</p>
  <p style="color:#444;line-height:1.6;">Click the button below to set a new password. This link expires in 1 hour.</p>
  <a href="{{ $url }}"
     style="display:inline-block;margin:20px 0;background:#1A5C38;color:#fff;padding:12px 28px;
            text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
    Reset my password
  </a>
  <p style="font-size:13px;color:#888;margin-top:32px;line-height:1.6;">
    If you didn&rsquo;t request this, you can safely ignore this email.
  </p>
</body>
</html>