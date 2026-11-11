<p>{{ __('mail.unit_album.intro', ['tenant' => $tenantName]) }}</p>

<p>
    <strong>{{ $unitName }}</strong><br>
    <span style="color: #64748b;">{{ $dayLabel }}</span>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
    @foreach (array_chunk($images, 2) as $row)
        <tr>
            @foreach ($row as $image)
                <td width="50%" style="padding: 4px; vertical-align: top;">
                    <img src="{{ $message->embedData($image['data'], $image['name'], $image['mime']) }}"
                         alt="{{ __('mail.unit_album.photo_alt', ['number' => $loop->parent->index * 2 + $loop->iteration]) }}"
                         width="270"
                         style="display: block; width: 100%; max-width: 270px; height: auto; border: 0; border-radius: 6px;">
                </td>
            @endforeach
            @if (count($row) === 1)
                <td width="50%" style="padding: 4px;"></td>
            @endif
        </tr>
    @endforeach
</table>

<p style="font-size: 13px; color: #64748b; margin-top: 16px;">
    {{ __('mail.unit_album.outro', ['tenant' => $tenantName]) }}
</p>
