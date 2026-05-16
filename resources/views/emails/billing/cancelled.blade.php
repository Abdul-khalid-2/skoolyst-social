<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscription Cancelled</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 40px 16px; color: #1e293b; }
  .card { background: #fff; border-radius: 16px; max-width: 520px; margin: 0 auto; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .logo { font-size: 20px; font-weight: 800; color: #2563eb; margin-bottom: 32px; }
  h1 { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
  p { color: #64748b; line-height: 1.6; margin: 0 0 16px; font-size: 14px; }
  .badge { display: inline-block; background: #fff1f2; color: #e11d48; font-weight: 700; font-size: 13px; padding: 6px 14px; border-radius: 100px; margin: 12px 0; }
  .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 40px; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">Skoolyst</div>
  <h1>Subscription cancelled</h1>
  <div class="badge">Cancelled</div>
  <p>Hi {{ $user->name }}, your <strong>{{ ucfirst($subscription->plan) }}</strong> subscription for workspace <strong>{{ $workspace->name }}</strong> has been cancelled.</p>
  <p>Your workspace has been moved to the <strong>Free plan</strong>. You can re-subscribe at any time from the Billing settings.</p>
  <p>Questions? Email us at <a href="mailto:skoolyst@gmail.com">skoolyst@gmail.com</a></p>
</div>
<div class="footer">© {{ date('Y') }} Skoolyst. All rights reserved.</div>
</body>
</html>
