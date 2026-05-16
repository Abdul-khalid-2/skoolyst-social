<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plan Updated</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 40px 16px; color: #1e293b; }
  .card { background: #fff; border-radius: 16px; max-width: 520px; margin: 0 auto; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .logo { font-size: 20px; font-weight: 800; color: #2563eb; margin-bottom: 32px; }
  .plan-badge { display: inline-block; background: #eff6ff; color: #2563eb; font-weight: 700; font-size: 13px; padding: 6px 14px; border-radius: 100px; margin: 16px 0; }
  h1 { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
  p { color: #64748b; line-height: 1.6; margin: 0 0 16px; font-size: 14px; }
  ul { padding: 0 0 0 20px; color: #475569; font-size: 14px; line-height: 2; }
  .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 40px; }
  .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">Skoolyst</div>
  <h1>Plan updated successfully</h1>
  <p>Hi {{ $user->name }}, your workspace <strong>{{ $workspace->name }}</strong> has been upgraded.</p>
  <div class="plan-badge">{{ ucfirst($subscription->plan) }} Plan</div>
  <hr class="divider">
  <p><strong>What's included:</strong></p>
  <ul>
    @foreach ($planConfig['features'] as $feature)
      <li>{{ $feature }}</li>
    @endforeach
  </ul>
  <hr class="divider">
  <p style="font-size:13px; color:#94a3b8">
    Started: {{ $subscription->started_at->format('M d, Y') }}
    @if ($subscription->expires_at)
      &nbsp;·&nbsp; Renews: {{ $subscription->expires_at->format('M d, Y') }}
    @endif
  </p>
  <p>If you have any questions, reply to this email or contact us at <a href="mailto:skoolyst@gmail.com">skoolyst@gmail.com</a>.</p>
</div>
<div class="footer">© {{ date('Y') }} Skoolyst. All rights reserved.</div>
</body>
</html>
