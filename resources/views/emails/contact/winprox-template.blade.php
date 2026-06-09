<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'WinProx' }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            line-height: 1.6;
            color: #334155;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .email-wrapper {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .accent-line {
            height: 4px;
            background-color: #10b981;
        }
        .body {
            padding: 32px 28px;
            font-size: 15px;
            line-height: 1.7;
            color: #475569;
        }
        .body p {
            margin: 0 0 16px 0;
        }
        .body p:last-child {
            margin-bottom: 0;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 24px 28px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }
        .footer-text {
            font-size: 12px;
            color: #64748b;
            margin: 0 0 8px 0;
        }
        .footer-brand {
            font-size: 13px;
            font-weight: 600;
            color: #059669;
            margin: 0;
        }
        @media (max-width: 600px) {
            .container {
                padding: 20px 16px;
            }
            .body {
                padding: 24px 20px;
            }
            .header {
                padding: 24px 20px;
            }
            .header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <div class="header">
                <h1>WinProx</h1>
            </div>
            <div class="accent-line"></div>
            <div class="body">
                <p>Beste {{ $recipientName }},</p>
                {!! nl2br(e($bodyText)) !!}
            </div>
            <div class="footer">
                <p class="footer-text">{{ __('Verzonden via WinProx Facility Management') }}</p>
                <p class="footer-brand">WinProx</p>
                <p class="footer-text" style="margin-top: 12px; font-size: 11px; color: #94a3b8;">
                    {{ __('Dit is een automatisch gegenereerd bericht. Gelieve niet te antwoorden op deze e-mail.') }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>
