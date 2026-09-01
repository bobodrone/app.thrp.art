<!DOCTYPE html>
<html lang="en">
<body style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:40px 24px;color:#111;">
  <p style="font-size:13px;color:#888;margin:0 0 32px;">The Human Response Project — Admin</p>
  <h1 style="font-size:22px;font-weight:700;margin:0 0 12px;">New contact message</h1>
  <p style="color:#444;line-height:1.6;">
    <strong>{{ $senderName }}</strong> ({{ $senderEmail }})
    @if ($fromMember)
      <span style="color:#888;">— signed in</span>
    @endif
    wrote about <strong>{{ $subjectLine }}</strong>.
  </p>
  <blockquote style="margin:20px 0;padding:12px 16px;background:#f8f8f8;border-left:3px solid #1A5C38;color:#333;line-height:1.6;white-space:pre-wrap;">{{ $body }}</blockquote>
  <p style="color:#888;font-size:13px;line-height:1.6;">
    Replying to this email reaches {{ $senderName }} directly.
  </p>
  <a href="{{ $inboxUrl }}"
     style="display:inline-block;margin:8px 0 24px;background:#1A5C38;color:#fff;padding:12px 28px;
            text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
    Open the inbox
  </a>
</body>
</html>
