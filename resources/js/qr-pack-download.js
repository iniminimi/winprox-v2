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

export async function wpDownloadAuthenticatedFile(url, accept = '*/*') {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: accept,
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
        ?? 'download';
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

export async function wpDownloadQrPackUrl(url) {
    await wpDownloadAuthenticatedFile(
        url,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document,*/*',
    );
}

export async function wpDownloadPromoQrUrl(url) {
    await wpDownloadAuthenticatedFile(url, 'image/png,*/*');
}

function wpHandlePromoRecipientQrDownload({ url }) {
    if (! url) {
        return;
    }

    void wpDownloadPromoQrUrl(url);
}

document.addEventListener('livewire:init', () => {
    Livewire.on('promo-recipient-qr-download', wpHandlePromoRecipientQrDownload);
}, { once: true });

window.wpDownloadAuthenticatedFile = wpDownloadAuthenticatedFile;
window.wpDownloadQrPackUrl = wpDownloadQrPackUrl;
window.wpDownloadPromoQrUrl = wpDownloadPromoQrUrl;

window.wpQrPackPicker = function wpQrPackPicker(downloadFailed) {
    return {
        downloading: null,
        error: null,
        async download(url, key) {
            if (this.downloading) {
                return;
            }

            this.downloading = key;
            this.error = null;

            try {
                await wpDownloadQrPackUrl(url);
            } catch (exception) {
                this.error = exception?.message || downloadFailed;
            } finally {
                this.downloading = null;
            }
        },
    };
};

