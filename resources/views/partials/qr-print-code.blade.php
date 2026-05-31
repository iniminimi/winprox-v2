{{-- SVG QR + gecentreerd logo (WinProx of organisatie); QR met ErrorCorrectionLevel::H(). --}}
<div
    class="wp-qr-code-frame"
    style="--wp-qr-logo-box: {{ \App\Support\Qr\QrLogoLayout::displayBoxPercent() }}%; --wp-qr-logo-pad: {{ \App\Support\Qr\QrLogoLayout::innerPaddingPx() }}px;"
>
    <div class="wp-qr-code-frame-inner">
        {!! $qrSvg !!}
        <div class="wp-qr-code-center-logo" aria-hidden="true">
            <img src="{{ $centerLogoUrl }}" alt="">
        </div>
    </div>
</div>
