<!DOCTYPE html>
<html lang="en">
<body style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:40px 24px;color:#111;">
  <p style="font-size:13px;color:#888;margin:0 0 32px;">The Human Response Project</p>
  <h1 style="font-size:22px;font-weight:700;margin:0 0 12px;">Verify your email</h1>
  <p style="color:#444;line-height:1.6;">Hi {{ $nickname }},</p>
  <p style="color:#444;line-height:1.6;">Click the button below to verify your email address and activate your account.</p>
  <a href="{{ $url }}"
     style="display:inline-block;margin:20px 0;background:#1A5C38;color:#fff;padding:12px 28px;
            text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
    Verify my email
  </a>
  <p style="font-size:13px;color:#888;margin-top:32px;line-height:1.6;">
    This link expires in 24 hours. If you didn&rsquo;t create an account, you can safely ignore this email.
  </p>
</body>
</html>