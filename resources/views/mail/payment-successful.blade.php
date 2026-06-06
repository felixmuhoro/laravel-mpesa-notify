<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Received</title>
    <style>
        /* Reset */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f6f9;
            color: #1a1a2e;
            line-height: 1.6;
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #00b09b 0%, #00c853 100%);
            padding: 40px 32px;
            text-align: center;
        }

        .header .icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .header .icon svg {
            width: 36px;
            height: 36px;
            fill: #ffffff;
        }

        .header h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .header p {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            margin-top: 6px;
        }

        /* Body */
        .body {
            padding: 36px 32px;
        }

        .greeting {
            font-size: 16px;
            color: #374151;
            margin-bottom: 20px;
        }

        /* Amount block */
        .amount-block {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            margin-bottom: 28px;
        }

        .amount-block .label {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
        }

        .amount-block .amount {
            font-size: 40px;
            font-weight: 800;
            color: #16a34a;
            margin: 8px 0 4px;
            letter-spacing: -1px;
        }

        .amount-block .desc {
            font-size: 14px;
            color: #6b7280;
        }

        /* Detail rows */
        .details {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row .detail-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }

        .detail-row .detail-value {
            font-size: 13px;
            color: #1f2937;
            font-weight: 600;
            text-align: right;
        }

        /* Message */
        .message {
            font-size: 15px;
            color: #374151;
            margin-bottom: 28px;
        }

        /* CTA button */
        .cta-wrapper {
            text-align: center;
            margin-bottom: 28px;
        }

        .cta-btn {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            padding: 14px 32px;
            border-radius: 8px;
            letter-spacing: 0.2px;
        }

        /* Footer */
        .footer {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 20px 32px;
            text-align: center;
        }

        .footer p {
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.8;
        }

        .footer a {
            color: #6b7280;
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .body { padding: 24px 20px; }
            .header { padding: 28px 20px; }
            .amount-block .amount { font-size: 32px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Header -->
        <div class="header">
            <div class="icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                </svg>
            </div>
            <h1>Payment Received</h1>
            <p>Your M-Pesa transaction was successful</p>
        </div>

        <!-- Body -->
        <div class="body">
            <p class="greeting">Hello <strong>{{  }}</strong>,</p>

            <div class="amount-block">
                <div class="label">Amount Paid</div>
                <div class="amount">{{  }}</div>
                <div class="desc">{{  }}</div>
            </div>

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Transaction ID</span>
                    <span class="detail-value">{{  }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time</span>
                    <span class="detail-value">{{  }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value" style="color:#16a34a;">&#10003; Successful</span>
                </div>
                @if(->referenceId)
                <div class="detail-row">
                    <span class="detail-label">Reference</span>
                    <span class="detail-value">{{ ->referenceId }}</span>
                </div>
                @endif
            </div>

            <p class="message">
                Your payment has been received and processed. Please keep your transaction ID
                (<strong>{{  }}</strong>) for your records.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                This is an automated message. Please do not reply directly to this email.<br />
                If you have questions, contact our support team.
            </p>
            <p style="margin-top:8px;">
                &copy; {{ date('Y') }} &mdash; Powered by
                <a href="https://packagist.org/packages/felixmuhoro/laravel-mpesa-notify" target="_blank">
                    felixmuhoro/laravel-mpesa-notify
                </a>
            </p>
        </div>
    </div>
</body>
</html>
