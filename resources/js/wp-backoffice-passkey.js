/**
 * WebAuthn op de telefoonpagina van de beheerlogin. De gsm toont zijn eigen
 * ontgrendel-prompt. WinProx bewaart geen biometrie.
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

function preparePublicKey(options) {
    const publicKey = { ...options };
    publicKey.challenge = b64ToBuf(publicKey.challenge);
    if (publicKey.user?.id) {
        publicKey.user = { ...publicKey.user, id: b64ToBuf(publicKey.user.id) };
    }
    if (Array.isArray(publicKey.allowCredentials)) {
        publicKey.allowCredentials = publicKey.allowCredentials.map((item) => ({
            ...item,
            id: b64ToBuf(item.id),
        }));
    }
    if (Array.isArray(publicKey.excludeCredentials)) {
        publicKey.excludeCredentials = publicKey.excludeCredentials.map((item) => ({
            ...item,
            id: b64ToBuf(item.id),
        }));
    }
    return publicKey;
}

function phoneHasPasskey() {
    try {
        return localStorage.getItem('wpPhonePasskey') === '1';
    } catch (e) {
        return false;
    }
}

function rememberPhonePasskey() {
    try {
        localStorage.setItem('wpPhonePasskey', '1');
    } catch (e) {
        // De vingerafdruk blijft op het toestel. Zonder lokale notitie toont de pagina het wachtwoord.
    }
}

function registerAlpineComponent() {
    if (!window.Alpine || window.Alpine.__wpBackofficePasskey) {
        return;
    }
    window.Alpine.__wpBackofficePasskey = true;

    window.Alpine.data('wpBackofficePasskey', (config = {}) => ({
        urls: config.urls ?? {},
        i18n: config.i18n ?? {},
        busy: false,
        error: '',
        hasKey: phoneHasPasskey(),

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        },

        async post(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok, data };
        },

        async assert() {
            if (this.busy || typeof navigator.credentials === 'undefined') {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const options = await this.post(this.urls.assertOptions);
                if (!options.ok || !options.data?.challenge) {
                    this.error = this.i18n[options.data?.error] ?? this.i18n.failed ?? '';
                    return;
                }
                const cred = await navigator.credentials.get({ publicKey: preparePublicKey(options.data) });
                const done = await this.post(this.urls.assert, {
                    credential: JSON.stringify(credentialToJson(cred, false)),
                });
                if (!done.ok || !done.data?.ok) {
                    this.error = this.i18n[done.data?.error] ?? this.i18n.failed ?? '';
                    return;
                }
                rememberPhonePasskey();
                window.location.reload();
            } catch (e) {
                this.error = e?.name === 'NotAllowedError' ? (this.i18n.cancelled ?? '') : (this.i18n.failed ?? '');
            } finally {
                this.busy = false;
            }
        },

        async register() {
            if (this.busy || typeof navigator.credentials === 'undefined') {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const options = await this.post(this.urls.registerOptions);
                if (!options.ok || !options.data?.challenge) {
                    this.error = this.i18n[options.data?.error] ?? this.i18n.failed ?? '';
                    return;
                }
                const cred = await navigator.credentials.create({ publicKey: preparePublicKey(options.data) });
                const done = await this.post(this.urls.register, {
                    credential: JSON.stringify(credentialToJson(cred, true)),
                });
                if (!done.ok || !done.data?.ok) {
                    this.error = this.i18n[done.data?.error] ?? this.i18n.failed ?? '';
                    return;
                }
                rememberPhonePasskey();
                window.location.reload();
            } catch (e) {
                this.error = e?.name === 'NotAllowedError' ? (this.i18n.cancelled ?? '') : (this.i18n.failed ?? '');
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
