<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.municipal_promo_letter.subject', ['municipality' => $municipalityName]) }}</title>
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
        .signature {
            margin-top: 24px;
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
            margin: 0;
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
                <p>{{ __('mail.municipal_promo_letter.greeting') }}</p>
                <p>{{ __('mail.municipal_promo_letter.intro', ['municipality' => $municipalityName]) }}</p>
                <p>{{ __('mail.municipal_promo_letter.body_1') }}</p>
                <p>{{ __('mail.municipal_promo_letter.body_2') }}</p>
                <p>{{ __('mail.municipal_promo_letter.body_3') }}</p>
                <p>{!! __('mail.municipal_promo_letter.body_4_html', ['url' => $promoUrl]) !!}</p>
                <div class="signature">
                    <p>{{ __('mail.municipal_promo_letter.closing') }}</p>
                    <p>
                        <strong>{{ __('mail.municipal_promo_letter.signer_name') }}</strong><br>
                        {{ __('mail.municipal_promo_letter.signer_title') }}<br>
                        {{ __('mail.municipal_promo_letter.phone') }}<br>
                        <a href="https://winprox.app">www.winprox.app</a>
                    </p>
                </div>
            </div>
            <div class="footer">
                <p class="footer-text">{{ __('mail.municipal_promo_letter.footer') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
