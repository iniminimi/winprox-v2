function parseContentDispositionFilename(header) {
    if (! header) {
        return null;
    }

    const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(header);
    if (utf8Match?.[1]) {
        return decodeURIComponent(utf8Match[1].trim());
    }

    const quotedMatch = /filename="([^"]+)"/i.exec(header);
    if (quotedMatch?.[1]) {
        return quotedMatch[1];
    }

    const plainMatch = /filename=([^;]+)/i.exec(header);
    if (plainMatch?.[1]) {
        return plainMatch[1].trim().replace(/^"|"$/g, '');
    }

    return null;
}

export async function wpDownloadQrPackUrl(url) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,*/*',
        },
    });

    if (! response.ok) {
        let message = '';

        try {
            message = (await response.text()).trim();
        } catch (_) {
            message = '';
        }

        throw new Error(message !== '' ? message : `HTTP ${response.status}`);
    }

    const blob = await response.blob();
    const filename = parseContentDispositionFilename(response.headers.get('Content-Disposition'))
        ?? 'winprox-qr-stickers.docx';
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = objectUrl;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
}

window.wpDownloadQrPackUrl = wpDownloadQrPackUrl;
