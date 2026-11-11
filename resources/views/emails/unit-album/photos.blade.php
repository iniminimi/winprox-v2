@php
    $bodyHtml = view('emails.unit-album.photos-body', [
        'message' => $message,
        'images' => $images,
        'tenantName' => $tenantName,
        'unitName' => $unitName,
        'dayLabel' => $dayLabel,
    ])->render();
@endphp
@include('emails.contact.winprox-template', [
    'subject' => $mailSubject,
    'bodyHtml' => $bodyHtml,
    'bodyText' => '',
    'recipientName' => '',
    'tenantName' => $tenantName,
])
