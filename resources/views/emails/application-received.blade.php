<!DOCTYPE html>
<html lang="en">
<body style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:40px 24px;color:#111;">
  <p style="font-size:13px;color:#888;margin:0 0 32px;">The Human Response Project — Admin</p>
  <h1 style="font-size:22px;font-weight:700;margin:0 0 12px;">New responder application</h1>
  <p style="color:#444;line-height:1.6;">
    <strong>{{ $applicantName }}</strong> ({{ $applicantEmail }}) has applied to become a responder.
  </p>
  <blockquote style="margin:20px 0;padding:12px 16px;background:#f8f8f8;border-left:3px solid #1A5C38;color:#333;font-style:italic;line-height:1.6;">
    &ldquo;{{ $message }}&rdquo;
  </blockquote>
  <a href="{{ $reviewUrl }}"
     style="display:inline-block;margin:8px 0 24px;background:#1A5C38;color:#fff;padding:12px 28px;
            text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
    Review Application
  </a>
</body>
</html>