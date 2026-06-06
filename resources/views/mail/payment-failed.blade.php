<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<title>Payment Failed</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#f4f6f9;color:#1a1a2e;line-height:1.6}
.wrapper{max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);padding:40px 32px;text-align:center}
.icon{width:64px;height:64px;background:rgba(255,255,255,.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px}
.icon svg{width:36px;height:36px;fill:#fff}
.header h1{color:#fff;font-size:26px;font-weight:700}
.header p{color:rgba(255,255,255,.85);font-size:14px;margin-top:6px}
.body{padding:36px 32px}
.greeting{font-size:16px;color:#374151;margin-bottom:20px}
.amount-block{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:24px;text-align:center;margin-bottom:28px}
.amount-block .label{font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:.8px;font-weight:600}
.amount-block .amount{font-size:40px;font-weight:800;color:#dc2626;margin:8px 0 4px;letter-spacing:-1px}
.amount-block .desc{font-size:14px;color:#6b7280}
.details{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:28px}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #f3f4f6}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:13px;color:#6b7280;font-weight:500}
.detail-value{font-size:13px;color:#1f2937;font-weight:600;text-align:right}
.alert-box{background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:16px 20px;margin-bottom:24px}
.alert-box p{font-size:14px;color:#92400e}
.message{font-size:15px;color:#374151;margin-bottom:28px}
.footer{background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 32px;text-align:center}
.footer p{font-size:12px;color:#9ca3af;line-height:1.8}
.footer a{color:#6b7280;text-decoration:underline}
@media(max-width:480px){.body{padding:24px 20px}.header{padding:28px 20px}.amount-block .amount{font-size:32px}}
</style>
</head>
<body>
<div class="wrapper">
<div class="header">
<div class="icon">
<svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>
</div>
<h1>Payment Failed</h1>
<p>Your M-Pesa transaction could not be completed</p>
</div>
<div class="body">
<p class="greeting">Hello <strong>{{ $name }}</strong>,</p>
<div class="amount-block">
<div class="label">Attempted Amount</div>
<div class="amount">{{ $amount }}</div>
<div class="desc">{{ $description }}</div>
</div>
<div class="alert-box">
<p><strong>Why did this happen?</strong><br />
@if($resultCode === 1032)
The transaction was cancelled. Please try again and enter your PIN when prompted.
@elseif($resultCode === 1037)
The STK push request timed out. Please initiate a new payment.
@elseif($resultCode === 2001)
Your M-Pesa PIN was entered incorrectly. Please try again.
@else
Your payment could not be processed (error code: {{ $resultCode ?? 'Unknown' }}).
Please try again or contact support.
@endif
</p>
</div>
<div class="details">
<div class="detail-row"><span class="detail-label">Date &amp; Time</span><span class="detail-value">{{ $date }}</span></div>
<div class="detail-row"><span class="detail-label">Error Code</span><span class="detail-value" style="color:#dc2626">{{ $resultCode ?? 'N/A' }}</span></div>
<div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" style="color:#dc2626">&#10007; Failed</span></div>
</div>
<p class="message">No money has been deducted from your M-Pesa account. You can safely retry the payment. If the problem persists, please contact our support team with the details above.</p>
</div>
<div class="footer">
<p>This is an automated message. Please do not reply directly to this email.<br />If you have questions, contact our support team.</p>
<p style="margin-top:8px">&copy; {{ date('Y') }} &mdash; Powered by <a href="https://packagist.org/packages/felixmuhoro/laravel-mpesa-notify" target="_blank">felixmuhoro/laravel-mpesa-notify</a></p>
</div>
</div>
</body>
</html>
