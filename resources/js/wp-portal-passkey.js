/**
 * WebAuthn/passkey voor het time-portaal. De gsm toont zijn eigen
 * ontgrendel-prompt (vingerafdruk, gezicht of toestelcode) — WinProx bouwt
 * geen eigen biometrie-dialoog. Registratie loopt via vier JSON-endpoints
 * met sessie-gebonden, eenmalige challenges; een geslaagde assertion markeert
 * de portalsessie server-side geverifieerd, waarna een reload volstaat.
 */

function b64ToBuf(value) {
    const b64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
    const bin = atob(b64 + '='.repeat((4 - (b64.length % 4)) % 4));
    const buf = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) {
        buf[i] = bin.charCodeAt(i);
    }
    return buf.buffer;
}

function bufToB64(buf) {
    const bytes = new Uint8Array(buf);
    let bin = '';
    for (let i = 0; i < bytes.length; i++) {
        bin += String.fromCharCode(bytes[i]);
    }
    return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function credentialToJson(cred, withAttestation) {
    const response = {
        clientDataJSON: bufToB64(cred.response.clientDataJSON),
    };
    if (withAttestation) {
        response.attestationObject = bufToB64(cred.response.attestationObject);
        if (typeof cred.response.getTransports === 'function') {
            response.transports = cred.response.getTransports();
        }
    } else {
        response.authenticatorData = bufToB64(cred.response.authenticatorData);
        response.signature = bufToB64(cred.response.signature);
        response.userHandle = cred.response.userHandle ? bufToB64(cred.response.userHandle) : null;
    }
    return {
        id: cred.id,
        rawId: bufToB64(cred.rawId),
        type: cred.type,
        response,
    };
}

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpPortalPasskey) {
        return;
    }
    window.Alpine.__wpPortalPasskey = true;

    window.Alpine.data('wpPortalPasskey', (config = {}) => ({
        i18n: config.i18n ?? {},
        urls: config.urls ?? {},
        busy: false,
        error: '',
        supported: typeof window.PublicKeyCredential !== 'undefined' && !!navigator.credentials,

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        },

        async post(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok, status: res.status, data };
        },

        setError(key) {
            this.error = this.i18n[key] ?? this.i18n.error_failed ?? '';
        },

        async enroll() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const opt = await this.post(this.urls.registerOptions);
                if (!opt.ok) {
                    this.setError('error_failed');
                    return;
                }
                const options = opt.data;
                options.challenge = b64ToBuf(options.challenge);
                if (options.user?.id) {
                    options.user.id = b64ToBuf(options.user.id);
                }
                options.excludeCredentials = (options.excludeCredentials ?? []).map((c) => ({
                    ...c,
                    id: b64ToBuf(c.id),
                }));

                const cred = await navigator.credentials.create({ publicKey: options });
                if (!cred) {
                    this.setError('error_cancelled');
                    return;
                }

                const done = await this.post(this.urls.register, {
                    credential: JSON.stringify(credentialToJson(cred, true)),
                });
                if (!done.ok || !done.data?.ok) {
                    this.setError('error_failed');
                    return;
                }
                window.location.reload();
            } catch (e) {
                this.setError(e?.name === 'NotAllowedError' ? 'error_cancelled' : 'error_failed');
            } finally {
                this.busy = false;
            }
        },

        async verify() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const opt = await this.post(this.urls.assertOptions);
                if (!opt.ok) {
                    this.setError('error_failed');
                    return;
                }
                const options = opt.data;
                options.challenge = b64ToBuf(options.challenge);
                options.allowCredentials = (options.allowCredentials ?? []).map((c) => ({
                    ...c,
                    id: b64ToBuf(c.id),
                }));

                const cred = await navigator.credentials.get({ publicKey: options });
                if (!cred) {
                    this.setError('error_cancelled');
                    return;
                }

                const done = await this.post(this.urls.assert, {
                    credential: JSON.stringify(credentialToJson(cred, false)),
                });
                if (!done.ok || !done.data?.ok) {
                    this.setError(done.status === 409 ? 'error_device' : 'error_failed');
                    return;
                }
                window.location.reload();
            } catch (e) {
                this.setError(e?.name === 'NotAllowedError' ? 'error_cancelled' : 'error_failed');
            } finally {
                this.busy = false;
            }
        },
    }));
}

document.addEventListener('alpine:init', registerAlpineComponent);
if (window.Alpine) {
    registerAlpineComponent();
}
