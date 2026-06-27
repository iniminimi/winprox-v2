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
            background-color: #ffffff;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .header img {
            max-width: 100px;
            height: auto;
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
        .body a {
            color: #059669;
            font-weight: 600;
            text-decoration: none;
        }
        .body ul,
        .body ol {
            margin: 0 0 16px 0;
            padding-left: 1.25rem;
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
                <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx">
            </div>
            <div class="accent-line"></div>
            <div class="body">
                @if($recipientName)
                    <p>{{ __('mail.outbound.greeting_name', ['name' => $recipientName]) }}</p>
                @endif
                @if (! empty($bodyHtml))
                    {!! $bodyHtml !!}
                @else
                    {!! nl2br(e($bodyText)) !!}
                @endif
            </div>
            <div class="footer">
                <p class="footer-text">
                    <a href="https://winprox.app" style="color: #059669; text-decoration: none;">{{ __('contact-messages.email_footer_sent_via') }}</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
