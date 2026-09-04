<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'WinProx' }}</title>
</head>
<body style="margin:0;padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#111111;text-align:left;background:#ffffff;">
@if (! empty($bodyHtml))
    {!! $bodyHtml !!}
@else
    {!! nl2br(e($bodyText ?? '')) !!}
@endif
</body>
</html>
