<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $mailSubject }}</title>
</head>
<body>
    <p>{{ __('mail.unit_album.greeting') }}</p>
    <p>{{ __('mail.unit_album.intro', ['sender' => $senderName, 'tenant' => $tenantName]) }}</p>
    <p>{{ __('mail.unit_album.taken', ['day' => $dayLabel, 'unit' => $unitName]) }}</p>
    @foreach ($images as $image)
        <p>
            <img src="{{ $message->embedData($image['data'], $image['name'], $image['mime']) }}"
                 alt="{{ __('mail.unit_album.photo_alt', ['number' => $loop->iteration]) }}"
                 width="600"
                 style="max-width: 100%; height: auto;">
        </p>
    @endforeach
    <p>{{ __('mail.unit_album.closing') }}</p>
    <p>{{ $senderName }}</p>
</body>
</html>
